<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * V3-B C3 — SÖZLÜK CSV DIŞA/İÇE AKTARMA (PNL-50/51).
 *
 * ASIL SINANAN ŞART: çakışmada KULLANICI TERİMİ KAZANIR. Kullanıcı bir
 * karşılığı elle düzelttiyse, sonradan yüklenen bir dosya onu EZMEMELİDİR —
 * ezseydi kimse fark etmezdi ve sözlük sessizce eski hâline dönerdi.
 */
final class SozlukCsvTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
    }

    public function testDISAAKTARIMCSVVEBOMLUDONER(): void
    {
        $yanit = $this->call('GET', '/api/settings/glossary/disa-aktar?lang=zh');

        self::assertSame(200, $yanit->getStatusCode());
        self::assertStringContainsString('text/csv', $yanit->getHeaderLine('Content-Type'));
        self::assertStringContainsString('sozluk-zh-tr.csv', $yanit->getHeaderLine('Content-Disposition'));

        $govde = (string) $yanit->getBody();
        // BOM olmadan Excel Windows'ta Çince terimleri bozuk gösterir.
        self::assertStringStartsWith("\xEF\xBB\xBF", $govde, 'CSV BOM ile başlamalı.');
        self::assertStringContainsString('kaynak;turkce', $govde, 'Başlık satırı olmalı.');
    }

    public function testICEAKTARIMYENITERIMEKLER(): void
    {
        $sonuc = $this->json($this->call(
            'POST',
            '/api/settings/glossary/ice-aktar',
            ['lang' => 'zh', 'csv' => "kaynak;turkce\n测试terim;Deneme Terimi\n"],
            [Csrf::HEADER => $this->csrf],
        ))['data'];

        self::assertSame(1, $sonuc['eklenen']);
        self::assertSame(0, $sonuc['atlanan']);

        $terimler = $this->json($this->call('GET', '/api/settings/glossary?lang=zh'))['data']['terms'];
        self::assertSame('Deneme Terimi', $terimler['测试terim'] ?? null);
    }

    public function testCAKISMADAKULLANICITERIMIKAZANIR(): void
    {
        // 1) Kullanıcı terimi ELLE yazar.
        $this->call(
            'PUT',
            '/api/settings/glossary',
            ['lang' => 'zh', 'terms' => ['共享术语' => 'Kullanıcının Karşılığı']],
            [Csrf::HEADER => $this->csrf],
        );

        // 2) Dosya AYNI terime BAŞKA karşılık getirir.
        $sonuc = $this->json($this->call(
            'POST',
            '/api/settings/glossary/ice-aktar',
            ['lang' => 'zh', 'csv' => "共享术语;Dosyadan Gelen\n"],
            [Csrf::HEADER => $this->csrf],
        ))['data'];

        self::assertSame(0, $sonuc['eklenen']);
        self::assertSame(1, $sonuc['atlanan'], 'Çakışan satır ATLANMALI.');

        $terimler = $this->json($this->call('GET', '/api/settings/glossary?lang=zh'))['data']['terms'];
        self::assertSame(
            'Kullanıcının Karşılığı',
            $terimler['共享术语'] ?? null,
            'Kullanıcının elle yazdığı karşılık dosyayla EZİLMEMELİ.',
        );
    }

    public function testVIRGULAYRACIDAKABULEDILIR(): void
    {
        // Excel'in Türkçe yereli `;`, başka araçlar `,` yazar. Kullanıcıya
        // "hangi ayracı kullanmalıyım?" diye sormuyoruz.
        $sonuc = $this->json($this->call(
            'POST',
            '/api/settings/glossary/ice-aktar',
            // Terimler BİLEREK uydurma: gerçek sözcükler temel sözlükte
            // bulunabilir ve o zaman bu test ayracı değil çakışma kuralını
            // ölçerdi — ilk denemede tam bu oldu ("widget" zaten sözlükteydi).
            ['lang' => 'en', 'csv' => "source,turkish\nzzqwidget,Parça\nzzqgadget,Aygıt\n"],
            [Csrf::HEADER => $this->csrf],
        ))['data'];

        self::assertSame(2, $sonuc['eklenen'], json_encode($sonuc, JSON_UNESCAPED_UNICODE));
        self::assertSame(0, $sonuc['bozuk'], 'Başlık satırı bozuk sayılmamalı.');
    }

    public function testBOZUKSATIRSAYILIRAMAAKISIDURDURMAZ(): void
    {
        $sonuc = $this->json($this->call(
            'POST',
            '/api/settings/glossary/ice-aktar',
            ['lang' => 'zh', 'csv' => "iyi;İyi\nbozuksatir\nikinci;İkinci\n"],
            [Csrf::HEADER => $this->csrf],
        ))['data'];

        self::assertSame(2, $sonuc['eklenen'], 'Bozuk satır diğerlerini düşürmemeli.');
        self::assertSame(1, $sonuc['bozuk'], 'Bozuk satır SAYILMALI — sessizce yutulmamalı.');
    }

    public function testBOSDOSYAREDDEDILIR(): void
    {
        $yanit = $this->call(
            'POST',
            '/api/settings/glossary/ice-aktar',
            ['lang' => 'zh', 'csv' => "   \n"],
            [Csrf::HEADER => $this->csrf],
        );

        self::assertSame(422, $yanit->getStatusCode());
    }

    public function testICEAKTARIMBILDIRIMURETIR(): void
    {
        $this->call(
            'POST',
            '/api/settings/glossary/ice-aktar',
            ['lang' => 'zh', 'csv' => "bildirimli;Bildirimli\n"],
            [Csrf::HEADER => $this->csrf],
        );

        $bildirimler = $this->json($this->call('GET', '/api/bildirimler'))['data']['bildirimler'];
        $kodlar = array_column($bildirimler, 'olay_kodu');

        self::assertContains('NTF-GLOSSARY-IMPORTED', $kodlar, 'Sözlük içe aktarımı sessiz çalışamaz.');
    }
}
