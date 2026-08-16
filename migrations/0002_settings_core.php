<?php

declare(strict_types=1);

/**
 * Ayar çekirdeği: settings + rate_history + categories + activity_log (docs/04 §2).
 * Kur alanı DECIMAL(12,4) — para/kur asla float değildir (K14).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE settings (
                `key` VARCHAR(64) NOT NULL PRIMARY KEY,
                value TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE rate_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                currency VARCHAR(8) NOT NULL,
                rate DECIMAL(12,4) NOT NULL,
                set_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE categories (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                sort INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE activity_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(32) NOT NULL,
                entity_id BIGINT UNSIGNED NULL,
                action VARCHAR(64) NOT NULL,
                detail TEXT NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_activity_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
