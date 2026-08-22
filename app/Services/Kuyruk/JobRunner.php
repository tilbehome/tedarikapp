<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * KUYRUK İŞLEYİCİSİ (İE#20 C3) — cron tetikli, süre sınırlı.
 *
 * Paylaşımlı hostingde arka plan süreci yoktur; işleyici cron'dan koşar ve
 * KENDİ SÜRESİNİ KOLLAR. `max_execution_time` tarafından ortadan kesilmek,
 * işi "çalışıyor" durumunda bırakır (kilit zaman aşımıyla kurtarılır ama
 * bir tur boşa gider). Bu yüzden koşum, süre veya iş sayısı sınırına gelince
 * KENDİ İSTEĞİYLE durur — kesilmeyi beklemez.
 *
 * İşleyici, iş türlerini BİLMEZ: her tür kendi işleyicisini kaydeder
 * (`kaydet('ceviri', fn)`). Böylece yeni bir iş türü eklemek bu sınıfa
 * dokunmadan yapılır.
 */
final class JobRunner
{
    /** @var array<string, callable(array<string, mixed>, array<string, mixed>): void> */
    private array $isleyiciler = [];

    public function __construct(
        private readonly JobQueue $kuyruk,
        private readonly LoggerInterface $logger,
        /** Koşum bütçesi (saniye): cron aralığından KISA olmalı. */
        private readonly int $sureSiniri = 50,
        private readonly int $isSiniri = 25,
    ) {
    }

    /**
     * @param callable(array<string, mixed>, array<string, mixed>): void $isleyici
     *        (yük, iş satırı) alır; hata fırlatırsa iş başarısız sayılır
     */
    public function kaydet(string $tur, callable $isleyici): void
    {
        $this->isleyiciler[$tur] = $isleyici;
    }

    /**
     * Bir cron turu koşar.
     *
     * @return array{islenen: int, basarili: int, basarisiz: int, sure: float, durma_nedeni: string}
     */
    public function kos(DateTimeImmutable $now, ?string $isleyiciKimligi = null): array
    {
        $kimlik = $isleyiciKimligi ?? (gethostname() ?: 'cron') . ':' . getmypid();
        $baslangic = microtime(true);
        $islenen = 0;
        $basarili = 0;
        $basarisiz = 0;
        $durmaNedeni = 'kuyruk boş';

        while (true) {
            if ($islenen >= $this->isSiniri) {
                $durmaNedeni = 'iş sınırı (' . $this->isSiniri . ')';

                break;
            }
            if ((microtime(true) - $baslangic) >= $this->sureSiniri) {
                $durmaNedeni = 'süre sınırı (' . $this->sureSiniri . ' sn)';

                break;
            }

            $is = $this->kuyruk->sahiplen($kimlik, $now);
            if ($is === null) {
                break;
            }

            $islenen++;
            $tur = (string) $is['tur'];
            $isleyici = $this->isleyiciler[$tur] ?? null;

            if ($isleyici === null) {
                // Tanınmayan tür: sessizce tekrar denemek sonsuz döngüdür.
                // Doğrudan ölü rafına gönderilir ki panelde GÖRÜNSÜN.
                $this->kuyruk->oldur((int) $is['id'], 'Tanınmayan iş türü: ' . $tur, $now);
                $basarisiz++;
                $this->logger->warning('Kuyrukta tanınmayan iş türü', ['tur' => $tur, 'id' => (int) $is['id']]);

                continue;
            }

            /** @var array<string, mixed> $yuk */
            $yuk = is_string($is['yuk'] ?? null) ? (json_decode((string) $is['yuk'], true) ?: []) : [];

            try {
                $isleyici($yuk, $is);
                $this->kuyruk->basarili((int) $is['id'], $now);
                $basarili++;
            } catch (Throwable $hata) {
                $this->kuyruk->basarisiz((int) $is['id'], $hata->getMessage(), $now);
                $basarisiz++;
                $this->logger->error('Kuyruk işi başarısız', [
                    'tur' => $tur,
                    'id' => (int) $is['id'],
                    'hata' => $hata->getMessage(),
                ]);
            }
        }

        return [
            'islenen' => $islenen,
            'basarili' => $basarili,
            'basarisiz' => $basarisiz,
            'sure' => round(microtime(true) - $baslangic, 2),
            'durma_nedeni' => $durmaNedeni,
        ];
    }
}
