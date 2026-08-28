<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Ilan\AramaMetni;
use App\Services\Ilan\HazirlikKapisi;
use PHPUnit\Framework\TestCase;

/**
 * İE#20 C7 (çift dilli arama) ve C8 ("HAZIR" kalite kapısı).
 */
final class KaliteVeAramaTest extends TestCase
{
    /** @return array<string, mixed> */
    private function tamUrun(): array
    {
        return [
            'name' => 'Çift Cidarlı Termos',
            'url' => 'https://detail.1688.com/offer/9001.html',
            'main_image' => '/media/abc.jpg',
            'sku_selection' => '{"renk":"Gri"}',
            'qty' => 240,
            'price_yuan' => '12.0000',
            'category_id' => 3,
        ];
    }

    // ── C8 ──────────────────────────────────────────────────────────────────
    public function testTamUrunHAZIROLABILIR(): void
    {
        self::assertSame([], HazirlikKapisi::eksikler($this->tamUrun()));
        self::assertTrue(HazirlikKapisi::hazirOlabilirMi($this->tamUrun()));
    }

    public function testEksikALANLARISIMISIMDONER(): void
    {
        $urun = $this->tamUrun();
        unset($urun['category_id']);
        $urun['main_image'] = '';

        $eksik = HazirlikKapisi::eksikler($urun);

        // Sayı değil İSİM: "3 alan eksik" kullanıcıyı tahmine iter.
        self::assertContains('Kategori', $eksik);
        self::assertContains('Ana görsel', $eksik);
        self::assertCount(2, $eksik);
    }

    public function testSIFIRFIYATGIRILMEMISSAYILIR(): void
    {
        $urun = $this->tamUrun();
        $urun['price_yuan'] = '0.0000';

        self::assertContains('Birim fiyat', HazirlikKapisi::eksikler($urun));
    }

    public function testBOSVARYANTSECIMIEKSIKTIR(): void
    {
        foreach (['', '{}', '[]', 'null'] as $bos) {
            $urun = $this->tamUrun();
            $urun['sku_selection'] = $bos;

            self::assertContains('Seçili varyant', HazirlikKapisi::eksikler($urun), 'Boş seçim: ' . $bos);
        }
    }

    public function testBOSLISTETAMAMLANAMAZ(): void
    {
        $sonuc = HazirlikKapisi::listeTamamlanabilirMi([]);

        self::assertFalse($sonuc['tamamlanabilir']);
        self::assertNotNull($sonuc['neden']);
        self::assertStringContainsString('BOŞ', $sonuc['neden']);
    }

    public function testHAZIROLMAYANURUNSAYILIRIPTALHARIC(): void
    {
        $eksikUrun = $this->tamUrun();
        unset($eksikUrun['url']);

        $iptalEdilmis = $this->tamUrun();
        unset($iptalEdilmis['url']);
        $iptalEdilmis['status'] = 'cancelled';

        $sonuc = HazirlikKapisi::listeTamamlanabilirMi([$this->tamUrun(), $eksikUrun, $iptalEdilmis]);

        self::assertTrue($sonuc['tamamlanabilir']);
        self::assertSame(1, $sonuc['hazir_olmayan'], 'İptal edilen ürün hazırlık kapısına girmemeli.');
    }

    // ── C7 ──────────────────────────────────────────────────────────────────
    public function testAramaMetniUCDILIDEICERIR(): void
    {
        $metin = AramaMetni::uret(
            [
                'name' => 'Termos',
                'name_original' => '保温杯',
                'external_id' => '9001',
                'vendor_name' => 'Ningbo Co.',
            ],
            ['Thermos flask'],
            'Mutfak',
        );

        self::assertStringContainsString('Termos', $metin);
        self::assertStringContainsString('保温杯', $metin, 'Çince başlıktan arama çalışmalı.');
        self::assertStringContainsString('Thermos flask', $metin, 'EN çeviriden arama çalışmalı.');
        self::assertStringContainsString('9001', $metin, 'İlan numarasından arama çalışmalı.');
        self::assertStringContainsString('Mutfak', $metin);
    }

    public function testAramaMetniTEKRARLARIELER(): void
    {
        $metin = AramaMetni::uret(['name' => 'Termos', 'name_original' => 'Termos'], ['Termos']);

        self::assertSame('Termos', $metin);
    }

    public function testAramaMetniSINIRLIDIR(): void
    {
        $metin = AramaMetni::uret(['name' => str_repeat('a', 5000)]);

        self::assertLessThanOrEqual(AramaMetni::MAX_UZUNLUK, mb_strlen($metin));
    }

    public function testCINCESORGULIKEYEDUSER(): void
    {
        // CJK'da kelime sınırı yoktur; FULLTEXT bulamaz (MariaDB'de ngram da yok).
        [$parca, $params] = AramaMetni::sorguParcasi('保温杯', true);

        self::assertStringContainsString('LIKE', $parca);
        self::assertSame('%保温杯%', $params['arama']);
    }

    public function testLatinSorguFULLTEXTKULLANIR(): void
    {
        [$parca, $params] = AramaMetni::sorguParcasi('termos kabı', true);

        self::assertStringContainsString('MATCH', $parca);
        self::assertSame('+termos* +kabı*', $params['arama']);
    }

    public function testFULLTEXTYOKSALIKEYEDUSER(): void
    {
        [$parca] = AramaMetni::sorguParcasi('termos', false);

        self::assertStringContainsString('LIKE', $parca);
    }

    public function testBOOLEANOPERATORLERIKACIRILIR(): void
    {
        // Kullanıcının yazdığı "+" bir operatör değil, arama terimidir; temizlenmezse
        // MySQL sözdizimi hatası verir ve arama kutusu ÇÖKER.
        [, $params] = AramaMetni::sorguParcasi('termos +(500ml)* "gri"', true);

        self::assertStringNotContainsString('(', $params['arama']);
        self::assertStringNotContainsString('"', $params['arama']);
    }

    public function testBOSSORGUPARCAURETMEZ(): void
    {
        self::assertSame(['', []], AramaMetni::sorguParcasi('   ', true));
    }
}
