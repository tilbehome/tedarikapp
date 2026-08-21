<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\CronLog;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * İE#14 D1 — gecelik koşunun görünür izi.
 *
 * Sınanan asıl şey şu: "kayıt yok" ile "koşu yok" artık ayırt edilebiliyor mu?
 */
final class CronLogTest extends TestCase
{
    private string $dizin = '';

    protected function setUp(): void
    {
        $this->dizin = sys_get_temp_dir() . '/tdk-cron-' . bin2hex(random_bytes(6));
        mkdir($this->dizin . '/storage/logs', 0775, true);
    }

    protected function tearDown(): void
    {
        $path = $this->dizin . '/storage/logs/cron.log';
        if (is_file($path)) {
            unlink($path);
        }
        @rmdir($this->dizin . '/storage/logs');
        @rmdir($this->dizin . '/storage');
        @rmdir($this->dizin);
    }

    public function testBasariliKosuTekSatirBirakir(): void
    {
        $log = new CronLog($this->dizin);
        $an = new DateTimeImmutable('2026-08-21 03:00:04');

        $log->write($an, true, 'yedek 4.2 MB, off-site ftp · bakım 3 iş', 12.44);

        $icerik = (string) file_get_contents($log->path());
        self::assertStringContainsString('2026-08-21 03:00:04', $icerik);
        self::assertStringContainsString('OK', $icerik);
        self::assertStringContainsString('yedek 4.2 MB', $icerik);
        self::assertStringContainsString('12.4 sn', $icerik);
        self::assertSame(1, substr_count($icerik, "\n"), 'Koşu başına TEK satır.');
    }

    public function testBasarisizKosuDaKAYDEDILIR(): void
    {
        $log = new CronLog($this->dizin);
        $log->write(new DateTimeImmutable('2026-08-22 03:00:02'), false, 'yedek başarısız: disk dolu', 1.05);

        $son = $log->last(new DateTimeImmutable('2026-08-22 06:00:02'));

        self::assertIsArray($son);
        self::assertFalse($son['ok'], 'Hata ile biten koşu OK sayılmaz.');
        self::assertSame(10800, $son['age_seconds'], 'Yaş, koşu anına göre hesaplanır.');
        self::assertStringContainsString('disk dolu', $son['line']);
    }

    public function testHicKosmadiysaNULL_donerVePanelBunuSoyleyebilir(): void
    {
        self::assertNull((new CronLog($this->dizin))->last(new DateTimeImmutable('2026-08-21 09:00:00')));
    }

    public function testSatirlarSinirsizBuyumez(): void
    {
        $log = new CronLog($this->dizin);
        for ($gun = 1; $gun <= 520; $gun++) {
            $log->write(
                (new DateTimeImmutable('2025-01-01 03:00:00'))->modify('+' . $gun . ' days'),
                true,
                'koşu ' . $gun,
                1.0,
            );
        }

        $satirlar = explode("\n", rtrim((string) file_get_contents($log->path()), "\n"));
        self::assertCount(500, $satirlar, 'En eski satırlar atılır (500 sınırı).');
        self::assertStringContainsString('koşu 520', $satirlar[499], 'En yeni satır sonda durur.');
    }

    public function testAyracVeSatirSonuOZETIBOZMAZ(): void
    {
        $log = new CronLog($this->dizin);
        $log->write(new DateTimeImmutable('2026-08-21 03:00:00'), true, "iki|parça\nalt satır", 2.0);

        $son = $log->last(new DateTimeImmutable('2026-08-21 03:00:10'));

        self::assertIsArray($son);
        self::assertTrue($son['ok'], 'Özetteki boru işareti alan sayısını kaydırmamalı.');
    }
}
