<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * LİSTE ŞABLONLARI — `list_templates` (0039; V3-C Blok E).
 *
 * Şablon, bir listenin ÜRÜN KÜMESİNİN dondurulmuş kopyasıdır (`urun_json`):
 * "her sezon aynı 40 ürün" işi için tekrar tekrar liste kopyalamak yerine
 * bir kez şablon alınır, her seferinde ondan taslak açılır. Şablon listeye
 * BAĞLI DEĞİLDİR: kaynak liste silinse de şablon durur (`kaynak_list_id`
 * yalnız provenance).
 *
 * `kullanim_sayisi` / `son_kullanim_at` sıralamayı besler: en çok kullanılan
 * şablon üstte. Fiyatlar şablonda DİZE olarak durur (K14).
 */
final class SablonRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $st = $this->connection->pdo()->query(
            'SELECT id, ad, aciklama, urun_json, kaynak_list_id, kullanim_sayisi, son_kullanim_at, created_at, updated_at
             FROM list_templates ORDER BY kullanim_sayisi DESC, son_kullanim_at DESC, id DESC',
        );

        /** @var list<array<string, mixed>> $rows */
        $rows = $st === false ? [] : ($st->fetchAll() ?: []);

        return $rows;
    }

    /** @return ?array<string, mixed> */
    public function find(int $id): ?array
    {
        $st = $this->connection->pdo()->prepare('SELECT * FROM list_templates WHERE id = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<array<string, mixed>> $urunler
     */
    public function create(string $ad, ?string $aciklama, array $urunler, ?int $kaynakListId, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $st = $pdo->prepare(
            'INSERT INTO list_templates (ad, aciklama, urun_json, kaynak_list_id, kullanim_sayisi, son_kullanim_at, created_at, updated_at)
             VALUES (:ad, :aciklama, :urunler, :kaynak, 0, NULL, :created, :updated)',
        );
        $st->execute([
            'ad' => $ad,
            'aciklama' => $aciklama,
            'urunler' => json_encode($urunler, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'kaynak' => $kaynakListId,
            'created' => Dates::toStorage($now),
            'updated' => Dates::toStorage($now),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $ad, ?string $aciklama, DateTimeImmutable $now): void
    {
        $st = $this->connection->pdo()->prepare('UPDATE list_templates SET ad = :ad, aciklama = :aciklama, updated_at = :now WHERE id = :id');
        $st->execute(['ad' => $ad, 'aciklama' => $aciklama, 'now' => Dates::toStorage($now), 'id' => $id]);
    }

    /** Kullanım sayacı: şablondan liste açıldığında. */
    public function kullanildi(int $id, DateTimeImmutable $now): void
    {
        $st = $this->connection->pdo()->prepare(
            'UPDATE list_templates SET kullanim_sayisi = kullanim_sayisi + 1, son_kullanim_at = :now, updated_at = :now2 WHERE id = :id',
        );
        $st->execute(['now' => Dates::toStorage($now), 'now2' => Dates::toStorage($now), 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->connection->pdo()->prepare('DELETE FROM list_templates WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Şablondaki ürünler (JSON çözülmüş).
     *
     * @param  array<string, mixed>       $row
     * @return list<array<string, mixed>>
     */
    public static function urunler(array $row): array
    {
        $veri = json_decode((string) ($row['urun_json'] ?? '[]'), true);
        if (!is_array($veri)) {
            return [];
        }

        return array_values(array_filter($veri, 'is_array'));
    }
}
