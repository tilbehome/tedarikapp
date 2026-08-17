<?php

declare(strict_types=1);

/**
 * Housekeeping cron'u (K15, İE#6 §5, K37 §C7/§E11) — yalnızca CLI.
 *
 * Üç görev:
 *  1. Saklama süresi (`TRASH_RETENTION_DAYS`, varsayılan 30 gün) dolan soft-delete
 *     kayıtlarını KALICI siler — fiziksel medya dosyaları dahil (K37 §C7).
 *  2. Yetim medya GC'si: DB'de referansı kalmamış sunucu-üretimi dosyaları temizler.
 *  3. `app_logs` saklama süresi (`LOG_RETENTION_DAYS`, varsayılan 30 gün): eski log
 *     kayıtlarını siler — tablo sınırsız büyümesin (K37 §E11).
 *
 * Cron adayı (docs/07 §7 yedekleme cron'unun yanında):
 *
 *   0 4 * * *  /usr/local/bin/php /home/<kullanıcı>/<alan-adı>/bin/purge-trash.php
 *
 * Silinen listeyle birlikte ürünleri ve görsel kayıtları FK CASCADE ile gider.
 * `--dry-run` ile ne silineceği yazdırılır, dokunulmaz.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Dates;
use App\Core\SystemClock;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use App\Services\CurlMediaFetcher;
use App\Services\MediaJanitor;
use App\Services\MediaService;
use App\Services\TrashPolicy;
use App\Services\UrlGuard;

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

    $allowedHosts = array_map('trim', explode(',', $config->get('MEDIA_ALLOWED_HOSTS', 'alicdn.com,1688.com')));
    $urlGuard = new UrlGuard($allowedHosts);
    $media = new MediaService(
        $basePath,
        $urlGuard,
        new CurlMediaFetcher($urlGuard, $config->getPositiveInt('MEDIA_DOWNLOAD_TIMEOUT', 25)),
        new SettingsRepository($connection),
        $config->getPositiveInt('MEDIA_MAX_MB', 8) * 1024 * 1024,
        $config->get('MEDIA_PATH', 'public/media'),
    );
    $janitor = new MediaJanitor($media, $products);

    $expiredLists = $lists->expiredTrashIds($threshold);
    $expiredProducts = $products->expiredTrashIds($threshold);

    printf(
        "Saklama süresi: %d gün · eşik: %s%s\n",
        $policy->retentionDays(),
        $threshold->format('Y-m-d H:i:s'),
        $dryRun ? '  [DENEME — hiçbir şey silinmeyecek]' : '',
    );

    // ── 1) Çöp kutusu temizliği (K15) — medya referansları SİLMEDEN ÖNCE toplanır (K37 §C7)
    $mediaReferences = [];

    // Önce ürünler: listesi silinecek ürünler zaten CASCADE ile gidecek,
    // ama listesi DURAN tek tek silinmiş ürünler ayrıca temizlenmeli.
    foreach ($expiredProducts as $productId) {
        echo 'ÜRÜN  #' . $productId . ($dryRun ? " (silinecekti)\n" : " silindi\n");
        if (!$dryRun) {
            $refs = $products->mediaReferencesForProduct($productId);
            $connection->transaction(static fn () => $products->forceDelete($productId));
            $mediaReferences = [...$mediaReferences, ...$refs];
        }
    }

    foreach ($expiredLists as $listId) {
        echo 'LİSTE #' . $listId . ($dryRun ? " (ürünleriyle silinecekti)\n" : " ürünleriyle silindi\n");
        if (!$dryRun) {
            $refs = $products->mediaReferencesForList($listId);
            $connection->transaction(static fn () => $lists->forceDelete($listId));
            $mediaReferences = [...$mediaReferences, ...$refs];
        }
    }

    $deletedFiles = $dryRun ? [] : $janitor->deleteUnreferenced($mediaReferences);

    // ── 2) Yetim medya GC'si (K37 §C7): referanssız kalmış sunucu-üretimi dosyalar
    $orphans = $dryRun ? [] : $janitor->purgeOrphans();

    // ── 3) app_logs saklama süresi (K37 §E11)
    $logRetentionDays = $config->getPositiveInt('LOG_RETENTION_DAYS', 30);
    $logThreshold = $now->modify(sprintf('-%d days', $logRetentionDays));
    $purgedLogs = 0;
    if (!$dryRun) {
        try {
            $statement = $connection->pdo()->prepare('DELETE FROM app_logs WHERE logged_at <= :threshold');
            $statement->execute(['threshold' => Dates::toStorage($logThreshold)]);
            $purgedLogs = $statement->rowCount();
        } catch (Throwable $e) {
            // app_logs olmayan (LOG_DRIVER=file, eski şema) kurulumda görev atlanır;
            // housekeeping'in kalanı bundan etkilenmez.
            fwrite(STDERR, 'UYARI: app_logs temizlenemedi: ' . $e->getMessage() . "\n");
        }
    }

    printf(
        "Toplam: %d liste, %d ürün%s · medya: %d dosya + %d yetim · log: %d kayıt (%d günden eski).\n",
        count($expiredLists),
        count($expiredProducts),
        $dryRun ? ' işaretlendi' : ' kalıcı silindi',
        count($deletedFiles),
        count($orphans),
        $purgedLogs,
        $logRetentionDays,
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
