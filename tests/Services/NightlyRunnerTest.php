<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\NightlyRunner;
use PHPUnit\Framework\TestCase;

/**
 * Gecelik koşu düzeni (İE#13 EK-A — tek cron ilkesi).
 *
 * KRİTİK kural: iki iş birbirinin hatasını YUTMAZ. Yedek adımı patlasa bile bakım
 * adımları koşar (canlıda tek cron olduğu için bakım yedeğe bağımlı olamaz).
 */
final class NightlyRunnerTest extends TestCase
{
    public function testYedekPATLASA_BILE_bakimKosar(): void
    {
        $bakimKostu = false;

        $sonuc = (new NightlyRunner())->run(
            static fn (): string => throw new \RuntimeException('mysqldump düştü'),
            static function () use (&$bakimKostu): string {
                $bakimKostu = true;

                return 'çöp 0/0 · log 5';
            },
        );

        self::assertTrue($bakimKostu, 'Yedek hatası bakımı iptal ETMEMELİ.');
        self::assertFalse($sonuc['ok']);
        self::assertFalse($sonuc['backup']['ok']);
        self::assertTrue($sonuc['maintenance']['ok']);
        self::assertStringContainsString('mysqldump düştü', $sonuc['summary']);
        self::assertStringContainsString('çöp 0/0 · log 5', $sonuc['summary'], 'Özet İKİ adımı da bildirmeli.');
    }

    public function testBakimPATLASA_BILE_yedekSonucuRaporlanir(): void
    {
        $sonuc = (new NightlyRunner())->run(
            static fn (): string => 'yedek-20260819.sql.enc (42.0 KB)',
            static fn (): string => throw new \RuntimeException('app_logs kilitli'),
        );

        self::assertFalse($sonuc['ok']);
        self::assertTrue($sonuc['backup']['ok']);
        self::assertStringContainsString('yedek-20260819.sql.enc', $sonuc['summary']);
        self::assertStringContainsString('app_logs kilitli', $sonuc['summary']);
    }

    public function testIkisiDeBasariliysaTekOzetSatiri(): void
    {
        $sonuc = (new NightlyRunner())->run(
            static fn (): string => 'yedek tamam',
            static fn (): string => 'bakım tamam',
        );

        self::assertTrue($sonuc['ok']);
        self::assertSame('gecelik koşu TAMAM | yedek: yedek tamam | bakım: bakım tamam', $sonuc['summary']);
    }
}
