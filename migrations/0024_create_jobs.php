<?php

declare(strict_types=1);

/**
 * jobs — VERİTABANI TABANLI İŞ KUYRUĞU (İE#20 C3).
 *
 * NEDEN KUYRUK: çeviri (C4), görsel işleme ve skor hesabı (C6) saniyeler sürer.
 * Bunları istek içinde yapmak iki şeyi bozar: kullanıcı bekler ve paylaşımlı
 * hostingin `max_execution_time`ı isteği ortasından keser — yarım iş kalır.
 *
 * NEDEN REDIS/BEACON DEĞİL: sunucuda Redis yok, arka plan süreci (daemon)
 * çalıştırılamaz (docs/04 §7: `exec/proc_open` YASAK). Elimizdeki tek düzenli
 * tetikleyici CRON'dur. Bu yüzden kuyruk bir TABLODUR ve işleyici cron'dan koşar.
 *
 * KİLİTLEME: iki cron üst üste binebilir (bir koşum uzarsa). İş sahiplenme,
 * koşullu UPDATE ile yapılır (`WHERE durum='bekliyor'`); etkilenen satır sayısı
 * yarışın kimin kazandığını söyler. Ayrıca `kilit_sahibi` + `kilitlendi_at`
 * yazılır: bir işleyici ölürse (PHP timeout) işi sonsuza dek kilitli kalmaz,
 * `kilitlendi_at` eskidiğinde iş geri alınır.
 *
 * ÖLÜ İŞ RAFI: `deneme` sayısı üst sınıra ulaşan iş `olu` durumuna geçer ve
 * ORADA KALIR — sessizce kaybolmaz, panelde görünür ve elle yeniden denenebilir.
 * Sessizce düşen bir iş, hiç kuyruğa alınmamış işten daha tehlikelidir: kimse
 * eksik olduğunu fark etmez.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if ($this->tabloVar($pdo, 'jobs')) {
            return;
        }

        $pdo->exec($sqlite
            ? <<<'SQL'
                CREATE TABLE jobs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tur VARCHAR(40) NOT NULL,
                    anahtar VARCHAR(190) NULL,
                    yuk TEXT NULL,
                    durum VARCHAR(16) NOT NULL DEFAULT 'bekliyor',
                    oncelik INT NOT NULL DEFAULT 100,
                    deneme INT NOT NULL DEFAULT 0,
                    max_deneme INT NOT NULL DEFAULT 3,
                    hata TEXT NULL,
                    kilit_sahibi VARCHAR(64) NULL,
                    kilitlendi_at DATETIME NULL,
                    calisacak_at DATETIME NOT NULL,
                    bitti_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                )
                SQL
            : <<<'SQL'
                CREATE TABLE jobs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    tur VARCHAR(40) NOT NULL,
                    anahtar VARCHAR(190) NULL,
                    yuk JSON NULL,
                    durum VARCHAR(16) NOT NULL DEFAULT 'bekliyor',
                    oncelik INT NOT NULL DEFAULT 100,
                    deneme INT UNSIGNED NOT NULL DEFAULT 0,
                    max_deneme INT UNSIGNED NOT NULL DEFAULT 3,
                    hata TEXT NULL,
                    kilit_sahibi VARCHAR(64) NULL,
                    kilitlendi_at DATETIME NULL,
                    calisacak_at DATETIME NOT NULL,
                    bitti_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    KEY idx_jobs_sira (durum, calisacak_at, oncelik),
                    KEY idx_jobs_tur (tur, durum),
                    UNIQUE KEY idx_jobs_anahtar (tur, anahtar)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);

        if ($sqlite) {
            // SQLite'ta UNIQUE ayrı deyimle kurulur.
            $pdo->exec('CREATE UNIQUE INDEX idx_jobs_anahtar ON jobs (tur, anahtar)');
            $pdo->exec('CREATE INDEX idx_jobs_sira ON jobs (durum, calisacak_at, oncelik)');
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
