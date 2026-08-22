<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface;

/**
 * İstemci IP'si — giriş kilidi anahtarının ve activity_log kaydının parçası (K16).
 *
 * YALNIZCA `REMOTE_ADDR` kullanılır. `X-Forwarded-For` istemci tarafından serbestçe
 * uydurulabildiğinden güvenilmez; ona güvenmek IP kilidini tek başlıkla aşılır hale getirir.
 */
final class ClientIp
{
    public static function from(ServerRequestInterface $request): string
    {
        $remote = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        return is_string($remote) ? $remote : '';
    }

    /**
     * PSR-7 isteği HENÜZ YOKKEN (oturum handler'ı kompozisyon anında kurulur) IP.
     *
     * Aynı kural geçerlidir: yalnız `REMOTE_ADDR`; `X-Forwarded-For` okunmaz.
     * En çok 45 karakter (IPv6) — `sessions.ip` kolonunun genişliği budur.
     */
    public static function fromGlobals(): ?string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!is_string($remote) || $remote === '') {
            return null;
        }

        return mb_substr($remote, 0, 45);
    }
}
