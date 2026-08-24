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
    /** İE#15 A1: oturumsuz indirme erişim kaydı (aynı zamanda hız sayacı). */
    public const ACTION_DOWNLOAD = 'share_download';
    private const MAX_INVALID = 30;
    private const WINDOW_MINUTES = 10;
    /** Token başına saatte en çok indirme (İE#15 A1). */
    private const MAX_DOWNLOAD = 20;
    /** İE#17 G4: taze imza ucu — token başına DAKİKADA en çok bağlantı. */
    public const ACTION_LINK = 'share_link';
    private const MAX_LINK_PER_MINUTE = 12;
    /** İE#18 G6: erişim anahtarı denemesi — token+IP başına DAKİKADA en çok. */
    public const ACTION_ANAHTAR = 'share_key_try';
    /** İE#21 B7: kilit ekranı bu sayıyı kullanıcıya yazar — tek kaynak burası. */
    public const MAX_ANAHTAR_PER_MINUTE = 5;

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

    /**
     * İE#15 A1 — indirme hız sınırı TOKEN başınadır, IP başına değil: firma
     * ofisinden birden çok kişi aynı bağlantıyı açabilir (tek NAT arkasında aynı
     * IP), ama tek bir liste saatte 20 kereden fazla indirilmez.
     */
    public function downloadBlocked(string $tokenPrefix, DateTimeImmutable $now): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COUNT(*) FROM activity_log
             WHERE action = :action AND detail LIKE :prefix AND created_at >= :window_start',
        );
        $statement->execute([
            'action' => self::ACTION_DOWNLOAD,
            'prefix' => 'önek:' . substr($tokenPrefix, 0, 8) . '%',
            'window_start' => Dates::toStorage($now->modify('-1 hour')),
        ]);

        return (int) $statement->fetchColumn() >= self::MAX_DOWNLOAD;
    }

    /**
     * İE#17 G4 — TAZE İMZA ucunun dakikalık sınırı.
     *
     * Uç, imza üretim otomasyonuna dönüşmesin diye sınırlıdır; ama SAATLİK
     * İNDİRME SAYACINI (20) TÜKETMEZ — o yalnız gerçek indirmede işler. Aksi
     * hâlde sayfayı birkaç kez tıklamak firmanın indirme hakkını yerdi.
     */
    public function linkBlocked(string $tokenPrefix, DateTimeImmutable $now): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COUNT(*) FROM activity_log
             WHERE action = :action AND detail LIKE :prefix AND created_at >= :window_start',
        );
        $statement->execute([
            'action' => self::ACTION_LINK,
            'prefix' => 'önek:' . substr($tokenPrefix, 0, 8) . '%',
            'window_start' => Dates::toStorage($now->modify('-1 minute')),
        ]);

        return (int) $statement->fetchColumn() >= self::MAX_LINK_PER_MINUTE;
    }

    /** Üretilen taze imzayı sayaca işler (panel akışında GÖRÜNMEZ — ActivityController süzer). */
    public function recordLink(string $tokenPrefix, string $format, string $dil, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO activity_log (entity_type, entity_id, action, detail, ip, actor_type, actor_id, created_at)
             VALUES (:entity_type, NULL, :action, :detail, NULL, :actor_type, NULL, :created_at)',
        );
        $statement->execute([
            'entity_type' => 'share',
            'action' => self::ACTION_LINK,
            'detail' => 'önek:' . substr($tokenPrefix, 0, 8) . ' · ' . $format . ' · ' . $dil,
            'actor_type' => 'visitor',
            'created_at' => Dates::toStorage($now),
        ]);
    }

    /**
     * İE#18 G6 — ERİŞİM ANAHTARI deneme sınırı: token+IP başına dakikada 5.
     *
     * Sınır TOKEN VE IP birlikte sayılır: aynı listeyi farklı ofislerden açan iki
     * kişi birbirinin hakkını yemez, ama tek bir kaynaktan kaba kuvvet denemesi
     * beş adımda durur. 6 hanelik alfabede (32 karakter) ~1.07 milyar olasılık
     * vardır; dakikada 5 deneme bunu pratikte imkânsız kılar.
     */
    public function anahtarBlocked(string $tokenPrefix, string $ip, DateTimeImmutable $now): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COUNT(*) FROM activity_log
             WHERE action = :action AND ip = :ip AND detail LIKE :prefix AND created_at >= :window_start',
        );
        $statement->execute([
            'action' => self::ACTION_ANAHTAR,
            'ip' => $ip,
            'prefix' => 'önek:' . substr($tokenPrefix, 0, 8) . '%',
            'window_start' => Dates::toStorage($now->modify('-1 minute')),
        ]);

        return (int) $statement->fetchColumn() >= self::MAX_ANAHTAR_PER_MINUTE;
    }

    /** Anahtar denemesini sayaca işler (doğru da olsa yanlış da — sayaç deneme sayar). */
    public function recordAnahtarDeneme(string $tokenPrefix, string $ip, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO activity_log (entity_type, entity_id, action, detail, ip, actor_type, actor_id, created_at)
             VALUES (:entity_type, NULL, :action, :detail, :ip, :actor_type, NULL, :created_at)',
        );
        $statement->execute([
            'entity_type' => 'share',
            'action' => self::ACTION_ANAHTAR,
            'detail' => 'önek:' . substr($tokenPrefix, 0, 8),
            'ip' => $ip,
            'actor_type' => 'visitor',
            'created_at' => Dates::toStorage($now),
        ]);
    }

    /**
     * Erişim kaydı: token ÖNEKİ (tam token asla), biçim, dil, zaman ve KIRPILMIŞ IP.
     */
    public function recordDownload(
        string $tokenPrefix,
        string $format,
        string $dil,
        string $kirpilmisIp,
        DateTimeImmutable $now,
    ): void {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO activity_log (entity_type, entity_id, action, detail, ip, actor_type, actor_id, created_at)
             VALUES (:entity_type, NULL, :action, :detail, :ip, :actor_type, NULL, :created_at)',
        );
        $statement->execute([
            'entity_type' => 'share',
            'action' => self::ACTION_DOWNLOAD,
            'detail' => 'önek:' . substr($tokenPrefix, 0, 8) . ' · ' . $format . ' · ' . $dil,
            'ip' => $kirpilmisIp,
            'actor_type' => 'visitor',
            'created_at' => Dates::toStorage($now),
        ]);
    }
}
