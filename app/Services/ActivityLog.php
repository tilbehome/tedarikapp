<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Connection;
use App\Core\Dates;
use App\Core\RequestContext;
use DateTimeImmutable;

/**
 * activity_log yazıcısı (docs/04 §2, K15/K16).
 *
 * GÜVENLİK KURALI: `detail` alanına şifre, TOTP kodu, kurtarma kodu veya token ASLA yazılmaz —
 * yalnızca kimin/nereden/ne yaptığı yazılır (İE#4 §5).
 */
final class ActivityLog
{
    public const ENTITY_AUTH = 'auth';

    /** Eylemi kimin yaptığı (K25): panel kullanıcısı, Chrome eklentisi veya sistemin kendisi. */
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_EXTENSION = 'extension';
    public const ACTOR_SYSTEM = 'system';

    public const LOGIN_SUCCESS = 'login_success';
    public const LOGIN_FAILED = 'login_failed';
    public const LOGIN_LOCKED = 'login_locked';
    public const TOTP_SUCCESS = 'totp_success';
    public const TOTP_FAILED = 'totp_failed';
    public const RECOVERY_USED = 'recovery_used';
    public const RECOVERY_FAILED = 'recovery_failed';
    public const LOGOUT = 'logout';
    public const REMEMBER_LOGIN = 'remember_login';
    public const REMEMBER_THEFT = 'remember_theft';
    public const REMEMBER_REVOKED = 'remember_revoked';
    public const USER_CREATED = 'user_created';

    // v1.2.2 B2 — APP_KEY emaneti. Başarılı ve BAŞARISIZ deneme ayrı ayrı
    // yazılır: anahtarı almaya çalışan birinin izi, alanınki kadar değerlidir.
    public const APP_KEY_REVEALED = 'app_key_revealed';
    public const APP_KEY_REVEAL_FAILED = 'app_key_reveal_failed';

    public function __construct(
        private readonly Connection $connection,
        private readonly ?RequestContext $requestContext = null,
    ) {
    }

    public function record(
        string $entityType,
        ?int $entityId,
        string $action,
        ?string $detail,
        ?string $ip,
        DateTimeImmutable $now,
        string $actorType = self::ACTOR_ADMIN,
        ?int $actorId = null,
    ): int {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO activity_log
                (entity_type, entity_id, action, detail, ip, actor_type, actor_id, request_id, user_agent, created_at)
             VALUES
                (:entity_type, :entity_id, :action, :detail, :ip, :actor_type, :actor_id, :request_id, :user_agent, :created_at)',
        );
        $statement->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'detail' => $detail,
            'ip' => $ip,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'request_id' => $this->requestContext?->id(),
            'user_agent' => $this->requestContext?->userAgent(),
            'created_at' => Dates::toStorage($now),
        ]);

        // V3-B A1: yazılan satırın kimliği DÖNER. Birleştirmesi kapalı
        // bildirimler "değiştirilemez audit bağlantısı" ister (katalog
        // sözleşmesi); bağlantı ancak buradan alınabilir. Dönüş değerini
        // kullanmayan eski çağrılar etkilenmez.
        return (int) $this->connection->pdo()->lastInsertId();
    }

    /** Kimlik doğrulama olayları için kısayol — `detail` her zaman e-postadır (docs İE#4 §5). */
    public function recordAuth(
        string $action,
        ?string $email,
        ?string $ip,
        DateTimeImmutable $now,
        ?int $userId = null,
    ): void {
        $this->record(self::ENTITY_AUTH, $userId, $action, $email, $ip, $now, self::ACTOR_ADMIN, $userId);
    }
}
