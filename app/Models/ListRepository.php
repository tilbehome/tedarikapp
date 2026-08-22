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
        share_expires_at, created_at, updated_at, archived_at, deleted_at';

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
     * @param array{visibility?: string, status?: string, q?: string} $filters
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
