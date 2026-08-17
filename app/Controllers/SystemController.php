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
    ) {
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
