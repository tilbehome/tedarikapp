<?php

declare(strict_types=1);

/**
 * Auth çekirdeği: users + recovery_codes + remember_tokens (docs/04 §2, K16).
 * MySQL/MariaDB hedeflidir; testler ayrı SQLite fikstürleriyle çalışır.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                totp_secret VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE recovery_codes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                used_at DATETIME NULL,
                CONSTRAINT fk_recovery_codes_user FOREIGN KEY (user_id)
                    REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE remember_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                selector VARCHAR(32) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY idx_remember_tokens_selector (selector),
                CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id)
                    REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
