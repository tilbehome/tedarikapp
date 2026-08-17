<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Yapılandırma erişimi — İKİ kaynak, tek arayüz (K44, İE#9.4):
 *
 *  1. **Dosya (birincil: `config.php`, geriye dönük: `.env`):** yalnız DB erişimi +
 *     APP_KEY dosyada yaşamak ZORUNDA (kilidin anahtarı kilidin içinde olamaz).
 *     config.php WordPress `wp-config.php` modelidir: saf PHP, `return [...]`.
 *  2. **Veritabanı (`settings` tablosu):** diğer TÜM ayarlar (APP_URL, LOG_*, MEDIA_*,
 *     SESSION_*, TZ …) buradan okunur — disksiz mod. `attachSettings()` ile bağlanır;
 *     okuma TEMBEL ve tek seferliktir, DB'ye ulaşılamazsa dosya/varsayılan devreye girer.
 *
 * Öncelik: settings (DB) → dosya değerleri → çağrı yerindeki varsayılan.
 * GÜVENLİK: DB_*, APP_KEY ve APP_ENV asla DB'den OKUNMAZ (önyükleme + sır sınırı).
 */
final class Config
{
    /** Uygulamanın ayağa kalkması için dosyada mutlaka bulunması gereken anahtarlar. */
    private const REQUIRED_KEYS = [
        'APP_ENV',
        'APP_URL',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'TZ',
    ];

    /** DB'deki settings tablosundan ASLA okunmayacak anahtarlar (önyükleme + sır). */
    private const FILE_ONLY_KEYS = ['APP_ENV', 'APP_KEY', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'EXTENSION_TOKEN_SALT'];

    /** config.php yalnız DB+APP_KEY taşır; kalan zorunlular bu varsayılanlarla dolar (K44). */
    private const CONFIG_PHP_DEFAULTS = [
        'APP_ENV' => 'production',
        'TZ' => 'Europe/Istanbul',
        // Gerçek APP_URL settings tablosundan gelir (kurulumda yazılır); bu yer tutucu
        // yalnız "zorunlu anahtar" denetimini geçirir ve https varsayımıyla GÜVENLİ taraftadır.
        'APP_URL' => 'https://localhost',
        'LOG_DRIVER' => 'db',
    ];

    /** @var callable(): array<string, string>|null */
    private $settingsLoader = null;

    /** @var array<string, string>|null tembel yüklenen DB ayarları */
    private ?array $settings = null;

    /** @param array<string, string> $values */
    public function __construct(private readonly array $values)
    {
        $this->assertRequiredKeys();
        if ($this->isProduction()) {
            $this->assertProductionSecrets();
        }
    }

    /**
     * DB ayar katmanını bağlar (İE#9.4). Yükleyici İLK okumada bir kez çağrılır;
     * fırlatırsa (DB kapalı, tablo yok) sessizce dosya/varsayılana düşülür.
     *
     * @param callable(): array<string, string> $loader
     */
    public function attachSettings(callable $loader): void
    {
        $this->settingsLoader = $loader;
        $this->settings = null;
    }

    private function lookup(string $key): ?string
    {
        if ($this->settingsLoader !== null && !in_array($key, self::FILE_ONLY_KEYS, true)) {
            if ($this->settings === null) {
                try {
                    $this->settings = ($this->settingsLoader)();
                } catch (\Throwable) {
                    $this->settings = []; // DB'siz an: dosya/varsayılan yeterli
                }
            }
            if (isset($this->settings[$key]) && trim($this->settings[$key]) !== '') {
                return $this->settings[$key];
            }
        }

        return $this->values[$key] ?? null;
    }

    /**
     * Proje kökündeki .env dosyasından konfigürasyon yükler.
     *
     * `createArrayBacked` kullanılır, `createImmutable` DEĞİL — iki sebeple:
     *
     * 1. **Tekrar çağrılabilirlik:** immutable adaptör değerleri `$_ENV`/`putenv` üzerine
     *    yazar ve İKİNCİ `load()` çağrısında BOŞ dizi döner (zaten yüklü sayar). Aynı
     *    istekte iki kez Config kuran her yol (kurulum kilidi + controller) sessizce
     *    "zorunlu anahtar eksik" hatası alırdı — canlı testte bu şekilde yakalandı.
     * 2. **Sır hijyeni:** değerler süreç ortamına sızmaz; `getenv()`, `phpinfo()` veya
     *    bir hata dökümü DB_PASS ve APP_KEY'i göstermez (CLAUDE.md §5).
     */
    public static function load(string $basePath): self
    {
        // K44: birincil kaynak config.php (WordPress modeli); .env geriye dönük desteklenir.
        $configFile = $basePath . '/config.php';
        if (is_file($configFile)) {
            /** @var mixed $raw */
            $raw = require $configFile;
            if (!is_array($raw)) {
                throw new RuntimeException('config.php bir dizi döndürmeli (return [...]).');
            }
            $values = self::CONFIG_PHP_DEFAULTS;
            foreach ($raw as $key => $value) {
                if (is_string($key) && (is_string($value) || is_int($value))) {
                    $values[$key] = (string) $value;
                }
            }

            return new self($values);
        }

        $dotenv = Dotenv::createArrayBacked($basePath);
        /** @var array<string, string> $values */
        $values = $dotenv->load();

        return new self($values);
    }

    public function get(string $key, ?string $default = null): string
    {
        $value = $this->lookup($key) ?? $default;
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
        $raw = $this->lookup($key);
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
        $raw = strtolower($this->lookup($key) ?? '');
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

        // K44: config.php modeli tuz taşımaz (Faz 3'te settings'e üretilecek);
        // legacy .env'de anahtar VARSA biçimi yine denetlenir.
        if (array_key_exists('EXTENSION_TOKEN_SALT', $this->values)) {
            $salt = trim($this->values['EXTENSION_TOKEN_SALT']);
            if (strlen($salt) < 32) {
                $problems[] = 'EXTENSION_TOKEN_SALT en az 32 karakter olmalı';
            }
        }

        if ($problems !== []) {
            throw new RuntimeException(sprintf(
                "Üretim ortamı konfigürasyonu eksik veya geçersiz:\n- %s",
                implode("\n- ", $problems),
            ));
        }
    }
}
