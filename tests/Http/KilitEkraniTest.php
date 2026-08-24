<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Share\ShareGate;
use Tests\Support\AuthTestCase;

/**
 * KİLİT EKRANI DÜZENİ (İE#21 B7 · referans: `erisim-anahtar-ekrani.png`).
 *
 * Ekranın işi iki cümlede: firmayı doğru yerde olduğuna ikna etmek ve anahtarı
 * kolayca girdirmek. Bu testler o iki işi ve İKİ BİLİNÇLİ SAPMAYI korur:
 *
 *  · anahtarın süresi YOK → geri sayım basılmaz (olmayan kural gösterilmez),
 *  · "yeni anahtar iste" bir DÜĞME değil bilgi satırıdır (çalışmayan vaat yok).
 *
 * Veri sınırı testleri `ErisimAnahtariTest`te; burada yalnız DÜZEN sınanır.
 */
final class KilitEkraniTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listId = 0;
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listId = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Mutfak Ürünleri',
            'supplier_name' => 'Yok Yok AVM',
        ]))['data']['id'];

        $url = (string) $this->json($this->write('POST', '/api/lists/' . $this->listId . '/share'))['data']['share_url'];
        $this->token = substr($url, strrpos($url, '/') + 1);
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function kilitEkrani(string $dil = 'tr'): string
    {
        return (string) $this->call('GET', '/liste/' . $this->token . ($dil === 'tr' ? '' : '?lang=' . $dil))->getBody();
    }

    public function testREFERANSDUZENIBASILIR(): void
    {
        $html = $this->kilitEkrani();

        self::assertStringContainsString('GÜVENLİ LİSTE ERİŞİMİ', $html);
        self::assertStringContainsString('Mutfak Ürünleri', $html);
        self::assertStringContainsString('Yok Yok AVM', $html);
        self::assertStringContainsString('Şifreli bağlantı', $html);
        self::assertStringContainsString('Listeyi görüntüle', $html);
        self::assertStringContainsString('Kodu yapıştırabilirsiniz', $html);
        self::assertStringContainsString('Enter ile devam', $html);
        self::assertStringContainsString('üçüncü taraflarla paylaşılmaz', $html);
    }

    public function testDENEMEHAKKITEKKAYNAKTANYAZILIR(): void
    {
        $html = $this->kilitEkrani();

        // Sayı ShareGate'ten gelir: ekranda yazan hak ile sunucunun uyguladığı
        // sınır ayrışırsa kullanıcı yanlış bilgilendirilmiş olur.
        self::assertStringContainsString(
            sprintf('dakikada %d deneme hakkı', ShareGate::MAX_ANAHTAR_PER_MINUTE),
            $html,
        );
    }

    public function testOLMAYANGERISAYIMGOSTERILMEZ(): void
    {
        $html = $this->kilitEkrani();

        // Anahtarın süresi yok (K62): "Anahtar süresi" ve dakika:saniye sayacı
        // referans karede olsa da BASILMAZ — gösterilse, olmayan bir kural
        // vaat edilmiş olurdu.
        self::assertStringNotContainsString('Anahtar süresi', $html);
        self::assertDoesNotMatchRegularExpression('/\d{2}:\d{2}<\/(strong|span|div)>/', $html);

        // Yerine GERÇEK bilgi: bağlantı bitişi yoksa "süre sınırı yok".
        self::assertStringContainsString('Bağlantı bitişi', $html);
        self::assertStringContainsString('Süre sınırı yok', $html);
    }

    public function testBAGLANTIBITISIVARSATARIHYAZILIR(): void
    {
        // Bitiş tarihli YENİ paylaşım: token yenilenir, adres de yenisidir.
        $yanit = $this->write('POST', '/api/lists/' . $this->listId . '/share', ['expires_at' => '2026-12-31']);
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        $govde = $this->json($yanit)['data'];
        self::assertNotNull($govde['share_expires_at'] ?? null, (string) $yanit->getBody());
        $yeni = (string) $govde['share_url'];
        $this->token = substr($yeni, strrpos($yeni, '/') + 1);

        $html = $this->kilitEkrani();

        self::assertStringContainsString('31.12.2026', $html);
        self::assertStringNotContainsString('Süre sınırı yok', $html);
    }

    public function testYENIANAHTARISTEDUGMEDEGILBILGIDIR(): void
    {
        $html = $this->kilitEkrani();

        self::assertStringContainsString('Anahtarınız yok mu?', $html);
        self::assertStringContainsString('listeyi paylaşan kişiyle iletişime geçin', $html);

        // Formda TEK gönderim düğmesi olmalı: çalışmayan ikinci bir düğme yok.
        self::assertSame(1, preg_match_all('/<button/', $html));
    }

    public function testDILSECICIUCDILISUNAR(): void
    {
        $html = $this->kilitEkrani();

        self::assertStringContainsString('?lang=en', $html);
        self::assertStringContainsString('?lang=zh', $html);
        // Aktif dil bağlantı DEĞİL: kullanıcı bulunduğu dile tıklamaz.
        self::assertStringContainsString('kis-dil-aktif', $html);
    }

    public function testINGILIZCEVECINCEKILITEKRANI(): void
    {
        $ingilizce = $this->kilitEkrani('en');
        self::assertStringContainsString('SECURE LIST ACCESS', $ingilizce);
        self::assertStringContainsString('View list', $ingilizce);
        self::assertStringNotContainsString('GÜVENLİ LİSTE ERİŞİMİ', $ingilizce);

        $cince = $this->kilitEkrani('zh');
        self::assertStringContainsString('安全清单访问', $cince);
        self::assertStringContainsString('查看清单', $cince);
    }

    public function testSATIRICISCRIPTVESTILYOK(): void
    {
        $html = $this->kilitEkrani();

        // K51: CSP satır içi script/stil kabul etmez — düzen değişse de bu kural
        // yerinde kalmalı.
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('onclick=', $html);
        self::assertStringNotContainsString('style="', $html);
    }

    public function testALTIHANEKUTUSUVEKALANSAYACIVAR(): void
    {
        $html = $this->kilitEkrani();

        self::assertSame(6, preg_match_all('/class="kis-hane"/', $html));
        self::assertStringContainsString('data-anahtar-kalan', $html);
        self::assertStringContainsString('6 hane kaldı', $html);
    }

    // ── İE#21 B6: KANAL METNİ UCU ──────────────────────────────────────────

    public function testKANALMETNIUCDILDEDONER(): void
    {
        $this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'Termos', 'qty' => 10, 'price_yuan' => '5.00',
        ]);

        $tr = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/share-text?lang=tr'))['data'];
        $en = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/share-text?lang=en'))['data'];
        $zh = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/share-text?lang=zh'))['data'];

        self::assertStringContainsString('Mutfak Ürünleri', $tr['mesaj']);
        self::assertStringContainsString('1 ürün', $tr['mesaj']);
        self::assertStringContainsString('supply list', mb_strtolower($en['mesaj']));
        self::assertStringContainsString('采购清单', $zh['mesaj']);
    }

    public function testKANALMETNIBAGLANTIYITASIMAZ(): void
    {
        $veri = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/share-text?lang=tr'))['data'];

        // Şablon `{link}` yer tutucusuyla döner: tam token isteğe (ve olası erişim
        // günlüğüne) hiç girmez; paneldeki adresle DEĞİŞTİRİLİR (K51).
        self::assertStringContainsString('{link}', $veri['mesaj']);
        self::assertStringNotContainsString($this->token, $veri['mesaj']);
    }

    public function testGECERSIZDILTURKCEYEDUSER(): void
    {
        $veri = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/share-text?lang=de'))['data'];

        self::assertSame('tr', $veri['dil']);
    }

    public function testKANALMETNIOTURUMISTER(): void
    {
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        $yanit = $this->call('GET', '/api/lists/' . $this->listId . '/share-text?lang=tr');

        self::assertSame(401, $yanit->getStatusCode());
    }
}
