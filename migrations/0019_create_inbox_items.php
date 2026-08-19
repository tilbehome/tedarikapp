<?php

declare(strict_types=1);

/**
 * inbox_items — eklenti yakalamalarının kuyruğu (İE#11 Faz 3, docs/04 §2c v2).
 *
 * `capture_id` UNIQUE = idempotans (K25): eklentinin tekrar denemeleri çift kayıt
 * açamaz. `payload_json` üç bloklu v2 yükünün TAMAMIDIR (raw dahil) — normalized
 * alan bozuk çıksa bile veri kaybolmaz (`status=error`). Liste kolonları
 * (name/price/image) kuyruğun panelde payload açılmadan listelenebilmesi içindir.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE inbox_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                capture_id CHAR(36) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                platform VARCHAR(30) NOT NULL,
                external_id VARCHAR(100) NULL,
                name VARCHAR(300) NULL,
                price_yuan DECIMAL(12,4) NULL,
                image_url VARCHAR(1000) NULL,
                url VARCHAR(1000) NULL,
                payload_json LONGTEXT NOT NULL,
                error_note VARCHAR(500) NULL,
                assigned_product_id BIGINT UNSIGNED NULL,
                assigned_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY idx_inbox_capture (capture_id),
                KEY idx_inbox_status (status, created_at),
                KEY idx_inbox_platform_external (platform, external_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
