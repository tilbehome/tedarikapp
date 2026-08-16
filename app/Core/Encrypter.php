<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use SensitiveParameter;

/**
 * TOTP secret'ı gibi GERİ OKUNMASI gereken sırlar için simetrik şifreleme (K16, docs/04 §2).
 * Şifreler hash'lenir (PasswordHasher), secret'lar şifrelenir — ikisi karıştırılmaz.
 *
 * Algoritma: AES-256-GCM (kimlik doğrulamalı şifreleme). Anahtar .env'deki APP_KEY'dir;
 * APP_KEY değişirse mevcut TOTP secret'ları çözülemez (2FA yeniden kurulur).
 */
final class Encrypter
{
    private const string CIPHER = 'aes-256-gcm';
    private const int IV_LENGTH = 12;
    private const int TAG_LENGTH = 16;

    private ?string $key = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function encrypt(#[SensitiveParameter] string $plaintext): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);
        if ($ciphertext === false) {
            throw new RuntimeException('Şifreleme başarısız oldu.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('Şifreli veri bozuk: çözülemedi.');
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException('Şifreli veri doğrulanamadı: anahtar değişmiş veya veri bozulmuş olabilir.');
        }

        return $plaintext;
    }

    /** Anahtar tembel çözülür: auth kullanılmayan isteklerde APP_KEY zorunlu değildir. */
    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $appKey = $this->config->get('APP_KEY', '');
        if (preg_match('/^[0-9a-f]{64}$/i', $appKey) !== 1) {
            throw new RuntimeException(
                'APP_KEY 64 haneli onaltılık bir dize olmalı. Üretmek için: php -r "echo bin2hex(random_bytes(32));"',
            );
        }

        $binary = hex2bin($appKey);
        if ($binary === false) {
            throw new RuntimeException('APP_KEY çözümlenemedi.');
        }

        return $this->key = $binary;
    }
}
