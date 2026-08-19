<?php

declare(strict_types=1);

/**
 * K49 — migration defteri eşitleme (İE#9.8) — yalnızca CLI.
 *
 * Canlı vaka: uygulama tabloları var ama `migrations` defteri boş ("Uygulanan 0 /
 * Bekleyen 17"). Bu araç bekleyen her migration için hedef nesnenin gerçekten var
 * olduğunu şema sorgusuyla doğrular ve VARSA kaydı KOŞMADAN deftere işler; yoksa
 * atlar ve nedenini yazar. HİÇBİR DDL ÇALIŞTIRMAZ, idempotenttir. Panel eşi:
 * Ayarlar > Sistem durumu > "Defteri eşitle".
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Migrator;

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

    $migrator = new Migrator($connection->pdo(), $basePath . '/migrations');
    $result = $migrator->baseline();

    foreach ($result['recorded'] as $name) {
        echo 'DEFTERE İŞLENDİ  ' . $name . "\n";
    }
    foreach ($result['skipped'] as $skip) {
        echo 'ATLANDI          ' . $skip['name'] . ' — ' . $skip['reason'] . "\n";
    }

    $pending = $migrator->pending();
    printf(
        "TOPLAM: %d deftere işlendi, %d atlandı · kalan bekleyen: %d\n",
        count($result['recorded']),
        count($result['skipped']),
        count($pending),
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
