<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\MoneyService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * K14/K24/K29 — para TEST-FIRST yazılır.
 *
 * Altın testler gerçek tedarik listesinden gelir: ¥9,00 × 7,04 = ₺63,36 ·
 * ¥12,00 × 7,04 = ₺84,48. Bu sayılar kuruşu kuruşuna tutmazsa panel, Excel ve
 * paylaşım sayfası birbirini tutmayan toplamlar üretir.
 */
final class MoneyServiceTest extends TestCase
{
    private MoneyService $money;

    protected function setUp(): void
    {
        parent::setUp();
        $this->money = new MoneyService();
    }

    // ─────────────── Altın testler ───────────────

    public function testAltinTestBirimFiyatTlCevirimi(): void
    {
        self::assertSame('63.36', $this->money->convert('9.0000', '7.0400'));
        self::assertSame('84.48', $this->money->convert('12.0000', '7.0400'));
    }

    public function testAltinTestUcKalemliListeToplami(): void
    {
        // 24 × ¥9,00 · 10 × ¥12,50 · 3 × ¥7,25  →  kur 7,0400
        $lines = [
            $this->money->lineTotal('9.0000', 24),
            $this->money->lineTotal('12.5000', 10),
            $this->money->lineTotal('7.2500', 3),
        ];

        self::assertSame(['216.00', '125.00', '21.75'], $lines);
        self::assertSame('362.75', $this->money->sum($lines));

        $tlLines = [
            $this->money->lineTotalInTl('9.0000', 24, '7.0400'),
            $this->money->lineTotalInTl('12.5000', 10, '7.0400'),
            $this->money->lineTotalInTl('7.2500', 3, '7.0400'),
        ];

        self::assertSame(['1520.64', '880.00', '153.12'], $tlLines);
        self::assertSame('2553.76', $this->money->sum($tlLines));
    }

    public function testGenelToplamYuvarlanmisSatirlarinToplamidir(): void
    {
        // K24: "önce yuvarla, sonra topla". Ham toplamı yuvarlamak farklı sonuç verebilir:
        // 3 × 0,005 ham toplamı 0,015 → 0,02; satır bazında ise 0,01+0,01+0,01 = 0,03.
        $lines = array_fill(0, 3, $this->money->lineTotal('0.0050', 1));

        self::assertSame(['0.01', '0.01', '0.01'], $lines);
        self::assertSame('0.03', $this->money->sum($lines));
    }

    // ─────────────── Yuvarlama ───────────────

    /** @return list<array{string, string}> */
    public static function halfUpOrnekleri(): array
    {
        return [
            ['0.005', '0.01'],   // tam yarım YUKARI
            ['0.015', '0.02'],
            ['0.025', '0.03'],   // bankacı yuvarlaması olsaydı 0.02 olurdu
            ['0.004', '0.00'],
            ['0.006', '0.01'],
            ['1.005', '1.01'],
            ['2.675', '2.68'],   // float ile 2.67 çıkar — bcmath ile doğru
        ];
    }

    #[DataProvider('halfUpOrnekleri')]
    public function testHalfUpYuvarlama(string $girdi, string $beklenen): void
    {
        self::assertSame($beklenen, $this->money->round($girdi));
    }

    public function testNegatifDegerlerHalfUpYuvarlanir(): void
    {
        self::assertSame('-0.01', $this->money->round('-0.005'));
        self::assertSame('-2.68', $this->money->round('-2.675'));
        self::assertSame('0.00', $this->money->round('-0.004'));
    }

    public function testFloatHassasiyetiSorunuYok(): void
    {
        // 0,1 + 0,2 float'ta 0,30000000000000004'tür. bcmath'te değil.
        self::assertSame('0.30', $this->money->sum(['0.10', '0.20']));
        // 1,15 × 100 float'ta 114,99999... olur.
        self::assertSame('115.00', $this->money->lineTotal('1.1500', 100));
    }

    // ─────────────── Biçim ───────────────

    public function testFormatIkiHaneyeSabitler(): void
    {
        self::assertSame('9.00', $this->money->format('9'));
        self::assertSame('63.36', $this->money->format('63.3600'));
        self::assertSame('0.00', $this->money->format('0'));
    }

    public function testKurDortHaneyeSabitlenir(): void
    {
        self::assertSame('7.0400', $this->money->formatRate('7.04'));
        self::assertSame('41.5000', $this->money->formatRate('41.5'));
    }

    public function testSifirMiktarSifirToplamVerir(): void
    {
        self::assertSame('0.00', $this->money->lineTotal('9.0000', 0));
        self::assertSame('0.00', $this->money->sum([]));
    }

    // ─────────────── Doğrulama (docs/04 §2d) ───────────────

    /** @return list<array{string, bool}> */
    public static function tutarOrnekleri(): array
    {
        return [
            ['0', true],
            ['0.00', true],
            ['9999999.99', true],
            ['9.5', true],
            ['9.50', true],
            ['10000000.00', false], // üst sınır aşıldı
            ['-1', false],          // negatif ASLA
            ['9.999', false],       // en çok 2 ondalık
            ['abc', false],
            ['', false],
            ['1e3', false],
            ['1,5', false],
        ];
    }

    #[DataProvider('tutarOrnekleri')]
    public function testTutarDogrulamasi(string $deger, bool $gecerli): void
    {
        self::assertSame($gecerli, $this->money->isValidAmount($deger));
    }

    /** @return list<array{string, bool}> */
    public static function kurOrnekleri(): array
    {
        return [
            ['0.0001', true],
            ['7.04', true],
            ['7.0400', true],
            ['1000', true],
            ['1000.0001', false], // üst sınır
            ['0', false],         // alt sınır: 0.0001
            ['0.00001', false],   // en çok 4 ondalık
            ['-7', false],
            ['abc', false],
        ];
    }

    #[DataProvider('kurOrnekleri')]
    public function testKurDogrulamasi(string $deger, bool $gecerli): void
    {
        self::assertSame($gecerli, $this->money->isValidRate($deger));
    }

    public function testGecersizTutarlaHesapReddedilir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->money->lineTotal('-5.00', 3);
    }

    public function testGecersizKurlaCevirimReddedilir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->money->convert('9.00', '0');
    }

    public function testNegatifAdetReddedilir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->money->lineTotal('9.00', -1);
    }
}
