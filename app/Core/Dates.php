<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * DATETIME sütunları ile docs/10 §1 (ISO 8601 +03:00) arasındaki tek dönüşüm noktası.
 * Veritabanında saat dilimi bilgisi taşınmaz; uygulama saat dilimi tek referanstır.
 */
final class Dates
{
    public const string STORAGE_FORMAT = 'Y-m-d H:i:s';

    public static function toStorage(DateTimeImmutable $moment): string
    {
        return $moment->format(self::STORAGE_FORMAT);
    }

    public static function fromStorage(string $stored, DateTimeZone $timezone): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat(self::STORAGE_FORMAT, $stored, $timezone);
        if ($parsed === false) {
            throw new RuntimeException(sprintf('Veritabanındaki tarih çözümlenemedi: "%s".', $stored));
        }

        return $parsed;
    }

    /** Depolanan DATETIME değerini API yanıtına uygun ISO 8601 dizesine çevirir. */
    public static function toIso(string $stored, DateTimeZone $timezone): string
    {
        return self::fromStorage($stored, $timezone)->format(DATE_ATOM);
    }
}
