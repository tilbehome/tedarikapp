<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Tur\TurIslemiReddedildi;
use App\Services\Yanit\ExcelIceAktarici;
use App\Services\Yanit\ExcelSablonu;
use App\Services\Yanit\ExcelSema;
use App\Services\Yanit\SatirImzasi;
use App\Services\Yanit\YanitDonusturucu;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * EXCEL GEL-GİT — şablon üretimi + içe aktarım önizlemesi (spec `excel-gelgit-spec.md`).
 *
 * DB'siz: tur ve RFQ satırları dizi olarak verilir. Uçtan uca (uç + DB) sınama
 * `tests/Http/TurYanitTest`te. Burada spec'in "fail-closed" maddeleri tek tek:
 *   §1 beş sayfa, VALIDATION/MANIFEST çok gizli, dosyada link/anahtar YOK
 *   §6 makro / dış bağlantı / OLE / şifreli → ret; formül çalıştırılmaz
 *   §7 başka tur → tüm içe aktarım ret; snapshot farkı → ret; manifest imzası
 *   §8 yabancı kimlik · mükerrer · imza bozuk · boş hücre temizlemez ·
 *      para birimsiz fiyat belirsiz · brüt<net hata · geçersiz kademe
 */
final class ExcelGelGitTest extends TestCase
{
    private const APP_KEY = 'test-app-key-0123456789abcdef';

    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/tedarikapp-excel-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
    }

    // ── şablon ─────────────────────────────────────────────────────────

    public function testSABLONBESSAYFAGIZLILISTELERVESIRSIZDIR(): void
    {
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'), 'en');

        $kitap = $this->yukle($bytes);
        $adlar = array_map(static fn (Worksheet $s): string => $s->getTitle(), $kitap->getAllSheets());
        self::assertSame([ExcelSema::SAYFA_START, ExcelSema::SAYFA_QUOTATION, ExcelSema::SAYFA_TIERS, ExcelSema::SAYFA_VALIDATION, ExcelSema::SAYFA_MANIFEST], $adlar);
        self::assertSame(Worksheet::SHEETSTATE_VERYHIDDEN, $kitap->getSheetByName(ExcelSema::SAYFA_VALIDATION)?->getSheetState());
        self::assertSame(Worksheet::SHEETSTATE_VERYHIDDEN, $kitap->getSheetByName(ExcelSema::SAYFA_MANIFEST)?->getSheetState());

        $q = $kitap->getSheetByName(ExcelSema::SAYFA_QUOTATION);
        self::assertNotNull($q);
        self::assertTrue($q->getProtection()->getSheet(), 'Sayfa koruması açık (yanlış düzenleme önlemi).');
        self::assertSame('S-0001', $q->getCell('A2')->getValue());
        self::assertSame('P00001', $q->getCell('D2')->getValue());
        self::assertSame('Glass oil dispenser', $q->getCell('E2')->getValue());
        self::assertSame('600', $q->getCell('H2')->getValue());
        self::assertSame('7', $q->getCell('B2')->getValue(), 'Tur kimliği gizli sütunda.');
        self::assertFalse($q->getColumnDimension('C')->getVisible(), 'İmza sütunu gizli.');

        // Dosyada link/anahtar/sır yok: paylaşım token'ı, 6 haneli anahtar, APP_KEY.
        self::assertStringNotContainsString(self::APP_KEY, $bytes);
        self::assertStringNotContainsString('/s/', (string) $q->getCell('F2')->getValue());
        self::assertStringNotContainsString('123456', $bytes);

        // Formül enjeksiyonu: "=" ile başlayan ürün adı METİN olarak yazılmıştır.
        self::assertSame('=HYPERLINK("x")', $q->getCell('E3')->getValue());
        self::assertSame(DataType::TYPE_STRING, $q->getCell('E3')->getDataType());
    }

    public function testMEVCUTTASLAKSABLONAONCEDENDOLUGELIR(): void
    {
        $mevcut = ['S-0001' => YanitDonusturucu::bos('S-0001') + []];
        $mevcut['S-0001']['yanit_durumu'] = 'found';
        $mevcut['S-0001']['ddp_birim_fiyat'] = '186.50';
        $mevcut['S-0001']['para_birimi'] = 'TRY';
        $mevcut['S-0001']['ddp_kdv_dahil_onayi'] = true;
        $mevcut['S-0001']['kademeler'] = [['min_adet' => '600', 'max_adet' => null, 'birim_fiyat' => '186.50', 'para_birimi' => 'TRY', 'kademe_tipi' => 'esik']];

        $kitap = $this->yukle($this->sablon()->uret($this->tur(), $this->rfq(), $mevcut, new DateTimeImmutable('2026-09-04 10:00:00')));
        $q = $kitap->getSheetByName(ExcelSema::SAYFA_QUOTATION);
        $t = $kitap->getSheetByName(ExcelSema::SAYFA_TIERS);

        self::assertSame('found', $q?->getCell('K2')->getValue());
        self::assertSame('186.50', $q?->getCell('L2')->getValue());
        self::assertSame('YES', $q?->getCell('N2')->getValue());
        self::assertSame('S-0001', $t?->getCell('A2')->getValue());
        self::assertSame('600', $t?->getCell('C2')->getValue());
    }

    // ── içe aktarım: güvenlik ───────────────────────────────────────────

    public function testMAKROLUDOSYAREDDEDILIR(): void
    {
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'));
        $yol = $this->tmp . '/makro.xlsx';
        file_put_contents($yol, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($yol));
        $zip->addFromString('xl/vbaProject.bin', 'MZ');
        $zip->close();

        $this->expectException(TurIslemiReddedildi::class);
        $this->expectExceptionMessageMatches('/Makro|makro/');
        $this->iceAktarici()->onizle((string) file_get_contents($yol), $this->tur(), $this->rfq(), []);
    }

    public function testSIFRELIVEYAESKIBICIMREDDEDILIR(): void
    {
        try {
            $this->iceAktarici()->onizle("\xD0\xCF\x11\xE0" . str_repeat("\0", 64), $this->tur(), $this->rfq(), []);
            self::fail('OLE dosyası reddedilmeli.');
        } catch (TurIslemiReddedildi $e) {
            self::assertSame('DOSYA_GUVENLIK', $e->kod);
        }
        try {
            $this->iceAktarici()->onizle('bu bir excel değil', $this->tur(), $this->rfq(), []);
            self::fail('Zip olmayan dosya reddedilmeli.');
        } catch (TurIslemiReddedildi $e) {
            self::assertSame('DOSYA_BICIM', $e->kod);
        }
    }

    // ── içe aktarım: tur/manifest eşleşmesi ────────────────────────────

    public function testBASKATURUNDOSYASITUMUYLEREDDEDILIR(): void
    {
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'));
        $baskaTur = ['id' => 8] + $this->tur();

        try {
            $this->iceAktarici()->onizle($bytes, $baskaTur, $this->rfq(), []);
            self::fail('Başka turun dosyası sessizce yazılmamalı (#28 numune 12).');
        } catch (TurIslemiReddedildi $e) {
            self::assertSame('DOSYA_YABANCI', $e->kod);
            self::assertStringContainsString('tur 7', $e->getMessage());
        }
    }

    public function testMANIFESTKIMLIGIDEGISTIRILIRSEIMZATUTMAZ(): void
    {
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'));
        $bytes = $this->duzenle($bytes, static function (Spreadsheet $k): void {
            $k->getSheetByName(ExcelSema::SAYFA_MANIFEST)?->setCellValueExplicit('B5', '99', DataType::TYPE_STRING); // row_count
        });

        $this->expectException(TurIslemiReddedildi::class);
        $this->expectExceptionMessageMatches('/imza/i');
        $this->iceAktarici()->onizle($bytes, $this->tur(), $this->rfq(), []);
    }

    // ── içe aktarım: satır davranışı (§8) ──────────────────────────────

    public function testDOLUSATIRUYGULANABILIRBOSSATIRDEGISIKLIKYOK(): void
    {
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'));
        $bytes = $this->duzenle($bytes, function (Spreadsheet $k): void {
            $this->satirDoldur($k, 2, ['K' => 'found', 'L' => '186.50', 'M' => 'TRY', 'N' => 'YES', 'O' => '600', 'Q' => 'order_confirmation', 'S' => '35', 'T' => 'calendar_day']);
            $t = $k->getSheetByName(ExcelSema::SAYFA_TIERS);
            $t?->setCellValueExplicit('A2', 'S-0001', DataType::TYPE_STRING);
            $t?->setCellValue('C2', 600);
            $t?->setCellValue('E2', 186.5);
            $t?->setCellValueExplicit('A3', 'S-0001', DataType::TYPE_STRING);
            $t?->setCellValue('C3', 1000);
            $t?->setCellValue('E3', 179);
        });

        $sonuc = $this->iceAktarici()->onizle($bytes, $this->tur(), $this->rfq(), []);

        self::assertSame(['uygulanabilir' => 1, 'uyarili' => 0, 'hatali' => 0, 'belirsiz' => 0, 'degisiklik_yok' => 1], $sonuc['ozet']);
        $satir = $sonuc['satirlar'][0];
        self::assertSame('S-0001', $satir['rfq_satir_id']);
        self::assertTrue($satir['varsayilan_secili']);
        self::assertSame(0, bccomp('186.5', (string) $satir['yeni']['ddp_birim_fiyat'], 2));
        self::assertSame('TRY', $satir['yeni']['para_birimi']);
        self::assertTrue($satir['yeni']['ddp_kdv_dahil_onayi']);
        self::assertCount(2, $satir['yeni']['kademeler']);
        self::assertNull($satir['yeni']['kademeler'][0]['max_adet'], 'Excel\'de boş bırakılan üst sınır boş kalır; içe aktarıcı sınır uydurmaz (§5).');
        self::assertSame('esik', $satir['yeni']['kademeler'][0]['kademe_tipi']);
        self::assertSame('degisiklik_yok', $sonuc['satirlar'][1]['grup']);
        self::assertSame(64, strlen($sonuc['parmak_izi']));
    }

    public function testBOSHUCREMEVCUTDEGERITEMIZLEMEZ(): void
    {
        $mevcut = ['S-0001' => YanitDonusturucu::bos('S-0001')];
        $mevcut['S-0001']['yanit_durumu'] = 'found';
        $mevcut['S-0001']['ddp_birim_fiyat'] = '186.50';
        $mevcut['S-0001']['para_birimi'] = 'TRY';
        $mevcut['S-0001']['ddp_kdv_dahil_onayi'] = true;
        $mevcut['S-0001']['moq_deger'] = '600';

        // Şablonu BOŞ üretip (mevcut yazılmadan) yalnız MOQ'yu değiştiriyoruz: fiyat hücresi boş kalır.
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'));
        $bytes = $this->duzenle($bytes, function (Spreadsheet $k): void {
            $this->satirDoldur($k, 2, ['O' => '500']);
        });

        $sonuc = $this->iceAktarici()->onizle($bytes, $this->tur(), $this->rfq(), $mevcut);
        $satir = $sonuc['satirlar'][0];

        self::assertSame('186.50', $satir['yeni']['ddp_birim_fiyat'], 'Boş fiyat hücresi mevcut fiyatı SİLMEZ (§8).');
        self::assertSame('500', $satir['yeni']['moq_deger']);
        self::assertSame(['moq_deger'], $satir['degisen']);
    }

    public function testPARABIRIMSIZFIYATBELIRSIZBRUTNETHATA(): void
    {
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'));
        $bytes = $this->duzenle($bytes, function (Spreadsheet $k): void {
            $this->satirDoldur($k, 2, ['K' => 'found', 'L' => '186.50', 'N' => 'YES', 'O' => '600', 'Q' => 'order_confirmation', 'S' => '35', 'T' => 'calendar_day']);
            $this->satirDoldur($k, 3, ['K' => 'found', 'L' => '5.10', 'M' => 'USD', 'N' => 'YES', 'O' => '500', 'Q' => 'deposit_received', 'S' => '30', 'T' => 'calendar_day', 'Z' => '9', 'AA' => '10.2']);
        });

        $sonuc = $this->iceAktarici()->onizle($bytes, $this->tur(), $this->rfq(), []);

        self::assertSame('belirsiz', $sonuc['satirlar'][0]['grup']);
        self::assertFalse($sonuc['satirlar'][0]['secilebilir'], 'Belirsiz satır seçilemez; kullanıcı kararı olmadan uygulanmaz.');
        self::assertSame('hatali', $sonuc['satirlar'][1]['grup']);
        self::assertStringContainsString('Brüt', implode(' ', $sonuc['satirlar'][1]['hatalar']));
    }

    public function testFORMULCALISTIRILMAZYABANCIVEMUKERRERBLOKLANIR(): void
    {
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'));
        $bytes = $this->duzenle($bytes, function (Spreadsheet $k): void {
            $q = $k->getSheetByName(ExcelSema::SAYFA_QUOTATION);
            $this->satirDoldur($k, 2, ['K' => 'found', 'L' => '5.10', 'M' => 'USD', 'N' => 'YES', 'O' => '500', 'Q' => 'deposit_received', 'S' => '30', 'T' => 'calendar_day']);
            $q?->setCellValue('AC2', '=1+1'); // firma notu formül
            $q?->setCellValueExplicit('A4', 'S-0002', DataType::TYPE_STRING); // 3. satırla mükerrer kimlik
            $q?->setCellValueExplicit('A5', 'S-9999', DataType::TYPE_STRING); // yabancı kimlik
            $this->satirDoldur($k, 5, ['K' => 'found', 'L' => '1.00', 'M' => 'USD']);
        });

        $sonuc = $this->iceAktarici()->onizle($bytes, $this->tur(), $this->rfq(), []);
        $gruplar = array_column($sonuc['satirlar'], 'grup', 'hucre');

        self::assertSame('hatali', $gruplar['QUOTATION!A2']);
        self::assertStringContainsString('formül', implode(' ', $sonuc['satirlar'][0]['hatalar']));
        self::assertSame('hatali', $gruplar['QUOTATION!A3']);
        self::assertSame('hatali', $gruplar['QUOTATION!A4']);
        self::assertStringContainsString('MÜKERRER', implode(' ', $sonuc['satirlar'][1]['hatalar']));
        self::assertStringContainsString('MÜKERRER', implode(' ', $sonuc['satirlar'][2]['hatalar']));
        self::assertSame('hatali', $gruplar['QUOTATION!A5']);
        self::assertStringContainsString('YABANCI', implode(' ', $sonuc['satirlar'][3]['hatalar']));
    }

    public function testKAYNAKALANDEGISTIRILIRSEIMZABOZUKUYGULANAMAZ(): void
    {
        $bytes = $this->sablon()->uret($this->tur(), $this->rfq(), [], new DateTimeImmutable('2026-09-04 10:00:00'));
        $bytes = $this->duzenle($bytes, function (Spreadsheet $k): void {
            $k->getSheetByName(ExcelSema::SAYFA_QUOTATION)?->setCellValueExplicit('C2', 'deadbeefdeadbeef', DataType::TYPE_STRING);
            $this->satirDoldur($k, 2, ['K' => 'found', 'L' => '5.10', 'M' => 'USD', 'N' => 'YES', 'O' => '500', 'Q' => 'deposit_received', 'S' => '30', 'T' => 'calendar_day']);
        });

        $sonuc = $this->iceAktarici()->onizle($bytes, $this->tur(), $this->rfq(), []);

        self::assertTrue($sonuc['satirlar'][0]['imza_bozuk']);
        self::assertSame('hatali', $sonuc['satirlar'][0]['grup']);
        self::assertNotNull($sonuc['satirlar'][0]['yeni'], 'Önizlenir ama uygulanamaz.');
    }

    // ── yardımcılar ────────────────────────────────────────────────────

    private function sablon(): ExcelSablonu
    {
        return new ExcelSablonu(new SatirImzasi(self::APP_KEY));
    }

    private function iceAktarici(): ExcelIceAktarici
    {
        return new ExcelIceAktarici(new SatirImzasi(self::APP_KEY), $this->tmp);
    }

    /** @return array<string, mixed> */
    private function tur(): array
    {
        return [
            'id' => 7, 'list_id' => 42, 'tur_no' => 1, 'rfq_snapshot_id' => 3, 'state' => 'SENT',
            'liste_adi' => 'Eylül', 'firma_adi' => 'Yiwu Test', 'gecerlilik_gun' => 15, 'valid_until' => '2026-09-19', 'portal_dili' => 'en',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function rfq(): array
    {
        return [
            [
                'id' => 1, 'rfq_satir_id' => 'S-0001', 'product_id' => 1, 'sira' => 1, 'urun_kodu' => 'P00001',
                'urun_adi_json' => json_encode(['tr' => 'Cam yağdanlık', 'en' => 'Glass oil dispenser', 'zh' => '高硼硅玻璃油壶 550ml'], JSON_UNESCAPED_UNICODE),
                'kaynak_urun_json' => json_encode(['platform' => '1688', 'url' => 'https://detail.1688.com/offer/1.html']),
                'talep_varyant_json' => 'clear', 'talep_miktar' => '600.000', 'talep_birim' => 'adet', 'alici_notu_json' => null, 'gorsel_url' => null,
            ],
            [
                'id' => 2, 'rfq_satir_id' => 'S-0002', 'product_id' => 2, 'sira' => 2, 'urun_kodu' => 'P00002',
                'urun_adi_json' => json_encode(['tr' => '=HYPERLINK("x")', 'en' => '=HYPERLINK("x")', 'zh' => '不锈钢保温杯'], JSON_UNESCAPED_UNICODE),
                'kaynak_urun_json' => null, 'talep_varyant_json' => null, 'talep_miktar' => '500.000', 'talep_birim' => 'adet', 'alici_notu_json' => null, 'gorsel_url' => null,
            ],
        ];
    }

    private function yukle(string $bytes): Spreadsheet
    {
        $yol = $this->tmp . '/y-' . bin2hex(random_bytes(3)) . '.xlsx';
        file_put_contents($yol, $bytes);

        return IOFactory::load($yol);
    }

    /** @param callable(Spreadsheet): void $degisiklik */
    private function duzenle(string $bytes, callable $degisiklik): string
    {
        $kitap = $this->yukle($bytes);
        $degisiklik($kitap);
        $yol = $this->tmp . '/d-' . bin2hex(random_bytes(3)) . '.xlsx';
        (new Xlsx($kitap))->save($yol);
        $kitap->disconnectWorksheets();

        return (string) file_get_contents($yol);
    }

    /** @param array<string, string> $hucreler harf → değer */
    private function satirDoldur(Spreadsheet $k, int $satir, array $hucreler): void
    {
        $q = $k->getSheetByName(ExcelSema::SAYFA_QUOTATION);
        self::assertNotNull($q);
        foreach ($hucreler as $harf => $deger) {
            $q->setCellValueExplicit($harf . $satir, $deger, DataType::TYPE_STRING);
        }
    }
}
