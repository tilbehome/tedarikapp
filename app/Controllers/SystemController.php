<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Core\AppVersion;
use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Connection;
use App\Core\Migrator;
use App\Core\Response;
use App\Middleware\Auth;
use App\Services\ActivityLog;
use App\Services\MediaService;
use App\Services\StateMachine;
use App\Setup\SetupLock;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Güncelleme yolu (İE#5 §12).
 *
 * Kurulum sihirbazı tek seferliktir ve bittikten sonra kilitlenir; ama uygulama
 * güncellendiğinde yeni migration'lar gelir. Bunları koşmak için sunucuya SSH ile
 * girmek gerekmesin diye panelden **kimlik doğrulamalı** bir yol bırakılır.
 *
 * `bin/migrate.php` (lokal geliştirme yolu) aynen durur — bu uçlar onun yerine geçmez.
 */
final class SystemController
{
    public function __construct(
        private readonly string $basePath,
        private readonly Connection $connection,
        private readonly SetupLock $lock,
        private readonly Clock $clock,
        private readonly ?MediaService $media = null,
        private readonly ?StateMachine $stateMachine = null,
        private readonly ?\App\Core\Config $appConfig = null,
    ) {
    }

    /**
     * POST /api/system/backup — İE#10.5: elle yedek al (+ yapılandırılmışsa off-site gönder).
     * Auth + CSRF arkasında. Sırlar/anahtar loglanmaz; sonuç activity_log'a yazılır.
     */
    public function backupCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        if ($this->appConfig === null) {
            return Response::error($response, 'SERVER_ERROR', 'Yapılandırma erişilemiyor.', 500);
        }

        $service = new \App\Services\BackupService($this->appConfig, $this->basePath);
        try {
            $backup = $service->create();
        } catch (Throwable $e) {
            return Response::error($response, 'BACKUP_FAILED', $e->getMessage(), 500);
        }

        $offsite = (new \App\Services\BackupOffsite($this->appConfig))
            ->send((string) $service->pathFor($backup['name']), $backup['name']);

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'backup_created',
            sprintf(
                '%s: %s (%.1f KB) · off-site: %s',
                $user->email,
                $backup['name'],
                $backup['size'] / 1024,
                $offsite['attempted'] ? ($offsite['sent'] ? 'gönderildi (' . $offsite['via'] . ')' : 'BAŞARISIZ') : 'yapılandırılmadı',
            ),
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, ['backup' => $backup, 'offsite' => $offsite]);
    }

    /** GET /api/system/backups — son yedekler + 24 saat uyarısı + off-site durumu. */
    public function backupList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticatedUser($request);
        if ($this->appConfig === null) {
            return Response::error($response, 'SERVER_ERROR', 'Yapılandırma erişilemiyor.', 500);
        }

        $service = new \App\Services\BackupService($this->appConfig, $this->basePath);
        $age = $service->lastBackupAgeSeconds();

        return Response::success($response, [
            'backups' => array_slice($service->list(), 0, 10),
            'writable' => $service->isWritable(),
            'last_age_seconds' => $age,
            'stale' => $age === null || $age > 86400,
            'offsite_configured' => (new \App\Services\BackupOffsite($this->appConfig))->configured(),
        ]);
    }

    /**
     * GET /api/system/backups/{name}/file — şifreli yedeği indirir (Auth'lu; ad deseni doğrulanır).
     *
     * @param array<string, string> $args
     */
    public function backupDownload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->authenticatedUser($request);
        if ($this->appConfig === null) {
            return Response::error($response, 'SERVER_ERROR', 'Yapılandırma erişilemiyor.', 500);
        }

        $path = (new \App\Services\BackupService($this->appConfig, $this->basePath))->pathFor((string) ($args['name'] ?? ''));
        if ($path === null) {
            return Response::error($response, 'NOT_FOUND', 'Yedek bulunamadı.', 404);
        }

        $response->getBody()->write((string) file_get_contents($path));

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"')
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * GET /api/system/state-machine — izinli durum geçişlerinin haritası.
     *
     * Panel (İE#8 §2) durum menüsünü buradan kurar: geçersiz geçiş kullanıcıya
     * hiç SUNULMAZ. Kuralın tek kaynağı backend'deki StateMachine'dir; arayüz
     * kendi kopyasını tutmaz — tuttuğu an docs/04 §2b ile ayrışma riski doğar.
     */
    public function stateMachine(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticatedUser($request);

        $machine = $this->stateMachine ?? new StateMachine();

        $product = [];
        foreach (array_keys(StateMachine::PRODUCT_TRANSITIONS) as $status) {
            $product[$status] = $machine->allowedProductTransitions((string) $status);
        }

        $list = [];
        foreach (array_keys(StateMachine::LIST_TRANSITIONS) as $status) {
            $list[$status] = $machine->allowedListTransitions((string) $status);
        }

        return Response::success($response, [
            'product' => $product,
            'list' => $list,
        ]);
    }

    /** GET /api/system/status — sürüm bilgisi + bekleyen migration sayısı. */
    /**
     * POST /api/system/setup-unlock — K46: kilit kaldırmanın ADMİN OTURUMU yolu.
     * Auth + CSRF arkasındadır (rota grubu); sihirbazdaki APP_KEY yolunun paneldeki eşi.
     */
    public function setupUnlock(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $now = $this->clock->now();

        (new \App\Setup\UnlockGate($this->connection, $this->basePath, new \DateTimeZone(date_default_timezone_get())))
            ->recordSuccess(\App\Core\ClientIp::from($request), $now, 'admin:' . $user->email);

        $this->lock->clear();

        return \App\Core\Response::success($response, ['unlocked' => true]);
    }

    /**
     * POST /api/system/media-migrate — K47: uzak görselleri arşive taşıma (bir parti).
     *
     * Auth + CSRF arkasındadır (rota grubu). Tek çağrı bir parti işler (zaman aşımı
     * yememek için); panel "kalan" sayısı sıfırlanana dek tekrar çağırır. Aynı işin
     * CLI eşi: `bin/media-migrate.php`.
     */
    public function mediaMigrate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);

        if ($this->media === null) {
            return Response::error($response, 'SERVER_ERROR', 'Medya servisi yapılandırılmamış.', 500);
        }
        if ($this->media->mode() !== MediaService::MODE_DOWNLOAD) {
            return Response::error(
                $response,
                'MEDIA_NOT_WRITABLE',
                'Medya klasörü yazılabilir değil — arşiv modu kapalı. public/media klasörüne yazma izni verin.',
                422,
            );
        }

        // İE#10 Blok 5b: panel önceki turların başarısız kimliklerini geçer — parti başı tıkanmaz.
        $body = (array) ($request->getParsedBody() ?? []);
        $excludeProducts = array_map('intval', is_array($body['exclude_products'] ?? null) ? $body['exclude_products'] : []);
        $excludeImages = array_map('intval', is_array($body['exclude_images'] ?? null) ? $body['exclude_images'] : []);

        try {
            $result = (new \App\Services\MediaMigrator($this->connection, $this->media))
                ->migrateBatch(20, $excludeProducts, $excludeImages);
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', 'Arşive taşıma çalıştırılamadı: ' . $e->getMessage(), 500);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'media_migrate',
            sprintf('%s: %d taşındı, %d başarısız, %d kaldı', $user->email, $result['migrated'], count($result['failed']), $result['remaining']),
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, $result);
    }

    /**
     * POST /api/system/migrate-baseline — K49: migration defterini gerçeğe eşitler.
     *
     * Auth + CSRF arkasındadır (rota grubu). PM kararı: APP_KEY kanıtı GEREKMEZ —
     * eylem yıkıcı değildir (HİÇBİR DDL çalıştırmaz; yalnız var olduğu doğrulanan
     * nesnelerin kayıtlarını deftere işler). İdempotent; CLI eşi bin/migrate-baseline.php.
     */
    public function migrateBaseline(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $now = $this->clock->now();

        try {
            $migrator = new Migrator($this->connection->pdo(), $this->basePath . '/migrations');
            $result = $migrator->baseline();
            $pending = $migrator->pending();
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', 'Defter eşitleme çalıştırılamadı: ' . $e->getMessage(), 500);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'migrate_baseline',
            sprintf(
                '%s: %d deftere işlendi, %d atlandı, kalan bekleyen %d',
                $user->email,
                count($result['recorded']),
                count($result['skipped']),
                count($pending),
            ),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, [
            'recorded' => $result['recorded'],
            'skipped' => $result['skipped'],
            'pending_count' => count($pending),
        ]);
    }

    /**
     * POST /api/system/media-check — İE#10 5d: medya bütünlük denetimi + onarım (bir parti).
     *
     * Yerel /media kayıtlarını diskle karşılaştırır; dosyası kayıpları saklanan orijinal
     * adresten yeniden indirir. İdempotent; kaynağı olmayan kayıt bozulmaz, raporlanır.
     */
    public function mediaCheck(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);

        if ($this->media === null) {
            return Response::error($response, 'SERVER_ERROR', 'Medya servisi yapılandırılmamış.', 500);
        }

        try {
            $result = (new \App\Services\MediaIntegrity($this->connection, $this->media))->repairBatch(20);
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', 'Bütünlük denetimi çalıştırılamadı: ' . $e->getMessage(), 500);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'media_check',
            sprintf(
                '%s: %d denetlendi, %d kayıp, %d onarıldı, %d başarısız',
                $user->email,
                $result['checked'],
                $result['missing'],
                $result['repaired'],
                count($result['failed']),
            ),
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, $result);
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticatedUser($request);

        $pending = [];
        $applied = 0;
        $databaseVersion = null;

        try {
            $migrator = new Migrator($this->connection->pdo(), $this->basePath . '/migrations');
            $pending = $migrator->pending();

            $statement = $this->connection->pdo()->query('SELECT COUNT(*) AS total FROM migrations');
            $row = $statement === false ? false : $statement->fetch();
            $applied = is_array($row) ? (int) $row['total'] : 0;

            $versionStatement = $this->connection->pdo()->query('SELECT VERSION() AS version');
            $versionRow = $versionStatement === false ? false : $versionStatement->fetch();
            $databaseVersion = is_array($versionRow) ? (string) $versionRow['version'] : null;
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', 'Sistem durumu okunamadı: ' . $e->getMessage(), 500);
        }

        $lockDetails = $this->lock->read();

        return Response::success($response, [
            'app_version' => AppVersion::VALUE,
            'php_version' => PHP_VERSION,
            'db_version' => $databaseVersion,
            'installed_at' => is_array($lockDetails) && isset($lockDetails['installed_at'])
                ? (string) $lockDetails['installed_at']
                : null,
            'migrations' => [
                'applied' => $applied,
                'pending' => $pending,
                'pending_count' => count($pending),
            ],
            // K33 çift modu — panel "görseller hotlink'te" rozetini buradan okur (Faz 1D).
            'media' => [
                'mode' => $this->media?->mode(),
                'writable' => $this->media?->isWritable(),
            ],
            'setup_lock_in_database' => $this->lock->storesInDatabase(),
        ]);
    }

    /** POST /api/system/migrate — bekleyen migration'ları koşar (auth + CSRF). */
    public function migrate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $now = $this->clock->now();

        try {
            $migrator = new Migrator($this->connection->pdo(), $this->basePath . '/migrations');
            $applied = $migrator->run();
        } catch (Throwable $e) {
            (new ActivityLog($this->connection))->record(
                'system',
                null,
                'migrate_failed',
                $user->email,
                ClientIp::from($request),
                $now,
                ActivityLog::ACTOR_ADMIN,
                $user->id,
            );

            return Response::error($response, 'SERVER_ERROR', 'Migration çalıştırılamadı: ' . $e->getMessage(), 500);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'migrate',
            sprintf('%s (%d migration)', $user->email, count($applied)),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, [
            'applied' => $applied,
            'applied_count' => count($applied),
        ]);
    }

    private function authenticatedUser(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(Auth::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new LogicException('Korumalı uç Auth middleware olmadan çağrıldı.');
        }

        return $user;
    }
}
