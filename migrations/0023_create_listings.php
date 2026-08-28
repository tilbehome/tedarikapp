<?php

declare(strict_types=1);

/**
 * listings — ÜRÜN ≠ İLAN (İE#20 C2).
 *
 * BUGÜNKÜ SORUN: `products` satırı iki ayrı şeyi birden taşıyor. Bir kısmı ürünün
 * KENDİSİNE ait (ad, kategori, not, miktar, durum), bir kısmı ise onu bulduğumuz
 * İLANA ait (platform, ilan numarası, adres, satıcı, fiyat kademeleri, ham veri).
 * İkisi aynı satırda olduğu sürece şu üç şey yapılamaz:
 *
 *   • aynı ürünü İKİ FARKLI platformda karşılaştırmak (hangi ilan daha ucuz?),
 *   • bir ilan kaybolduğunda (satıcı sildi) ürünü korumak,
 *   • ilan sinyallerini (satış adedi, değerlendirme, satıcı karnesi) zamanla
 *     izlemek — skor (C6) bunlara dayanır ve ürünün kendisine ait DEĞİLDİR.
 *
 * Bu migration İLAN tarafını ayırır. `products` tablosuna DOKUNMAZ: kolonlar
 * yerinde kalır, veri KOPYALANIR. Bu bilinçli bir seçimdir — göç tamamen
 * TOPLAMALIDIR (additive), dolayısıyla geri dönüş planı "yeni tabloları yok say"
 * kadar basittir; hiçbir kaydı silmeyen bir göçün geri alınacak bir yanı yoktur.
 *
 * ALAN YOKSA "—": platformdan gelmeyen sinyal NULL kalır ve arayüzde "—" basılır;
 * uydurma değer yazılmaz (aynı disiplin: menşe, skor).
 *
 * Fiyat kademeleri ayrı tabloda (`listing_price_tiers`): 1688'de "100+ adet ¥8,
 * 500+ adet ¥7" gibi kademeler vardır ve bunları JSON'a gömmek sorgulanamaz
 * kılar — "miktarıma göre birim fiyat" hesabı SQL'de yapılabilmelidir.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if (!$this->tabloVar($pdo, 'listings')) {
            $pdo->exec($sqlite
                ? <<<'SQL'
                    CREATE TABLE listings (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        product_id INTEGER NOT NULL,
                        platform_id INTEGER NULL,
                        platform_kod VARCHAR(30) NULL,
                        external_id VARCHAR(100) NULL,
                        url VARCHAR(1000) NULL,
                        baslik_orijinal VARCHAR(500) NULL,
                        satici_ad VARCHAR(200) NULL,
                        satici_url VARCHAR(1000) NULL,
                        satici_yil INT NULL,
                        satici_puan DECIMAL(4,2) NULL,
                        satis_adedi INT NULL,
                        degerlendirme_adedi INT NULL,
                        degerlendirme_puani DECIMAL(4,2) NULL,
                        yanit_orani DECIMAL(5,2) NULL,
                        moq INT NULL,
                        birim_fiyat DECIMAL(12,4) NULL,
                        para_birimi VARCHAR(3) NULL,
                        ham_veri TEXT NULL,
                        yakalandi_at DATETIME NULL,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL
                    )
                    SQL
                : <<<'SQL'
                    CREATE TABLE listings (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        product_id BIGINT UNSIGNED NOT NULL,
                        platform_id BIGINT UNSIGNED NULL,
                        platform_kod VARCHAR(30) NULL,
                        external_id VARCHAR(100) NULL,
                        url VARCHAR(1000) NULL,
                        baslik_orijinal VARCHAR(500) NULL,
                        satici_ad VARCHAR(200) NULL,
                        satici_url VARCHAR(1000) NULL,
                        satici_yil INT UNSIGNED NULL,
                        satici_puan DECIMAL(4,2) NULL,
                        satis_adedi INT UNSIGNED NULL,
                        degerlendirme_adedi INT UNSIGNED NULL,
                        degerlendirme_puani DECIMAL(4,2) NULL,
                        yanit_orani DECIMAL(5,2) NULL,
                        moq INT UNSIGNED NULL,
                        birim_fiyat DECIMAL(12,4) NULL,
                        para_birimi VARCHAR(3) NULL,
                        ham_veri JSON NULL,
                        yakalandi_at DATETIME NULL,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL,
                        KEY idx_listings_product (product_id),
                        KEY idx_listings_platform (platform_kod, external_id),
                        CONSTRAINT fk_listings_product FOREIGN KEY (product_id)
                            REFERENCES products (id) ON DELETE CASCADE,
                        CONSTRAINT fk_listings_platform FOREIGN KEY (platform_id)
                            REFERENCES platforms (id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL);
        }

        if (!$this->tabloVar($pdo, 'listing_price_tiers')) {
            $pdo->exec($sqlite
                ? <<<'SQL'
                    CREATE TABLE listing_price_tiers (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        listing_id INTEGER NOT NULL,
                        min_adet INT NOT NULL,
                        birim_fiyat DECIMAL(12,4) NOT NULL,
                        para_birimi VARCHAR(3) NOT NULL DEFAULT 'CNY'
                    )
                    SQL
                : <<<'SQL'
                    CREATE TABLE listing_price_tiers (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        listing_id BIGINT UNSIGNED NOT NULL,
                        min_adet INT UNSIGNED NOT NULL,
                        birim_fiyat DECIMAL(12,4) NOT NULL,
                        para_birimi VARCHAR(3) NOT NULL DEFAULT 'CNY',
                        KEY idx_tiers_listing (listing_id, min_adet),
                        CONSTRAINT fk_tiers_listing FOREIGN KEY (listing_id)
                            REFERENCES listings (id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL);
        }
    }

    private function tabloVar(PDO $pdo, string $tablo): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
            $statement->execute([$tablo]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        );
        $statement->execute([$tablo]);

        return (int) $statement->fetchColumn() > 0;
    }
};
