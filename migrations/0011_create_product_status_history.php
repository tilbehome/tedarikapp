<?php

declare(strict_types=1);

/**
 * product_status_history — ürün durum tarihçesi (K25).
 *
 * Durum geçişleri activity_log'a GÖMÜLMEZ: activity_log serbest metinli genel bir
 * denetim kaydıdır; durum tarihçesi ise sorgulanabilir olmalı ("bu ürün ne zaman yola çıktı",
 * "geçen ay kaç ürün iptal edildi"). Ayrı tablo bunu tek sorguya indirir.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE product_status_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                from_status VARCHAR(16) NULL,
                to_status VARCHAR(16) NOT NULL,
                actor_type VARCHAR(16) NOT NULL DEFAULT 'admin',
                actor_id BIGINT UNSIGNED NULL,
                changed_at DATETIME NOT NULL,
                request_id CHAR(26) NULL,
                CONSTRAINT fk_psh_product FOREIGN KEY (product_id)
                    REFERENCES products (id) ON DELETE CASCADE,
                KEY idx_psh_product (product_id, changed_at),
                KEY idx_psh_to_status (to_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
