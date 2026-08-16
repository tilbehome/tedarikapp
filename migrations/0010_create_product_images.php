<?php

declare(strict_types=1);

/**
 * product_images — ürünün ek görselleri (docs/04 §2).
 *
 * `path` sunucudaki dosya yoludur, dış URL değil: görseller yakalandığı an indirilir (K6),
 * böylece 1688 linki ölse veya hotlink engellense bile liste bozulmaz.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE product_images (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                path VARCHAR(255) NOT NULL,
                sort INT UNSIGNED NOT NULL DEFAULT 0,
                CONSTRAINT fk_product_images_product FOREIGN KEY (product_id)
                    REFERENCES products (id) ON DELETE CASCADE,
                KEY idx_product_images_product (product_id, sort)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
