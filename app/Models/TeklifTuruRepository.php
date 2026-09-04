<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * TEKLİF TURLARI (`supplier_rounds` + `rfq_snapshots` + `rfq_lines`, 0036).
 *
 * Birim `liste × firma × tur` (K103). Bu depo yalnız YAZAR ve OKUR; hangi
 * geçişin izinli olduğuna `TurDurumMakinesi`, ne zaman neyin donacağına
 * `TeklifTuruServisi` karar verir. Depoda kural yoktur — kural iki yerde
 * olursa biri geride kalır.
 *
 * DURUM YAZIMI CAS'TIR (`WHERE state = :onceki`): iki panel sekmesi aynı turu
 * aynı anda göndermeye kalkarsa yalnız biri kazanır; kaybeden 0 satır
 * günceller ve çağıran bunu "geçiş reddedildi" olarak görür. Sessizce ikinci
 * bir snapshot/paylaşım üretmek, firmaya iki link demek olurdu.
 */
final class TeklifTuruRepository
{
    private const KOLONLAR = 'r.id, r.list_id, r.supplier_id, r.tur_no, r.parent_round_id, r.state,
        r.state_changed_at, r.state_changed_by_type, r.state_reason, r.rfq_snapshot_id, r.rate_snapshot_id,
        r.kur_para_birimi, r.kur_degeri, r.kur_kaynagi, r.kur_kilit_at, r.rate_policy, r.share_id,
        r.gecerlilik_gun, r.valid_until, r.portal_dili, r.firma_yazan_ad,
        r.drafted_at, r.sent_at, r.first_viewed_at, r.last_viewed_at, r.pricing_started_at,
        r.last_partial_submitted_at, r.partial_submission_count, r.responded_at, r.approved_at,
        r.revision_requested_at, r.expired_at, r.revoked_at, r.created_at, r.updated_at,
        s.ad AS firma_adi, l.name AS liste_adi';

    private const KAYNAK = 'FROM supplier_rounds r
        JOIN suppliers s ON s.id = r.supplier_id
        JOIN lists l ON l.id = r.list_id';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::KOLONLAR . ' ' . self::KAYNAK . ' WHERE r.id = :id',
        );
        $statement->execute(['id' => $id]);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    /** @return list<array<string, mixed>> */
    public function listeninTurlari(int $listId): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::KOLONLAR . ' ' . self::KAYNAK . '
             WHERE r.list_id = :liste ORDER BY r.supplier_id, r.tur_no',
        );
        $statement->execute(['liste' => $listId]);

        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $statement->fetchAll() ?: [];

        return $satirlar;
    }

    /**
     * Firma için AÇIK tur (nihai olmayan): aynı firmaya ikinci bir açık tur,
     * firmaya iki link demektir.
     *
     * @return array<string, mixed>|null
     */
    public function firmaninAcikTuru(int $listId, int $supplierId): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::KOLONLAR . ' ' . self::KAYNAK . '
             WHERE r.list_id = :liste AND r.supplier_id = :firma
               AND r.state IN (\'DRAFT\', \'SENT\', \'VIEWED\', \'PRICING\', \'RESPONDED\')
             ORDER BY r.tur_no DESC LIMIT 1',
        );
        $statement->execute(['liste' => $listId, 'firma' => $supplierId]);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    public function sonrakiTurNo(int $listId, int $supplierId): int
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COALESCE(MAX(tur_no), 0) FROM supplier_rounds WHERE list_id = :liste AND supplier_id = :firma',
        );
        $statement->execute(['liste' => $listId, 'firma' => $supplierId]);

        return (int) $statement->fetchColumn() + 1;
    }

    /**
     * Açık (nihai olmayan) turlar — Teklifler ekranının "Açık turlar" kolonu.
     *
     * @return list<array<string, mixed>>
     */
    public function acikTurlar(): array
    {
        return $this->turlar(
            "r.state IN ('DRAFT', 'SENT', 'VIEWED', 'PRICING', 'RESPONDED', 'EXPIRED')",
            'r.sent_at IS NULL DESC, r.sent_at ASC, r.id ASC',
        );
    }

    /**
     * Kapanmış turlar — "Geçmiş turlar".
     *
     * @return list<array<string, mixed>>
     */
    public function gecmisTurlar(int $limit = 100): array
    {
        return $this->turlar(
            "r.state IN ('APPROVED', 'ABANDONED', 'REVOKED', 'REVISION_REQUESTED')",
            'r.state_changed_at DESC, r.id DESC',
            $limit,
        );
    }

    /**
     * @param array{list_id: int, supplier_id: int, tur_no: int, parent_round_id?: int|null,
     *              rate_policy?: string, gecerlilik_gun?: int|null, portal_dili?: string,
     *              kur_para_birimi?: string|null, kur_degeri?: string|null, kur_kaynagi?: string|null,
     *              kur_kilit_at?: string|null, rate_snapshot_id?: int|null} $veri
     */
    public function ac(array $veri, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $zaman = Dates::toStorage($now);
        $statement = $pdo->prepare(
            'INSERT INTO supplier_rounds
                (list_id, supplier_id, tur_no, parent_round_id, state, state_changed_at, state_changed_by_type,
                 rate_policy, gecerlilik_gun, portal_dili,
                 kur_para_birimi, kur_degeri, kur_kaynagi, kur_kilit_at, rate_snapshot_id,
                 drafted_at, created_at, updated_at)
             VALUES
                (:liste, :firma, :tur_no, :ebeveyn, \'DRAFT\', :durum_at, \'admin\',
                 :politika, :gecerlilik, :dil,
                 :kur_pb, :kur_deger, :kur_kaynak, :kur_kilit, :kur_snapshot,
                 :taslak_at, :olusma_at, :guncelleme_at)',
        );
        $statement->execute([
            'liste' => $veri['list_id'],
            'firma' => $veri['supplier_id'],
            'tur_no' => $veri['tur_no'],
            'ebeveyn' => $veri['parent_round_id'] ?? null,
            'durum_at' => $zaman,
            'politika' => $veri['rate_policy'] ?? 'inherit',
            'gecerlilik' => $veri['gecerlilik_gun'] ?? null,
            'dil' => $veri['portal_dili'] ?? 'zh',
            'kur_pb' => $veri['kur_para_birimi'] ?? null,
            'kur_deger' => $veri['kur_degeri'] ?? null,
            'kur_kaynak' => $veri['kur_kaynagi'] ?? null,
            'kur_kilit' => $veri['kur_kilit_at'] ?? null,
            'kur_snapshot' => $veri['rate_snapshot_id'] ?? null,
            'taslak_at' => $zaman,
            'olusma_at' => $zaman,
            'guncelleme_at' => $zaman,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * DURUM GEÇİŞİ — CAS. `$onceki` tutmazsa false döner; çağıran reddeder.
     *
     * @param array<string, string|int|null> $ekAlanlar aynı yazımda dolacak zaman/kur alanları
     */
    public function durumGecisi(
        int $id,
        string $onceki,
        string $yeni,
        DateTimeImmutable $now,
        ?string $sebep = null,
        array $ekAlanlar = [],
    ): bool {
        $set = ['state = :yeni', 'state_changed_at = :durum_at', 'state_changed_by_type = \'admin\'',
            'state_reason = :sebep', 'updated_at = :guncelleme_at'];
        $parametreler = [
            'yeni' => $yeni,
            'durum_at' => Dates::toStorage($now),
            'sebep' => $sebep === null ? null : mb_substr($sebep, 0, 500),
            'guncelleme_at' => Dates::toStorage($now),
            'id' => $id,
            'onceki' => $onceki,
        ];
        foreach ($ekAlanlar as $kolon => $deger) {
            if (preg_match('/^[a-z_]+$/', (string) $kolon) !== 1) {
                continue; // kolon adı parametrelenemez; beyaz liste burada
            }
            $set[] = $kolon . ' = :ek_' . $kolon;
            $parametreler['ek_' . $kolon] = $deger;
        }

        $statement = $this->connection->pdo()->prepare(
            'UPDATE supplier_rounds SET ' . implode(', ', $set) . ' WHERE id = :id AND state = :onceki',
        );
        $statement->execute($parametreler);

        return $statement->rowCount() === 1;
    }

    // ── RFQ snapshot ────────────────────────────────────────────────────

    /**
     * Snapshot başlığını açar; satırlar `rfqSatiriEkle()` ile gelir.
     */
    public function rfqSnapshotAc(int $listId, int $listRevision, int $satirSayisi, ?int $olusturanId, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO rfq_snapshots (list_id, list_revision, satir_sayisi, olusturan_id, created_at)
             VALUES (:liste, :revizyon, :satir, :olusturan, :simdi)',
        );
        $statement->execute([
            'liste' => $listId,
            'revizyon' => $listRevision,
            'satir' => $satirSayisi,
            'olusturan' => $olusturanId,
            'simdi' => Dates::toStorage($now),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array{rfq_satir_id: string, product_id: int|null, sira: int, urun_kodu: string|null,
     *              urun_adi_json: string, kaynak_urun_json: string|null, talep_varyant_json: string|null,
     *              talep_miktar: string, talep_birim: string, alici_notu_json: string|null,
     *              gorsel_url: string|null} $satir
     */
    public function rfqSatiriEkle(int $snapshotId, array $satir, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO rfq_lines
                (rfq_snapshot_id, rfq_satir_id, product_id, sira, urun_kodu, urun_adi_json, kaynak_urun_json,
                 talep_varyant_json, talep_miktar, talep_birim, alici_notu_json, gorsel_url, created_at)
             VALUES
                (:snapshot, :satir_id, :urun, :sira, :kod, :ad_json, :kaynak_json,
                 :varyant_json, :miktar, :birim, :not_json, :gorsel, :simdi)',
        );
        $statement->execute([
            'snapshot' => $snapshotId,
            'satir_id' => $satir['rfq_satir_id'],
            'urun' => $satir['product_id'],
            'sira' => $satir['sira'],
            'kod' => $satir['urun_kodu'],
            'ad_json' => $satir['urun_adi_json'],
            'kaynak_json' => $satir['kaynak_urun_json'],
            'varyant_json' => $satir['talep_varyant_json'],
            'miktar' => $satir['talep_miktar'],
            'birim' => $satir['talep_birim'],
            'not_json' => $satir['alici_notu_json'],
            'gorsel' => $satir['gorsel_url'],
            'simdi' => Dates::toStorage($now),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function rfqSatirlari(int $snapshotId): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, rfq_satir_id, product_id, sira, urun_kodu, urun_adi_json, kaynak_urun_json,
                    talep_varyant_json, talep_miktar, talep_birim, alici_notu_json, gorsel_url
             FROM rfq_lines WHERE rfq_snapshot_id = :snapshot ORDER BY sira, id',
        );
        $statement->execute(['snapshot' => $snapshotId]);

        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $statement->fetchAll() ?: [];

        return $satirlar;
    }

    /** @return list<array<string, mixed>> */
    private function turlar(string $kosul, string $sira, ?int $limit = null): array
    {
        $sql = 'SELECT ' . self::KOLONLAR . ' ' . self::KAYNAK . ' WHERE ' . $kosul . ' ORDER BY ' . $sira;
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, min(500, $limit));
        }
        $statement = $this->connection->pdo()->query($sql);

        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $statement === false ? [] : ($statement->fetchAll() ?: []);

        return $satirlar;
    }
}
