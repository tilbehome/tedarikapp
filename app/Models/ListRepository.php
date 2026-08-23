<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * lists tablosu erişimi (docs/04 §2). Yalnızca parametreli sorgu — CLAUDE.md §5.
 *
 * Soft delete kuralı: normal uçlar `deleted_at IS NULL` filtresini HER sorguda uygular;
 * silinen kayıt yalnızca çöp kutusu uçlarından görünür (K15).
 */
final class ListRepository
{
    private const COLUMNS = 'id, name, period, supplier_name, status, note, visibility,
        yuan_rate, usd_rate, rate_locked_at, revision, share_token_hash, share_token_prefix,
        share_expires_at, share_key_hash, share_key_plain, share_key_enabled,
        created_at, updated_at, archived_at, deleted_at';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM lists WHERE id = :id';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{visibility?: string, status?: string, q?: string, haric_id?: int} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM lists WHERE deleted_at IS NULL';
        $params = [];

        if (isset($filters['visibility']) && $filters['visibility'] !== '') {
            $sql .= ' AND visibility = :visibility';
            $params['visibility'] = $filters['visibility'];
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            // HER SÜTUN İÇİN AYRI YER TUTUCU (canlı hata dersi): üretimde PDO
            // native prepare kullanır (ATTR_EMULATE_PREPARES=false) ve MySQL aynı
            // isimli yer tutucunun İKİ KEZ geçmesine izin vermez — istek HY093
            // ile düşer. Emülasyon açık olan SQLite bunu hoş gördüğü için hata
            // testlerde görünmüyordu; artık isimler ayrı, değer iki kez bağlanır.
            $sql .= ' AND (name LIKE :q_ad OR supplier_name LIKE :q_tedarikci)';
            $params['q_ad'] = '%' . $filters['q'] . '%';
            $params['q_tedarikci'] = '%' . $filters['q'] . '%';
        }

        // İE#21 B4 (PM şartı): SİSTEM listesi hiçbir listelemede görünmez — liste
        // ekranı, liste seçicileri ve Panorama'daki "aktif liste" sayısı dahil.
        // Hariç tutmayı SORGUYA koymak, her çağıranın hatırlamasına bırakmaktan
        // güvenlidir: tek bir yer unutulursa havuz oradan sızar.
        if (isset($filters['haric_id']) && (int) $filters['haric_id'] > 0) {
            $sql .= ' AND id <> :haric_id';
            $params['haric_id'] = (int) $filters['haric_id'];
        }

        $sql .= ' ORDER BY created_at DESC, id DESC';

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO lists (name, period, supplier_name, status, note, visibility,
                yuan_rate, usd_rate, rate_locked_at, revision, created_at, updated_at)
             VALUES (:name, :period, :supplier_name, :status, :note, :visibility,
                :yuan_rate, :usd_rate, :rate_locked_at, 0, :created_at, :updated_at)',
        );
        $statement->execute([
            'name' => $data['name'],
            'period' => $data['period'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'note' => $data['note'] ?? null,
            'visibility' => $data['visibility'] ?? 'active',
            'yuan_rate' => $data['yuan_rate'],
            'usd_rate' => $data['usd_rate'],
            'rate_locked_at' => $data['rate_locked_at'] ?? null,
            'created_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * KİLİTLENMEMİŞ listelerin kurunu güncel ayara TAZELER (İE#21 B5).
     *
     * SAHA BULGUSU VE KÖK NEDEN: `lists.yuan_rate` liste OLUŞTURULURKEN ayardan
     * kopyalanıyordu ve bir daha okunmuyordu. K4 "kur listeye KİLİTLENİR" der ama
     * kilit anı listenin İLETİLDİĞİ andır — o ana kadar taslak, güncel kuru İZLEMELİDİR
     * (aynı dosyada `LIST_DRAFT`e dönüşte kilidin açılması da bu niyeti söyler).
     * Kopya tazelenmeyince canlıda taslak listeler 7,04/41,50 ile donup kaldı: antette
     * eski kur, ₺ karşılıklarında eski çarpan.
     *
     * NEDEN OKUMA ANINDA DEĞİL DE YAZMA ANINDA: kuru okuyan tek yol yok — panel,
     * paylaşım sayfası, Excel, PDF ve export snapshot'ı hepsi kolonu okuyor. Okuma
     * anında çözseydik bu yolların BİRİNİ atlamak sapmayı geri getirirdi. Kolonu
     * kaynakta doğru tutmak, tek değişiklikle hepsini düzeltir.
     *
     * REVİZYON İLERLEMEZ (K71): revizyon, firmaya GİDEN belgenin değiştiğini söyler;
     * kilitlenmemiş liste henüz gitmemiştir. `updated_at` de dokunulmaz — kur tazeleme
     * kullanıcı düzenlemesi değildir ve "son güncelleme" damgasını yalanlamamalıdır.
     *
     * @return int etkilenen liste sayısı
     */
    public function kilitsizKurlariTazele(string $yuanRate, string $usdRate): int
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE lists SET yuan_rate = :yuan_rate, usd_rate = :usd_rate
             WHERE rate_locked_at IS NULL AND deleted_at IS NULL
               AND (yuan_rate <> :yuan_kiyas OR usd_rate <> :usd_kiyas)',
        );
        $statement->execute([
            'yuan_rate' => $yuanRate,
            'usd_rate' => $usdRate,
            'yuan_kiyas' => $yuanRate,
            'usd_kiyas' => $usdRate,
        ]);

        return $statement->rowCount();
    }

    /**
     * Kısmi güncelleme — yalnızca verilen alanlar yazılır.
     *
     * @param array<string, mixed> $fields
     */
    public function update(int $id, array $fields, DateTimeImmutable $now): void
    {
        if ($fields === []) {
            return;
        }

        $allowed = [
            'name', 'period', 'supplier_name', 'status', 'note', 'visibility',
            'yuan_rate', 'usd_rate', 'rate_locked_at', 'archived_at',
            'share_token_hash', 'share_token_prefix', 'share_expires_at',
            // İE#18 G6 (K62): erişim anahtarı — hash + panelde gösterilen düz metin + kapı anahtarı.
            'share_key_hash', 'share_key_plain', 'share_key_enabled',
        ];

        $assignments = [];
        $params = ['id' => $id, 'updated_at' => Dates::toStorage($now)];
        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                continue;
            }
            $assignments[] = sprintf('%s = :%s', $column, $column);
            $params[$column] = $value;
        }
        if ($assignments === []) {
            return;
        }
        $assignments[] = 'updated_at = :updated_at';

        $statement = $this->connection->pdo()->prepare(
            sprintf('UPDATE lists SET %s WHERE id = :id', implode(', ', $assignments)),
        );
        $statement->execute($params);
    }

    /**
     * Ada göre desen araması — kopya numaralandırması için (İE#10 Blok 5a).
     * Çöp kutusundakiler DE sayılır: geri yüklenince ad çakışması olmasın.
     *
     * @return list<string>
     */
    public function namesLike(string $pattern): array
    {
        $statement = $this->connection->pdo()->prepare('SELECT name FROM lists WHERE name LIKE :pattern');
        $statement->execute(['pattern' => $pattern]);

        /** @var list<string> */
        return $statement->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Paylaşım token'ı araması (K34 — DB'de yalnız SHA-256 hash durur, İE#10 Blok 4).
     * Silinmiş/çöpteki liste paylaşılmaz; süre denetimi çağırana aittir (sabit 404 için).
     *
     * @return array<string, mixed>|null
     */
    public function findByShareHash(string $hash): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM lists WHERE share_token_hash = :hash AND deleted_at IS NULL',
        );
        $statement->execute(['hash' => $hash]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** Ürün/fiyat/adet/sıra değişiminde çağrılır — "çıktı güncel değil" rozetinin sayacı (K25). */
    public function bumpRevision(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE lists SET revision = revision + 1, updated_at = :updated_at WHERE id = :id',
        );
        $statement->execute(['id' => $id, 'updated_at' => Dates::toStorage($now)]);
    }

    public function softDelete(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE lists SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL',
        );
        $statement->execute([
            'id' => $id,
            'deleted_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
        ]);
    }

    public function restore(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE lists SET deleted_at = NULL, updated_at = :updated_at WHERE id = :id',
        );
        $statement->execute(['id' => $id, 'updated_at' => Dates::toStorage($now)]);
    }

    /** Kalıcı silme — ürünler ve görseller FK CASCADE ile birlikte gider. */
    public function forceDelete(int $id): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM lists WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * Çöp kutusundaki listeler (eskiden yeniye).
     *
     * @return list<array<string, mixed>>
     */
    public function trashed(): array
    {
        $statement = $this->connection->pdo()->query(
            'SELECT ' . self::COLUMNS . ' FROM lists WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC, id DESC',
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /** @return list<int> Saklama süresi dolmuş liste kimlikleri. */
    public function expiredTrashIds(DateTimeImmutable $threshold): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id FROM lists WHERE deleted_at IS NOT NULL AND deleted_at <= :threshold',
        );
        $statement->execute(['threshold' => Dates::toStorage($threshold)]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** @return array<string, mixed>|null Listenin son export kaydı. */
    public function lastExport(int $listId): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT format, created_at, list_revision FROM exports
             WHERE list_id = :list_id ORDER BY created_at DESC, id DESC',
        );
        $statement->execute(['list_id' => $listId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
