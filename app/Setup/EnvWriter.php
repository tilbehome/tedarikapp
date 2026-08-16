<?php

declare(strict_types=1);

namespace App\Setup;

use RuntimeException;
use SensitiveParameter;

/**
 * `.env` üretimi (İE#5 §11c).
 *
 * Şablon `.env.example`'dır: yorum satırları ve alan belgeleri korunur, yalnızca
 * değerler doldurulur. Böylece kurulan sistemde de her ayarın ne işe yaradığı yazılı kalır.
 *
 * GÜVENLİK:
 *  • APP_KEY ve EXTENSION_TOKEN_SALT kriptografik rastgele üretilir (tahmin edilemez).
 *  • Dosya izni 0600'e daraltılır — aynı sunucudaki başka hesaplar okuyamasın.
 *  • Yazma atomiktir; yarım .env uygulamayı açılışta anlaşılmaz hatayla düşürürdü.
 *  • Bu sınıf hiçbir değeri loglamaz.
 */
final class EnvWriter
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function path(): string
    {
        return $this->basePath . '/.env';
    }

    public static function generateAppKey(): string
    {
        return bin2hex(random_bytes(32)); // 64 hex hane — Encrypter'ın beklediği biçim
    }

    public static function generateExtensionTokenSalt(): string
    {
        return bin2hex(random_bytes(32)); // 64 karakter, asgari 32 şartının iki katı
    }

    /**
     * @param array{host: string, port: int, name: string, user: string, pass: string} $database
     */
    public function write(string $appUrl, #[SensitiveParameter] array $database): void
    {
        $template = @file_get_contents($this->basePath . '/.env.example');
        if ($template === false) {
            throw new RuntimeException('.env.example okunamadı — kurulum paketi eksik olabilir.');
        }

        $values = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'APP_KEY' => self::generateAppKey(),
            'DB_HOST' => $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_NAME' => $database['name'],
            'DB_USER' => $database['user'],
            'DB_PASS' => $database['pass'],
            'EXTENSION_TOKEN_SALT' => self::generateExtensionTokenSalt(),
        ];

        $content = $template;
        foreach ($values as $key => $value) {
            $content = $this->setValue($content, $key, $value);
        }

        $this->putAtomically($content);
    }

    /** Anahtar şablonda varsa değeri değiştirir, yoksa sonuna ekler. */
    private function setValue(string $content, string $key, string $value): string
    {
        $line = $key . '=' . $this->quote($value);
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content) === 1) {
            $replaced = preg_replace($pattern, str_replace('\\', '\\\\', $line), $content, 1);

            return $replaced ?? $content;
        }

        return rtrim($content, "\n") . "\n" . $line . "\n";
    }

    /** Boşluk veya özel karakter içeren değerler tırnaklanır (.env.example kuralı). */
    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9._\/:@-]+$/', $value) === 1) {
            return $value;
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function putAtomically(#[SensitiveParameter] string $content): void
    {
        $temporary = $this->path() . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new RuntimeException(
                'Uygulama kökü yazılabilir değil, .env oluşturulamadı. '
                . 'cPanel > Dosya Yöneticisi\'nden klasöre geçici yazma izni verin.',
            );
        }
        @chmod($temporary, 0600);

        if (!@rename($temporary, $this->path())) {
            @unlink($temporary);

            throw new RuntimeException('.env dosyası yerine taşınamadı.');
        }

        @chmod($this->path(), 0600);
    }
}
