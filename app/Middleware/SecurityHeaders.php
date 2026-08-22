<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * K16 güvenlik başlıkları — her yanıta eklenir (docs/04 §5).
 *
 * `img-src` sabit değildir: K33 hotlink modunda görseller 1688/alicdn üzerinden
 * gösterilir, sabit `'self' data:` politikası bu görselleri tarayıcıda BLOKLAR.
 * Bu yüzden izinli kaynaklar `MEDIA_ALLOWED_HOSTS` ayarından türetilir — indirme
 * beyaz listesiyle görüntüleme politikası tek kaynaktan beslenir.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    /** @var list<string> */
    private readonly array $imageSources;

    /** @var list<string> */
    private readonly array $videoSources;

    /**
     * @param list<string> $mediaHosts `MEDIA_ALLOWED_HOSTS` (örn. ['alicdn.com', '1688.com'])
     * @param list<string> $videoHosts `VIDEO_ALLOWED_HOSTS` — İE#17 G11: YALNIZ `media-src`
     *                                 besler. Ürün videoları çoğunlukla görsellerden BAŞKA
     *                                 bir CDN'den gelir (cloud.video.taobao.com); bu hostlar
     *                                 img-src'ye ve İNDİRME beyaz listesine (UrlGuard /
     *                                 MEDIA_ALLOWED_HOSTS) EKLENMEZ: video yalnız gömülü
     *                                 oynatılır, sunucu oradan dosya İNDİRMEZ (K8/K31 dar kapı).
     */
    public function __construct(array $mediaHosts = [], array $videoHosts = [])
    {
        $this->imageSources = self::kaynaklar($mediaHosts);
        // Video kaynakları görsel kaynaklarını KAPSAR: poster görüntüsü aynı
        // beyaz listeden gelir, ayrıca video CDN'i eklenir.
        $this->videoSources = array_values(array_unique(
            array_merge($this->imageSources, self::kaynaklar($videoHosts)),
        ));
    }

    /**
     * @param list<string> $hosts
     *
     * @return list<string>
     */
    private static function kaynaklar(array $hosts): array
    {
        $sources = ["'self'", 'data:'];
        foreach ($hosts as $host) {
            $host = strtolower(trim($host));
            if ($host === '') {
                continue;
            }
            // Yalnız HTTPS (K33 hükmü) ve alt alan adları: cbu01.alicdn.com gibi CDN'ler.
            $sources[] = 'https://' . $host;
            $sources[] = 'https://*.' . $host;
        }

        return array_values(array_unique($sources));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // İE#17 G11 — media-src AYRI beslenir. Eskiden img-src ile aynıydı; 1688'in
        // oynatılabilir video adresleri başka bir CDN'den geldiği için tarayıcı
        // oynatmayı SESSİZCE blokluyordu (konsol dışında belirti yok). Görsel
        // beyaz listesi genişletilmedi: video hostları YALNIZ media-src'dedir.
        $csp = sprintf(
            "default-src 'self'; img-src %s; media-src %s; object-src 'none'; frame-ancestors 'none'; base-uri 'self'",
            implode(' ', $this->imageSources),
            implode(' ', $this->videoSources),
        );

        return $handler->handle($request)
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            // Ürün Sahibi kararı (İE#10.5 ek): uygulama aramaya TAMAMEN kapalı —
            // her yanıt indexleme/arşivleme yasağı taşır (paylaşım sayfası dahil).
            ->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
