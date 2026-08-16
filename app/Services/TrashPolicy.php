<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Dates;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Çöp kutusu saklama kuralı (K15) — TEK yerde.
 *
 * Silme kaza korumasıdır: kayıt `TRASH_RETENTION_DAYS` gün geri alınabilir kalır.
 * Hem arayüzdeki "kalan gün" hem de purge betiği aynı hesabı kullanır ki
 * kullanıcıya "3 gün kaldı" derken betik onu silmesin.
 */
final class TrashPolicy
{
    public function __construct(private readonly int $retentionDays = 30)
    {
    }

    public function retentionDays(): int
    {
        return $this->retentionDays;
    }

    /** Bu andan önce silinmiş kayıtlar kalıcı silinebilir. */
    public function purgeThreshold(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify(sprintf('-%d days', $this->retentionDays));
    }

    /** Kalan gün (0 = süresi doldu). */
    public function daysLeft(string $deletedAt, DateTimeImmutable $now, DateTimeZone $timezone): int
    {
        $deleted = Dates::fromStorage($deletedAt, $timezone);
        $expiresAt = $deleted->modify(sprintf('+%d days', $this->retentionDays));

        $seconds = $expiresAt->getTimestamp() - $now->getTimestamp();
        if ($seconds <= 0) {
            return 0;
        }

        return (int) ceil($seconds / 86400);
    }
}
