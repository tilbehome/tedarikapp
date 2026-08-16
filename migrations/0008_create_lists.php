<?php

declare(strict_types=1);

/**
 * lists — sipariş listeleri, sistemin ana birimi (K3, docs/04 §2).
 *
 * Kur alanları DECIMAL(12,4) ve listeye KİLİTLENİR (K4): `sent` durumuna geçişte
 * `rate_locked_at` yazılır ve kurlar bir daha değişmez. TL değerleri saklanmaz,
 * her yerde kilitli kurla hesaplanır (K24).
 *
 * `revision`: ürün/fiyat/adet/sıra değişiminde +1 — "çıktı güncel değil" rozetinin
 * dayanağıdır (K25); `updated_at` karşılaştırması not düzenlemede yanlış pozitif veriyordu.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE lists (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) NOT NULL,
                period VARCHAR(50) NULL,
                supplier_name VARCHAR(200) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                note TEXT NULL,
                visibility VARCHAR(16) NOT NULL DEFAULT 'active',
                yuan_rate DECIMAL(12,4) NOT NULL,
                usd_rate DECIMAL(12,4) NOT NULL,
                rate_locked_at DATETIME NULL,
                revision INT UNSIGNED NOT NULL DEFAULT 0,
                share_token_hash CHAR(64) NULL,
                share_token_prefix VARCHAR(12) NULL,
                share_expires_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,
                UNIQUE KEY idx_lists_share_token (share_token_hash),
                KEY idx_lists_visibility (visibility),
                KEY idx_lists_status (status),
                KEY idx_lists_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
