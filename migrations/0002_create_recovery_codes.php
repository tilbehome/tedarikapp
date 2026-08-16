<?php

declare(strict_types=1);

/**
 * recovery_codes — 2FA kurtarma kodları: hash'li, TEK kullanımlık (docs/04 §2, K16).
 * E-posta kapalı olduğundan (K8) telefon kaybında tek giriş yolu budur.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
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
    }
};
