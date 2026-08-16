<?php

declare(strict_types=1);

/**
 * Migration koşucusu (yalnızca CLI — web tetikleme kurulum sihirbazının işidir, İE#5).
 * Kullanım: php bin/migrate.php
 */

use App\Core\Config;
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

    $migrator = new Migrator(Database::connect($config), $basePath . '/migrations');
    $applied = $migrator->run();

    if ($applied === []) {
        echo "Uygulanacak migration yok — veritabanı güncel.\n";
    } else {
        foreach ($applied as $name) {
            echo "UYGULANDI: {$name}\n";
        }
        echo sprintf("Toplam %d migration uygulandı.\n", count($applied));
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
