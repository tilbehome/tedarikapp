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
        if ($this->isProduction()) {
            $this->assertProductionSecrets();
        }
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

    /**
     * Katı tam sayı okuma (K27): `is_numeric` yetmez — "1.5" ve "12abc" gibi değerler
     * sessizce 1 ve 12'ye kırpılır ve yanlış zaman aşımı/limit değerleriyle çalışılır.
     */
    public function getInt(string $key, ?int $default = null): int
    {
        $raw = $this->values[$key] ?? null;
        if ($raw === null || trim($raw) === '') {
            if ($default === null) {
                throw new RuntimeException(sprintf('Konfigürasyon anahtarı eksik: "%s" (tam sayı bekleniyor).', $key));
            }

            return $default;
        }

        $trimmed = trim($raw);
        if (preg_match('/^-?\d+$/', $trimmed) !== 1) {
            throw new RuntimeException(sprintf(
                'Konfigürasyon anahtarı "%s" tam sayı olmalı, verilen: "%s".',
                $key,
                $raw,
            ));
        }

        return (int) $trimmed;
    }

    /** Süre, deneme sayısı ve boyut gibi 0'dan büyük olması gereken ayarlar için. */
    public function getPositiveInt(string $key, ?int $default = null): int
    {
        $value = $this->getInt($key, $default);
        if ($value <= 0) {
            throw new RuntimeException(sprintf(
                'Konfigürasyon anahtarı "%s" 0\'dan büyük olmalı, verilen: %d.',
                $key,
                $value,
            ));
        }

        return $value;
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

    /**
     * Üretimde sır zorunlulukları (K27).
     *
     * APP_KEY olmadan TOTP secret'ı çözülemez, EXTENSION_TOKEN_SALT olmadan eklenti
     * token'ı üretilemez. Bunların eksikliği geliştirmede fark edilmeyip üretimde
     * çalışma anında patlamasın diye uygulama AÇILIŞTA durur.
     * Yerelde (APP_ENV != production) opsiyoneldir — kurulum sihirbazı öncesi durum İE#5'te.
     */
    private function assertProductionSecrets(): void
    {
        $problems = [];

        $appKey = $this->values['APP_KEY'] ?? '';
        if (preg_match('/^[0-9a-f]{64}$/i', trim($appKey)) !== 1) {
            $problems[] = 'APP_KEY 64 haneli onaltılık bir dize olmalı (üretmek için: php -r "echo bin2hex(random_bytes(32));")';
        }

        $salt = trim($this->values['EXTENSION_TOKEN_SALT'] ?? '');
        if (strlen($salt) < 32) {
            $problems[] = 'EXTENSION_TOKEN_SALT en az 32 karakter olmalı';
        }

        if ($problems !== []) {
            throw new RuntimeException(sprintf(
                "Üretim ortamı konfigürasyonu eksik veya geçersiz:\n- %s",
                implode("\n- ", $problems),
            ));
        }
    }
}
