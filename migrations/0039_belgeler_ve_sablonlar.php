<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * V3-C BLOK A1 — BELGELER VE LİSTE ŞABLONLARI (yalnız DDL).
 *
 * BELGELER: proforma, fatura, konşimento, dekont, foto, mal kabul. Bugün bu
 * dosyalar WhatsApp'ta ve kimsenin masaüstünde duruyor; "o proformayı bir
 * bulsak" cümlesi bu tablonun yokluğunun karşılığı.
 *
 * SUNUCU BİRİNCİL (K33 sınırı içinde): dosya `storage/documents/` altına
 * yazılır, tablo yalnız ÜSTVERİYİ tutar. Dosyanın kendisini veritabanına
 * koymak, yedek boyutunu ve bellek kullanımını gereksiz büyütürdü.
 *
 * BAĞLAR AYRI TABLODA (`document_links`): bir proforma hem bir listeye hem
 * bir tura hem de tek bir ürüne bağlı olabilir. Tek bir `list_id` kolonu
 * bunların hepsini karşılayamaz ve ilk çok bağlı belgede şema değişikliği
 * gerekirdi.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        $this->documents($pdo, $sqlite);
        $this->documentLinks($pdo, $sqlite);
        $this->listTemplates($pdo, $sqlite);
    }

    private function documents(PDO $pdo, bool $sqlite): void
    {
        if ($this->tabloVar($pdo, 'documents')) {
            return;
        }

        // `surum` ve `onceki_id`: aynı belgenin düzeltilmiş hâli YENİ SATIRDIR,
        // eskisi silinmez. "Hangi proformayı onaylamıştık?" sorusunun cevabı
        // ancak eski sürüm dururken verilebilir.
        $pdo->exec($sqlite
            ? 'CREATE TABLE documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tur TEXT NOT NULL DEFAULT "diger",
                ad TEXT NOT NULL,
                dosya_yolu TEXT NOT NULL,
                mime TEXT NULL,
                boyut INTEGER NOT NULL DEFAULT 0,
                sha256 TEXT NULL,
                belge_kodu TEXT NULL,
                surum INTEGER NOT NULL DEFAULT 1,
                onceki_id INTEGER NULL,
                notlar TEXT NULL,
                yukleyen_tip TEXT NOT NULL DEFAULT "admin",
                yukleyen_id INTEGER NULL,
                supplier_round_id INTEGER NULL,
                silindi_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
            : 'CREATE TABLE documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tur VARCHAR(24) NOT NULL DEFAULT "diger",
                ad VARCHAR(300) NOT NULL,
                dosya_yolu VARCHAR(500) NOT NULL,
                mime VARCHAR(120) NULL,
                boyut BIGINT UNSIGNED NOT NULL DEFAULT 0,
                sha256 CHAR(64) NULL,
                belge_kodu VARCHAR(64) NULL,
                surum INT UNSIGNED NOT NULL DEFAULT 1,
                onceki_id BIGINT UNSIGNED NULL,
                notlar TEXT NULL,
                yukleyen_tip VARCHAR(16) NOT NULL DEFAULT "admin",
                yukleyen_id BIGINT UNSIGNED NULL,
                supplier_round_id BIGINT UNSIGNED NULL,
                silindi_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY ix_belge_tur (tur, created_at),
                KEY ix_belge_tur_kaydi (supplier_round_id),
                KEY ix_belge_silinme (silindi_at),
                KEY ix_belge_kod (belge_kodu)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
    }

    private function documentLinks(PDO $pdo, bool $sqlite): void
    {
        if ($this->tabloVar($pdo, 'document_links')) {
            return;
        }

        $pdo->exec($sqlite
            ? 'CREATE TABLE document_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_id INTEGER NOT NULL,
                hedef_tip TEXT NOT NULL,
                hedef_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (document_id, hedef_tip, hedef_id)
            )'
            : 'CREATE TABLE document_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                document_id BIGINT UNSIGNED NOT NULL,
                hedef_tip VARCHAR(16) NOT NULL,
                hedef_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_belge_bag (document_id, hedef_tip, hedef_id),
                KEY ix_bag_hedef (hedef_tip, hedef_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
    }

    /**
     * LİSTE ŞABLONLARI — "tekrar sipariş" akışının temeli.
     *
     * Ürün kimliklerini JSON olarak tutar, ürünleri KOPYALAMAZ: şablondan yeni
     * liste türetilirken ürünler o an okunur. Kopyalasaydık, bir üründe
     * yapılan düzeltme şablonda eski hâliyle donup kalırdı.
     */
    private function listTemplates(PDO $pdo, bool $sqlite): void
    {
        if ($this->tabloVar($pdo, 'list_templates')) {
            return;
        }

        $pdo->exec($sqlite
            ? 'CREATE TABLE list_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ad TEXT NOT NULL,
                aciklama TEXT NULL,
                urun_json TEXT NOT NULL,
                kaynak_list_id INTEGER NULL,
                kullanim_sayisi INTEGER NOT NULL DEFAULT 0,
                son_kullanim_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
            : 'CREATE TABLE list_templates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                ad VARCHAR(190) NOT NULL,
                aciklama VARCHAR(500) NULL,
                urun_json JSON NOT NULL,
                kaynak_list_id BIGINT UNSIGNED NULL,
                kullanim_sayisi INT UNSIGNED NOT NULL DEFAULT 0,
                son_kullanim_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY ix_sablon_kullanim (kullanim_sayisi, son_kullanim_at)
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
