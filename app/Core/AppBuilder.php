<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthServices;
use App\Auth\NativeSession;
use App\Auth\SessionInterface;
use App\Controllers\AuthController;
use App\Controllers\SystemController;
use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Middleware\JsonRequest;
use App\Middleware\LoginRateLimit;
use App\Middleware\RequestId;
use App\Middleware\SecurityHeaders;
use App\Setup\SetupLock;
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
     * @param SetupLock|null $setupLock Kurulum kilidi — `GET /api/system/status` kurulum tarihini buradan okur.
     * @param RequestContext|null $requestContext Logger ile PAYLAŞILAN bağlam; verilmezse yenisi kurulur.
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    public static function build(
        Config $config,
        callable $pdoFactory,
        LoggerInterface $logger,
        ?SessionInterface $session = null,
        ?Clock $clock = null,
        ?SetupLock $setupLock = null,
        ?RequestContext $requestContext = null,
        ?string $basePath = null,
    ): App {
        $requestContext ??= new RequestContext();
        $basePath ??= dirname(__DIR__, 2);
        $setupLock ??= new SetupLock($basePath . '/storage');

        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $connection = Connection::fromCallable($pdoFactory);
        $services = new AuthServices(
            $config,
            $connection,
            $session ?? NativeSession::fromConfig($config),
            $clock ?? SystemClock::fromConfig($config),
            $logger,
            $requestContext,
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

        // Güncelleme yolu (İE#5 §12): kurulum kilitlendikten sonra migration koşmanın
        // kimlik doğrulamalı yolu. Yazma ucu ayrıca CSRF ister.
        $system = new SystemController($basePath, $connection, $setupLock, $services->clock);
        $app->group('/api/system', static function (RouteCollectorProxy $group) use ($system): void {
            $group->get('/status', [$system, 'status']);
            $group->post('/migrate', [$system, 'migrate']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory));

        $app->addErrorMiddleware(
            displayErrorDetails: !$config->isProduction(),
            logErrors: true,
            logErrorDetails: true,
            logger: $logger,
        )->setDefaultErrorHandler(self::errorHandler($app->getResponseFactory(), $logger));

        // Bunlar hata middleware'inden SONRA eklenir, yani EN DIŞTA koşar:
        // 404/405/415/500 gibi hata yanıtları da güvenlik başlıklarını ve X-Request-Id'yi alır.
        // JsonRequest gövde ayrıştırmadan ÖNCE devreye girer (docs/10 §1).
        $app->add(new JsonRequest($app->getResponseFactory()));
        $app->add(new SecurityHeaders());
        $app->add(new RequestId($requestContext));

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
                // K25: gerçek 405 + izin verilen metodları bildiren Allow başlığı.
                $allowed = $exception->getAllowedMethods();

                return Response::error(
                    $response->withHeader('Allow', implode(', ', $allowed)),
                    'METHOD_NOT_ALLOWED',
                    sprintf('Bu uç bu HTTP metodunu desteklemiyor. İzin verilenler: %s.', implode(', ', $allowed)),
                    405,
                );
            }
            $logger->error('Beklenmeyen hata', ['hata' => $exception->getMessage(), 'iz' => $exception->getTraceAsString()]);

            return Response::error($response, 'SERVER_ERROR', 'Beklenmeyen bir hata oluştu.', 500);
        };
    }
}
