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
        // V3-B A3: geçersiz token bildirim doğurur — kullanıcı "eklenti neden
        // çalışmıyor?" sorusunun cevabını panelde görür, sunucu logunda değil.
        private readonly ?\App\Services\Bildirim\BildirimYayinci $bildirim = null,
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

        $token = $this->bearerToken($request);

        $storedHash = (new SettingsRepository($this->connection))->get(SettingsRepository::KEY_EXTENSION_TOKEN_HASH, '');
        if ($token === '' || $storedHash === null || $storedHash === '' || !hash_equals($storedHash, hash('sha256', $token))) {
            // Token DEĞERİ bildirime GİRMEZ (K34/K51): yalnız istemci kimliği
            // olarak Origin ve hata kodu taşınır. Birleştirme sayesinde ard arda
            // gelen yüzlerce geçersiz istek tek satırda sayılır.
            $this->bildirim?->guvenliYayimla('NTF-TOKEN-INVALID', [
                'istemci_id' => $origin === '' ? 'bilinmiyor' : $origin,
                'hata_kodu' => $token === '' ? 'eksik' : 'gecersiz',
            ]);

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

    /**
     * Bearer token'ı çıkarır — İE#11 canlı arızası: cgi-fcgi (LiteSpeed/Apache CGI) altında
     * Authorization başlığı PHP'ye iletilmez; PSR-7 isteği başlığı BOŞ görür ve her istek 401 olur.
     * .htaccess geçirmesi asıl çözümdür, burası kod yedeğidir: htaccess'in taşıdığı
     * HTTP_AUTHORIZATION ve (iç yönlendirmede) REDIRECT_HTTP_AUTHORIZATION anahtarlarına bakar.
     * Token değeri hiçbir dalda loglanmaz.
     */
    private function bearerToken(ServerRequestInterface $request): string
    {
        $adaylar = [$request->getHeaderLine('Authorization')];
        $server = $request->getServerParams();
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            $deger = $server[$key] ?? null;
            if (is_string($deger)) {
                $adaylar[] = $deger;
            }
        }

        foreach ($adaylar as $aday) {
            $aday = trim($aday);
            if (stripos($aday, 'Bearer ') === 0) {
                $token = trim(substr($aday, 7));
                if ($token !== '') {
                    return $token;
                }
            }
        }

        return '';
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
