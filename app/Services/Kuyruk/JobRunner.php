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
     * Uzun süren bir işin kirasını uzatır (İE#21 B11).
     *
     * İşleyiciler bunu iş satırıyla birlikte çağırır: `$kosucu->kalpAtisi($is, $now)`.
     * false dönerse iş ARTIK BİZİM DEĞİLDİR (kira devralınmış) ve işleyici durmalıdır.
     *
     * @param array<string, mixed> $is
     */
    public function kalpAtisi(array $is, DateTimeImmutable $now): bool
    {
        $token = is_string($is['kilit_token'] ?? null) ? (string) $is['kilit_token'] : '';

        return $token !== '' && $this->kuyruk->kalpAtisi((int) $is['id'], $token, $now);
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

            // B11: sahiplenmede verilen kira token'ı sonuç yazarken KANIT olur.
            // Kirası dolup devralınan işin eski sahibi buraya geldiğinde token'ı
            // eşleşmez ve sonucu yazamaz — çift koşan işin sonuçları birbirini ezmez.
            $token = is_string($is['kilit_token'] ?? null) ? (string) $is['kilit_token'] : '';

            try {
                $isleyici($yuk, $is);
                $this->kuyruk->basarili((int) $is['id'], $now, $token);
                $basarili++;
            } catch (Throwable $hata) {
                // B11: hata SINIFLANDIRILIR — kalıcı hata tekrar denenmez, hız
                // sınırında sağlayıcının istediği süre beklenir, geçici hatada
                // jitter'lı geri çekilme uygulanır (gerekçe: HataSinifi).
                ['sinif' => $sinif, 'bekleme' => $saglayiciBeklemesi] = HataSinifi::siniflandir($hata);

                $this->kuyruk->basarisiz(
                    (int) $is['id'],
                    $hata->getMessage(),
                    $now,
                    $sinif,
                    $saglayiciBeklemesi,
                    $token,
                );
                $basarisiz++;
                $this->logger->error('Kuyruk işi başarısız', [
                    'tur' => $tur,
                    'id' => (int) $is['id'],
                    'sinif' => $sinif,
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
