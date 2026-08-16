<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * .env yükleyici ve tipli konfigürasyon erişimi.
 * Zorunlu anahtarlar eksikse uygulama anlaşılır bir hatayla durur (docs/04 §2d — sınırda doğrulama).
 */
final class Config
{
    /** Uygulamanın ayağa kalkması için .env'de mutlaka bulunması gereken anahtarlar. */
    private const array REQUIRED_KEYS = [
        'APP_ENV',
        'APP_URL',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'TZ',
    ];

    /** @param array<string, string> $values */
    public function __construct(private readonly array $values)
    {
        $this->assertRequiredKeys();
    }

    /** Proje kökündeki .env dosyasından konfigürasyon yükler. */
    public static function load(string $basePath): self
    {
        $dotenv = Dotenv::createImmutable($basePath);
        /** @var array<string, string> $values */
        $values = $dotenv->load();

        return new self($values);
    }

    public function get(string $key, ?string $default = null): string
    {
        $value = $this->values[$key] ?? $default;
        if ($value === null) {
            throw new RuntimeException(sprintf(
                'Konfigürasyon anahtarı eksik: "%s". .env dosyanızı .env.example ile karşılaştırın.',
                $key,
            ));
        }

        return $value;
    }

    public function getInt(string $key, ?int $default = null): int
    {
        $raw = $this->values[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($default === null) {
                throw new RuntimeException(sprintf('Konfigürasyon anahtarı eksik: "%s" (tam sayı bekleniyor).', $key));
            }

            return $default;
        }
        if (!is_numeric($raw)) {
            throw new RuntimeException(sprintf('Konfigürasyon anahtarı "%s" tam sayı olmalı, verilen: "%s".', $key, $raw));
        }

        return (int) $raw;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $raw = strtolower($this->values[$key] ?? '');
        if ($raw === '') {
            return $default;
        }

        return in_array($raw, ['1', 'true', 'on', 'yes'], true);
    }

    public function isProduction(): bool
    {
        return $this->get('APP_ENV') === 'production';
    }

    private function assertRequiredKeys(): void
    {
        $missing = [];
        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($this->values[$key]) || trim($this->values[$key]) === '') {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'Zorunlu konfigürasyon anahtarları eksik: %s. .env dosyanızı .env.example şablonuna göre doldurun.',
                implode(', ', $missing),
            ));
        }
    }
}
