<?php

declare(strict_types=1);

namespace App\Services\Bildirim;

use App\Core\Clock;
use App\Core\Connection;
use App\Models\RateSnapshotRepository;
use App\Models\SettingsRepository;
use App\Services\Kuyruk\JobQueue;
use Throwable;

/**
 * EŞİK SÜPÜRMESİ (V3-B A3) — OLAY DEĞİL, DURUM OKUYAN BİLDİRİMLER.
 *
 * Kataloğun çoğu olayı bir EYLEMDEN doğar: liste iletildi, anahtar yenilendi.
 * Üçü ise farklıdır — bir eşiğin AŞILMASINDAN doğar ve o anı kimse tetiklemez:
 *
 *   · NTF-QUEUE-STALLED   — en eski hazır iş bekleme eşiğini aştı,
 *   · NTF-LIST-RATE-DRIFT — kilitli liste kuru ile güncel kur arasındaki sapma
 *                           eşiği aştı.
 *
 * Bunlar için bir tarayıcıya ihtiyaç var. K86 gereği CRON KULLANILMAZ: süpürme,
 * kuyruk turunun peşine takılır — panel ziyareti ve yakalama sonrası zaten
 * çalışan fırsatçı tetiğe. Cron varsa fazlalıktır, yoksa eksik değildir.
 *
 * GÜRÜLTÜ KESİCİ: süpürme her istekte koşmaz. Eşik AŞILI KALDIĞI sürece olay her
 * turda yeniden doğar; bildirim birleştirmesi bunu tek satırda toplasa da
 * `birlesen_sayi` şişer ve "×340" gibi anlamsız bir rozet çıkardı. Bu yüzden
 * süpürme kendi aralığını tutar.
 */
final class EsikSupurmesi
{
    /** Ayar anahtarı: son süpürme zamanı. */
    public const KEY_SON_SUPURME = 'bildirim_son_supurme';

    /** İki süpürme arasındaki en kısa süre (dk). */
    private const ARALIK_DAKIKA = 30;

    /** Kuyruk "durmuş" sayılma eşiği (dk) — BRF-012 ile aynı. */
    public const KUYRUK_ESIK_DAKIKA = 15;

    /** Kur sapması eşiği (yüzde) — bunun altındaki fark gürültüdür. */
    public const KUR_SAPMA_YUZDE = 5.0;

    public function __construct(
        private readonly Connection $connection,
        private readonly BildirimYayinci $yayinci,
        private readonly SettingsRepository $ayarlar,
        private readonly Clock $saat,
    ) {
    }

    /**
     * Eşikleri tarar ve gerekiyorsa bildirim üretir.
     *
     * Hiçbir istisna dışarı sızmaz: süpürme yardımcı bir iştir, onu çağıran
     * kuyruk turunu ya da HTTP yanıtını düşüremez.
     *
     * @param  bool $zorla aralığı yok say (test ve elle tetikleme)
     * @return list<string> üretilen olay kodları
     */
    public function supur(bool $zorla = false): array
    {
        $now = $this->saat->now();

        if (!$zorla && !$this->zamaniGeldiMi($now)) {
            return [];
        }

        $this->ayarlar->set(self::KEY_SON_SUPURME, $now->format(DATE_ATOM));
        $uretilen = [];

        try {
            if ($this->kuyrukDurgunluguBildir($now)) {
                $uretilen[] = 'NTF-QUEUE-STALLED';
            }
        } catch (Throwable) {
            // Kuyruk tablosu yoksa (kurulum yarım) süpürme sessizce geçer.
        }

        try {
            foreach ($this->sapanListeler() as $liste) {
                $this->yayinci->yayimla(
                    'NTF-LIST-RATE-DRIFT',
                    $liste,
                    $this->denetimIzi('list', (int) $liste['liste_id'], 'rate_drift_detected', sprintf(
                        'kilitli %s → güncel %s (%%%.1f sapma)',
                        (string) $liste['kilitli_kur'],
                        (string) $liste['guncel_kur'],
                        (float) $liste['sapma_yuzde'],
                    ), $now),
                );
                $uretilen[] = 'NTF-LIST-RATE-DRIFT';
            }
        } catch (Throwable) {
            // rate_snapshots yoksa sapma ölçülemez; "bilinmeyen ≠ sıfır" (K67)
            // gereği UYDURMA sapma üretilmez, olay hiç doğmaz.
        }

        return $uretilen;
    }

    private function zamaniGeldiMi(\DateTimeImmutable $now): bool
    {
        $son = $this->ayarlar->get(self::KEY_SON_SUPURME);
        if (!is_string($son) || $son === '') {
            return true;
        }

        try {
            $sonAn = new \DateTimeImmutable($son);
        } catch (Throwable) {
            return true;
        }

        return ($now->getTimestamp() - $sonAn->getTimestamp()) >= self::ARALIK_DAKIKA * 60;
    }

    /** Kuyruk eşiği aşıldıysa bildirimi ÜRETİR ve true döner. */
    private function kuyrukDurgunluguBildir(\DateTimeImmutable $now): bool
    {
        $saglik = (new JobQueue($this->connection))->saglik($now);
        $enEski = $saglik['en_eski_bekleyen_dakika'];

        if ($enEski === null || $enEski < self::KUYRUK_ESIK_DAKIKA) {
            return false;
        }

        $this->yayinci->yayimla(
            'NTF-QUEUE-STALLED',
            ['bekleme_dakika' => $enEski, 'bekleyen' => $saglik['bekleyen']],
            $this->denetimIzi('job', null, 'queue_stalled', sprintf(
                'en eski hazır iş %d dakikadır bekliyor (%d iş)',
                $enEski,
                $saglik['bekleyen'],
            ), $now),
        );

        return true;
    }

    /**
     * Kilitli kuru güncel kurdan belirgin sapan listeler.
     *
     * K48/K50 SINIRI: bu bir UYARIDIR, bir düzeltme değil. Kilitli listenin
     * kuru DEĞİŞTİRİLMEZ ve belgesi aynen yeniden üretilir; kullanıcıya yalnız
     * "bu liste eski kurla kilitli" denir. Kararı kullanıcı verir.
     *
     * @return list<array<string, scalar|null>>
     */
    private function sapanListeler(): array
    {
        $snapshot = new RateSnapshotRepository($this->connection);
        $guncel = $snapshot->aktifDeger('CNY');
        if ($guncel === null || (float) $guncel <= 0.0) {
            return [];
        }

        $statement = $this->connection->pdo()->prepare(
            'SELECT id, name, yuan_rate FROM lists
             WHERE rate_locked_at IS NOT NULL AND status NOT IN (:tamam, :iptal)
               AND (visibility IS NULL OR visibility <> :arsiv)',
        );
        $statement->execute(['tamam' => 'completed', 'iptal' => 'cancelled', 'arsiv' => 'archived']);

        $sapanlar = [];
        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $statement->fetchAll() ?: [];

        foreach ($satirlar as $satir) {
            $kilitli = (float) $satir['yuan_rate'];
            if ($kilitli <= 0.0) {
                continue;
            }

            $sapma = abs((float) $guncel - $kilitli) / $kilitli * 100.0;
            if ($sapma < self::KUR_SAPMA_YUZDE) {
                continue;
            }

            $sapanlar[] = [
                'liste_id' => (int) $satir['id'],
                'liste_adi' => (string) $satir['name'],
                'kilitli_kur' => (string) $satir['yuan_rate'],
                'guncel_kur' => $guncel,
                'sapma_yuzde' => round($sapma, 1),
            ];
        }

        return $sapanlar;
    }

    /**
     * Eşik olayları da denetim izi bırakır: ikisi de `birlestirme.izinli=false`
     * ve katalog "değiştirilemez audit bağlantısı" istiyor.
     */
    private function denetimIzi(
        string $varlik,
        ?int $varlikId,
        string $eylem,
        string $ayrinti,
        \DateTimeImmutable $now,
    ): int {
        return (new \App\Services\ActivityLog($this->connection))->record(
            $varlik,
            $varlikId,
            $eylem,
            mb_substr($ayrinti, 0, 500),
            null,
            $now,
            \App\Services\ActivityLog::ACTOR_SYSTEM,
            null,
        );
    }
}
