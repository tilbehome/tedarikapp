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
}
