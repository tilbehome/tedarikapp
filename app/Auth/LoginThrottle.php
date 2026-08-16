<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Connection;
use App\Core\Dates;
use App\Services\ActivityLog;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Giriş denemesi kısıtlayıcısı (K16, İE#4 §3): IP + e-posta anahtarıyla artan bekleme.
 *
 * Sayaç ayrı bir tabloda değil `activity_log` üzerinde yaşar — böylece kilit mantığı ile
 * denetim kaydı tek gerçeğe dayanır ve E9 aktivite ekranı kilitlenmeleri de gösterir.
 *
 * Kural: son BAŞARILI girişten bu yana biriken hatalı deneme sayısı `maxAttempts`'e ulaşınca
 * kilit başlar; her ek hatalı deneme bekleme süresini ikiye katlar (üst sınır 60 dakika).
 * Başarılı giriş sayacı sıfırlar (sorgu penceresi son başarıdan sonrasını sayar).
 */
final class LoginThrottle
{
    private const int MAX_LOCK_MINUTES = 60;

    /** Kilide sayılan olaylar: şifre, TOTP ve kurtarma kodu hataları birlikte değerlendirilir. */
    private const array FAILURE_ACTIONS = [
        ActivityLog::LOGIN_FAILED,
        ActivityLog::TOTP_FAILED,
        ActivityLog::RECOVERY_FAILED,
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly DateTimeZone $timezone,
        private readonly int $maxAttempts,
        private readonly int $baseLockMinutes,
    ) {
    }

    /**
     * Bu anahtar için kalan bekleme süresi (saniye). 0 → istek işlenebilir.
     */
    public function retryAfterSeconds(string $email, string $ip, DateTimeImmutable $now): int
    {
        $failures = $this->failuresSinceLastSuccess($email, $ip);
        $count = count($failures);
        if ($count < $this->maxAttempts) {
            return 0;
        }

        $lastFailure = Dates::fromStorage($failures[$count - 1], $this->timezone);
        $lockUntil = $lastFailure->modify(sprintf('+%d minutes', $this->lockMinutes($count)));
        $remaining = $lockUntil->getTimestamp() - $now->getTimestamp();

        return max(0, $remaining);
    }

    /** Kilit süresi: ilk aşımda taban süre, sonraki her hatada iki katı (üst sınır 60 dk). */
    private function lockMinutes(int $failureCount): int
    {
        $steps = $failureCount - $this->maxAttempts; // 0 → ilk kilit
        $minutes = $this->baseLockMinutes;
        for ($i = 0; $i < $steps && $minutes < self::MAX_LOCK_MINUTES; $i++) {
            $minutes *= 2;
        }

        return min($minutes, self::MAX_LOCK_MINUTES);
    }

    /**
     * Son başarılı girişten sonraki hatalı denemelerin zaman damgaları (eskiden yeniye).
     *
     * @return list<string>
     */
    private function failuresSinceLastSuccess(string $email, string $ip): array
    {
        $pdo = $this->connection->pdo();

        $successStatement = $pdo->prepare(
            'SELECT MAX(created_at) AS last_success FROM activity_log
             WHERE entity_type = :entity_type AND action = :action AND detail = :detail AND ip = :ip',
        );
        $successStatement->execute([
            'entity_type' => ActivityLog::ENTITY_AUTH,
            'action' => ActivityLog::LOGIN_SUCCESS,
            'detail' => $email,
            'ip' => $ip,
        ]);
        $successRow = $successStatement->fetch();
        $lastSuccess = is_array($successRow) && is_string($successRow['last_success'])
            ? $successRow['last_success']
            : '0000-00-00 00:00:00';

        $placeholders = [];
        $params = [
            'entity_type' => ActivityLog::ENTITY_AUTH,
            'detail' => $email,
            'ip' => $ip,
            'since' => $lastSuccess,
        ];
        foreach (self::FAILURE_ACTIONS as $index => $action) {
            $placeholders[] = ':action' . $index;
            $params['action' . $index] = $action;
        }

        $failureStatement = $pdo->prepare(sprintf(
            'SELECT created_at FROM activity_log
             WHERE entity_type = :entity_type AND action IN (%s) AND detail = :detail AND ip = :ip
               AND created_at > :since
             ORDER BY created_at, id',
            implode(', ', $placeholders),
        ));
        $failureStatement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $failureStatement->fetchAll();

        return array_map(static fn (array $row): string => (string) $row['created_at'], $rows);
    }
}
