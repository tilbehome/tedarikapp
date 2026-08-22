<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Export\PdfRenderer;
use PHPUnit\Framework\TestCase;

/**
 * İE#15 B3 — TAŞMA KANITI: 22 ürünlü, uzun Çince başlıklı liste.
 *
 * Canlı kusur, sağdaki DDP sütunlarının kâğıt dışında kalmasıydı. Bu test belgenin
 * gerçekten çok sayfa olarak üretildiğini, yatay A4 kaldığını ve sütun setinin
 * eksiksiz basıldığını sabitler. (Görsel doğrulama iş emri raporundadır; buradaki
 * amaç, ileride bir düzen değişikliğinin bu üretimi sessizce bozmamasıdır.)
 */
final class PdfTasmaTest extends TestCase
{
    private const CINCE_BASLIK = '双层不锈钢真空保温饭盒学生上班族便携大容量分格餐盒带餐具套装';

    /** @return array<string, mixed> */
    private function snapshot(int $adet = 22): array
    {
        $urunler = [];
        for ($i = 1; $i <= $adet; $i++) {
            $urunler[] = [
                'no' => $i,
                'sort_no' => $i,
                'category' => $i % 3 === 0 ? null : '厨房用品',
                'name' => 'Çift Katmanlı Paslanmaz Çelik Vakumlu Termos Yemek Kabı — Bölmeli Set ' . $i,
                'name_original' => self::CINCE_BASLIK . ' ' . $i,
                'detail' => 'Malzeme: Paslanmaz çelik 304 · Renk: Gri · Kapasite: 1500 ml · Menşe: Çin',
                'url' => 'https://detail.1688.com/offer/8334389621' . $i . '.html',
                'main_image' => null,
                'qty' => 120 + $i,
                'price_yuan' => '12.50',
                'price_yuan_tl' => '58.75',
                'price_ddp_usd' => '2.35',
                'price_ddp_tl' => '82.25',
                'line_total_yuan' => '1500.00',
                'line_total_yuan_tl' => '7050.00',
                'status' => ['to_order', 'ordered', 'in_transit', 'received'][$i % 4],
                'platform' => '1688',
                'external_id' => '8334389621' . $i,
                'variant' => 'Gri · Mavi · Siyah … (12 seçenek)',
                'moq' => '2',
                'units_per_carton' => 20,
                'note' => 'Kutu logolu olacak, koli etiketi Türkçe',
                'video_url' => null,
                'price_target_try' => null,
                'unit_profit_try' => null,
                'line_profit_try' => null,
            ];
        }

        return [
            'snapshot_version' => 2,
            'generated_at' => '2026-08-21T15:00:00+03:00',
            'options' => [
                'copy' => 'firma',
                'statuses' => [],
                'lang' => 'tr',
                'document_code' => 'TDK-2026-0042',
                'revision_label' => 'A',
                'share_url' => null,
            ],
            'document_header' => [
                'company' => 'Tilbe Home',
                'web' => 'tilbehome.com',
                'email' => null,
                'prepared_by' => 'B. TİLBE',
            ],
            'list' => [
                'id' => 42,
                'name' => 'Taşma kanıtı listesi',
                'share_token_prefix' => null,
                'period' => 'Eylül 2026',
                'supplier_name' => null,
                'status' => 'sent',
                'revision' => 3,
                'yuan_rate' => '4.7000',
                'usd_rate' => '35.0000',
                'rate_locked_at' => '2026-08-20 09:00:00',
            ],
            'totals' => [
                'qty' => '2882',
                'yuan' => '33000.00',
                'yuan_tl' => '155100.00',
                'ddp_usd' => '6600.00',
                'ddp_tl' => '231000.00',
            ],
            'products' => $urunler,
        ];
    }

    public function testYirmiIkiUrunlukListeCokSayfaliVeYatayUretilir(): void
    {
        $bytes = (new PdfRenderer(dirname(__DIR__, 2)))->render($this->snapshot());

        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertGreaterThan(20_000, strlen($bytes), 'Belge gerçekten üretilmiş olmalı.');

        $sayfa = preg_match_all('/\/Type\s*\/Page[^s]/', $bytes);
        self::assertGreaterThanOrEqual(2, $sayfa, '22 ürün tek sayfaya sığmaz; başlık tekrarı bu yüzden gerekir.');

        // Yatay A4: MediaBox genişliği yükseklikten büyük olmalı (842 x 595 pt).
        self::assertMatchesRegularExpression('/MediaBox\s*\[\s*0\s+0\s+8\d\d(\.\d+)?\s+5\d\d/', $bytes);
    }

    /** Sütun seti ve başlık tekrarı şablonda sabittir (rev7 şartnamesi). */
    public function testSablonSutunSetiVeBaslikTekrariKORUNUR(): void
    {
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Export/PdfRenderer.php');

        self::assertStringContainsString("'format' => 'A4-L'", $kaynak, 'Yatay A4 korunmalı.');
        self::assertStringContainsString('<thead>', $kaynak, 'Başlık her sayfada tekrar etsin diye thead kullanılır.');

        // Sütun başlıkları TemplateV2'den gelir — firma kopyasında 13 sütun basılır.
        $sutunlar = \App\Services\Export\TemplateV2::COLUMNS;
        unset($sutunlar['A']); // görsel sütunu PDF'te basılmaz
        $etiketler = array_map(static fn (array $sutun): string => (string) $sutun[1], array_values($sutunlar));

        // Görsel dahil 14 sütun; yazdırma yüzdeleri (İE#14 B2) GÖRSELSİZ 13 veri
        // sütunu içindir — paylaşım sayfası tablosu ile belge şablonu ayrı setlerdir.
        self::assertCount(14, $etiketler, 'Firma kopyası: görsel + 13 veri sütunu.');
        foreach (['DDP $', 'DDP ₺', 'Varyasyon', 'Kategori', 'Miktar', 'Görsel'] as $sutun) {
            self::assertContains($sutun, $etiketler, $sutun . ' sütunu şablonda olmalı.');
        }
        // İç kopya sütunları firma kopyasında ASLA yer almaz (A4).
        foreach (['Hedef Satış (₺)', 'Birim Kâr (₺)', 'Toplam Kâr (₺)'] as $ic) {
            self::assertNotContains($ic, $etiketler);
        }
    }

    /**
     * REV4 GÖRSEL KATMANI (İE#18 G3 Aşama B).
     *
     * Ürün Sahibi bulgusu "renkler çarpık"tı: lacivert yalnız antette değil
     * tablo başlığında ve toplam bandında da vardı. Bu test o üç kuralı
     * sabitler — biri geri gelirse belge yine kirlenir.
     */
    public function testREV4_GORSEL_KATMANI_KORUNUR(): void
    {
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Export/PdfRenderer.php');

        // 1) Gövde beyaz; tablo başlığı AÇIK GRİ zemin + koyu metin.
        self::assertStringContainsString('background: #fff;', $kaynak);
        self::assertStringContainsString('table.veri th { background: #F1F4F9; color: #334155;', $kaynak);
        self::assertStringNotContainsString(
            "table.veri th { background: #' . TemplateV2::LACIVERT_ACIK",
            $kaynak,
            'Tablo başlığı LACİVERT olmamalı (rev4).',
        );

        // 2) Toplam bandı koyu DEĞİL: açık zemin + lacivert üst çizgi.
        self::assertStringContainsString('.toplam td { background: #F1F4F9;', $kaynak);

        // 3) Ürün adı düz koyu metin — köprü mavi/altı çizili DEĞİL.
        self::assertStringContainsString('.ad .adlink { color: #101828; text-decoration: none; }', $kaynak);

        // 4) PDF başlıkları TEK SATIR TÜRKÇE (üç dilli kademe kalktı).
        self::assertStringNotContainsString('<span class="cn">', $kaynak);
        self::assertStringNotContainsString('<span class="en">', $kaynak);
        self::assertStringContainsString('mb_strtoupper($tr', $kaynak);
    }

    /** Şartname örneği ÜRETİM SINIFIYLA üretilir — belge ile kod ayrışamaz. */
    public function testSARTNAME_ORNEGI_URETIM_CIKTISIYLA_AYNI_KURALLARI_TASIR(): void
    {
        $ornek = dirname(__DIR__, 2) . '/docs/sablon/sablon-v2-pdf-ornek-rev4.pdf';
        self::assertFileExists($ornek, 'rev4 örneği repoda olmalı.');

        $bytes = (new PdfRenderer(dirname(__DIR__, 2)))->render($this->snapshot(18));

        self::assertStringStartsWith('%PDF-', $bytes);
        // Örnek de üretim de YATAY A4 ve tek/çok sayfa kuralında aynı davranır.
        self::assertMatchesRegularExpression('/MediaBox\s*\[\s*0\s+0\s+8\d\d(\.\d+)?\s+5\d\d/', $bytes);
        self::assertMatchesRegularExpression(
            '/MediaBox\s*\[\s*0\s+0\s+8\d\d(\.\d+)?\s+5\d\d/',
            (string) file_get_contents($ornek),
        );
    }

}
