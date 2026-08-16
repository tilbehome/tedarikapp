<?php

declare(strict_types=1);

/**
 * settings — anahtar/değer ayarları: yuan_tl, usd_tl, extension_token_hash… (docs/04 §2).
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
    }
};
