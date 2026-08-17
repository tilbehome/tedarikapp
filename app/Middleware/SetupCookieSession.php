<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Setup\CookieSession;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CookieSession'ı isteğe bağlar ve yanıtla geri yazar (K44 — disksiz sihirbaz oturumu).
 * Guard dahil oturuma bakan her katmandan ÖNCE koşmalıdır.
 */
final class SetupCookieSession implements MiddlewareInterface
{
    public function __construct(private readonly CookieSession $session)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->session->bindRequest($request);

        return $this->session->commitTo($handler->handle($request));
    }
}
