<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Connection;
use App\Core\Dates;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use DateTimeImmutable;

/**
 * Gecelik bakım işleri (İE#11 EK-2 REV2 · İE#13 EK-A ile ORTAKLAŞTIRILDI).
 *
 * Aynı işler iki yerden koşar: `bin/bakim.php` (elle) ve `bin/backup.php` sonundaki
 * bakım adımı (tek cron ilkesi). Mantık burada tek kopya durur ki iki giriş noktası
 * zamanla ayrışmasın.
 *
 * Her adım KENDİ hatasını yutar ve rapor satırında bildirir: (b) düşerse (c) yine koşar
 * — bir tablonun kilitli olması tüm bakımı iptal etmemeli.
 */
final class MaintenanceTasks
{
    public function __construct(
        private readonly Config $config,
        private readonly Connection $connection,
        private readonly string $basePath,
    ) {
    }

    /**
     * @return array{lines: list<string>, ozet: string, uyarilar: list<string>}
     */
    public function run(DateTimeImmutable $now): array
    {
        $lines = [];
        $uyarilar = [];

        [$purgedLists, $purgedProducts, $deletedFiles, $orphans] = $this->cop($now, $uyarilar);
        $lines[] = sprintf(
            '(a) çöp kutusu: %d liste + %d ürün kalıcı silindi · medya: %d dosya + %d yetim',
            $purgedLists,
            $purgedProducts,
            $deletedFiles,
            $orphans,
        );

        $logRetentionDays = $this->config->getPositiveInt('LOG_RETENTION_DAYS', 30);
        $purgedLogs = $this->sil(
            'DELETE FROM app_logs WHERE logged_at <= :threshold',
            Dates::toStorage($now->modify(sprintf('-%d days', $logRetentionDays))),
            'app_logs',
            $uyarilar,
        );
        $lines[] = sprintf('(b) app_logs: %d kayıt silindi (%d günden eski)', $purgedLogs, $logRetentionDays);

        // (b2) Hız sayacı satırları: pencere 1 dakikadır, 2 günden eskisi ölü veridir.
        $purgedCounters = $this->sil(
            "DELETE FROM activity_log WHERE action = 'capture_request' AND created_at <= :threshold",
            Dates::toStorage($now->modify('-2 days')),
            'hız sayacı',
            $uyarilar,
        );
        $lines[] = sprintf('(b2) hız sayacı: %d capture_request satırı silindi (2 günden eski)', $purgedCounters);

        $prunedCount = 0;
        try {
            $pruned = (new BackupService($this->config, $this->basePath))
                ->prune($this->config->getPositiveInt('BACKUP_RETENTION_DAYS', 14));
            $prunedCount = count($pruned);
        } catch (\Throwable $exception) {
            $uyarilar[] = 'yedek saklama: ' . $exception->getMessage();
        }
        $lines[] = sprintf('(c) yedekler: %d eski dosya silindi (en yeni 5 her koşulda korunur)', $prunedCount);

        return [
            'lines' => $lines,
            'ozet' => sprintf(
                'çöp %d/%d · medya %d+%d · log %d · sayaç %d · yedek %d',
                $purgedLists,
                $purgedProducts,
                $deletedFiles,
                $orphans,
                $purgedLogs,
                $purgedCounters,
                $prunedCount,
            ),
            'uyarilar' => $uyarilar,
        ];
    }

    /**
     * Çöp kutusu kalıcı temizliği + yetim medya toplama.
     *
     * @param list<string> $uyarilar
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function cop(DateTimeImmutable $now, array &$uyarilar): array
    {
        try {
            $lists = new ListRepository($this->connection);
            $products = new ProductRepository($this->connection);
            $allowedHosts = array_map('trim', explode(',', $this->config->get('MEDIA_ALLOWED_HOSTS', 'alicdn.com,1688.com')));
            $urlGuard = new UrlGuard($allowedHosts);
            $media = new MediaService(
                $this->basePath,
                $urlGuard,
                new CurlMediaFetcher($urlGuard, $this->config->getPositiveInt('MEDIA_DOWNLOAD_TIMEOUT', 25)),
                new SettingsRepository($this->connection),
                $this->config->getPositiveInt('MEDIA_MAX_MB', 8) * 1024 * 1024,
                $this->config->get('MEDIA_PATH', 'public/media'),
            );
            $janitor = new MediaJanitor($media, $products);
            $threshold = (new TrashPolicy($this->config->getPositiveInt('TRASH_RETENTION_DAYS', 30)))->purgeThreshold($now);

            $mediaReferences = [];
            $purgedProducts = 0;
            foreach ($products->expiredTrashIds($threshold) as $productId) {
                $refs = $products->mediaReferencesForProduct($productId);
                $this->connection->transaction(static fn () => $products->forceDelete($productId));
                $mediaReferences = [...$mediaReferences, ...$refs];
                $purgedProducts++;
            }
            $purgedLists = 0;
            foreach ($lists->expiredTrashIds($threshold) as $listId) {
                $refs = $products->mediaReferencesForList($listId);
                $this->connection->transaction(static fn () => $lists->forceDelete($listId));
                $mediaReferences = [...$mediaReferences, ...$refs];
                $purgedLists++;
            }

            return [$purgedLists, $purgedProducts, count($janitor->deleteUnreferenced($mediaReferences)), count($janitor->purgeOrphans())];
        } catch (\Throwable $exception) {
            $uyarilar[] = 'çöp kutusu/medya: ' . $exception->getMessage();

            return [0, 0, 0, 0];
        }
    }

    /** @param list<string> $uyarilar */
    private function sil(string $sql, string $threshold, string $ad, array &$uyarilar): int
    {
        try {
            $statement = $this->connection->pdo()->prepare($sql);
            $statement->execute(['threshold' => $threshold]);

            return $statement->rowCount();
        } catch (\Throwable $exception) {
            $uyarilar[] = $ad . ': ' . $exception->getMessage();

            return 0;
        }
    }
}
