<?php

declare(strict_types=1);

/**
 * products — bir ürün her zaman bir listeye aittir (docs/02 M3, docs/04 §2).
 *
 * Para alanları DECIMAL(12,4) (K24 — birim fiyatlar 4 hane); TL karşılıkları
 * SAKLANMAZ, listenin kilitli kuruyla hesaplanır.
 *
 * `units_per_carton`: 1688'de yapılandırılmış "koli içi adet" alanı YOKTUR
 * (docs/arastirma parser raporu §B.4) — bu yüzden NULL ve admin girer.
 *
 * Tekrar kontrolü `platform + external_id` çifti üzerinden yapılır (K25); benzersizlik
 * ZORLANMAZ, aynı ürün bilerek iki listede olabilir — indeks yalnızca uyarı içindir.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE products (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                list_id BIGINT UNSIGNED NOT NULL,
                sort_no INT UNSIGNED NOT NULL DEFAULT 0,
                category_id BIGINT UNSIGNED NULL,
                platform VARCHAR(16) NULL,
                external_id VARCHAR(64) NULL,
                name VARCHAR(300) NOT NULL,
                name_original VARCHAR(500) NULL,
                detail TEXT NULL,
                url VARCHAR(1000) NULL,
                vendor_name VARCHAR(200) NULL,
                vendor_url VARCHAR(1000) NULL,
                sku_selection JSON NULL,
                sku_matrix JSON NULL,
                main_image VARCHAR(255) NULL,
                video_url VARCHAR(1000) NULL,
                qty INT UNSIGNED NOT NULL DEFAULT 1,
                price_yuan DECIMAL(12,4) NOT NULL DEFAULT 0,
                price_ddp_usd DECIMAL(12,4) NOT NULL DEFAULT 0,
                units_per_carton INT UNSIGNED NULL,
                tracking_no VARCHAR(100) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'to_order',
                note TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_products_list (list_id),
                KEY idx_products_status (status),
                KEY idx_products_external (platform, external_id),
                KEY idx_products_deleted (deleted_at),
                KEY idx_products_sort (list_id, sort_no),
                CONSTRAINT fk_products_list FOREIGN KEY (list_id)
                    REFERENCES lists (id) ON DELETE CASCADE,
                CONSTRAINT fk_products_category FOREIGN KEY (category_id)
                    REFERENCES categories (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
