<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Export\CsvRenderer;
use App\Services\Export\SafeCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * İE#19 G5 — CSV/hesap tablosu formül enjeksiyonu.
 *
 * Bu testin sınadığı zarar BİZDE değil ALICIDA oluşur: dosyayı açan tedarikçinin
 * Excel'i formülü çalıştırır. Bu yüzden "bizde hata yok" savunması geçerli değildir
 * ve regresyon testi metnin ÇIKTIDAKİ hâline bakar.
 */
final class SafeCellTest extends TestCase
{
    /** @return list<array{string, bool}> */
    public static function ornekler(): array
    {
        return [
            ['=HYPERLINK("http://kotu.site","Tikla")', true],
            ['+1+1', true],
            ['-2+3', true],
            ['@SUM(A1:A9)', true],
            ["\t=cmd|'/c calc'!A1", true],
            ["\r\n=1+1", true],
            ['Çift Cidarlı Termos', false],
            ['20-25 iş günü', false],   // ortada tire riskli DEĞİL
            ['', false],
        ];
    }

    #[DataProvider('ornekler')]
    public function testRiskliOnekTespiti(string $girdi, bool $bekleniyor): void
    {
        self::assertSame($bekleniyor, SafeCell::riskli($girdi));
    }

    public function testRiskliDegerTekTirnaklaOneklenir(): void
    {
        self::assertSame("'=1+1", SafeCell::text('=1+1'));
        self::assertSame('Termos 500 ml', SafeCell::text('Termos 500 ml'), 'Zararsız metin DEĞİŞTİRİLMEMELİ.');
    }

    public function testVeriKaybiYok(): void
    {
        $ozgun = '=SUM(1;2)';
        self::assertSame($ozgun, substr(SafeCell::text($ozgun), 1), 'Önek dışında değer korunmalı.');
    }

    public function testCsvCiktisindaUrunAdiFormULOLARAKBASLAMAZ(): void
    {
        $csv = (new CsvRenderer())->render($this->snapshot('=HYPERLINK("http://kotu.site","Fatura")'));

        self::assertStringNotContainsString(';=HYPERLINK', $csv, 'Ürün adı hücresi formül olarak başlıyor.');
        self::assertStringContainsString("'=HYPERLINK", $csv, 'Değer korunmalı, yalnız nötrlenmeli.');
    }

    public function testSayiHucreleriONEKLENMEZ(): void
    {
        $csv = (new CsvRenderer())->render($this->snapshot('Normal ürün'));

        // Fiyat sütunları ham ondalık kalmalı: tırnaklanmış sayı Excel'de metin olur.
        self::assertStringContainsString(';12.00;', $csv);
        self::assertStringNotContainsString(";'12.00", $csv);
    }

    /** @return array<string, mixed> */
    private function snapshot(string $urunAdi): array
    {
        return [
            'snapshot_version' => 2,
            'generated_at' => '2026-08-22T12:00:00+03:00',
            'options' => ['copy' => 'firma', 'statuses' => [], 'lang' => 'tr'],
            'list' => [
                'id' => 1,
                'name' => 'Deneme listesi',
                'period' => 'EYLÜL 2026',
                'supplier_name' => 'Ornek Co.',
                'status' => 'sent',
                'revision' => 1,
                'yuan_rate' => '4.7000',
                'usd_rate' => '35.0000',
            ],
            'totals' => ['qty' => '10', 'yuan' => '120.00', 'yuan_tl' => '564.00', 'ddp_usd' => '0.00', 'ddp_tl' => '0.00'],
            'products' => [[
                'no' => 1,
                'sort_no' => 1,
                'category' => 'Mutfak',
                'name' => $urunAdi,
                'detail' => null,
                'url' => 'https://detail.1688.com/offer/1.html',
                'qty' => '10',
                'price_yuan' => '12.00',
                'price_yuan_tl' => '56.40',
                'price_ddp_usd' => null,
                'price_ddp_tl' => null,
            ]],
        ];
    }
}
