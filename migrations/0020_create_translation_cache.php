<?php

declare(strict_types=1);

/**
 * 0020 — İE#13'ün TEK migration'ı (K23): iki iş bir arada.
 *
 *  (1) translation_cache — ZH→TR başlık ÖNERİLERİ için önbellek (Blok C, K54).
 *  (2) products.price_target_try — hedef satış fiyatı (Blok F5): yalnız İÇ KOPYA
 *      çıktısında kâr sütunlarını beslemek içindir; firma kopyasında ASLA basılmaz
 *      ve paylaşım sayfasında GÖRÜNMEZ.
 *
 * K23 notu: kural "1 migration = 1 DDL"dir; PM kararıyla İE#13 tek migration'la
 * kapanır (0020). İki DDL ard arda koşar; ikincisi düşerse defter kaydı YAZILMAZ ve
 * tekrar koşumda ilk CREATE "zaten var" der — bu yüzden ALTER önce IF NOT EXISTS
 * mantığıyla değil, temiz kurulumda TEK SEFER çalışacak biçimde yazılmıştır.
 *
 * Neden önbellek: aynı ürün başlığı Gelen Kutusu'nda, eklentide ve panelde defalarca
 * görünür; dış servise her seferinde gitmek hem yavaş hem de ücretsiz kotayı yakar.
 * `source_hash` = sha256(kaynak dili|hedef dili|metin) → aynı metin ikinci kez sorulmaz.
 *
 * K54: buradaki metin bir ÖNERİDİR. Hiçbir ürün alanına kendiliğinden yazılmaz;
 * kullanıcı "Kullan" demedikçe yalnız gösterilir. RAW/orijinal başlık asla değişmez.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE translation_cache (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_hash CHAR(64) NOT NULL,
                source_lang VARCHAR(10) NOT NULL DEFAULT 'zh',
                target_lang VARCHAR(10) NOT NULL DEFAULT 'tr',
                source_text VARCHAR(1000) NOT NULL,
                suggested_text VARCHAR(1000) NOT NULL,
                provider VARCHAR(40) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY idx_translation_hash (source_hash),
                KEY idx_translation_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        // F5: hedef satış fiyatı — para DECIMAL(12,2) (K14), NULL = hedef girilmemiş.
        $pdo->exec('ALTER TABLE products ADD COLUMN price_target_try DECIMAL(12,2) NULL AFTER price_ddp_usd');
    }
};
