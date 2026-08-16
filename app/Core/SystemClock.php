<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Gerçek sistem saati — uygulama saat dilimini (.env TZ) kullanır.
 */
final class SystemClock implements Clock
{
    public function __construct(private readonly DateTimeZone $timezone)
    {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(new DateTimeZone($config->get('TZ', 'Europe/Istanbul')));
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
