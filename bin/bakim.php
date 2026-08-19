<?php

declare(strict_types=1);

/**
 * BAKIM BETİĞİ (İE#11 EK-2 REV2 · İE#13 EK-A) — yalnızca CLI.
 *
 * TEK CRON İLKESİ (İE#13 EK-A): canlıda tek zamanlanmış görev vardır ve bakım artık
 * `bin/backup.php` sonunda AYNI süreçte koşar. Bu betik ELLE koşum için durur:
 *
 *   php bin/bakim.php
 *
 * İşler (mantık `App\Services\MaintenanceTasks` içinde — iki giriş noktası ortak):
 *   (a) çöp kutusu kalıcı temizliği + yetim medya GC'si,
 *   (b) app_logs saklama süresi (LOG_RETENTION_DAYS) + (b2) hız sayacı satırları,
 *   (c) yedek saklama (BACKUP_RETENTION_DAYS; en yeni 5 korunur).
 * bin/purge-trash.php geriye uyum için DURUR.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\SystemClock;
use App\Services\MaintenanceTasks;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $now = SystemClock::fromConfig($config)->now();

    echo '=== tedarikapp bakım koşusu (' . $now->format('Y-m-d H:i') . ") ===\n";

    $result = (new MaintenanceTasks($config, $connection, $basePath))->run($now);
    foreach ($result['lines'] as $line) {
        echo $line . "\n";
    }
    foreach ($result['uyarilar'] as $uyari) {
        fwrite(STDERR, 'UYARI: ' . $uyari . "\n");
    }

    echo $result['uyarilar'] === [] ? "BAKIM TAMAM\n" : "BAKIM KISMİ (yukarıdaki uyarılara bakın)\n";
    exit($result['uyarilar'] === [] ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
