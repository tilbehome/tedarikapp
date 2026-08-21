<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Gecelik koşunun GÖRÜNÜR izi (İE#14 D1).
 *
 * SORUN: cron çalıştı mı çalışmadı mı belli değildi. app_logs'a satır yazılıyordu
 * ama cron HİÇ tetiklenmediyse orada da bir şey olmaz — "kayıt yok" ile "koşu yok"
 * ayırt edilemiyordu. Artık her koşu, başarılı da başarısız da olsa
 * `storage/logs/cron.log` dosyasına TEK satır bırakır; panel bu satırı okur.
 *
 * Biçim (docs/10'da örneği vardır):
 *   2026-08-21 03:00:04 | OK   | yedek 4.2 MB, off-site ftp, bakım 3 iş | 12.4 sn
 *   2026-08-22 03:00:02 | HATA | yedek başarısız: disk dolu            | 1.1 sn
 *
 * Dosya SINIRLI büyür: 500 satırı aşınca en eski satırlar atılır — cron günde bir
 * kez koştuğu için bu ~1.5 yıllık geçmiş demektir, döndürmeye gerek kalmaz.
 */
final class CronLog
{
    private const MAX_SATIR = 500;

    public function __construct(private readonly string $basePath)
    {
    }

    public function path(): string
    {
        return $this->basePath . '/storage/logs/cron.log';
    }

    /**
     * Koşu sonucunu tek satır olarak ekler. Log yazımı ASLA koşuyu düşürmez:
     * dosya yazılamıyorsa sessizce geçilir (yedeğin kendisi zaten alınmıştır).
     */
    public function write(DateTimeImmutable $now, bool $ok, string $ozet, float $sureSaniye): void
    {
        $satir = sprintf(
            '%s | %-4s | %s | %.1f sn',
            $now->format('Y-m-d H:i:s'),
            $ok ? 'OK' : 'HATA',
            str_replace(["\n", "\r", '|'], ' ', trim($ozet)),
            $sureSaniye,
        );

        $path = $this->path();
        $dizin = dirname($path);
        if (!is_dir($dizin) && !@mkdir($dizin, 0775, true) && !is_dir($dizin)) {
            return;
        }

        $mevcut = is_file($path) ? (string) @file_get_contents($path) : '';
        $satirlar = $mevcut === '' ? [] : explode("\n", rtrim($mevcut, "\n"));
        $satirlar[] = $satir;
        if (count($satirlar) > self::MAX_SATIR) {
            $satirlar = array_slice($satirlar, -self::MAX_SATIR);
        }

        @file_put_contents($path, implode("\n", $satirlar) . "\n", LOCK_EX);
    }

    /**
     * Son koşu satırı ve yaşı — panel "cron çalışıyor mu?" sorusunu buradan yanıtlar.
     *
     * @return array{line: string, ok: bool, at: string, age_seconds: int}|null
     */
    public function last(DateTimeImmutable $now): ?array
    {
        $path = $this->path();
        if (!is_file($path)) {
            return null;
        }

        $mevcut = rtrim((string) @file_get_contents($path), "\n");
        if ($mevcut === '') {
            return null;
        }

        $satirlar = explode("\n", $mevcut);
        $son = trim($satirlar[count($satirlar) - 1]);
        $parcalar = array_map('trim', explode('|', $son));
        $tarih = $parcalar[0];

        try {
            $an = new DateTimeImmutable($tarih);
        } catch (\Throwable) {
            return null;
        }

        return [
            'line' => $son,
            'ok' => strtoupper($parcalar[1] ?? '') === 'OK',
            'at' => $an->format(DATE_ATOM),
            'age_seconds' => max(0, $now->getTimestamp() - $an->getTimestamp()),
        ];
    }
}
