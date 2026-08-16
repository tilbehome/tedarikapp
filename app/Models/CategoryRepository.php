<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;

/**
 * categories tablosu (docs/04 §2). Ürünler kategoriye `category_id` ile bağlanır;
 * serbest metin kategori KABUL EDİLMEZ (§2d).
 */
final class CategoryRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array{id: int, name: string, sort: int, product_count: int}> */
    public function all(): array
    {
        $statement = $this->connection->pdo()->query(
            'SELECT c.id, c.name, c.sort,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
             FROM categories c
             ORDER BY c.sort, c.name',
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'sort' => (int) $row['sort'],
                'product_count' => (int) $row['product_count'],
            ];
        }

        return $categories;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->pdo()->prepare('SELECT id, name, sort FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByName(string $name, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT id, name, sort FROM categories WHERE name = :name';
        $params = ['name' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude';
            $params['exclude'] = $excludeId;
        }

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(string $name, int $sort): int
    {
        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare('INSERT INTO categories (name, sort) VALUES (:name, :sort)');
        $statement->execute(['name' => $name, 'sort' => $sort]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): void
    {
        $assignments = [];
        $params = ['id' => $id];
        foreach (['name', 'sort'] as $column) {
            if (array_key_exists($column, $fields)) {
                $assignments[] = sprintf('%s = :%s', $column, $column);
                $params[$column] = $fields[$column];
            }
        }
        if ($assignments === []) {
            return;
        }

        $statement = $this->connection->pdo()->prepare(
            sprintf('UPDATE categories SET %s WHERE id = :id', implode(', ', $assignments)),
        );
        $statement->execute($params);
    }

    public function delete(int $id): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /** Kullanımda olan kategori silinemez (docs/10 §7 → 409/422). */
    public function productCount(int $id): int
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COUNT(*) AS total FROM products WHERE category_id = :id AND deleted_at IS NULL',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? (int) $row['total'] : 0;
    }

    public function maxSort(): int
    {
        $statement = $this->connection->pdo()->query('SELECT COALESCE(MAX(sort), 0) AS max_sort FROM categories');
        $row = $statement === false ? false : $statement->fetch();

        return is_array($row) ? (int) $row['max_sort'] : 0;
    }
}
