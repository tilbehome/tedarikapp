<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * ULID üreteci — 26 karakterlik, zaman sıralı tekil kimlik (`activity_log.request_id` CHAR(26)).
 *
 * UUIDv4 yerine ULID: aynı tekillik gücünü verirken sözlüksel sıralaması zaman sırasıyla
 * aynıdır — log ve activity_log kayıtları request_id'ye göre sıralandığında kronolojik gelir.
 *
 * Yapı: 48 bit zaman damgası (ms) + 80 bit rastgelelik, Crockford Base32 ile kodlanır.
 */
final class Ulid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(?DateTimeImmutable $moment = null): string
    {
        $milliseconds = $moment === null
            ? (int) round(microtime(true) * 1000)
            : (int) round((float) $moment->format('U.u') * 1000);

        return self::encodeTime($milliseconds) . self::encodeRandomness();
    }

    /** 48 bit zaman → 10 karakter. */
    private static function encodeTime(int $milliseconds): string
    {
        $encoded = '';
        for ($i = 0; $i < 10; $i++) {
            $encoded = self::ALPHABET[$milliseconds % 32] . $encoded;
            $milliseconds = intdiv($milliseconds, 32);
        }

        return $encoded;
    }

    /** 80 bit rastgelelik → 16 karakter. */
    private static function encodeRandomness(): string
    {
        $encoded = '';
        for ($i = 0; $i < 16; $i++) {
            $encoded .= self::ALPHABET[random_int(0, 31)];
        }

        return $encoded;
    }
}
