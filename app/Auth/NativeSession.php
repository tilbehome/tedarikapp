<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Config;

/**
 * PHP native session sarmalayıcısı.
 * Çerez bayrakları K16'ya göre sabittir: HttpOnly + SameSite=Lax + (HTTPS'te) Secure.
 */
final class NativeSession implements SessionInterface
{
    private bool $started = false;

    public function __construct(private readonly Config $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        session_name($this->config->get('SESSION_NAME', 'tedarikapp_sid'));
        session_set_cookie_params([
            'lifetime' => 0, // tarayıcı oturumu; boşta kalma aşımı sunucuda last_activity ile uygulanır
            'path' => '/',
            'secure' => str_starts_with($this->config->get('APP_URL', ''), 'https://'),
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
