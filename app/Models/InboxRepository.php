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
     * Bekleyen kuyruk (pending + error) — yeniden eskiye.
     *
     * @return list<array<string, mixed>>
     */
    public function queue(): array
    {
        $statement = $this->connection->pdo()->query(
            "SELECT id, capture_id, status, platform, external_id, name, price_yuan, image_url, url, error_note, created_at
             FROM inbox_items WHERE status IN ('pending', 'error') ORDER BY created_at DESC, id DESC",
        );

        /** @var list<array<string, mixed>> */
        return $statement === false ? [] : ($statement->fetchAll() ?: []);
    }

    public function pendingCount(): int
    {
        $statement = $this->connection->pdo()->query(
            "SELECT COUNT(*) FROM inbox_items WHERE status IN ('pending', 'error')",
        );

        return $statement === false ? 0 : (int) $statement->fetchColumn();
    }

    public function markAssigned(int $id, int $productId, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            "UPDATE inbox_items SET status = 'assigned', assigned_product_id = :product_id, assigned_at = :at WHERE id = :id",
        );
        $statement->execute(['product_id' => $productId, 'at' => Dates::toStorage($now), 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM inbox_items WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
