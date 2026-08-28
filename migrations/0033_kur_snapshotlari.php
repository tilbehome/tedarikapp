<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * `rate_snapshots` — KURUN SÜRÜMLÜ GEÇMİŞİ (İE#22 Blok A1).
 *
 * BUGÜNKÜ EKSİK: sistem "güncel kur" değerini `settings.yuan_tl/usd_tl`de
 * ZAMANSIZ tutuyor; `rate_history` ise değişiklikleri yazıyor ama hangi satırın
 * HÂLEN GEÇERLİ olduğunu söylemiyor. "Kur kaç saattir aynı?" ve "bu liste hangi
 * kur sürümüyle kilitlendi?" sorularının cevabı hiçbir yerde yok.
 *
 * ÇÖZÜM: her kur değişikliği bir SNAPSHOT satırıdır. Aktif satır
 * `superseded_at IS NULL` olandır — tek koşullu sorgu, ayrı "sürüm no" kolonu
 * gerekmez; `id` zaten monotondur ve İE#23'ün tur bazlı kur seçimi bu kimliği
 * referans alacaktır.
 *
 * K23: bu dosya YALNIZ DDL yapar. Mevcut `rate_history` satırlarının aktarımı
 * AYRI migration'dadır (0034) — MySQL'de DDL örtük commit yapar; tek dosyada
 * hem tablo açıp hem veri taşımak, yarıda kalırsa geri alınamayan bir hâl
 * bırakır.
 *
 * K50 SINIRI: bu tablo BELGE ÜRETİMİNE BAĞLANMAZ. Çıktının kuru her zaman
 * `lists.yuan_rate` kopyasından okunur; snapshot satırı sonradan değişse bile
 * geçmiş belge aynen yeniden üretilir.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        if ($this->tabloVar($pdo, 'rate_snapshots')) {
            return;
        }

        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        $pdo->exec($sqlite
            ? 'CREATE TABLE rate_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                currency TEXT NOT NULL,
                rate TEXT NOT NULL,
                source TEXT NOT NULL DEFAULT "elle",
                effective_from TEXT NOT NULL,
                superseded_at TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT NOT NULL,
                UNIQUE (currency, effective_from)
            )'
            : 'CREATE TABLE rate_snapshots (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                currency VARCHAR(8) NOT NULL,
                rate DECIMAL(12,4) NOT NULL,
                source VARCHAR(8) NOT NULL DEFAULT "elle",
                effective_from DATETIME NOT NULL,
                superseded_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_kur_gecerlilik (currency, effective_from),
                KEY ix_kur_aktif (currency, superseded_at)
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
