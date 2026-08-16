<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthServices;
use App\Auth\NativeSession;
use App\Auth\SessionInterface;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\ListController;
use App\Controllers\ProductController;
use App\Controllers\SettingsController;
use App\Controllers\SystemController;
use App\Controllers\TrashController;
use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Middleware\JsonRequest;
use App\Middleware\LoginRateLimit;
use App\Middleware\RequestId;
use App\Middleware\SecurityHeaders;
use App\Models\CategoryRepository;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use App\Services\CurlMediaFetcher;
use App\Services\InputValidator;
use App\Services\ListPresenter;
use App\Services\MediaService;
use App\Services\MoneyService;
use App\Services\StateMachine;
use App\Services\TrashPolicy;
use App\Services\UrlGuard;
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

        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $connection = Connection::fromCallable($pdoFactory);
        // K33: kilit dosyada değil veritabanında; üretimde uygulama diske yazamıyor.
        $setupLock ??= new SetupLock($connection, $basePath . '/storage');
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

        // Paylaşılan servisler (İE#6 + İE#7) — rotalar bunların üzerine kurulur.
        $lists = new ListRepository($connection);
        $products = new ProductRepository($connection);
        $money = new MoneyService();
        $presenter = new ListPresenter($lists, $products, $money, $services->timezone);
        $validator = new InputValidator($money);
        $stateMachine = new StateMachine();

        $allowedHosts = array_map('trim', explode(',', $config->get('MEDIA_ALLOWED_HOSTS', 'alicdn.com,1688.com')));
        $urlGuard = new UrlGuard($allowedHosts);
        $settingsRepository = new SettingsRepository($connection);
        $mediaService = new MediaService(
            $basePath,
            $urlGuard,
            new CurlMediaFetcher($urlGuard, $config->getPositiveInt('MEDIA_DOWNLOAD_TIMEOUT', 25)),
            $settingsRepository,
            $config->getPositiveInt('MEDIA_MAX_MB', 8) * 1024 * 1024,
            $config->get('MEDIA_PATH', 'public/media'),
        );

        // Güncelleme yolu (İE#5 §12): kurulum kilitlendikten sonra migration koşmanın
        // kimlik doğrulamalı yolu. Yazma ucu ayrıca CSRF ister.
        $system = new SystemController($basePath, $connection, $setupLock, $services->clock, $mediaService);
        $app->group('/api/system', static function (RouteCollectorProxy $group) use ($system): void {
            $group->get('/status', [$system, 'status']);
            $group->post('/migrate', [$system, 'migrate']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory));

        $listController = new ListController(
            $lists,
            $products,
            $settingsRepository,
            $presenter,
            $validator,
            $stateMachine,
            $services->activity,
            $services->clock,
        );
        $productController = new ProductController(
            $lists,
            $products,
            $presenter,
            $validator,
            $stateMachine,
            $services->activity,
            $services->clock,
        );
        $trashController = new TrashController(
            $lists,
            $products,
            new TrashPolicy($config->getPositiveInt('TRASH_RETENTION_DAYS', 30)),
            $services->activity,
            $services->clock,
            $services->timezone,
        );

        $settingsController = new SettingsController(
            $connection,
            $settingsRepository,
            $mediaService,
            $money,
            $validator,
            $services->activity,
            $services->clock,
            $services->timezone,
        );
        $categoryController = new CategoryController(
            new CategoryRepository($connection),
            $services->activity,
            $services->clock,
        );

        $app->group('/api', static function (RouteCollectorProxy $group) use ($settingsController, $categoryController): void {
            $group->get('/settings', [$settingsController, 'show']);
            $group->put('/settings/rates', [$settingsController, 'updateRates']);
            $group->get('/settings/rates/history', [$settingsController, 'rateHistory']);

            $group->get('/categories', [$categoryController, 'index']);
            $group->post('/categories', [$categoryController, 'store']);
            $group->patch('/categories/{id}', [$categoryController, 'update']);
            $group->delete('/categories/{id}', [$categoryController, 'destroy']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory));

        $app->group('/api', static function (RouteCollectorProxy $group) use ($listController, $productController, $trashController): void {
            $group->get('/lists', [$listController, 'index']);
            $group->post('/lists', [$listController, 'store']);
            $group->get('/lists/{id}', [$listController, 'show']);
            $group->patch('/lists/{id}', [$listController, 'update']);
            $group->delete('/lists/{id}', [$listController, 'destroy']);
            $group->post('/lists/{id}/duplicate', [$listController, 'duplicate']);

            $group->get('/lists/{id}/products', [$productController, 'index']);
            $group->post('/lists/{id}/products', [$productController, 'store']);
            $group->patch('/lists/{id}/products/reorder', [$productController, 'reorder']);

            // bulk, {id} deseninden ÖNCE tanımlanır; aksi hâlde "bulk" bir kimlik sanılır.
            $group->patch('/products/bulk', [$productController, 'bulk']);
            $group->patch('/products/{id}', [$productController, 'update']);
            $group->patch('/products/{id}/status', [$productController, 'updateStatus']);
            $group->delete('/products/{id}', [$productController, 'destroy']);

            $group->get('/trash', [$trashController, 'index']);
            $group->post('/trash/{type}/{id}/restore', [$trashController, 'restore']);
            $group->delete('/trash/{type}/{id}', [$trashController, 'destroy']);
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
