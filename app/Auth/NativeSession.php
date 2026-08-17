<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Config;
use SessionHandlerInterface;

/**
 * PHP native session sarmalayıcısı.
 * Çerez bayrakları K16'ya göre sabittir: HttpOnly + SameSite=Lax + (HTTPS'te) Secure.
 *
 * K44 (disksiz mod): üretimde depo DAİMA veritabanıdır — `DbSessionHandler` verilir
 * ve `session.save_path`e hiç güvenilmez (sunucu diske yazamıyor; dosya session'ı
 * her istekte kaybolurdu). Handler verilmezse (yalnız geliştirme) PHP varsayılanı çalışır.
 */
final class NativeSession implements SessionInterface
{
    private bool $started = false;

    public function __construct(
        private readonly string $name,
        private readonly bool $secure,
        private readonly ?SessionHandlerInterface $handler = null,
    ) {
    }

    public static function fromConfig(Config $config, ?SessionHandlerInterface $handler = null): self
    {
        return new self(
            $config->get('SESSION_NAME', 'tedarikapp_sid'),
            str_starts_with($config->get('APP_URL', ''), 'https://'),
            $handler,
        );
    }

    /**
     * Kurulum sihirbazı için: .env henüz yokken Config kurulamaz, bu yüzden
     * oturum adı ve Secure bayrağı doğrudan verilir (İE#5).
     *
     * K44 NOTU: sihirbaz artık bunu KULLANMAZ (CookieSession'a geçti — disksiz);
     * geriye dönük uyumluluk ve testler için duruyor.
     */
    public static function forSetup(bool $secure): self
    {
        return new self('tedarikapp_setup', $secure);
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        if ($this->handler !== null) {
            session_set_save_handler($this->handler, true);
        }
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0, // tarayıcı oturumu; boşta kalma aşımı sunucuda last_activity ile uygulanır
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        session_destroy();
        $this->started = false;
    }
}
