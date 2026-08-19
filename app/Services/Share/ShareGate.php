<?php

declare(strict_types=1);

namespace App\Services\Share;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * Paylaşım sayfası hız sınırı (İE#10 Blok 4 — K34/K51).
 *
 * Token 256-bit rastgeledir; tahmin pratikte imkânsızdır ama enumeration denemesi
 * log gürültüsü ve kaynak tüketimidir. Kural: aynı IP'den pencere içinde çok sayıda
 * GEÇERSİZ token denemesi gelirse o IP'ye (geçerli token dahil) sabit 404 verilir —
 * yanıt hiçbir durumda "token yanlış/az kaldı" ayrımı sızdırmaz (sabit yanıt ilkesi).
 * Sayaç activity_log'dadır (kilit/log ile aynı veritabanı, disk yok). Token'ın kendisi
 * ASLA loglanmaz — yalnız ilk 8 hanesi (tanıma amaçlı, K34 hash modeliyle uyumlu).
 */
final class ShareGate
{
    public const ACTION_INVALID = 'share_invalid_token';
    private const MAX_INVALID = 30;
    private const WINDOW_MINUTES = 10;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** Bu IP pencere içindeki geçersiz deneme sınırını aştı mı? */
    public function blocked(string $ip, DateTimeImmutable $now): bool
    {
        $windowStart = $now->modify(sprintf('-%d minutes', self::WINDOW_MINUTES));
        $statement = $this->connection->pdo()->prepare(
            'SELECT COUNT(*) FROM activity_log
             WHERE action = :action AND ip = :ip AND created_at >= :window_start',
        );
        $statement->execute([
            'action' => self::ACTION_INVALID,
            'ip' => $ip,
            'window_start' => Dates::toStorage($windowStart),
        ]);

        return (int) $statement->fetchColumn() >= self::MAX_INVALID;
    }

    /** Geçersiz token denemesini kaydeder — token değeri DEĞİL, yalnız ilk 8 hane. */
    public function recordInvalid(string $ip, string $tokenPrefix, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO activity_log (entity_type, entity_id, action, detail, ip, actor_type, actor_id, created_at)
             VALUES (:entity_type, NULL, :action, :detail, :ip, :actor_type, NULL, :created_at)',
        );
        $statement->execute([
            'entity_type' => 'share',
            'action' => self::ACTION_INVALID,
            'detail' => 'önek:' . substr($tokenPrefix, 0, 8),
            'ip' => $ip,
            'actor_type' => 'visitor',
            'created_at' => Dates::toStorage($now),
        ]);
    }
}
