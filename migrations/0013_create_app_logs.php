<?php

declare(strict_types=1);

/**
 * app_logs — uygulama günlüğünün VERİTABANI hedefi (K33).
 *
 * Üretim sunucusunda PHP `nobody` (DSO) kullanıcısıyla çalışıyor ve uygulama diske
 * YAZAMIYOR; bu kalıcı bir kısıt. Dosyaya yazamayan bir uygulamada log tutmamak,
 * ilk gerçek hatada elimizde hiçbir şey olmaması demektir — bu yüzden loglar DB'ye alınır.
 *
 * `LOG_DRIVER=file` ile dosya hedefi (geliştirme makinesi) korunur.
 * Redaction ve request_id aynen geçerlidir (K27): hassas alanlar buraya da yazılmaz.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE app_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                channel VARCHAR(32) NOT NULL,
                level_name VARCHAR(16) NOT NULL,
                level INT UNSIGNED NOT NULL,
                message TEXT NOT NULL,
                context LONGTEXT NULL,
                extra LONGTEXT NULL,
                request_id CHAR(26) NULL,
                logged_at DATETIME NOT NULL,
                KEY idx_app_logs_time (logged_at),
                KEY idx_app_logs_level (level, logged_at),
                KEY idx_app_logs_request (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
