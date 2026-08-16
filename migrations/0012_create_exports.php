<?php

declare(strict_types=1);

/**
 * exports — export geçmişi + GERÇEK anlık görüntü (K25).
 *
 * `snapshot_json`: dosyanın üretildiği andaki liste + ürün verisi. Export bir
 * "anlık görüntü"dür (K15); dosya diskten silinse bile firmaya hangi verinin
 * gönderildiği kayıtta kalır.
 *
 * `list_revision`: "çıktı güncel değil" rozeti `lists.revision != list_revision`
 * karşılaştırmasıyla belirlenir — not düzenlemek çıktıyı eskitmez.
 *
 * Uçların içi (xlsx/pdf üretimi) Faz 2 iş emrindedir; tablo şimdi kuruluyor ki
 * liste nesnesi `last_export`/`is_export_stale` alanlarını bugünden doğru üretebilsin.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE exports (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                list_id BIGINT UNSIGNED NOT NULL,
                format VARCHAR(8) NOT NULL,
                snapshot_json LONGTEXT NULL,
                sha256 CHAR(64) NULL,
                file_size BIGINT UNSIGNED NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'ready',
                list_revision INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_exports_list FOREIGN KEY (list_id)
                    REFERENCES lists (id) ON DELETE CASCADE,
                KEY idx_exports_list (list_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
