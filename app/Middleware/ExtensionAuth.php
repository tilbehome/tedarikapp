<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ClientIp;
use App\Core\Connection;
use App\Core\Dates;
use App\Core\Response;
use App\Models\SettingsRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Eklenti kimlik doğrulaması + CORS + hız sınırı (İE#11 — K30/K34).
 *
 * • Bearer token: DB'de yalnız SHA-256 hash durur (K34); `hash_equals` sabit zamanlı.
 *   Token üretilmemiş/iptal edilmişse TÜM istekler 401 — eklenti "Ayarlar'dan token
 *   üretin" yönlendirmesi gösterir.
 * • CORS (K30): yalnız allowlist'teki extension origin'leri — wildcard YASAK.
 *   Allowlist `EXTENSION_ALLOWED_ORIGINS` (virgüllü, config dosyasından); Web Store
 *   sabit ID'si çıkınca eklenir (K38). OPTIONS preflight burada yanıtlanır.
 * • Hız sınırı: IP başına dakikada `CAPTURE_RATE_PER_MIN` istek (sayaç activity_log
 *   — disksiz); aşımda 429. Token değeri HİÇBİR yerde loglanmaz.
 */
final class ExtensionAuth implements MiddlewareInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $allowedOrigins,
        private readonly int $ratePerMinute,
        private readonly \DateTimeZone $timezone,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = trim($request->getHeaderLine('Origin'));

        // Preflight: tarayıcı Authorization başlıklı isteği önce OPTIONS ile sorar.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withCors($this->responseFactory->createResponse(204), $origin)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
                ->withHeader('Access-Control-Max-Age', '600');
        }

        $token = '';
        $authorization = $request->getHeaderLine('Authorization');
        if (str_starts_with($authorization, 'Bearer ')) {
            $token = trim(substr($authorization, 7));
        }

        $storedHash = (new SettingsRepository($this->connection))->get(SettingsRepository::KEY_EXTENSION_TOKEN_HASH, '');
        if ($token === '' || $storedHash === null || $storedHash === '' || !hash_equals($storedHash, hash('sha256', $token))) {
            return $this->withCors(
                Response::error($this->responseFactory->createResponse(), 'UNAUTHENTICATED', 'Eklenti token\'ı geçersiz veya iptal edilmiş. Panel > Ayarlar > Güvenlik\'ten yeni token üretin.', 401),
                $origin,
            );
        }

        if ($this->overRateLimit(ClientIp::from($request))) {
            return $this->withCors(
                Response::error($this->responseFactory->createResponse(), 'RATE_LIMITED', 'Çok sık istek — bir dakika sonra tekrar deneyin.', 429),
                $origin,
            );
        }

        return $this->withCors($handler->handle($request), $origin);
    }

    /** K30: origin allowlist'te ise CORS başlıkları eklenir; değilse HİÇ eklenmez (tarayıcı engeller). */
    private function withCors(ResponseInterface $response, string $origin): ResponseInterface
    {
        if ($origin === '') {
            return $response;
        }
        $allowed = array_filter(array_map('trim', explode(',', $this->allowedOrigins)));
        if (!in_array($origin, $allowed, true)) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin');
    }

    private function overRateLimit(string $ip): bool
    {
        try {
            $pdo = $this->connection->pdo();
            $windowStart = Dates::toStorage(new \DateTimeImmutable('-1 minute', $this->timezone));
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM activity_log WHERE action = 'capture_request' AND ip = :ip AND created_at >= :start",
            );
            $statement->execute(['ip' => $ip, 'start' => $windowStart]);
            if ((int) $statement->fetchColumn() >= $this->ratePerMinute) {
                return true;
            }

            $insert = $pdo->prepare(
                "INSERT INTO activity_log (entity_type, entity_id, action, detail, ip, actor_type, actor_id, created_at)
                 VALUES ('extension', NULL, 'capture_request', NULL, :ip, 'extension', NULL, :now)",
            );
            $insert->execute(['ip' => $ip, 'now' => Dates::toStorage(new \DateTimeImmutable('now', $this->timezone))]);
        } catch (\Throwable) {
            // Sayaç okunamıyorsa istek düşürülmez — hız sınırı koruma katmanıdır, kapı değil.
        }

        return false;
    }
}
