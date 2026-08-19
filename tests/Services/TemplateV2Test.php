<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Export\TemplateV2;
use App\Services\Export\XlsxRenderer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

/**
 * Şablon v2 çıktısı (İE#13 F1/F5/F7) — üretilen dosya AÇILIP okunur.
 *
 * Referans: docs/sablon/sablon-v2-rev7.xlsx (NİHAİ). Burada "birebir" iddiası tek tek
 * kanıtlanır: sade sütun seti, üç SATIR başlık (TR/中文/EN), kimlik bandı + antet,
 * KPI formülleri, iki satırlı durum rozetleri, GENEL TOPLAM'da yalnız miktar,
 * dondurulmuş başlıklar, hair dikey ayraçlar.
 *
 * F5 kritik kuralı: firma kopyasında kâr sütunları DOSYADA HİÇ YOKTUR.
 */
final class TemplateV2Test extends TestCase
{
    /** @return array<string, mixed> */
    private function snapshot(string $copy = 'firma', string $revision = 'A'): array
    {
        return [
            'snapshot_version' => 2,
            'generated_at' => '2026-08-20T09:41:00+03:00',
            'options' => [
                'copy' => $copy,
                'statuses' => [],
                'document_code' => TemplateV2::documentCode(34, 2026, $revision),
                'revision_label' => $revision,
                'share_url' => null,
            ],
            'document_header' => [
                'company' => 'Tilbe Home',
                'web' => 'tilbehome.com',
                'email' => 'info@tilbehome.com',
                'prepared_by' => 'Bünyamin TİLBE',
            ],
            'list' => [
                'id' => 34,
                'name' => 'Eylül Siparişi',
                'period' => 'Eylül 2026',
                'supplier_name' => 'YOKYOKAVM',
                'status' => 'sent',
                'revision' => 3,
                'yuan_rate' => '7.0400',
                'usd_rate' => '41.5000',
                'rate_locked_at' => '2026-08-19T10:00:00+03:00',
                'share_token_prefix' => null,
            ],
            'totals' => ['qty' => 288, 'yuan' => '52800.00', 'yuan_tl' => '371712.00', 'ddp_usd' => '0.00', 'ddp_tl' => '0.00'],
            'products' => [
                [
                    'no' => 1,
                    'sort_no' => 1,
                    'category' => 'Mutfak',
                    'name' => 'Çift Cidarlı Termos Yemek Kabı 500 ml',
                    'name_original' => '双层不锈钢保温饭盒500ml',
                    'detail' => 'Paslanmaz çelik iç hazne · sızdırmaz kapak',
                    'url' => 'https://detail.1688.com/offer/833438962156.html',
                    'main_image' => null,
                    'qty' => 240,
                    'price_yuan' => '12.00',
                    'price_yuan_tl' => '84.48',
                    'price_ddp_usd' => '0.00',
                    'price_ddp_tl' => '0.00',
                    'line_total_yuan' => '2880.00',
                    'line_total_yuan_tl' => '20275.20',
                    'status' => 'ordered',
                    'platform' => '1688',
                    'external_id' => '833438962156',
                    'variant' => 'Gri · Mavi (2 seçenek)',
                    'moq' => '2 adet',
                    'units_per_carton' => 24,
                    'note' => 'Kutu logolu olacak',
                    'video_url' => null,
                    'price_target_try' => '150.00',
                    'unit_profit_try' => '65.52',
                    'line_profit_try' => '15724.80',
                ],
                [
                    'no' => 2,
                    'sort_no' => 2,
                    'category' => 'Hırdavat',
                    'name' => "Lityum Şarjlı El Aleti Seti 4'lü 21V",
                    'name_original' => '锂电四件套电动工具组合',
                    'detail' => 'Matkap + darbeli anahtar · 2 batarya',
                    'url' => 'https://detail.1688.com/offer/900000000000.html',
                    'main_image' => null,
                    'qty' => 48,
                    'price_yuan' => '1040.00',
                    'price_yuan_tl' => '7321.60',
                    'price_ddp_usd' => '0.00',
                    'price_ddp_tl' => '0.00',
                    'line_total_yuan' => '49920.00',
                    'line_total_yuan_tl' => '351436.80',
                    'status' => 'cancelled',
                    'platform' => 'taobao',
                    'external_id' => '900000000000',
                    'variant' => null,
                    'moq' => null,
                    'units_per_carton' => 4,
                    'note' => null,
                    'video_url' => null,
                    'price_target_try' => null,
                    'unit_profit_try' => null,
                    'line_profit_try' => null,
                ],
            ],
        ];
    }

    /** @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet */
    private function uret(array $snapshot): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $bytes = (new XlsxRenderer(dirname(__DIR__, 2)))->render($snapshot);
        $tmp = tempnam(sys_get_temp_dir(), 'tdk') . '.xlsx';
        file_put_contents($tmp, $bytes);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        return $sheet;
    }

    public function testKimlikBandiVeBelgeKodu(): void
    {
        $sheet = $this->uret($this->snapshot());

        self::assertSame('TEDARİK SİPARİŞ LİSTESİ', $sheet->getCell('D2')->getValue());
        self::assertStringContainsString('Eylül 2026 Dönemi', (string) $sheet->getCell('D3')->getValue());
        self::assertSame('BELGE KODU', $sheet->getCell('I2')->getValue());
        self::assertSame('TDK-2026-0034 · Rev A', $sheet->getCell('I3')->getValue());
        self::assertSame('20.08.2026 09:41', $sheet->getCell('K3')->getValue());
        self::assertSame('¥ 7,0400 · $ 41,5000', $sheet->getCell('M3')->getValue());
        // Antet: firma adı · web · e-posta · dönem · DDP Tedarik · Firma (alıcı).
        $antet = (string) $sheet->getCell('D3')->getValue();
        self::assertStringContainsString('Tilbe Home · tilbehome.com · info@tilbehome.com', $antet);
        self::assertStringContainsString('Firma: YOKYOKAVM', $antet);
        // Kurumsal renkler: lacivert bant + altın çizgi.
        self::assertSame('FF0F2557', $sheet->getStyle('D2')->getFill()->getStartColor()->getARGB());
        self::assertSame('FFD4A017', $sheet->getStyle('D4')->getFill()->getStartColor()->getARGB());
    }

    public function testUcSatirBaslik_TR_CINCE_INGILIZCE(): void
    {
        $sheet = $this->uret($this->snapshot());

        self::assertSame('Ürün Adı', $sheet->getCell('D8')->getValue());
        self::assertSame('产品名称', $sheet->getCell('D9')->getValue());
        self::assertSame('Product name', $sheet->getCell('D10')->getValue());

        self::assertTrue($sheet->getStyle('D8')->getFont()->getBold(), 'TR satırı kalın beyaz.');
        self::assertSame('FF93C5FD', $sheet->getStyle('D9')->getFont()->getColor()->getARGB(), '中文 açık mavi.');
        self::assertTrue($sheet->getStyle('D10')->getFont()->getItalic(), 'EN satırı italik soluk.');
        self::assertSame('FF6B8DC9', $sheet->getStyle('D10')->getFont()->getColor()->getARGB());
    }

    /** Sütun seti SADE: koli/ilan no/MOQ ve satır toplamları BASILMAZ (şartname). */
    public function testSadeSutunSeti(): void
    {
        $sheet = $this->uret($this->snapshot());

        self::assertSame(
            ['No', 'Görsel', 'Ürün Adı', 'Ürün Detayları', 'Varyasyon', 'Kategori', 'Kaynak', 'Durum', 'Not',
                'Miktar', 'Vitrin Fiyatı', '₺ Karşılığı', 'DDP $', 'DDP ₺'],
            array_map(
                static fn (string $harf): string => (string) $sheet->getCell($harf . '8')->getValue(),
                range('B', 'O'),
            ),
        );
        self::assertSame('O', $sheet->getHighestColumn());
    }

    public function testUrunSatiri_platformBagimsiz_ve_orijinalBaslikIkinciSatirda(): void
    {
        $sheet = $this->uret($this->snapshot());

        $ad = (string) $sheet->getCell('D11')->getValue();
        self::assertStringContainsString('Çift Cidarlı Termos', $ad);
        self::assertStringContainsString('双层不锈钢保温饭盒500ml', $ad, 'Orijinal Çince başlık ikinci satırda olmalı.');
        self::assertSame('https://detail.1688.com/offer/833438962156.html', $sheet->getCell('D11')->getHyperlink()->getUrl());

        self::assertSame('1688.com', $sheet->getCell('H11')->getValue(), 'Kaynak rozeti platformu yazar.');
        self::assertSame('Taobao', $sheet->getCell('H12')->getValue(), 'Şablon PLATFORM BAĞIMSIZ.');
        // İki satırlı durum rozetleri (dar sütun).
        self::assertSame("● Sipariş\nVerildi", $sheet->getCell('I11')->getValue());
        self::assertSame("● İptal\nEdildi", $sheet->getCell('I12')->getValue());
        self::assertSame('FFFEE2E2', $sheet->getStyle('I12')->getFill()->getStartColor()->getARGB());
        self::assertTrue($sheet->getStyle('I11')->getAlignment()->getWrapText());
    }

    /** Ürün SATICISI basılmaz; antetteki "Firma" ise ALICI firmadır ve basılır. */
    public function testUrunSaticisiBasilmaz(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['products'][0]['vendor_name'] = 'Yiwu Ticaret Ltd';
        $sheet = $this->uret($snapshot);

        $tumMetin = '';
        foreach ($sheet->toArray(null, true, false, false) as $satir) {
            $tumMetin .= implode('|', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $satir));
        }

        self::assertStringNotContainsString('Yiwu Ticaret', $tumMetin, 'Şartname: ürün satıcısı BASILMAZ.');
        self::assertStringContainsString('YOKYOKAVM', $tumMetin, 'Antetteki alıcı firma basılır.');
    }

    /** GENEL TOPLAM bandında yalnız MİKTAR + karta işaret notu bulunur (şartname). */
    public function testGenelToplamYalnizMiktarVeKartNotu(): void
    {
        $sheet = $this->uret($this->snapshot());

        self::assertSame('GENEL TOPLAM', $sheet->getCell('B13')->getValue());
        self::assertSame('=SUM(K11:K12)', $sheet->getCell('K13')->getValue());
        self::assertSame('Parasal toplamlar üstteki özet kartlarındadır', $sheet->getCell('L13')->getValue());
    }

    public function testKpiKartlariParasalToplamlarinTEK_yeri(): void
    {
        $sheet = $this->uret($this->snapshot());

        self::assertSame('TOPLAM ÜRÜN', $sheet->getCell('B6')->getValue());
        self::assertSame('=COUNTA(D11:D12)', $sheet->getCell('B7')->getValue());
        self::assertSame('TOPLAM MİKTAR', $sheet->getCell('E6')->getValue());
        self::assertSame('=SUM(K11:K12)', $sheet->getCell('E7')->getValue());
        self::assertSame('MAL BEDELİ (¥)', $sheet->getCell('H6')->getValue());
        self::assertSame('=SUMPRODUCT(K11:K12,L11:L12)', $sheet->getCell('H7')->getValue());
        self::assertSame('=SUMPRODUCT(K11:K12,M11:M12)', $sheet->getCell('K7')->getValue());
        self::assertSame('DDP TOPLAM (₺ · KDV dahil)', $sheet->getCell('M6')->getValue());
        self::assertSame('=SUMPRODUCT(K11:K12,O11:O12)', $sheet->getCell('M7')->getValue());
    }

    public function testDondurulmusBasliklarVeSayfaTekrari(): void
    {
        $sheet = $this->uret($this->snapshot());

        self::assertSame('B11', $sheet->getFreezePane());
        self::assertSame(['8', '10'], array_map(strval(...), $sheet->getPageSetup()->getRowsToRepeatAtTop()));
    }

    /** Zarif ayraç: sütunlar arası HAIR (0,25pt) dikey çizgi + zebra satır. */
    public function testHairAyracVeZebra(): void
    {
        $sheet = $this->uret($this->snapshot());

        self::assertSame('hair', $sheet->getStyle('D11')->getBorders()->getLeft()->getBorderStyle());
        self::assertSame('FFD9E2EC', $sheet->getStyle('D11')->getBorders()->getLeft()->getColor()->getARGB());
        self::assertSame('FFF1F5F9', $sheet->getStyle('D12')->getFill()->getStartColor()->getARGB(), 'İkinci satır zebra.');
    }

    public function testFirmaKopyasindaKAR_SUTUNLARI_YOK(): void
    {
        $sheet = $this->uret($this->snapshot('firma'));

        self::assertSame('O', $sheet->getHighestColumn(), 'Firma kopyası O sütununda biter.');
        $tumMetin = '';
        foreach ($sheet->toArray(null, true, false, false) as $satir) {
            $tumMetin .= implode('|', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $satir));
        }
        self::assertStringNotContainsString('Kâr', $tumMetin);
        self::assertStringNotContainsString('Hedef Satış', $tumMetin);
    }

    public function testIcKopyaUcEkSutunVeKarToplamiIcerir(): void
    {
        $sheet = $this->uret($this->snapshot('ic'));

        self::assertSame('R', $sheet->getHighestColumn());
        self::assertSame('Hedef Satış (₺)', $sheet->getCell('P8')->getValue());
        self::assertSame('Birim Kâr (₺)', $sheet->getCell('Q8')->getValue());
        self::assertSame('Toplam Kâr (₺)', $sheet->getCell('R8')->getValue());
        self::assertSame(150.0, $sheet->getCell('P11')->getValue());
        self::assertSame(15724.8, $sheet->getCell('R11')->getValue());
        self::assertSame('—', $sheet->getCell('P12')->getValue(), 'Hedef girilmemişse tire basılır.');
        self::assertSame('TOPLAM KÂR (₺ · iç kopya)', $sheet->getCell('P6')->getValue());
        self::assertSame('=SUM(R11:R12)', $sheet->getCell('P7')->getValue());
    }

    public function testRevizyonBelgeKoduVeGecersizKilmaIbaresi(): void
    {
        $sheet = $this->uret($this->snapshot('firma', 'B'));

        self::assertSame('TDK-2026-0034 · Rev B', $sheet->getCell('I3')->getValue());
        self::assertStringContainsString('GEÇERSİZ KILAR', (string) $sheet->getCell('B16')->getValue());
        self::assertStringContainsString('Hazırlayan: Bünyamin TİLBE', (string) $sheet->getCell('J16')->getValue());
    }

    public function testRevizyonHarfiSayactanUretilir(): void
    {
        self::assertSame('A', TemplateV2::revisionLabel(1));
        self::assertSame('B', TemplateV2::revisionLabel(2));
        self::assertSame('Z', TemplateV2::revisionLabel(26));
        self::assertSame('AA', TemplateV2::revisionLabel(27));
    }
}
