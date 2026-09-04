<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * FİRMALAR (`suppliers`, 0036) — V3-C Aşama 2.1.
 *
 * Firma hesabı YOKTUR (K103/#15): firma bir KAYITTIR, bir kullanıcı değil.
 * Teklif turu `liste × firma × tur` üçlüsüne bağlanır; firma kaydı o üçlünün
 * "kim" ayağıdır — ad, varsayılan portal dili, varsayılan geçerlilik.
 *
 * Kapsam bilinçli olarak dar: Firmalar & Kişiler modülü (yol haritası §7.11)
 * V3-C'nin bu aşamasında değil; burada yalnız tur açmaya yetecek çekirdek var.
 */
final class FirmaRepository
{
    private const KOLONLAR = 'id, ad, tip, ulke, platform, varsayilan_dil, varsayilan_gecerlilik_gun,
        whatsapp, eposta, notlar, arsivlendi_at, created_at, updated_at';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::KOLONLAR . ' FROM suppliers WHERE id = :id AND arsivlendi_at IS NULL',
        );
        $statement->execute(['id' => $id]);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $statement = $this->connection->pdo()->query(
            'SELECT ' . self::KOLONLAR . ' FROM suppliers WHERE arsivlendi_at IS NULL ORDER BY ad, id',
        );

        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $statement === false ? [] : ($statement->fetchAll() ?: []);

        return $satirlar;
    }

    /**
     * @param array{ad: string, tip?: string, ulke?: string|null, platform?: string|null,
     *              varsayilan_dil?: string, varsayilan_gecerlilik_gun?: int|null,
     *              whatsapp?: string|null, eposta?: string|null, notlar?: string|null} $veri
     */
    public function create(array $veri, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO suppliers
                (ad, tip, ulke, platform, varsayilan_dil, varsayilan_gecerlilik_gun,
                 whatsapp, eposta, notlar, created_at, updated_at)
             VALUES
                (:ad, :tip, :ulke, :platform, :dil, :gecerlilik, :whatsapp, :eposta, :notlar, :olusma_at, :guncelleme_at)',
        );
        $zaman = Dates::toStorage($now);
        $statement->execute([
            'ad' => $veri['ad'],
            'tip' => $veri['tip'] ?? 'uretici',
            'ulke' => $veri['ulke'] ?? null,
            'platform' => $veri['platform'] ?? null,
            'dil' => $veri['varsayilan_dil'] ?? 'zh',
            'gecerlilik' => $veri['varsayilan_gecerlilik_gun'] ?? null,
            'whatsapp' => $veri['whatsapp'] ?? null,
            'eposta' => $veri['eposta'] ?? null,
            'notlar' => $veri['notlar'] ?? null,
            'olusma_at' => $zaman,
            'guncelleme_at' => $zaman,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
