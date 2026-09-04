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
 *  • Loopback — aynı makine trafiği ağa çıkmaz; APP_ENV ayarlanmamış geliştirme
 *    kurulumları böyle çalışabilir.
 *
 * v1.2.1 C1 — ÜÇ DÜZELTME:
 *
 *  1. KAPI ZİNCİRE BAĞLANDI. Bu sınıf yazılmıştı, test edilmişti, gerekçesi
 *     belgelenmişti — ve HİÇBİR middleware zincirine eklenmemişti. Yani üretimde
 *     DB şifresi, yönetici şifresi ve TOTP kodu düz HTTP üzerinden gidebiliyordu.
 *     Ölü bir güvenlik kontrolü, hiç yazılmamış olandan TEHLİKELİDİR: belgede
 *     "var" görünür ve kimse bir daha bakmaz.
 *
 *  2. LOOPBACK KARARI `REMOTE_ADDR`A DAYANIR. Eskiden `Host` başlığına
 *     bakılıyordu; o değeri İSTEMCİ yazar. `Host: localhost` yazan düz bir HTTP
 *     isteği kapıyı açıyordu. Bağlantının gerçekten nereden geldiği yalnız
 *     `REMOTE_ADDR`da yazar.
 *
 *  3. GÜVENİLİR PROXY LİSTESİ (K44 modeli). Ters proxy arkasında şema `http`
 *     görünür; HTTPS yalnız `X-Forwarded-Proto` ile bilinir. Bu başlığı koşulsuz
 *     okumak kapıyı anlamsız kılardı (istemci kendi yazar), hiç okumamak ise
 *     proxy arkasındaki GERÇEK HTTPS kurulumlarını kilitlerdi. Başlık YALNIZ
 *     `TRUSTED_PROXIES` içindeki bir uzak adresten geldiğinde okunur.
 */
final class SetupHttpsGate implements MiddlewareInterface
{
    /** Sır girilen kurulum uçları (DB şifresi, yönetici şifresi, TOTP kodu). */
    private const SECRET_PATHS = [
        '/api/setup/database',
        '/api/setup/admin',
        '/api/setup/admin/verify',
    ];

    /**
     * Loopback UZAK ADRESLERİ — `Host` başlığı değil, bağlantının kaynağı.
     *
     * Boş dize CLI/test koşumlarında `REMOTE_ADDR` hiç gelmediği durumdur;
     * orada zaten ağ yoktur.
     */
    private const LOOPBACK_ADRESLER = ['127.0.0.1', '::1', ''];

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $appEnv,
        /**
         * Virgülle ayrılmış güvenilir proxy adresleri (config.php TRUSTED_PROXIES).
         * BOŞ VARSAYILAN BİLİNÇLİDİR: yapılandırılmamış bir kurulumda hiçbir
         * forwarded başlığı okunmaz — fail-closed.
         */
        private readonly string $guvenilirProxyler = '',
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

        return in_array($this->uzakAdres($request), self::LOOPBACK_ADRESLER, true);
    }

    /** Bağlantının GERÇEK kaynağı — istemci bu değeri yazamaz. */
    private function uzakAdres(ServerRequestInterface $request): string
    {
        $uzak = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        return is_string($uzak) ? strtolower(trim($uzak)) : '';
    }

    /** Uzak adres güvenilir proxy listesinde mi? */
    private function guvenilirKaynakMi(ServerRequestInterface $request): bool
    {
        if (trim($this->guvenilirProxyler) === '') {
            return false;
        }

        $liste = array_filter(array_map(
            static fn (string $adres): string => strtolower(trim($adres)),
            explode(',', $this->guvenilirProxyler),
        ));

        return in_array($this->uzakAdres($request), $liste, true);
    }

    private function isHttps(ServerRequestInterface $request): bool
    {
        $https = $request->getServerParams()['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        if ($request->getUri()->getScheme() === 'https') {
            return true;
        }

        // FORWARDED BAŞLIĞI YALNIZ GÜVENİLİR KAYNAKTAN OKUNUR (K44 modeli).
        // Aksi hâlde herkes `X-Forwarded-Proto: https` yazıp kapıdan geçerdi.
        if (!$this->guvenilirKaynakMi($request)) {
            return false;
        }

        return strtolower(trim($request->getHeaderLine('X-Forwarded-Proto'))) === 'https';
    }
}
