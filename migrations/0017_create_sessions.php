<?php

declare(strict_types=1);

/**
 * sessions — PHP oturumlarının VERİTABANI deposu (K44, İE#9.4 — disksiz mod).
 *
 * Üretim sunucusu `session.save_path`e yazamıyor; oturum dosyada değil burada
 * yaşar (DbSessionHandler). `data` base64'lü serileştirme taşır; `last_activity`
 * üzerinden PHP gc'si süresi dolanları temizler.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data MEDIUMTEXT NOT NULL,
                last_activity DATETIME NOT NULL,
                ip VARCHAR(45) NULL,
                KEY idx_sessions_last_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
