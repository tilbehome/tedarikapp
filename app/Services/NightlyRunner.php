<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Gecelik koşunun iki adımını SIRAYLA ama BİRBİRİNDEN BAĞIMSIZ çalıştırır
 * (İE#13 EK-A — tek cron ilkesi).
 *
 * Kural: yedek adımı patlasa bile bakım adımı KOŞAR; bakım patlasa bile yedeğin
 * sonucu raporlanır. İki iş birbirinin hatasını yutmaz — her adımın kendi
 * try/catch'i vardır ve sonuç tek birleşik özet satırında toplanır.
 *
 * Saf sınıf: ağ/DB/dosya bilmez, adımları dışarıdan callable olarak alır — testte
 * "yedek başarısız olsa da bakım koşuyor" kuralı ağa çıkmadan kanıtlanır.
 */
final class NightlyRunner
{
    /**
     * @param callable(): string $backup     başarı durumunda kısa özet metni döndürür
     * @param callable(): string $maintenance aynı sözleşme
     *
     * @return array{ok: bool, backup: array{ok: bool, message: string}, maintenance: array{ok: bool, message: string}, summary: string}
     */
    public function run(callable $backup, callable $maintenance): array
    {
        $backupResult = $this->step($backup);
        $maintenanceResult = $this->step($maintenance);

        $ok = $backupResult['ok'] && $maintenanceResult['ok'];

        return [
            'ok' => $ok,
            'backup' => $backupResult,
            'maintenance' => $maintenanceResult,
            'summary' => sprintf(
                'gecelik koşu %s | yedek: %s%s | bakım: %s%s',
                $ok ? 'TAMAM' : 'KISMİ',
                $backupResult['ok'] ? '' : 'HATA — ',
                $backupResult['message'],
                $maintenanceResult['ok'] ? '' : 'HATA — ',
                $maintenanceResult['message'],
            ),
        ];
    }

    /** @return array{ok: bool, message: string} */
    private function step(callable $callback): array
    {
        try {
            return ['ok' => true, 'message' => $callback()];
        } catch (\Throwable $exception) {
            // Mesaj log'a gider: sır içermemesi çağıranın sorumluluğudur (yedek anahtarı
            // ve kimlikler BackupService/BackupOffsite içinde hiçbir istisnaya girmez).
            return ['ok' => false, 'message' => $exception->getMessage()];
        }
    }
}
