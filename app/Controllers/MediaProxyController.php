<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\MediaDeniedException;
use App\Services\MediaException;
use App\Services\MediaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * K47 GÖRSEL VEKİLİ — geçici gösterim (v1.2.2 D2).
 *
 * Yakalama artık indirmediği için (D1) ürün, kuyruk gelene kadar UZAK bir
 * adres taşır. Tarayıcı o adresi doğrudan çizemez: alicdn Referer ACL uygular
 * ve kare boş kalır. Vekil görseli SUNUCU üzerinden getirir — kullanıcı
 * bekleme süresinde de bir şey görür.
 *
 * VEKİL BİR SSRF KAPISI DEĞİLDİR, çünkü indirme hattıyla AYNI denetimden
 * geçer (`MediaService::vekilGetir` → `UrlGuard`): yalnız https, yalnız
 * MEDIA_ALLOWED_HOSTS, yalnız açık ağ adresleri, DNS pinleme. İki ayrı beyaz
 * liste iki ayrı açık demektir; bu yüzden ikinci bir liste YOKTUR.
 *
 * GERÇEK GÖRSEL ŞARTI: kaynak "image/jpeg" dese de gövde HTML ise 415 döner.
 * Kullanıcının tarayıcısına başkasının HTML'ini akıtmak, vekilin en kolay
 * kötüye kullanımıdır; imza denetimi bunu yapısal olarak kapatır.
 *
 * Yalnız girişli kullanıcı (panel yüzeyi); GET olduğu için CSRF muaf.
 */
final class MediaProxyController
{
    public function __construct(private readonly MediaService $media)
    {
    }

    public function proxy(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $url = $request->getQueryParams()['url'] ?? null;
        if (!is_string($url) || trim($url) === '') {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['url' => 'Görsel adresi zorunludur.']);
        }

        try {
            $gorsel = $this->media->vekilGetir(trim($url));
        } catch (MediaDeniedException) {
            // K51: sabit mesaj — hangi kuralın reddettiği saldırgana bilgi verir.
            return Response::error($response, 'FORBIDDEN', 'Bu adres vekil üzerinden gösterilemez.', 403);
        } catch (MediaException $hata) {
            // Gövde görsel değil ↔ ağ/kaynak hatası: ikisi ayrı teşhistir.
            $gorselDegil = str_contains($hata->getMessage(), 'görsel')
                || str_contains($hata->getMessage(), 'tür');

            return $gorselDegil
                ? Response::error($response, 'UNSUPPORTED_MEDIA', 'Kaynak bir görsel döndürmedi.', 415)
                : Response::error($response, 'UPSTREAM_ERROR', 'Görsel kaynaktan alınamadı.', 502);
        }

        $response->getBody()->write($gorsel['body']);

        return $response
            ->withHeader('Content-Type', $gorsel['mime'])
            ->withHeader('Content-Length', (string) strlen($gorsel['body']))
            // private: girişli kullanıcıya özel yüzey; ara önbellekler saklamaz.
            ->withHeader('Cache-Control', 'private, max-age=3600')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Robots-Tag', 'noindex');
    }
}
