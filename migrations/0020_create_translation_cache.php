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
 * İDEMPOTENT (PM şartı — K23 istisnasının bedeli): kural "1 migration = 1 DDL"dir;
 * PM kararıyla İE#13 tek migration'la kapanır. İki DDL'li dosyada ikincisi düşerse
 * MySQL örtük commit yaptığı için ilk DDL geri ALINMAZ ama defter kaydı da YAZILMAZ;
 * tekrar koşumda düz bir CREATE "tablo zaten var" ile kilitlenirdi. Bu yüzden:
 *   • CREATE TABLE IF NOT EXISTS,
 *   • ALTER yalnızca kolon YOKSA (şema sorgusuyla denetlenir).
 * Sonuç: dosya iki kez çağrılsa da hata vermez (kanıt: Migration0020IdempotentTest).
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
            CREATE TABLE IF NOT EXISTS translation_cache (
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
        // MySQL'in "ADD COLUMN IF NOT EXISTS" desteği sürüme bağlıdır; taşınabilir
        // olması için varlık ŞEMA SORGUSUYLA denetlenir.
        if (!$this->kolonVar($pdo, 'products', 'price_target_try')) {
            $pdo->exec('ALTER TABLE products ADD COLUMN price_target_try DECIMAL(12,2) NULL AFTER price_ddp_usd');
        }
    }

    /** Kolon var mı? MySQL'de information_schema, SQLite'ta pragma ile (Migrator ile aynı desen). */
    private function kolonVar(PDO $pdo, string $tablo, string $kolon): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?');
            $statement->execute([$tablo, $kolon]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $statement->execute([$tablo, $kolon]);

        return (int) $statement->fetchColumn() > 0;
    }
};
