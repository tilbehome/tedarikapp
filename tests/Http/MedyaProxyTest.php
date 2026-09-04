<?php

declare(strict_types=1);

namespace Tests\Http;

use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;

/**
 * v1.2.2 BLOK D2 — K47 GÖRSEL VEKİLİ (geçici gösterim).
 *
 * Yakalama artık indirmediği için (D1) ürün, kuyruk gelene kadar UZAK bir
 * adres taşır. Tarayıcı o adresi doğrudan çizemez: alicdn Referer ACL
 * uygular ve kare boş kalır. Vekil, görseli SUNUCU üzerinden getirir —
 * kullanıcı bekleme süresinde de bir şey görür.
 *
 * VEKİL BİR SSRF KAPISI OLMAMALI:
 *   · yalnız girişli kullanıcı (panel yüzeyi),
 *   · yalnız MEDIA_ALLOWED_HOSTS beyaz listesi (UrlGuard — indirme hattıyla
 *     AYNI denetim; iki ayrı beyaz liste, iki ayrı açık demektir),
 *   · yalnız GERÇEK görsel (imza denetimi; HTML dönen bir adres 415 alır —
 *     kullanıcının tarayıcısına başkasının HTML'ini akıtmayız),
 *   · boyut MEDIA_MAX_MB ile sınırlı.
 */
final class MedyaProxyTest extends AuthTestCase
{
    private FakeMediaFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetcher = new FakeMediaFetcher();
        $this->mediaFetcher = $this->fetcher;
    }

    private function girisYap(): void
    {
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
    }

    private function vekil(string $url): ResponseInterface
    {
        return $this->call('GET', '/api/media/proxy?url=' . rawurlencode($url));
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(8, 8);
        self::assertNotFalse($image);
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    public function testGIRISSIZ401(): void
    {
        self::assertSame(401, $this->vekil('https://cbu01.alicdn.com/img/a.jpg')->getStatusCode());
    }

    public function testBEYAZLISTEDISIHOST403(): void
    {
        $this->girisYap();
        $this->fetcher->respondWith('https://evil.example/a.jpg', $this->jpeg(), 'image/jpeg');

        $yanit = $this->vekil('https://evil.example/a.jpg');

        self::assertSame(403, $yanit->getStatusCode());
        self::assertSame(0, $this->fetcher->callCount, 'Beyaz liste dışı adres için ağa ÇIKILMAMALI.');
    }

    public function testICAGADRESI403(): void
    {
        $this->girisYap();

        self::assertSame(403, $this->vekil('http://127.0.0.1/admin')->getStatusCode());
        self::assertSame(0, $this->fetcher->callCount);
    }

    public function testGORSELBAYTLARIVEDOGRUICERIKTURUYLEDONER(): void
    {
        $this->girisYap();
        $govde = $this->jpeg();
        $this->fetcher->respondWith('https://cbu01.alicdn.com/img/a.jpg', $govde, 'image/jpeg');

        $yanit = $this->vekil('https://cbu01.alicdn.com/img/a.jpg');

        self::assertSame(200, $yanit->getStatusCode());
        self::assertSame('image/jpeg', $yanit->getHeaderLine('Content-Type'));
        self::assertSame($govde, (string) $yanit->getBody());
        self::assertStringContainsString('private', $yanit->getHeaderLine('Cache-Control'));
        // Genel ara katman başlığı genişletir (nofollow, noarchive); vekil en az noindex der.
        self::assertStringContainsString('noindex', $yanit->getHeaderLine('X-Robots-Tag'));
    }

    public function testGORSELOLMAYANICERIK415(): void
    {
        // Kaynak "image/jpeg" dese de gövde HTML ise vekil onu AKITMAZ.
        $this->girisYap();
        $this->fetcher->respondWith('https://cbu01.alicdn.com/img/sahte.jpg', '<html><script>x</script>', 'image/jpeg');

        $yanit = $this->vekil('https://cbu01.alicdn.com/img/sahte.jpg');

        self::assertSame(415, $yanit->getStatusCode());
        self::assertStringNotContainsString('<script>', (string) $yanit->getBody());
    }

    public function testINDIRMEHATASI502(): void
    {
        $this->girisYap();
        // Yanıt tanımsız → sahte indirici MediaException atar (ağ hatasının eşdeğeri).

        self::assertSame(502, $this->vekil('https://cbu01.alicdn.com/img/yok.jpg')->getStatusCode());
    }

    public function testURLPARAMETRESIZORUNLU(): void
    {
        $this->girisYap();

        self::assertSame(422, $this->call('GET', '/api/media/proxy')->getStatusCode());
    }
}
