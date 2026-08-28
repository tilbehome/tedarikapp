<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Translation\DilSaptayici;
use App\Services\Translation\KanonikDiller;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * D12 — KANONİK ÜÇ DİL VE KAYNAK DİLİ SAPTAMA.
 *
 * Kararın özü tek cümlede: kaynak dil ORİJİNALDİR, çevrilmez; eksik olanlar
 * üretilir. Bu testler kuralın platformdan bağımsız olduğunu sabitler — 1688
 * (zh), Amazon (en) ve Trendyol (tr) aynı kodla doğru davranmalıdır.
 */
final class KanonikDillerTest extends TestCase
{
    public function testUCLUSABITTIR(): void
    {
        self::assertSame(['tr', 'en', 'zh'], KanonikDiller::HEPSI);
        self::assertSame('tr', KanonikDiller::PANEL, 'V3-M: panel her yerde Türkçedir.');
    }

    public function testKAYNAKDILICEVRILMEZ(): void
    {
        // 1688 → Çince orijinal; TR ve EN üretilir.
        self::assertSame(['tr', 'en'], KanonikDiller::uretilecekler('zh'));
        // Amazon → İngilizce orijinal; TR ve ZH üretilir.
        self::assertSame(['tr', 'zh'], KanonikDiller::uretilecekler('en'));
        // Trendyol → TÜRKÇE orijinal; motor TR'ye DOKUNMAZ.
        self::assertSame(['en', 'zh'], KanonikDiller::uretilecekler('tr'));
        self::assertNotContains('tr', KanonikDiller::uretilecekler('tr'));
    }

    public function testUCLUDISIKAYNAKTAUCUDEURETILIR(): void
    {
        // Almanca bir site: ham orijinal ayrıca saklanır, üçü de üretilir.
        self::assertSame(['tr', 'en', 'zh'], KanonikDiller::uretilecekler('de'));
        self::assertTrue(KanonikDiller::ucluDisiMi('de'));
        self::assertFalse(KanonikDiller::ucluDisiMi('zh'));
    }

    public function testKAYNAKBILINMIYORSAUCUDEISTENIR(): void
    {
        // Eksik bilgi yüzünden bir dili atlamak, kullanıcının onu hiç görmemesidir.
        self::assertSame(['tr', 'en', 'zh'], KanonikDiller::uretilecekler(null));
        self::assertSame(['tr', 'en', 'zh'], KanonikDiller::uretilecekler(''));
    }

    /** @return list<array{0: string, 1: string}> */
    public static function metinler(): array
    {
        return [
            ['洞洞鞋男士2025夏季新款外穿包头拖鞋', 'zh'],
            ['EVA 防滑厚底凉拖鞋', 'zh'],
            ['Kalın Tabanlı Erkek Terlik', 'tr'],
            ['Kadin Yazlik Sandalet ve Terlik', 'tr'],
            ['Men Summer Beach Sandals with Thick Sole', 'en'],
            ['EVA Non-slip Slippers', 'en'],
            ['ABC-123', 'en'],
        ];
    }

    #[DataProvider('metinler')]
    public function testKAYNAKDILISAPTANIR(string $metin, string $beklenen): void
    {
        self::assertSame($beklenen, DilSaptayici::sapta($metin));
    }

    public function testBOSMETINPANELDILINEDUSER(): void
    {
        self::assertSame('tr', DilSaptayici::sapta('   '));
    }
}
