<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Middleware\Auth;
use App\Middleware\RequestId;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Korumalı uçların ortak zemini: istek gövdesi okuma, doğrulanmış kullanıcı,
 * request_id erişimi. Hepsi tek yerde olsun ki her controller aynı şeyi
 * kendince yorumlamasın.
 */
abstract class ApiController
{
    /** @return array<string, mixed> */
    protected function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    protected function user(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(Auth::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new LogicException('Korumalı uç Auth middleware olmadan çağrıldı.');
        }

        return $user;
    }

    protected function requestId(ServerRequestInterface $request): ?string
    {
        $id = $request->getAttribute(RequestId::ATTRIBUTE);

        return is_string($id) ? $id : null;
    }

    /** @param array<string, string> $args */
    protected function intArg(array $args, string $key): ?int
    {
        $value = $args[$key] ?? '';

        return preg_match('/^\d+$/', $value) === 1 ? (int) $value : null;
    }

    /** @param array<string, mixed> $body */
    protected function str(array $body, string $key): string
    {
        $value = $body[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /** Sorgu dizesinden tek bir metin parametre. */
    protected function query(ServerRequestInterface $request, string $key): string
    {
        $params = $request->getQueryParams();
        $value = $params[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}
