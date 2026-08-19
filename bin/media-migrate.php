<?php

declare(strict_types=1);

/**
 * Toplu arşive geçiş aracı (K47 — İE#9.6 Görev 2) — yalnızca CLI.
 *
 * Hotlink döneminden kalan uzak görselleri (products.main_image http-URL'leri ve
 * product_images storage_mode=remote) MediaService üzerinden indirir → yeniden
 * kodlar → yerel arşive alır. İdempotenttir: tamamlananlar atlanır, araç istendiği
 * kadar tekrar koşulabilir. Parti parti çalışır (varsayılan 20'şer), her partinin
 * sonucu ekrana basılır; başarısızlıklar ürün + URL + hata sınıfıyla listelenir ve
 * kayıt BOZULMAZ (remote kalır, sonraki koşumda yeniden denenir).
 *
 * Kullanım:  php bin/media-migrate.php [--batch=20] [--max-batches=0]
 *   --batch       parti boyutu (varsayılan 20)
 *   --max-batches güvenlik sınırı; 0 = kalan bitene dek (başarısızlar tekrar
 *                 denenmez — parti içinde ilerleme yoksa döngü durur)
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Models\SettingsRepository;
use App\Services\CurlMediaFetcher;
use App\Services\MediaMigrator;
use App\Services\MediaService;
use App\Services\UrlGuard;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

$options = getopt('', ['batch::', 'max-batches::']);
$batchSize = max(1, (int) ($options['batch'] ?? 20));
$maxBatches = max(0, (int) ($options['max-batches'] ?? 0));

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
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
    $migrator = new MediaMigrator($connection, $media);

    if ($media->mode() !== MediaService::MODE_DOWNLOAD) {
        fwrite(STDERR, "HATA: medya klasörü yazılabilir değil — arşiv modu kapalı, taşıma yapılamaz.\n");
        fwrite(STDERR, "Çözüm: public/media klasörüne yazma izni verin (755/775) ve tekrar koşun.\n");
        exit(1);
    }

    printf("Bekleyen uzak görsel: %d · parti boyutu: %d\n", $migrator->remainingCount(), $batchSize);

    $totalMigrated = 0;
    $totalFailed = 0;
    $batch = 0;

    while (true) {
        $batch++;
        $result = $migrator->migrateBatch($batchSize);

        printf(
            "Parti %d: %d tarandı, %d taşındı, %d başarısız · kalan %d\n",
            $batch,
            $result['scanned'],
            $result['migrated'],
            count($result['failed']),
            $result['remaining'],
        );
        foreach ($result['failed'] as $failure) {
            printf(
                "  BAŞARISIZ %s #%d (ürün #%d): %s → %s\n",
                $failure['kind'],
                $failure['id'],
                $failure['product_id'],
                $failure['url'],
                $failure['error'],
            );
        }

        $totalMigrated += $result['migrated'];
        $totalFailed += count($result['failed']);

        $done = $result['remaining'] === 0
            || $result['migrated'] === 0 // ilerleme yok: kalanların hepsi başarısız — döngüye girme
            || ($maxBatches > 0 && $batch >= $maxBatches);
        if ($done) {
            break;
        }
    }

    printf("TOPLAM: %d taşındı, %d başarısız, %d kaldı.\n", $totalMigrated, $totalFailed, $migrator->remainingCount());
    exit($totalFailed > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
