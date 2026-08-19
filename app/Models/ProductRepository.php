<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * products + product_images + product_status_history erişimi (docs/04 §2).
 *
 * Soft delete: normal sorgular `deleted_at IS NULL` filtreler (K15).
 * Durum geçişleri `product_status_history`'ye yazılır (K25) — activity_log'a gömülmez.
 */
final class ProductRepository
{
    private const COLUMNS = 'id, list_id, sort_no, category_id, platform, external_id,
        name, name_original, detail, url, vendor_name, vendor_url, sku_selection, sku_matrix,
        main_image, main_image_source, video_url, qty, price_yuan, price_ddp_usd, units_per_carton, tracking_no,
        status, note, created_at, updated_at, deleted_at';

    /** Uçlardan yazılabilen alanlar (docs/10 §4). */
    public const WRITABLE = [
        'category_id', 'platform', 'external_id', 'name', 'name_original', 'detail', 'url',
        'vendor_name', 'vendor_url', 'sku_selection', 'sku_matrix', 'main_image', 'main_image_source', 'video_url',
        'qty', 'price_yuan', 'price_ddp_usd', 'units_per_carton', 'tracking_no', 'note',
    ];

    /** Değişince listenin `revision` sayacını artıran alanlar (K25). */
    public const REVISION_FIELDS = ['qty', 'price_yuan', 'price_ddp_usd', 'name', 'sort_no'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM products WHERE id = :id';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{status?: string, category_id?: int, q?: string} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function forList(int $listId, array $filters = []): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM products WHERE list_id = :list_id AND deleted_at IS NULL';
        $params = ['list_id' => $listId];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset($filters['category_id'])) {
            $sql .= ' AND category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $sql .= ' AND (name LIKE :q OR name_original LIKE :q OR detail LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql .= ' ORDER BY sort_no, id';

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /** @return list<string> Listedeki silinmemiş ürünlerin durumları. */
    public function statusesForList(int $listId): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT status FROM products WHERE list_id = :list_id AND deleted_at IS NULL',
        );
        $statement->execute(['list_id' => $listId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(static fn (array $row): string => (string) $row['status'], $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(int $listId, array $data, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO products (list_id, sort_no, category_id, platform, external_id, name,
                name_original, detail, url, vendor_name, vendor_url, sku_selection, sku_matrix,
                main_image, main_image_source, video_url, qty, price_yuan, price_ddp_usd, units_per_carton,
                tracking_no, status, note, created_at, updated_at)
             VALUES (:list_id, :sort_no, :category_id, :platform, :external_id, :name,
                :name_original, :detail, :url, :vendor_name, :vendor_url, :sku_selection, :sku_matrix,
                :main_image, :main_image_source, :video_url, :qty, :price_yuan, :price_ddp_usd, :units_per_carton,
                :tracking_no, :status, :note, :created_at, :updated_at)',
        );
        $statement->execute([
            'list_id' => $listId,
            'sort_no' => $data['sort_no'] ?? ($this->maxSortNo($listId) + 1),
            'category_id' => $data['category_id'] ?? null,
            'platform' => $data['platform'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'name' => $data['name'],
            'name_original' => $data['name_original'] ?? null,
            'detail' => $data['detail'] ?? null,
            'url' => $data['url'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'vendor_url' => $data['vendor_url'] ?? null,
            'sku_selection' => $data['sku_selection'] ?? null,
            'sku_matrix' => $data['sku_matrix'] ?? null,
            'main_image' => $data['main_image'] ?? null,
            'main_image_source' => $data['main_image_source'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'qty' => $data['qty'],
            'price_yuan' => $data['price_yuan'] ?? '0',
            'price_ddp_usd' => $data['price_ddp_usd'] ?? '0',
            'units_per_carton' => $data['units_per_carton'] ?? null,
            'tracking_no' => $data['tracking_no'] ?? null,
            'status' => $data['status'] ?? 'to_order',
            'note' => $data['note'] ?? null,
            'created_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields, DateTimeImmutable $now): void
    {
        $assignments = [];
        $params = ['id' => $id, 'updated_at' => Dates::toStorage($now)];

        foreach ($fields as $column => $value) {
            if (!in_array($column, [...self::WRITABLE, 'status', 'sort_no', 'list_id'], true)) {
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
            sprintf('UPDATE products SET %s WHERE id = :id', implode(', ', $assignments)),
        );
        $statement->execute($params);
    }

    public function softDelete(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE products SET deleted_at = :deleted_at, updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL',
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
            'UPDATE products SET deleted_at = NULL, updated_at = :updated_at WHERE id = :id',
        );
        $statement->execute(['id' => $id, 'updated_at' => Dates::toStorage($now)]);
    }

    public function forceDelete(int $id): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function trashed(): array
    {
        $statement = $this->connection->pdo()->query(
            'SELECT p.id, p.list_id, p.name, p.deleted_at, l.name AS list_name, l.deleted_at AS list_deleted_at
             FROM products p
             INNER JOIN lists l ON l.id = p.list_id
             WHERE p.deleted_at IS NOT NULL
             ORDER BY p.deleted_at DESC, p.id DESC',
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /** @return list<int> */
    public function expiredTrashIds(DateTimeImmutable $threshold): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id FROM products WHERE deleted_at IS NOT NULL AND deleted_at <= :threshold',
        );
        $statement->execute(['threshold' => Dates::toStorage($threshold)]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Aynı platform + external_id çifti sistemde var mı (K25 — tekrar UYARISI, engel değil).
     *
     * @return array<string, mixed>|null
     */
    public function findDuplicate(string $platform, string $externalId, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT p.id, p.list_id, p.name, l.name AS list_name
                FROM products p INNER JOIN lists l ON l.id = p.list_id
                WHERE p.platform = :platform AND p.external_id = :external_id
                  AND p.deleted_at IS NULL AND l.deleted_at IS NULL';
        $params = ['platform' => $platform, 'external_id' => $externalId];

        if ($excludeId !== null) {
            $sql .= ' AND p.id <> :exclude';
            $params['exclude'] = $excludeId;
        }

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function maxSortNo(int $listId): int
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COALESCE(MAX(sort_no), 0) AS max_sort FROM products WHERE list_id = :list_id',
        );
        $statement->execute(['list_id' => $listId]);
        $row = $statement->fetch();

        return is_array($row) ? (int) $row['max_sort'] : 0;
    }

    /**
     * Sıra numaralarını yeniden yazar.
     *
     * @param list<int> $orderedIds
     */
    public function reorder(int $listId, array $orderedIds, DateTimeImmutable $now): int
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE products SET sort_no = :sort_no, updated_at = :updated_at
             WHERE id = :id AND list_id = :list_id AND deleted_at IS NULL',
        );

        $updated = 0;
        foreach ($orderedIds as $index => $productId) {
            $statement->execute([
                'sort_no' => $index + 1,
                'updated_at' => Dates::toStorage($now),
                'id' => $productId,
                'list_id' => $listId,
            ]);
            $updated += $statement->rowCount();
        }

        return $updated;
    }

    /** Durum geçişini tarihçeye yazar (K25). */
    public function recordStatusChange(
        int $productId,
        ?string $fromStatus,
        string $toStatus,
        DateTimeImmutable $now,
        string $actorType = 'admin',
        ?int $actorId = null,
        ?string $requestId = null,
    ): void {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO product_status_history
                (product_id, from_status, to_status, actor_type, actor_id, changed_at, request_id)
             VALUES (:product_id, :from_status, :to_status, :actor_type, :actor_id, :changed_at, :request_id)',
        );
        $statement->execute([
            'product_id' => $productId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'changed_at' => Dates::toStorage($now),
            'request_id' => $requestId,
        ]);
    }

    // ─────────────── Medya referansları (K37 §C7) ───────────────

    /**
     * Ürünün işaret ettiği medya referansları: ana görsel + ek görseller.
     * Kalıcı silme ÖNCESİ toplanır; dosyalar DB kaydı gittikten sonra temizlenir.
     *
     * @return list<string>
     */
    public function mediaReferencesForProduct(int $productId): array
    {
        $references = [];

        $statement = $this->connection->pdo()->prepare('SELECT main_image FROM products WHERE id = :id');
        $statement->execute(['id' => $productId]);
        $row = $statement->fetch();
        if (is_array($row) && is_string($row['main_image']) && $row['main_image'] !== '') {
            $references[] = $row['main_image'];
        }

        $statement = $this->connection->pdo()->prepare('SELECT path FROM product_images WHERE product_id = :id');
        $statement->execute(['id' => $productId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $imageRow) {
            $references[] = (string) $imageRow['path'];
        }

        return $references;
    }

    /**
     * Listedeki TÜM ürünlerin (soft-delete dahil — CASCADE ile birlikte gidecekler)
     * medya referansları. Liste kalıcı silinmeden önce çağrılır.
     *
     * @return list<string>
     */
    public function mediaReferencesForList(int $listId): array
    {
        $references = [];

        $statement = $this->connection->pdo()->prepare(
            'SELECT main_image FROM products WHERE list_id = :list_id AND main_image IS NOT NULL',
        );
        $statement->execute(['list_id' => $listId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $row) {
            if (is_string($row['main_image']) && $row['main_image'] !== '') {
                $references[] = $row['main_image'];
            }
        }

        $statement = $this->connection->pdo()->prepare(
            'SELECT pi.path FROM product_images pi
             INNER JOIN products p ON p.id = pi.product_id
             WHERE p.list_id = :list_id',
        );
        $statement->execute(['list_id' => $listId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $row) {
            $references[] = (string) $row['path'];
        }

        return $references;
    }

    /**
     * Sistemdeki TÜM medya referansları (soft-delete dahil: çöp kutusundaki ürün
     * geri alınabilir, görseli de yerinde kalmalı). Yetim dosya GC'si bunu kullanır.
     *
     * @return list<string>
     */
    public function allMediaReferences(): array
    {
        $references = [];

        $statement = $this->connection->pdo()->query(
            "SELECT main_image FROM products WHERE main_image IS NOT NULL AND main_image <> ''",
        );
        if ($statement !== false) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll();
            foreach ($rows as $row) {
                $references[] = (string) $row['main_image'];
            }
        }

        $statement = $this->connection->pdo()->query('SELECT path FROM product_images');
        if ($statement !== false) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll();
            foreach ($rows as $row) {
                $references[] = (string) $row['path'];
            }
        }

        return $references;
    }

    /**
     * Bu DOSYA ADINA işaret eden kayıt sayısı (kopyalanan listeler aynı görseli
     * paylaşır — dosya ancak son referans da silinince temizlenebilir).
     * Ad sunucu üretimi 32 hanelik hex olduğundan LIKE eşleşmesi güvenlidir.
     */
    public function mediaFileReferenceCount(string $fileName): int
    {
        $like = '%' . $fileName;

        $statement = $this->connection->pdo()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM products WHERE main_image LIKE :a)
              + (SELECT COUNT(*) FROM product_images WHERE path LIKE :b) AS total',
        );
        $statement->execute(['a' => $like, 'b' => $like]);
        $row = $statement->fetch();

        return is_array($row) ? (int) $row['total'] : 0;
    }

    /** @return list<array{id: int, url: string, sort: int}> */
    /**
     * Yakalamadan gelen ek görselleri REMOTE galeri satırı olarak yazar (İE#11).
     * K47 arşive-taşıma hattı bunları sonra indirir (storage_mode=remote + source_url).
     *
     * @param list<string> $urls
     */
    public function addRemoteImages(int $productId, array $urls): void
    {
        $statement = $this->connection->pdo()->prepare(
            "INSERT INTO product_images (product_id, path, sort, storage_mode, source_url)
             VALUES (:product_id, :path, :sort, 'remote', :source_url)",
        );
        $sort = 0;
        foreach ($urls as $url) {
            if (!str_starts_with($url, 'https://') || mb_strlen($url) > 1000) {
                continue;
            }
            $statement->execute(['product_id' => $productId, 'path' => $url, 'sort' => ++$sort, 'source_url' => $url]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function images(int $productId): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, path, sort FROM product_images WHERE product_id = :product_id ORDER BY sort, id',
        );
        $statement->execute(['product_id' => $productId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $images = [];
        foreach ($rows as $row) {
            $images[] = [
                'id' => (int) $row['id'],
                'url' => '/' . ltrim((string) $row['path'], '/'),
                'sort' => (int) $row['sort'],
            ];
        }

        return $images;
    }
}
