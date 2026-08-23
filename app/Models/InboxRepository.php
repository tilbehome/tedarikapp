<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * inbox_items erişimi (İE#11 Faz 3 — docs/04 §2c v2).
 * Kuyruk mantığı: pending/error → panelde listelenir; assigned → üründür, kuyruğa dönmez.
 */
final class InboxRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null capture_id idempotansı: varsa ilk kaydı döndür. */
    public function findByCaptureId(string $captureId): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT * FROM inbox_items WHERE capture_id = :capture_id',
        );
        $statement->execute(['capture_id' => $captureId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->pdo()->prepare('SELECT * FROM inbox_items WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function create(array $fields, DateTimeImmutable $now): int
    {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO inbox_items (capture_id, status, platform, external_id, name, price_yuan,
                image_url, url, payload_json, error_note, created_at)
             VALUES (:capture_id, :status, :platform, :external_id, :name, :price_yuan,
                :image_url, :url, :payload_json, :error_note, :created_at)',
        );
        $statement->execute([
            'capture_id' => $fields['capture_id'],
            'status' => $fields['status'] ?? 'pending',
            'platform' => $fields['platform'] ?? 'bilinmiyor',
            'external_id' => $fields['external_id'] ?? null,
            'name' => $fields['name'] ?? null,
            'price_yuan' => $fields['price_yuan'] ?? null,
            'image_url' => $fields['image_url'] ?? null,
            'url' => $fields['url'] ?? null,
            'payload_json' => $fields['payload_json'],
            'error_note' => $fields['error_note'] ?? null,
            'created_at' => Dates::toStorage($now),
        ]);

        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * Bekleyen kuyruk (pending + error) — yeniden eskiye, filtreli ve SAYFALI (İE#13 B5).
     *
     * Filtreler istemciden gelir ama sorguya PARAMETRE olarak girer (birleştirme yok).
     * Tarih aralığı gün bazlıdır: `to` günün SONUNU kapsar (kullanıcı "19 Ağu" derken
     * o günü dışarıda bırakmayı beklemez).
     *
     * @param array{q?: string, platform?: string, from?: string, to?: string} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function queue(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        [$where, $params] = $this->queryFor($filters);
        $statement = $this->connection->pdo()->prepare(
            "SELECT id, capture_id, status, platform, external_id, name, price_yuan, image_url, url, error_note, created_at
             FROM inbox_items WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset",
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> */
        return $statement->fetchAll() ?: [];
    }

    /** @param array{q?: string, platform?: string, from?: string, to?: string} $filters */
    public function countQueue(array $filters = []): int
    {
        [$where, $params] = $this->queryFor($filters);
        $statement = $this->connection->pdo()->prepare("SELECT COUNT(*) FROM inbox_items WHERE {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** Kuyrukta görünen platformlar — filtre menüsü uydurma değil, VERİDEN doldurulur. */
    /** @return list<string> */
    public function platforms(): array
    {
        $statement = $this->connection->pdo()->query(
            "SELECT DISTINCT platform FROM inbox_items WHERE status IN ('pending', 'error') ORDER BY platform",
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<string> */
        return array_map(static fn (array $row): string => (string) $row['platform'], $statement->fetchAll() ?: []);
    }

    /**
     * @param array{q?: string, platform?: string, from?: string, to?: string} $filters
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function queryFor(array $filters): array
    {
        $where = "status IN ('pending', 'error')";
        $params = [];

        $q = trim($filters['q'] ?? '');
        if ($q !== '') {
            // LIKE joker karakterleri kaçırılır: "%" arayan kullanıcı tüm kuyruğu görmemeli.
            // Kaçış karakteri '!' — ters bölü MySQL ve SQLite'ta FARKLI yorumlanır, '!' ikisinde de düz karakterdir.
            $where .= " AND name LIKE :q ESCAPE '!'";
            $params['q'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        }

        $platform = trim($filters['platform'] ?? '');
        if ($platform !== '') {
            $where .= ' AND platform = :platform';
            $params['platform'] = $platform;
        }

        $from = trim($filters['from'] ?? '');
        if ($from !== '') {
            $where .= ' AND created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }

        $to = trim($filters['to'] ?? '');
        if ($to !== '') {
            $where .= ' AND created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        return [$where, $params];
    }

    public function pendingCount(): int
    {
        $statement = $this->connection->pdo()->query(
            "SELECT COUNT(*) FROM inbox_items WHERE status IN ('pending', 'error')",
        );

        return $statement === false ? 0 : (int) $statement->fetchColumn();
    }

    /**
     * SAHİPLENME (İE#19 G6) — kuyruk satırını "işleniyor" olarak kilitler.
     *
     * Satır YALNIZCA hâlâ `pending`/`error` iken sahiplenilir; koşullu UPDATE'in
     * etkilediği satır sayısı yarışın kimin kazandığını söyler. İki eşzamanlı
     * "listeye taşı" isteğinden kaybeden `false` alır ve ürün YAZMAZ — eskiden
     * ikisi de yazıyor, aynı yakalamadan iki ürün doğuyordu.
     */
    public function claim(int $id, DateTimeImmutable $now): bool
    {
        $statement = $this->connection->pdo()->prepare(
            "UPDATE inbox_items SET status = 'assigned', assigned_at = :at
             WHERE id = :id AND status IN ('pending', 'error')",
        );
        $statement->execute(['at' => Dates::toStorage($now), 'id' => $id]);

        return $statement->rowCount() === 1;
    }

    public function markAssigned(int $id, int $productId, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            "UPDATE inbox_items SET status = 'assigned', assigned_product_id = :product_id, assigned_at = :at WHERE id = :id",
        );
        $statement->execute(['product_id' => $productId, 'at' => Dates::toStorage($now), 'id' => $id]);
    }

    /**
     * GERİ ALMA (İE#21 B4 · E2E-PNL-19): atanmış kaydı yeniden BEKLEYENE çevirir.
     *
     * Ürün bağı da kopar — kayıt geri geldiğinde artık var olmayan bir ürüne
     * işaret ediyorsa, panel "atandı ama ürünü yok" gibi tutarsız bir hâl gösterir.
     */
    public function markPending(int $id): void
    {
        $statement = $this->connection->pdo()->prepare(
            // `inbox_items`te `updated_at` YOKTUR (0019); zaman damgası
            // `assigned_at` alanındadır ve geri almada temizlenir.
            "UPDATE inbox_items SET status = 'pending', assigned_product_id = NULL, assigned_at = NULL
             WHERE id = :id AND status = 'assigned'",
        );
        $statement->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM inbox_items WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * Toplu silme (İE#13 B1) — yalnız kuyruktaki (pending/error) kayıtlar silinir;
     * `assigned` kayıt zaten üründür ve buradan silinemez (ürün silme çöp kutusudur).
     *
     * @param list<int> $ids
     *
     * @return int silinen kayıt sayısı
     */
    public function deleteMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->connection->pdo()->prepare(
            "DELETE FROM inbox_items WHERE status IN ('pending', 'error') AND id IN ({$placeholders})",
        );
        $statement->execute($ids);

        return $statement->rowCount();
    }
}
