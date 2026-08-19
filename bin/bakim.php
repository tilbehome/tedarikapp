<?php

declare(strict_types=1);

/**
 * TEK BAKIM BETİĞİ (İE#11 EK-2 REV2) — yalnızca CLI.
 *
 * Ürün Sahibi'nin cron listesi İKİ satırda sabitlenir (docs/07 "Zamanlanmış görevler"):
 *
 *   0 3 * * *   /usr/local/bin/php /home/<kullanıcı>/<alan-adı>/bin/backup.php
 *   30 3 * * *  /usr/local/bin/php /home/<kullanıcı>/<alan-adı>/bin/bakim.php
 *
 * Sırayla koşar ve raporlar:
 *   (a) çöp kutusu kalıcı temizliği + yetim medya GC'si (purge-trash mantığı),
 *   (b) app_logs saklama süresi (LOG_RETENTION_DAYS),
 *   (c) yedek saklama (BACKUP_RETENTION_DAYS; en yeni 5 korunur).
 * bin/purge-trash.php geriye uyum için DURUR; runbook artık bu betiği önerir.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Dates;
use App\Core\SystemClock;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use App\Services\BackupService;
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

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $now = SystemClock::fromConfig($config)->now();

    echo "=== tedarikapp bakım koşusu (" . $now->format('Y-m-d H:i') . ") ===\n";

    // ── (a) Çöp kutusu + yetim medya (purge-trash mantığı) ──
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
    $threshold = (new TrashPolicy($config->getPositiveInt('TRASH_RETENTION_DAYS', 30)))->purgeThreshold($now);

    $mediaReferences = [];
    $purgedProducts = 0;
    foreach ($products->expiredTrashIds($threshold) as $productId) {
        $refs = $products->mediaReferencesForProduct($productId);
        $connection->transaction(static fn () => $products->forceDelete($productId));
        $mediaReferences = [...$mediaReferences, ...$refs];
        $purgedProducts++;
    }
    $purgedLists = 0;
    foreach ($lists->expiredTrashIds($threshold) as $listId) {
        $refs = $products->mediaReferencesForList($listId);
        $connection->transaction(static fn () => $lists->forceDelete($listId));
        $mediaReferences = [...$mediaReferences, ...$refs];
        $purgedLists++;
    }
    $deletedFiles = $janitor->deleteUnreferenced($mediaReferences);
    $orphans = $janitor->purgeOrphans();
    printf("(a) çöp kutusu: %d liste + %d ürün kalıcı silindi · medya: %d dosya + %d yetim\n", $purgedLists, $purgedProducts, count($deletedFiles), count($orphans));

    // ── (b) app_logs saklama ──
    $logRetentionDays = $config->getPositiveInt('LOG_RETENTION_DAYS', 30);
    $purgedLogs = 0;
    try {
        $statement = $connection->pdo()->prepare('DELETE FROM app_logs WHERE logged_at <= :threshold');
        $statement->execute(['threshold' => Dates::toStorage($now->modify(sprintf('-%d days', $logRetentionDays)))]);
        $purgedLogs = $statement->rowCount();
    } catch (Throwable $e) {
        fwrite(STDERR, 'UYARI: app_logs temizlenemedi: ' . $e->getMessage() . "\n");
    }
    printf("(b) app_logs: %d kayıt silindi (%d günden eski)\n", $purgedLogs, $logRetentionDays);

    // ── (b2) hız sayacı satırları (İE#11 EK-3): pencere 1 dakikadır, 2 günden eskisi ölü veridir ──
    $purgedCounters = 0;
    try {
        $statement = $connection->pdo()->prepare(
            "DELETE FROM activity_log WHERE action = 'capture_request' AND created_at <= :threshold",
        );
        $statement->execute(['threshold' => Dates::toStorage($now->modify('-2 days'))]);
        $purgedCounters = $statement->rowCount();
    } catch (Throwable $e) {
        fwrite(STDERR, 'UYARI: hız sayacı satırları temizlenemedi: ' . $e->getMessage() . "\n");
    }
    printf("(b2) hız sayacı: %d capture_request satırı silindi (2 günden eski)\n", $purgedCounters);

    // ── (c) yedek saklama ──
    $backupService = new BackupService($config, $basePath);
    $pruned = $backupService->prune($config->getPositiveInt('BACKUP_RETENTION_DAYS', 14));
    printf("(c) yedekler: %d eski dosya silindi (en yeni 5 her koşulda korunur)\n", count($pruned));

    echo "BAKIM TAMAM\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
