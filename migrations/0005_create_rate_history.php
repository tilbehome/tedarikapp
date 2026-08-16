<?php

declare(strict_types=1);

/**
 * rate_history — kur tarihçesi (docs/04 §2).
 * Kur alanı DECIMAL(12,4); para/kur asla float değildir (K14/K24).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE rate_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                currency VARCHAR(8) NOT NULL,
                rate DECIMAL(12,4) NOT NULL,
                set_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
