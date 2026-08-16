<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthServices;
use App\Auth\NativeSession;
use App\Auth\SessionInterface;
use App\Controllers\AuthController;
use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Middleware\LoginRateLimit;
use App\Middleware\SecurityHeaders;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Throwable;

/**
 * Slim uygulamasını kurar. index.php gerçek bağımlılıklarla, testler
 * sahte PDO fabrikası + dizi tabanlı oturumla çağırır — uçlar HTTP sunucusu olmadan test edilebilir.
 */
final class AppBuilder
{
    /**
     * @param callable(): \PDO $pdoFactory Bağlantı tembel kurulur; sağlık ucu başarısızlığı zarfla raporlar.
     * @param SessionInterface|null $session Testlerde dizi tabanlı oturum enjekte edilir.
     * @param Clock|null $clock Testlerde zaman sabitlenir (giriş kilidi, token ömrü).
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    public static function build(
        Config $config,
        callable $pdoFactory,
        LoggerInterface $logger,
        ?SessionInterface $session = null,
        ?Clock $clock = null,
    ): App {
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new SecurityHeaders());

        $connection = Connection::fromCallable($pdoFactory);
        $services = new AuthServices(
            $config,
            $connection,
            $session ?? new NativeSession($config),
            $clock ?? SystemClock::fromConfig($config),
            $logger,
        );

        $app->get('/api/health', self::healthAction($config, $connection, $logger));

        // Auth uçları iki gruba ayrılır (İE#4 §3):
        //   • Giriş uçları — oturum YOKken çağrılır, LoginRateLimit ile kilitlenir.
        //   • Korumalı uçlar — Auth (oturum/remember) + Csrf ile korunur.
        // Slim'de en son eklenen middleware en dışta çalışır: Auth, Csrf'ten önce koşar
        // (remember çerezinden sessiz giriş CSRF token'ını üretir).
        $controller = new AuthController($services);
        $responseFactory = $app->getResponseFactory();

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

        $app->addErrorMiddleware(
            displayErrorDetails: !$config->isProduction(),
            logErrors: true,
            logErrorDetails: true,
            logger: $logger,
        )->setDefaultErrorHandler(self::errorHandler($app->getResponseFactory(), $logger));

        return $app;
    }

    private static function healthAction(Config $config, Connection $connection, LoggerInterface $logger): Closure
    {
        return static function (ServerRequestInterface $request, ResponseInterface $response) use ($config, $connection, $logger): ResponseInterface {
            try {
                $connection->pdo()->query('SELECT 1');
            } catch (Throwable $e) {
                $logger->error('Sağlık denetimi: veritabanına bağlanılamadı', ['hata' => $e->getMessage()]);

                return Response::error($response, 'SERVER_ERROR', 'Veritabanı bağlantısı kurulamadı.', 500);
            }

            $now = new DateTimeImmutable('now', new DateTimeZone($config->get('TZ', 'Europe/Istanbul')));

            return Response::success($response, [
                'app' => 'tedarikapp',
                'time' => $now->format(DATE_ATOM),
            ]);
        };
    }

    /** Beklenmeyen her hatayı docs/10 zarfına çevirir; teknik detay yalnızca loga gider. */
    private static function errorHandler(ResponseFactoryInterface $responseFactory, LoggerInterface $logger): Closure
    {
        return static function (ServerRequestInterface $request, Throwable $exception) use ($responseFactory, $logger): ResponseInterface {
            $response = $responseFactory->createResponse();
            if ($exception instanceof HttpNotFoundException) {
                return Response::error($response, 'NOT_FOUND', 'İstenen kaynak bulunamadı.', 404);
            }
            if ($exception instanceof HttpMethodNotAllowedException) {
                return Response::error($response, 'VALIDATION', 'Bu uç bu HTTP metodunu desteklemiyor.', 422);
            }
            $logger->error('Beklenmeyen hata', ['hata' => $exception->getMessage(), 'iz' => $exception->getTraceAsString()]);

            return Response::error($response, 'SERVER_ERROR', 'Beklenmeyen bir hata oluştu.', 500);
        };
    }
}
