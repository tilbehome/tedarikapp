<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Durdurulmuş saat — giriş kilidi (backoff), oturum aşımı ve token ömrü testleri
 * gerçek zamanı beklemeden koşabilsin diye.
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $moment = '2026-08-16 10:00:00', string $timezone = 'Europe/Istanbul')
    {
        $this->now = new DateTimeImmutable($moment, new DateTimeZone($timezone));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /** Örn. `+16 minutes`, `+31 days`. */
    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
