<?php

declare(strict_types=1);

namespace App\Core\Routes;

use App\Auth\AuthServices;
use App\Controllers\AuthController;
use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Middleware\LoginRateLimit;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Kimlik uçları (İE#10.5 Blok 6 — AppBuilder bölünmesi; davranış AYNEN taşındı).
 *
 * İki grup (İE#4 §3): giriş uçları oturum YOKken çağrılır ve LoginRateLimit ile
 * kilitlenir; korumalı uçlar Auth (oturum/remember) + Csrf ile korunur. Slim'de en
 * son eklenen middleware en dışta çalışır: Auth, Csrf'ten önce koşar (remember
 * çerezinden sessiz giriş CSRF token'ını üretir).
 */
final class AuthRoutes
{
    /**
     * @template T of \Psr\Container\ContainerInterface|null
     *
     * @param App<T> $app kompozisyon kökünden gelir
     */
    public static function register(
        App $app,
        AuthController $controller,
        AuthServices $services,
        ResponseFactoryInterface $responseFactory,
    ): void {
        $app->group('/api/auth', static function (RouteCollectorProxy $group) use ($controller): void {
            $group->post('/login', [$controller, 'login']);
            $group->post('/totp', [$controller, 'totp']);
            $group->post('/recovery', [$controller, 'recovery']);
        })->add(new LoginRateLimit($services, $responseFactory));

        $app->group('/api/auth', static function (RouteCollectorProxy $group) use ($controller): void {
            $group->get('/me', [$controller, 'me']);
            $group->get('/sessions', [$controller, 'sessions']);
            $group->post('/logout', [$controller, 'logout']);
            $group->delete('/sessions/{id}', [$controller, 'revokeSession']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory));
    }
}
