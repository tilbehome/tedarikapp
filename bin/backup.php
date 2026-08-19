<?php

declare(strict_types=1);

/**
 * Veritabanı yedeği (İE#10.5 Blok 1b) — yalnızca CLI; cPanel cron adayı:
 *
 *   0 3 * * *  /usr/local/bin/php /home/<kullanıcı>/<alan-adı>/bin/backup.php
 *
 * Dump PHP içinden üretilir (exec YASAK), AES-256-GCM ile şifrelenir (anahtar
 * APP_KEY'den türetilir), storage/backups/ altına yazılır (web'den erişilemez)
 * ve yapılandırılmışsa off-site hedefe (FTP/SMTP, K8: cURL) gönderilir.
 * Panel eşi: Ayarlar > Yedekler.
 */

use App\Core\Config;
use App\Services\BackupOffsite;
use App\Services\BackupService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

    $service = new BackupService($config, $basePath);
    $backup = $service->create();
    printf("YEDEK ALINDI  %s (%.1f KB, sha256 %s...)\n", $backup['name'], $backup['size'] / 1024, substr($backup['sha256'], 0, 12));

    $offsite = (new BackupOffsite($config))->send((string) $service->pathFor($backup['name']), $backup['name']);
    if (!$offsite['attempted']) {
        echo "OFF-SITE: yapılandırılmadı (BACKUP_FTP_* veya BACKUP_SMTP_* girilirse otomatik gönderilir).\n";
    } elseif ($offsite['sent']) {
        echo 'OFF-SITE: gönderildi (' . $offsite['via'] . ")\n";
    } else {
        fwrite(STDERR, 'OFF-SITE BAŞARISIZ: ' . $offsite['error'] . "\n");
        exit(2);
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
