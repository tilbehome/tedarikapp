<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;
use Throwable;

/**
 * KUR SNAPSHOT DEPOSU (İE#22 Blok A) — kurun sürümlü geçmişi.
 *
 * AKTİF SATIR TANIMI TEKTİR: `superseded_at IS NULL`. Ayrı bir "sürüm no"
 * kolonu yoktur; her yeni kur, öncekine bitiş damgası basar. Bu sayede
 * "kur kaç saattir aynı?" sorusu tek sorguyla yanıtlanır (BRF-013) ve
 * İE#23'ün tur bazlı kur seçimi satır kimliğini referans alabilir.
 *
 * ÇİFT KAYNAK RİSKİ VE ÇÖZÜMÜ: `settings.yuan_tl/usd_tl` KALDIRILMADI —
 * yirmiden fazla çağrı yeri ona bakıyor. Ama artık TÜRETİLMİŞ KOPYADIR:
 * `SettingsRepository::yuanRate()` önce buradaki aktif satıra bakar, yalnız
 * snapshot yoksa ayara düşer. Yazma her zaman aynı transaction'da ikisini
 * birden günceller (`SettingsController::updateRates`).
 *
 * K50 SINIRI: belge üretimi bu sınıfı ÇAĞIRMAZ. Çıktının kuru `lists.yuan_rate`
 * kopyasındadır; snapshot satırı sonradan ne olursa olsun geçmiş belge aynen
 * yeniden üretilir. `ExportSnapshotKurBekcisiTest` bunu sabitler.
 */
final class RateSnapshotRepository
{
    public const KAYNAK_ELLE = 'elle';
    public const KAYNAK_TCMB = 'tcmb';

    /** Panelde ve API'de kullanılan para birimleri. */
    public const PARA_BIRIMLERI = ['CNY', 'USD'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Bir para biriminin AKTİF snapshot'ı.
     *
     * @return array{id: int, currency: string, rate: string, source: string, effective_from: string}|null
     */
    public function aktif(string $currency): ?array
    {
        try {
            $statement = $this->connection->pdo()->prepare(
                'SELECT id, currency, rate, source, effective_from
                 FROM rate_snapshots
                 WHERE currency = :currency AND superseded_at IS NULL
                 ORDER BY effective_from DESC, id DESC
                 LIMIT 1',
            );
            $statement->execute(['currency' => $currency]);
        } catch (Throwable) {
            // Tablo henüz yoksa (migration bekliyor) kur okuması ÇÖKMEZ:
            // çağıran ayardaki kopyaya düşer. Kur olmadan panel açılmaz.
            return null;
        }

        $satir = $statement->fetch();
        if (!is_array($satir)) {
            return null;
        }

        return [
            'id' => (int) $satir['id'],
            'currency' => (string) $satir['currency'],
            'rate' => (string) $satir['rate'],
            'source' => (string) $satir['source'],
            'effective_from' => (string) $satir['effective_from'],
        ];
    }

    /** Aktif kur değeri; yoksa null (çağıran ayardaki kopyaya düşer). */
    public function aktifDeger(string $currency): ?string
    {
        return $this->aktif($currency)['rate'] ?? null;
    }

    /**
     * Yeni kur satırı açar ve öncekini kapatır.
     *
     * ÇAĞIRAN TRANSACTION AÇMIŞ OLMALIDIR: ayar kopyası, snapshot ve aktivite
     * kaydı birlikte yazılır; yarısı yazılmış bir kur değişikliği "geçmişsiz
     * kur" demektir (K37 §B5).
     */
    public function yeniSurum(
        string $currency,
        string $rate,
        DateTimeImmutable $now,
        string $source = self::KAYNAK_ELLE,
        ?int $kullaniciId = null,
    ): int {
        $zaman = Dates::toStorage($now);
        $pdo = $this->connection->pdo();

        $kapat = $pdo->prepare(
            'UPDATE rate_snapshots SET superseded_at = :zaman
             WHERE currency = :currency AND superseded_at IS NULL',
        );
        $kapat->execute(['zaman' => $zaman, 'currency' => $currency]);

        // AYNI SANİYEDE İKİNCİ DEĞİŞİKLİK KAYBOLMAZ.
        //
        // `UNIQUE (currency, effective_from)` iki satırın aynı ana denk gelmesini
        // engeller — bu doğru bir kısıttır (aynı anda iki "geçerli başlangıç"
        // olamaz) ama ham INSERT bunu HATAYA çevirirdi: kullanıcı kuru düzeltip
        // hemen yeniden kaydettiğinde işlem geri sarar ve DEĞİŞİKLİK KAYBOLURDU.
        // Doğru davranış son yazanın kazanmasıdır: aynı ana denk gelen satır
        // güncellenir, yeni satır açılmaz.
        $guncelle = $pdo->prepare(
            'UPDATE rate_snapshots
             SET rate = :rate, source = :source, created_by = :created_by, superseded_at = NULL
             WHERE currency = :currency AND effective_from = :effective_from',
        );
        $guncelle->execute([
            'rate' => $rate,
            'source' => $source,
            'created_by' => $kullaniciId,
            'currency' => $currency,
            'effective_from' => $zaman,
        ]);

        if ($guncelle->rowCount() > 0) {
            $bul = $pdo->prepare(
                'SELECT id FROM rate_snapshots WHERE currency = :currency AND effective_from = :effective_from',
            );
            $bul->execute(['currency' => $currency, 'effective_from' => $zaman]);

            return (int) $bul->fetchColumn();
        }

        $ekle = $pdo->prepare(
            'INSERT INTO rate_snapshots (currency, rate, source, effective_from, superseded_at, created_by, created_at)
             VALUES (:currency, :rate, :source, :effective_from, NULL, :created_by, :created_at)',
        );
        $ekle->execute([
            'currency' => $currency,
            'rate' => $rate,
            'source' => $source,
            'effective_from' => $zaman,
            'created_by' => $kullaniciId,
            'created_at' => $zaman,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Geçmiş — Ayarlar "Kur tarihçesi" ekranının kaynağı.
     *
     * Aktif satır `aktif: true` işaretiyle döner; ekran onu "geçerli" rozetiyle
     * gösterir. Kaynak (elle/TCMB) ve geçerlilik başlangıcı da taşınır: "bu kuru
     * kim, ne zaman, nereden koydu" sorusu ekrandan yanıtlanabilmeli.
     *
     * @return list<array{id: int, currency: string, rate: string, source: string, effective_from: string, superseded_at: string|null, aktif: bool}>
     */
    public function gecmis(?string $currency = null, int $limit = 100): array
    {
        $sql = 'SELECT id, currency, rate, source, effective_from, superseded_at FROM rate_snapshots';
        $params = [];
        if ($currency !== null && $currency !== '') {
            $sql .= ' WHERE currency = :currency';
            $params['currency'] = $currency;
        }
        $sql .= ' ORDER BY effective_from DESC, id DESC LIMIT ' . max(1, min(500, $limit));

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);

        $cikti = [];
        foreach ($statement->fetchAll() ?: [] as $satir) {
            $bitis = $satir['superseded_at'] ?? null;
            $cikti[] = [
                'id' => (int) $satir['id'],
                'currency' => (string) $satir['currency'],
                'rate' => (string) $satir['rate'],
                'source' => (string) $satir['source'],
                'effective_from' => (string) $satir['effective_from'],
                'superseded_at' => is_string($bitis) ? $bitis : null,
                'aktif' => $bitis === null,
            ];
        }

        return $cikti;
    }

    /**
     * Aktif kurun yaşı (saat) — BRF-013'ün ("kur 24 saattir güncellenmedi")
     * bekledigi tek veri. Aktif satır yoksa null: "bilmiyoruz" ile "0 saat"
     * aynı şey değildir (K67).
     */
    public function aktifYasSaat(string $currency, DateTimeImmutable $now): ?int
    {
        $aktif = $this->aktif($currency);
        if ($aktif === null) {
            return null;
        }

        try {
            $baslangic = new DateTimeImmutable($aktif['effective_from']);
        } catch (Throwable) {
            return null;
        }

        return (int) floor(($now->getTimestamp() - $baslangic->getTimestamp()) / 3600);
    }
}
