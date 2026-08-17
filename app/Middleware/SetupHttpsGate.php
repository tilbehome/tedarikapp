<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Kurulumda sır taşıyan adımların HTTPS kapısı (K37 §A3).
 *
 * `APP_ENV=production` iken DB şifresi, yönetici şifresi ve TOTP kodu taşıyan uçlar
 * HTTPS olmayan bağlantıdan İLERLEMEZ: bu değerler ağda açık gider ve tek seferlik
 * kurulum sırları (APP_KEY dahil) daha ilk gün sızmış olurdu.
 *
 * İstisnalar:
 *  • `APP_ENV != production` (geliştirme makinesi) — `.env` henüz yokken değer
 *    sunucu ortam değişkeninden okunur; hiç ayarlanmamışsa GÜVENLİ varsayılan
 *    `production`dur (fail-safe).
 *  • Loopback (localhost/127.0.0.1/::1) — aynı makine trafiği ağa çıkmaz;
 *    APP_ENV ayarlanmamış geliştirme kurulumları böyle çalışabilir.
 */
final class SetupHttpsGate implements MiddlewareInterface
{
    /** Sır girilen kurulum uçları (DB şifresi, yönetici şifresi, TOTP kodu). */
    private const SECRET_PATHS = [
        '/api/setup/database',
        '/api/setup/admin',
        '/api/setup/admin/verify',
    ];

    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]', ''];

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $appEnv,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->appliesTo($request) || $this->isAllowed($request)) {
            return $handler->handle($request);
        }

        return Response::error(
            $this->responseFactory->createResponse(),
            'HTTPS_REQUIRED',
            'Bu adımda şifre ve gizli anahtarlar gönderilir; bağlantı HTTPS değil. '
            . 'Kuruluma devam etmeden önce SSL sertifikası kurun ve sihirbazı https:// adresinden açın.',
            403,
        );
    }

    private function appliesTo(ServerRequestInterface $request): bool
    {
        return $request->getMethod() === 'POST'
            && in_array($request->getUri()->getPath(), self::SECRET_PATHS, true);
    }

    private function isAllowed(ServerRequestInterface $request): bool
    {
        if ($this->appEnv !== 'production') {
            return true;
        }
        if ($this->isHttps($request)) {
            return true;
        }

        return in_array(strtolower($request->getUri()->getHost()), self::LOOPBACK_HOSTS, true);
    }

    private function isHttps(ServerRequestInterface $request): bool
    {
        $https = $request->getServerParams()['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return $request->getUri()->getScheme() === 'https';
    }
}
