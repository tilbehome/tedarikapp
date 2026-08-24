<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Share\ShareLockPage;
use Tests\Support\AuthTestCase;

/**
 * KİLİT EKRANI DÜZENİ (İE#21 B7 · referans: `erisim-anahtar-ekrani.png`).
 *
 * Ekranın işi iki cümlede: firmayı doğru yerde olduğuna ikna etmek ve anahtarı
 * kolayca girdirmek.
 *
 * İE#21 EK-4 (PM denetimi) ile SÖZLEŞME DEĞİŞTİ — testler de onunla değişti:
 *  · TAZELEME SAYACI var (10 dk) ve bu ANAHTAR SÜRESİ DEĞİLDİR (K62 sürüyor),
 *  · "yeni anahtar iste" numara doluysa WhatsApp DÜĞMESİ, boşsa bilgi satırı,
 *  · "dakikada 5 deneme hakkı" KALICI satırı KALDIRILDI; uyarı yalnız art arda
 *    hatalı denemede belirir ve kalan hak sayısını söylemez.
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

    private function numarayiAyarla(string $numara): void
    {
        $yanit = $this->write('PUT', '/api/settings/share-contact', ['share_contact_phone' => $numara]);
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
    }

    /** Kilit ekranındaki wa.me bağlantısının çözülmüş mesaj metni. */
    private function whatsappMesaji(string $html): string
    {
        preg_match('/wa\.me\/\d+\?text=([^"]+)/', $html, $eslesme);
        self::assertNotEmpty($eslesme, 'WhatsApp köprüsü bulunamadı.');

        return rawurldecode((string) $eslesme[1]);
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

    public function testDENEMEHAKKIKALICIYAZILMAZ(): void
    {
        $html = $this->kilitEkrani();

        // EK-4 madde 4: hata yapmamış kullanıcı "deneme hakkı" yazısıyla karşılanmaz.
        self::assertStringNotContainsString('deneme hakkı', $html);
        self::assertStringNotContainsString('attempts per minute', $html);
        // Uyarı da henüz yok: art arda hata olmadan görünmemeli.
        self::assertStringNotContainsString('data-ardisik', $html);
    }

    public function testARDISIKHATADAUYARIBELIRIRAMASAYIVERMEZ(): void
    {
        for ($i = 0; $i < ShareLockPage::UYARI_ESIGI; $i++) {
            $this->call('POST', '/liste/' . $this->token . '/anahtar', ['anahtar' => 'ZZZZZ' . $i]);
        }

        $html = $this->kilitEkrani();

        self::assertStringContainsString('data-ardisik', $html);
        self::assertStringContainsString('Art arda hatalı deneme', $html);
        // Kalan hak SAYISI söylenmez (K51): saldırgana bütçesi bildirilmez.
        self::assertStringNotContainsString('deneme hakkı', $html);
    }

    public function testTAZELEMESAYACIVARVEANAHTARSURESIDEGILDIR(): void
    {
        $html = $this->kilitEkrani();

        // EK-4 madde 2: sayaç EKRANIN tazelenmesini sayar…
        self::assertStringContainsString('data-tazele="' . ShareLockPage::TAZELEME_SANIYE . '"', $html);
        self::assertStringContainsString('Bu güvenli giriş ekranı', $html);
        self::assertStringContainsString('tazelenir', $html);
        self::assertStringContainsString('10:00', $html);

        // …ANAHTARIN süresi olarak SUNULMAZ (K62 değişmedi).
        self::assertStringNotContainsString('Anahtar süresi', $html);

        // Bağlantı bitişi bilgi satırı yerinde kalır.
        self::assertStringContainsString('Bağlantı bitişi', $html);
        self::assertStringContainsString('Süre sınırı yok', $html);
    }

    public function testJSKAPALIYKENDETAZELENIR(): void
    {
        $html = $this->kilitEkrani();

        // Söz "ekran tazelenir"dir; betiksiz tarayıcıda da tutulmalı.
        self::assertStringContainsString(
            '<meta http-equiv="refresh" content="' . ShareLockPage::TAZELEME_SANIYE . '">',
            $html,
        );
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

    public function testNUMARAYOKSADUGMEBASILMAZ(): void
    {
        $html = $this->kilitEkrani();

        // Zarif bozulma: kanal yoksa vaat de yok — bilgi satırı kalır.
        self::assertStringNotContainsString('data-anahtar-iste', $html);
        self::assertStringContainsString('Anahtarınız yok mu?', $html);
        self::assertStringContainsString('listeyi paylaşan kişiyle iletişime geçin', $html);
    }

    public function testNUMARAVARSAWHATSAPPKOPRUSUBASILIR(): void
    {
        $this->numarayiAyarla('+90 532 123 45 67');

        $html = $this->kilitEkrani();

        self::assertStringContainsString('data-anahtar-iste', $html);
        // wa.me yalnız rakam kabul eder: "+", boşluk ve tire temizlenir.
        self::assertStringContainsString('https://wa.me/905321234567?text=', $html);
        self::assertStringContainsString('Yeni anahtar iste', $html);
    }

    public function testWHATSAPPMESAJIANAHTARTASIMAZ(): void
    {
        $this->numarayiAyarla('+90 532 123 45 67');
        $anahtar = (string) $this->json(
            $this->call('GET', '/api/lists/' . $this->listId . '/share-key'),
        )['data']['key'];

        $mesaj = $this->whatsappMesaji($this->kilitEkrani());

        // Anahtar mesaja ASLA girmez: talep kanalı, dağıtım kanalı değildir.
        self::assertStringNotContainsString($anahtar, $mesaj);
        self::assertStringContainsString('Mutfak Ürünleri', $mesaj);
        self::assertStringContainsString('erişim anahtarını rica ediyorum', $mesaj);
        self::assertStringContainsString('/liste/' . $this->token, $mesaj);
    }

    public function testWHATSAPPMESAJISECILIDILDE(): void
    {
        $this->numarayiAyarla('905321234567');

        foreach (['en' => 'access key', 'zh' => '访问密钥'] as $dil => $beklenen) {
            $mesaj = $this->whatsappMesaji($this->kilitEkrani($dil));

            self::assertStringContainsString($beklenen, $mesaj, $dil . ' mesajı kendi dilinde olmalı.');
        }
    }

    public function testGECERSIZNUMARAREDDEDILIR(): void
    {
        $yanit = $this->write('PUT', '/api/settings/share-contact', ['share_contact_phone' => '123']);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertStringContainsString('Ülke koduyla', (string) $yanit->getBody());
    }

    public function testNUMARATEMIZLENEBILIR(): void
    {
        $this->numarayiAyarla('905321234567');
        self::assertStringContainsString('data-anahtar-iste', $this->kilitEkrani());

        $this->write('PUT', '/api/settings/share-contact', ['share_contact_phone' => '']);

        self::assertStringNotContainsString('data-anahtar-iste', $this->kilitEkrani());
    }

    public function testHATALIDENEMEDESECILIDILKORUNUR(): void
    {
        $yanit = $this->call('POST', '/liste/' . $this->token . '/anahtar', [
            'anahtar' => 'ZZZZZZ',
            'lang' => 'en',
        ]);

        self::assertSame(401, $yanit->getStatusCode());
        $html = (string) $yanit->getBody();
        // Dil kaybolursa firma kendi dilinden Türkçeye düşer — form gizli alanla taşır.
        self::assertStringContainsString('SECURE LIST ACCESS', $html);
        self::assertStringContainsString('Incorrect key', $html);
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
