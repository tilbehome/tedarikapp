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

    /** @param list<string> $mediaHosts `MEDIA_ALLOWED_HOSTS` değerleri (örn. ['alicdn.com', '1688.com']) */
    public function __construct(array $mediaHosts = [])
    {
        $sources = ["'self'", 'data:'];
        foreach ($mediaHosts as $host) {
            $host = strtolower(trim($host));
            if ($host === '') {
                continue;
            }
            // Yalnız HTTPS (K33 hükmü) ve alt alan adları: cbu01.alicdn.com gibi CDN'ler.
            $sources[] = 'https://' . $host;
            $sources[] = 'https://*.' . $host;
        }

        $this->imageSources = array_values(array_unique($sources));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $csp = sprintf(
            "default-src 'self'; img-src %s; object-src 'none'; frame-ancestors 'none'; base-uri 'self'",
            implode(' ', $this->imageSources),
        );

        return $handler->handle($request)
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
