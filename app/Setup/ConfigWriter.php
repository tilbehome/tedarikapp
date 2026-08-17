<?php

declare(strict_types=1);

namespace App\Setup;

use RuntimeException;
use SensitiveParameter;

/**
 * `config.php` üretimi (K44 — WordPress `wp-config.php` modeli, İE#9.4).
 *
 * Dosyada YALNIZ iki şey yaşar: veritabanı erişimi + APP_KEY — çünkü DB'ye
 * bağlanmak için kimliği bir yerden okumak zorunludur (kilidin anahtarı kilidin
 * içinde olamaz). DİĞER TÜM ayarlar `settings` tablosundadır (disksiz mod).
 *
 * `.env` GERİYE DÖNÜK okunur (Config::load) ama artık birincil değildir;
 * sihirbaz yalnız config.php üretir.
 *
 * Yazılamayan kökte (üretim: `nobody` + yazılamaz docroot) içerik ekranda
 * gösterilir, kullanıcı File Manager ile `config.php` olarak kaydeder,
 * doğrulama adımı APP_KEY eşleşmesiyle dosyanın bizim içerik olduğunu anlar.
 */
final class ConfigWriter
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?bool $writableOverride = null,
    ) {
    }

    public function path(): string
    {
        return $this->basePath . '/config.php';
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /** Geriye dönük: eski kurulumların .env'i de "yapılandırılmış" sayılır. */
    public function legacyEnvExists(): bool
    {
        return is_file($this->basePath . '/.env');
    }

    /** Sistem yapılandırılmış mı? (config.php VEYA legacy .env) — kurulum kilidi katmanı. */
    public function configured(): bool
    {
        return $this->exists() || $this->legacyEnvExists();
    }

    public function canWrite(): bool
    {
        return $this->writableOverride ?? is_writable($this->basePath);
    }

    public static function generateAppKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * config.php içeriğini ÜRETİR — diske yazmaz (K33 manuel akışıyla aynı model).
     *
     * @param array{host: string, port: int, name: string, user: string, pass: string} $database
     *
     * @return array{content: string, app_key: string}
     */
    public function generate(#[SensitiveParameter] array $database): array
    {
        $appKey = self::generateAppKey();

        $content = "<?php\n\n"
            . "/**\n"
            . " * tedarikapp yapılandırması (K44 — WordPress wp-config.php modeli).\n"
            . " *\n"
            . " * Burada YALNIZ veritabanı erişimi ve APP_KEY bulunur; diğer tüm ayarlar\n"
            . " * veritabanındaki `settings` tablosunda yaşar ve panelden yönetilir.\n"
            . " * Bu dosyayı yedekleyin — APP_KEY kaybolursa 2FA secret'ları çözülemez.\n"
            . " * Dosya asla web'den erişilebilir olmamalı (kök .htaccess public/ dışını kapatır).\n"
            . " */\n\n"
            . "return [\n"
            . "    'DB_HOST' => " . var_export($database['host'], true) . ",\n"
            . "    'DB_PORT' => " . var_export((string) $database['port'], true) . ",\n"
            . "    'DB_NAME' => " . var_export($database['name'], true) . ",\n"
            . "    'DB_USER' => " . var_export($database['user'], true) . ",\n"
            . "    'DB_PASS' => " . var_export($database['pass'], true) . ",\n"
            . "    'APP_KEY' => " . var_export($appKey, true) . ",\n"
            . "];\n";

        return ['content' => $content, 'app_key' => $appKey];
    }

    /**
     * Kök yazılabilirse dosyayı atomik yazar; mevcut config.php'nin üzerine ASLA yazmaz (K37 §A2).
     *
     * @param array{host: string, port: int, name: string, user: string, pass: string} $database
     *
     * @return string üretilen APP_KEY
     */
    public function write(#[SensitiveParameter] array $database): string
    {
        if ($this->exists()) {
            throw new RuntimeException(
                'config.php zaten mevcut — kurulum sihirbazı mevcut yapılandırmanın üzerine yazmaz (K37). '
                . 'Yeniden kurulum gerekiyorsa dosyayı sunucudan elle silin.',
            );
        }

        $generated = $this->generate($database);

        $temporary = $this->path() . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($temporary, $generated['content'], LOCK_EX) === false) {
            throw new RuntimeException(
                'Uygulama kökü yazılabilir değil, config.php oluşturulamadı. '
                . 'İçeriği ekrandan kopyalayıp File Manager ile "config.php" adıyla kaydedin.',
            );
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $this->path())) {
            @unlink($temporary);

            throw new RuntimeException('config.php dosyası yerine taşınamadı.');
        }
        @chmod($this->path(), 0600);

        return $generated['app_key'];
    }

    /**
     * Elle kaydedilen config.php BİZİM ürettiğimiz içerik mi? (APP_KEY karşılaştırması)
     */
    public function verify(#[SensitiveParameter] string $expectedAppKey): bool
    {
        $appKey = self::readAppKey($this->path());

        return $appKey !== null && hash_equals($expectedAppKey, $appKey);
    }

    /** config.php'den APP_KEY okur (dosya yoksa/deforme ise null). */
    public static function readAppKey(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        try {
            /** @var mixed $values */
            $values = require $path;
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($values) || !is_string($values['APP_KEY'] ?? null)) {
            return null;
        }

        $appKey = trim($values['APP_KEY']);

        return preg_match('/^[0-9a-f]{64}$/i', $appKey) === 1 ? $appKey : null;
    }
}
