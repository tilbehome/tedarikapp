<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use SensitiveParameter;

/**
 * TOTP secret'ı gibi GERİ OKUNMASI gereken sırlar için simetrik şifreleme (K16/K27, docs/04 §2).
 * Şifreler hash'lenir (PasswordHasher), secret'lar şifrelenir — ikisi karıştırılmaz.
 *
 * **Algoritma (K27):** öncelik libsodium `crypto_secretbox` (XSalsa20-Poly1305); eklenti yoksa
 * OpenSSL AES-256-GCM'e düşülür. İkisi de kimlik doğrulamalı şifrelemedir: kurcalanan veri
 * çözülmez, sessizce bozuk sonuç dönmez.
 *
 * **Anahtar:** APP_KEY'den HKDF-SHA256 ile sabit bir bilgi etiketi (`info`) kullanılarak türetilir.
 * APP_KEY doğrudan kullanılmaz — aynı ana anahtardan başka amaçlar için (ör. paylaşım token'ı)
 * birbirinden bağımsız alt anahtarlar türetilebilsin diye.
 *
 * **Kayıt formatı:** `<sürüm>:<nonce base64>:<ciphertext base64>`
 *   • `v1s` → sodium crypto_secretbox
 *   • `v1a` → AES-256-GCM (ciphertext'in başında 16 baytlık doğrulama etiketi)
 * Sürüm etiketi kayda gömülüdür: ileride algoritma veya anahtar değiştiğinde eski kayıtlar
 * okunmaya devam eder, yenileri yeni sürümle yazılır (anahtar rotasyonuna hazır).
 */
final class Encrypter
{
    private const string VERSION_SODIUM = 'v1s';
    private const string VERSION_AES_GCM = 'v1a';

    /** HKDF bilgi etiketi — amaca özel alt anahtar üretir. */
    private const string KEY_INFO = 'tedarikapp:totp-secret:v1';

    private const string AES_CIPHER = 'aes-256-gcm';
    private const int AES_IV_LENGTH = 12;
    private const int AES_TAG_LENGTH = 16;

    private ?string $key = null;

    private readonly bool $useSodium;

    /**
     * @param bool|null $useSodium Yalnızca test için: null → sunucuda ne varsa o kullanılır.
     *                             Testler yedek yolun (AES-GCM) da çalıştığını bu bayrakla doğrular.
     */
    public function __construct(
        private readonly Config $config,
        ?bool $useSodium = null,
    ) {
        $this->useSodium = $useSodium ?? self::sodiumAvailable();
    }

    /** Sunucuda libsodium var mı — kurulum denetimi ve raporlama için (K27). */
    public static function sodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox');
    }

    public function encrypt(#[SensitiveParameter] string $plaintext): string
    {
        if ($this->useSodium) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key());

            return self::pack(self::VERSION_SODIUM, $nonce, $ciphertext);
        }

        $iv = random_bytes(self::AES_IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::AES_CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::AES_TAG_LENGTH,
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Şifreleme başarısız oldu.');
        }

        return self::pack(self::VERSION_AES_GCM, $iv, $tag . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        [$version, $nonce, $ciphertext] = self::unpack($payload);

        return match ($version) {
            self::VERSION_SODIUM => $this->decryptSodium($nonce, $ciphertext),
            self::VERSION_AES_GCM => $this->decryptAesGcm($nonce, $ciphertext),
            default => throw new RuntimeException(sprintf(
                'Bilinmeyen şifreleme sürümü: "%s". Kayıt daha yeni bir sürümle yazılmış olabilir.',
                $version,
            )),
        };
    }

    private function decryptSodium(string $nonce, string $ciphertext): string
    {
        if (!self::sodiumAvailable()) {
            throw new RuntimeException(
                'Bu kayıt libsodium ile şifrelenmiş ama sunucuda sodium eklentisi yok. '
                . 'Eklentiyi etkinleştirin veya 2FA\'yı yeniden kurun.',
            );
        }
        if (strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Şifreli veri bozuk: nonce uzunluğu geçersiz.');
        }

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key());
        if ($plaintext === false) {
            throw new RuntimeException('Şifreli veri doğrulanamadı: anahtar değişmiş veya veri bozulmuş olabilir.');
        }

        return $plaintext;
    }

    private function decryptAesGcm(string $iv, string $tagAndCiphertext): string
    {
        if (strlen($iv) !== self::AES_IV_LENGTH || strlen($tagAndCiphertext) <= self::AES_TAG_LENGTH) {
            throw new RuntimeException('Şifreli veri bozuk: çözülemedi.');
        }

        $tag = substr($tagAndCiphertext, 0, self::AES_TAG_LENGTH);
        $ciphertext = substr($tagAndCiphertext, self::AES_TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::AES_CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException('Şifreli veri doğrulanamadı: anahtar değişmiş veya veri bozulmuş olabilir.');
        }

        return $plaintext;
    }

    private static function pack(string $version, string $nonce, string $ciphertext): string
    {
        return $version . ':' . base64_encode($nonce) . ':' . base64_encode($ciphertext);
    }

    /** @return array{string, string, string} sürüm, nonce, ciphertext */
    private static function unpack(string $payload): array
    {
        $parts = explode(':', $payload, 3);
        if (count($parts) !== 3) {
            throw new RuntimeException('Şifreli veri bozuk: beklenen biçim "sürüm:nonce:ciphertext".');
        }

        $nonce = base64_decode($parts[1], true);
        $ciphertext = base64_decode($parts[2], true);
        if ($nonce === false || $ciphertext === false || $nonce === '' || $ciphertext === '') {
            throw new RuntimeException('Şifreli veri bozuk: base64 çözülemedi.');
        }

        return [$parts[0], $nonce, $ciphertext];
    }

    /**
     * Anahtar tembel türetilir: auth kullanılmayan isteklerde APP_KEY zorunlu değildir
     * (üretimde Config zaten açılışta zorunlu kılar — K27).
     */
    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $appKey = trim($this->config->get('APP_KEY', ''));
        if (preg_match('/^[0-9a-f]{64}$/i', $appKey) !== 1) {
            throw new RuntimeException(
                'APP_KEY 64 haneli onaltılık bir dize olmalı. Üretmek için: php -r "echo bin2hex(random_bytes(32));"',
            );
        }

        $master = hex2bin($appKey);
        if ($master === false) {
            throw new RuntimeException('APP_KEY çözümlenemedi.');
        }

        return $this->key = hash_hkdf('sha256', $master, 32, self::KEY_INFO);
    }
}
