<?php

declare(strict_types=1);

namespace App\Setup;

use App\Auth\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Kurulum sihirbazının DİSKSİZ oturumu (K44, İE#9.4).
 *
 * KANITLI KÖK NEDEN: sihirbaz state'i PHP native session'daydı; üretim sunucusu
 * session dosyasını DİSKE yazamıyor (nobody + yazılamaz docroot) → her istekte
 * state sıfırlanıyor, "/api/setup/env 200 ama migrate 'önce env'" döngüsü.
 *
 * Çözüm: adımlar arası state (adım, DB bilgileri, bekleyen admin, CSRF) küçük →
 * AES-256-GCM ile ŞİFRELİ + doğrulamalı tek çerezde taşınır. Diske ve
 * `session.save_path`e HİÇ dokunulmaz; DB'ye de ihtiyaç yoktur (migrate öncesi
 * `sessions` tablosu zaten yoktur).
 *
 * Anahtar iki evreli:
 *  • config.php YOKKEN: sunucuya özgü türetilmiş önyükleme anahtarı (kurulum
 *    öncesi sistemde korunacak sır yoktur; sihirbaz zaten herkese açıktır).
 *  • config.php VARKEN: APP_KEY'den türetilir. Çözümde iki anahtar da denenir —
 *    config.php'nin yazıldığı/elle kaydedildiği geçiş isteği state kaybetmez;
 *    yazımda config varsa DAİMA APP_KEY kullanılır.
 */
final class CookieSession implements SessionInterface
{
    public const COOKIE_NAME = 'tedarikapp_setup_state';

    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    /** @var array<string, mixed> */
    private array $data = [];

    private bool $bound = false;

    private bool $dirty = false;

    private bool $destroyed = false;

    private ?string $rawCookie = null;

    public function __construct(
        private readonly string $basePath,
        private readonly bool $secure,
    ) {
    }

    public function start(): void
    {
        // Native oturum yok (K44): state çerezde taşınır; başlatılacak bir şey yok.
    }

    /** İstekteki çerezi bağlar (middleware çağırır). */
    public function bindRequest(ServerRequestInterface $request): void
    {
        $cookies = $request->getCookieParams();
        $this->rawCookie = is_string($cookies[self::COOKIE_NAME] ?? null) ? $cookies[self::COOKIE_NAME] : null;
        $this->bound = false; // tembel çözülür
    }

    /** Yanıta güncel state çerezini yazar (middleware çağırır). */
    public function commitTo(ResponseInterface $response): ResponseInterface
    {
        if ($this->destroyed) {
            return $response->withAddedHeader('Set-Cookie', sprintf(
                '%s=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax%s',
                self::COOKIE_NAME,
                $this->secure ? '; Secure' : '',
            ));
        }
        if (!$this->dirty) {
            return $response;
        }

        return $response->withAddedHeader('Set-Cookie', sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Lax%s',
            self::COOKIE_NAME,
            $this->encode(),
            $this->secure ? '; Secure' : '',
        ));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->hydrate();

        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->hydrate();
        $this->data[$key] = $value;
        $this->dirty = true;
    }

    public function remove(string $key): void
    {
        $this->hydrate();
        unset($this->data[$key]);
        $this->dirty = true;
    }

    public function regenerate(): void
    {
        // Kimlik çereze gömülü değil (state'in kendisi taşınıyor); yenilenecek id yok.
        $this->dirty = true;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->destroyed = true;
        $this->dirty = true;
    }

    // ─────────────── şifreleme ───────────────

    private function hydrate(): void
    {
        if ($this->bound) {
            return;
        }
        $this->bound = true;

        if ($this->rawCookie === null || $this->rawCookie === '') {
            return;
        }

        $binary = base64_decode(strtr($this->rawCookie, '-_', '+/'), true);
        if ($binary === false || strlen($binary) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return;
        }

        $iv = substr($binary, 0, self::IV_LENGTH);
        $tag = substr($binary, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($binary, self::IV_LENGTH + self::TAG_LENGTH);

        foreach ($this->candidateKeys() as $key) {
            $plain = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) {
                continue;
            }
            $decoded = json_decode($plain, true);
            if (is_array($decoded)) {
                $this->data = $decoded;
            }

            return;
        }
        // Hiçbir anahtar açamadı (bozuk/yabancı çerez) → temiz state ile başla.
    }

    private function encode(): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt(
            json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            self::CIPHER,
            $this->writeKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Kurulum oturumu şifrelenemedi.');
        }

        return rtrim(strtr(base64_encode($iv . $tag . $ciphertext), '+/', '-_'), '=');
    }

    /** Yazım anahtarı: config varsa APP_KEY (kalıcı), yoksa önyükleme anahtarı. */
    private function writeKey(): string
    {
        return $this->configKey() ?? $this->bootstrapKey();
    }

    /** @return list<string> çözümde denenecek anahtarlar (config önce) */
    private function candidateKeys(): array
    {
        $keys = [];
        $configKey = $this->configKey();
        if ($configKey !== null) {
            $keys[] = $configKey;
        }
        $keys[] = $this->bootstrapKey();

        return $keys;
    }

    private function configKey(): ?string
    {
        $appKey = ConfigWriter::readAppKey($this->basePath . '/config.php');
        if ($appKey === null) {
            return null;
        }
        $binary = hex2bin($appKey);

        return $binary === false ? null : hash_hkdf('sha256', $binary, 32, 'tedarikapp:setup-cookie:v1');
    }

    /**
     * Önyükleme anahtarı — config henüz yokken. Diske/DB'ye yazmadan istekler arası
     * SABİT kalması gerekir; sunucuya özgü değerlerden türetilir. Kurulum öncesi
     * sistemde korunacak kalıcı sır yoktur (sihirbaz herkese açık) — model İE#5 ile aynı.
     */
    private function bootstrapKey(): string
    {
        $material = hash(
            'sha256',
            'tedarikapp-bootstrap|' . $this->basePath . '|' . PHP_VERSION . '|' . php_uname('n')
            . '|' . (string) @filemtime($this->basePath . '/composer.lock'),
            true,
        );

        return hash_hkdf('sha256', $material, 32, 'tedarikapp:setup-cookie-bootstrap:v1');
    }
}
