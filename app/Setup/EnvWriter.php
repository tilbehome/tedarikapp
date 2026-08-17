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
    /**
     * @param bool|null $writableOverride Yalnızca test için: null → gerçek izin okunur.
     *                                    Windows'ta "yazılamayan klasör" üretmek güvenilir
     *                                    değil; K33 manuel akışı bu bayrakla sınanır.
     */
    public function __construct(
        private readonly string $basePath,
        private readonly ?bool $writableOverride = null,
    ) {
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
     * Uygulama kökü yazılabilir mi? (K33 — üretimde PHP `nobody` ile çalışıyor ve yazamıyor.)
     */
    public function canWrite(): bool
    {
        return $this->writableOverride ?? is_writable($this->basePath);
    }

    /**
     * `.env` içeriğini ÜRETİR — diske yazmaz.
     *
     * Yazılamayan sunucularda (K33) bu içerik kullanıcıya gösterilir; kullanıcı cPanel
     * Dosya Yöneticisi'nden kaydeder, sihirbaz sonra dosyayı okuyup doğrular.
     *
     * @param array{host: string, port: int, name: string, user: string, pass: string} $database
     *
     * @return array{content: string, app_key: string}
     */
    public function generate(string $appUrl, #[SensitiveParameter] array $database): array
    {
        $template = @file_get_contents($this->basePath . '/.env.example');
        if ($template === false) {
            throw new RuntimeException('.env.example okunamadı — kurulum paketi eksik olabilir.');
        }

        $appKey = self::generateAppKey();
        $values = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'APP_KEY' => $appKey,
            'DB_HOST' => $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_NAME' => $database['name'],
            'DB_USER' => $database['user'],
            'DB_PASS' => $database['pass'],
            'EXTENSION_TOKEN_SALT' => self::generateExtensionTokenSalt(),
            // K33: üretimde diske yazılamadığı için loglar veritabanına gider.
            'LOG_DRIVER' => 'db',
        ];

        $content = $template;
        foreach ($values as $key => $value) {
            $content = $this->setValue($content, $key, $value);
        }

        return ['content' => $content, 'app_key' => $appKey];
    }

    /**
     * @param array{host: string, port: int, name: string, user: string, pass: string} $database
     *
     * @return string Üretilen APP_KEY (doğrulama için çağırana döner)
     *
     * @throws RuntimeException `.env` zaten varsa — HTTP kurulum akışı mevcut dosyanın
     *                          üzerine ASLA yazmaz (K37 §A2). Yeniden kurulum için
     *                          dosyanın sunucudan elle silinmesi gerekir.
     */
    public function write(string $appUrl, #[SensitiveParameter] array $database): string
    {
        if ($this->exists()) {
            throw new RuntimeException(
                '.env dosyası zaten mevcut — kurulum sihirbazı mevcut yapılandırmanın üzerine yazmaz (K37). '
                . 'Yeniden kurulum gerekiyorsa dosyayı sunucudan elle silin.',
            );
        }

        $generated = $this->generate($appUrl, $database);
        $this->putAtomically($generated['content']);

        return $generated['app_key'];
    }

    /**
     * Elle kaydedilen `.env` gerçekten yerinde mi ve BİZİM ürettiğimiz dosya mı?
     *
     * APP_KEY karşılaştırması, kullanıcının içeriği eksik yapıştırmasını veya başka bir
     * dosyayı kaydetmesini yakalar — dosyanın varlığına bakmak yeterli değildir.
     */
    public function verify(#[SensitiveParameter] string $expectedAppKey): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $content = @file_get_contents($this->path());
        if ($content === false) {
            return false;
        }

        if (preg_match('/^APP_KEY=("?)([0-9a-f]{64})\1\s*$/m', $content, $matches) !== 1) {
            return false;
        }

        return hash_equals($expectedAppKey, $matches[2]);
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
