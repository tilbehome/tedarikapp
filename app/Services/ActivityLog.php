<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * activity_log yazıcısı (docs/04 §2, K15/K16).
 *
 * GÜVENLİK KURALI: `detail` alanına şifre, TOTP kodu, kurtarma kodu veya token ASLA yazılmaz —
 * yalnızca kimin/nereden/ne yaptığı yazılır (İE#4 §5).
 */
final class ActivityLog
{
    public const string ENTITY_AUTH = 'auth';

    public const string LOGIN_SUCCESS = 'login_success';
    public const string LOGIN_FAILED = 'login_failed';
    public const string LOGIN_LOCKED = 'login_locked';
    public const string TOTP_SUCCESS = 'totp_success';
    public const string TOTP_FAILED = 'totp_failed';
    public const string RECOVERY_USED = 'recovery_used';
    public const string RECOVERY_FAILED = 'recovery_failed';
    public const string LOGOUT = 'logout';
    public const string REMEMBER_LOGIN = 'remember_login';
    public const string REMEMBER_THEFT = 'remember_theft';
    public const string REMEMBER_REVOKED = 'remember_revoked';
    public const string USER_CREATED = 'user_created';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(
        string $entityType,
        ?int $entityId,
        string $action,
        ?string $detail,
        ?string $ip,
        DateTimeImmutable $now,
    ): void {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO activity_log (entity_type, entity_id, action, detail, ip, created_at)
             VALUES (:entity_type, :entity_id, :action, :detail, :ip, :created_at)',
        );
        $statement->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'detail' => $detail,
            'ip' => $ip,
            'created_at' => Dates::toStorage($now),
        ]);
    }

    /** Kimlik doğrulama olayları için kısayol — `detail` her zaman e-postadır (docs İE#4 §5). */
    public function recordAuth(
        string $action,
        ?string $email,
        ?string $ip,
        DateTimeImmutable $now,
        ?int $userId = null,
    ): void {
        $this->record(self::ENTITY_AUTH, $userId, $action, $email, $ip, $now);
    }
}
