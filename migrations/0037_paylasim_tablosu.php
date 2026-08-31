<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * V3-C BLOK A1 — `shares` TABLOSU (yalnız DDL; göç 0038'de).
 *
 * BUGÜN PAYLAŞIM `lists` KOLONLARINDA: `share_token_hash`, `share_token_prefix`,
 * `share_expires_at`, `share_key_hash`, `share_key_plain`, `share_key_enabled`.
 * Bu model bir listenin TEK bir paylaşımı olabileceğini varsayıyor. V3-C'nin
 * birimi ise `liste × firma × tur` — aynı liste üç firmaya gidebilir ve her
 * birinin AYRI linki, AYRI anahtarı olmalıdır. Kolonlar oldukları yerde
 * kalsaydı ikinci firma birincinin linkini ezerdi.
 *
 * `recipient_type` BU FAZDA AÇILIR ama tek değer alır: `importer`. V3-N'de
 * müşteri ve üretici paylaşımları gelecek; kolonu şimdi açmak, o gün
 * `shares` tablosunu yeniden yazmayı önler. Değer kümesi uygulama katmanında
 * zorlanır (liste/ürün durum makinesiyle aynı desen).
 *
 * K62/K82 DEĞİŞMEZ: erişim anahtarının ÖMRÜ YOKTUR. `expires_at` LİNKİN
 * ömrüdür ve bugün de vardı; anahtar bundan bağımsızdır. Teklif geçerliliği
 * (`supplier_rounds.valid_until`) ÜÇÜNCÜ ve ayrı bir kavramdır — üçü
 * birbirinin yerine geçmez (#28 bağlayıcı çekirdek).
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        if ($this->tabloVar($pdo, 'shares')) {
            return;
        }

        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        $pdo->exec($sqlite
            ? 'CREATE TABLE shares (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                supplier_round_id INTEGER NULL,
                recipient_type TEXT NOT NULL DEFAULT "importer",
                token_hash TEXT NOT NULL,
                token_prefix TEXT NULL,
                key_hash TEXT NULL,
                key_plain TEXT NULL,
                key_enabled INTEGER NOT NULL DEFAULT 1,
                expires_at TEXT NULL,
                revoked_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (token_hash)
            )'
            : 'CREATE TABLE shares (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                list_id BIGINT UNSIGNED NOT NULL,
                supplier_round_id BIGINT UNSIGNED NULL,
                recipient_type VARCHAR(16) NOT NULL DEFAULT "importer",
                token_hash CHAR(64) NOT NULL,
                token_prefix VARCHAR(12) NULL,
                key_hash CHAR(64) NULL,
                key_plain VARCHAR(12) NULL,
                key_enabled TINYINT(1) NOT NULL DEFAULT 1,
                expires_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_share_token (token_hash),
                KEY ix_share_liste (list_id, revoked_at),
                KEY ix_share_tur (supplier_round_id),
                KEY ix_share_alici (recipient_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );

        $this->dispatchLog($pdo, $sqlite);
    }

    /**
     * GÖNDERİM KAYDI — "hangi link kime ne zaman hangi kanaldan gitti?"
     *
     * Bugün bu sorunun cevabı hiçbir yerde yok: link üretiliyor, WhatsApp'a
     * kopyalanıyor ve iz kalmıyor. Firma "bana bir şey gelmedi" dediğinde
     * söyleyecek bir şey olmuyordu.
     */
    private function dispatchLog(PDO $pdo, bool $sqlite): void
    {
        if ($this->tabloVar($pdo, 'share_dispatch_log')) {
            return;
        }

        $pdo->exec($sqlite
            ? 'CREATE TABLE share_dispatch_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                share_id INTEGER NOT NULL,
                supplier_round_id INTEGER NULL,
                kanal TEXT NOT NULL DEFAULT "whatsapp",
                alici TEXT NULL,
                dil TEXT NULL,
                gonderen_id INTEGER NULL,
                not_metni TEXT NULL,
                created_at TEXT NOT NULL
            )'
            : 'CREATE TABLE share_dispatch_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                share_id BIGINT UNSIGNED NOT NULL,
                supplier_round_id BIGINT UNSIGNED NULL,
                kanal VARCHAR(16) NOT NULL DEFAULT "whatsapp",
                alici VARCHAR(190) NULL,
                dil VARCHAR(8) NULL,
                gonderen_id BIGINT UNSIGNED NULL,
                not_metni VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                KEY ix_gonderim_share (share_id, created_at),
                KEY ix_gonderim_tur (supplier_round_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
    }

    private function tabloVar(PDO $pdo, string $tablo): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :ad");
            $statement->execute(['ad' => $tablo]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :ad',
        );
        $statement->execute(['ad' => $tablo]);

        return (int) $statement->fetchColumn() > 0;
    }
};
