<?php

declare(strict_types=1);

/**
 * Çöp kutusu temizliği (K15, İE#6 §5) — yalnızca CLI.
 *
 * Saklama süresi (`TRASH_RETENTION_DAYS`, varsayılan 30 gün) dolan soft-delete
 * kayıtlarını KALICI siler. Cron adayıdır (docs/07 §7 yedekleme cron'unun yanında):
 *
 *   0 4 * * *  /usr/local/bin/php /home/<kullanıcı>/<alan-adı>/bin/purge-trash.php
 *
 * Silinen listeyle birlikte ürünleri ve görselleri FK CASCADE ile gider.
 * `--dry-run` ile ne silineceği yazdırılır, dokunulmaz.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\SystemClock;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Services\TrashPolicy;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $policy = new TrashPolicy($config->getPositiveInt('TRASH_RETENTION_DAYS', 30));
    $now = SystemClock::fromConfig($config)->now();
    $threshold = $policy->purgeThreshold($now);

    $lists = new ListRepository($connection);
    $products = new ProductRepository($connection);

    $expiredLists = $lists->expiredTrashIds($threshold);
    $expiredProducts = $products->expiredTrashIds($threshold);

    printf(
        "Saklama süresi: %d gün · eşik: %s%s\n",
        $policy->retentionDays(),
        $threshold->format('Y-m-d H:i:s'),
        $dryRun ? '  [DENEME — hiçbir şey silinmeyecek]' : '',
    );

    if ($expiredLists === [] && $expiredProducts === []) {
        echo "Süresi dolan kayıt yok.\n";
        exit(0);
    }

    // Önce ürünler: listesi silinecek ürünler zaten CASCADE ile gidecek,
    // ama listesi DURAN tek tek silinmiş ürünler ayrıca temizlenmeli.
    foreach ($expiredProducts as $productId) {
        echo 'ÜRÜN  #' . $productId . ($dryRun ? " (silinecekti)\n" : " silindi\n");
        if (!$dryRun) {
            $products->forceDelete($productId);
        }
    }

    foreach ($expiredLists as $listId) {
        echo 'LİSTE #' . $listId . ($dryRun ? " (ürünleriyle silinecekti)\n" : " ürünleriyle silindi\n");
        if (!$dryRun) {
            $lists->forceDelete($listId);
        }
    }

    printf(
        "Toplam: %d liste, %d ürün%s.\n",
        count($expiredLists),
        count($expiredProducts),
        $dryRun ? ' işaretlendi' : ' kalıcı silindi',
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
