<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * exports tablosu erişimi (K25 — export gerçek anlık görüntüdür, Faz 2 / İE#10).
 *
 * Dosya diske YAZILMAZ (K33/K44): kayıt yalnız snapshot_json + üretilen dosyanın
 * sha256/boyutunu tutar; indirme her seferinde snapshot'tan ANLIK üretilir.
 */
final class ExportRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array<string, mixed> $snapshot */
    public function record(
        int $listId,
        string $format,
        array $snapshot,
        string $sha256,
        int $fileSize,
        int $listRevision,
        DateTimeImmutable $now,
    ): int {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO exports (list_id, format, snapshot_json, sha256, file_size, status, list_revision, created_at)
             VALUES (:list_id, :format, :snapshot, :sha256, :file_size, :status, :list_revision, :created_at)',
        );
        $statement->execute([
            'list_id' => $listId,
            'format' => $format,
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sha256' => $sha256,
            'file_size' => $fileSize,
            'status' => 'ready',
            'list_revision' => $listRevision,
            'created_at' => Dates::toStorage($now),
        ]);

        return (int) $this->connection->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, list_id, format, snapshot_json, sha256, file_size, status, list_revision, created_at
             FROM exports WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Liste detayındaki export geçmişi — snapshot GÖVDESİZ (liste ekranı şişmesin).
     *
     * @return list<array<string, mixed>>
     */
    public function forList(int $listId): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, format, sha256, file_size, status, list_revision, created_at
             FROM exports WHERE list_id = :list_id ORDER BY created_at DESC, id DESC',
        );
        $statement->execute(['list_id' => $listId]);

        /** @var list<array<string, mixed>> */
        return $statement->fetchAll() ?: [];
    }

    /**
     * Listenin şimdiye kadarki çıktı sayısı — İE#13 F7 revizyon harfi bundan türer
     * (ilk çıktı Rev A, ikincisi Rev B…). Silinmiş kayıt yoktur; sayaç geri gitmez.
     */
    public function countForList(int $listId): int
    {
        $statement = $this->connection->pdo()->prepare('SELECT COUNT(*) FROM exports WHERE list_id = :list_id');
        $statement->execute(['list_id' => $listId]);

        return (int) $statement->fetchColumn();
    }
}
