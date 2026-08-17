<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthServices;
use App\Auth\NativeSession;
use App\Auth\SessionInterface;
use App\Controllers\ActivityController;
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
use App\Services\ListMutationPolicy;
use App\Services\ListPresenter;
use App\Services\MediaJanitor;
use App\Services\MediaService;
use App\Services\MoneyService;
use App\Services\StateMachine;
use App\Services\TrashPolicy;
use App\Services\UrlGuard;
use App\Setup\SetupDiagnostics;
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
            // K44 disksiz mod: gerçek dağıtımda oturum DAİMA DB'de (sessions tablosu) —
            // save_path'e güvenilmez. Testler kendi oturumunu enjekte eder.
            $session ?? NativeSession::fromConfig($config, new \App\Auth\DbSessionHandler($connection)),
            $clock ?? SystemClock::fromConfig($config),
            $logger,
            $requestContext,
        );

        $app->get('/api/health', self::healthAction($config, $connection, $logger));

        // K43: kurulum bütünlüğü — MANIFEST.txt'e göre eksik/bozuk dosya listesi.
        // /api/health gibi kimliksizdir: sır içermez, yalnız göreli yol adları döner;
        // "site tuhaf davranıyor" anında ilk bakılacak yer.
        $app->get('/api/system/integrity', static function (ServerRequestInterface $request, ResponseInterface $response) use ($basePath): ResponseInterface {
            return Response::success($response, (new \App\Services\IntegrityChecker($basePath))->check());
        });

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
        $settingsRepository = new SettingsRepository($connection);
        // K4 düzeltmesi: taslak listeler GÜNCEL ayar kurunu gösterir — presenter ayarları bilir.
        $presenter = new ListPresenter($lists, $products, $money, $services->timezone, $settingsRepository);
        $validator = new InputValidator($money);
        $stateMachine = new StateMachine();
        $mutationPolicy = new ListMutationPolicy();

        $allowedHosts = array_map('trim', explode(',', $config->get('MEDIA_ALLOWED_HOSTS', 'alicdn.com,1688.com')));
        $urlGuard = new UrlGuard($allowedHosts);
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
        $system = new SystemController($basePath, $connection, $setupLock, $services->clock, $mediaService, $stateMachine);
        $app->group('/api/system', static function (RouteCollectorProxy $group) use ($system): void {
            $group->get('/status', [$system, 'status']);
            $group->get('/state-machine', [$system, 'stateMachine']);
            $group->post('/migrate', [$system, 'migrate']);
            // K46: kilit kaldırmanın admin-oturumu yolu (Auth + CSRF bu grupta).
            $group->post('/setup-unlock', [$system, 'setupUnlock']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory));

        $listController = new ListController(
            $connection,
            $lists,
            $products,
            $settingsRepository,
            $presenter,
            $validator,
            $stateMachine,
            $mutationPolicy,
            $services->activity,
            $services->clock,
        );
        $productController = new ProductController(
            $connection,
            $lists,
            $products,
            $presenter,
            $validator,
            $stateMachine,
            $mutationPolicy,
            $services->activity,
            $services->clock,
            $mediaService,
        );
        $trashController = new TrashController(
            $connection,
            $lists,
            $products,
            new TrashPolicy($config->getPositiveInt('TRASH_RETENTION_DAYS', 30)),
            $mutationPolicy,
            new MediaJanitor($mediaService, $products),
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

        $activityController = new ActivityController($connection, $services->timezone);

        $app->group('/api', static function (RouteCollectorProxy $group) use ($settingsController, $categoryController, $activityController): void {
            $group->get('/settings', [$settingsController, 'show']);
            $group->put('/settings/rates', [$settingsController, 'updateRates']);
            $group->get('/settings/rates/history', [$settingsController, 'rateHistory']);

            $group->get('/activity', [$activityController, 'index']);

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

        // Panel (İE#8 §5): Vite çıktısı public/panel/ altındadır. Var olan dosyaları
        // Apache doğrudan sunar; /panel/listeler/5 gibi istemci tarafı rotalar buraya
        // düşer ve index.html'e verilir ki sayfa yenilendiğinde 404 alınmasın.
        $app->get('/panel[/{path:.*}]', self::panelAction($basePath));

        $app->addErrorMiddleware(
            displayErrorDetails: !$config->isProduction(),
            logErrors: true,
            logErrorDetails: true,
            logger: $logger,
        )->setDefaultErrorHandler(self::errorHandler($app->getResponseFactory(), $logger, $setupLock, $requestContext, $basePath));

        // Bunlar hata middleware'inden SONRA eklenir, yani EN DIŞTA koşar:
        // 404/405/415/500 gibi hata yanıtları da güvenlik başlıklarını ve X-Request-Id'yi alır.
        // JsonRequest gövde ayrıştırmadan ÖNCE devreye girer (docs/10 §1).
        $app->add(new JsonRequest($app->getResponseFactory()));
        // img-src, indirme beyaz listesinden türetilir: hotlink modunda görsellerin
        // tarayıcıda açılabilmesi için politikanın onları tanıması gerekir (K33).
        $app->add(new SecurityHeaders($allowedHosts));
        $app->add(new RequestId($requestContext));
        // EN DIŞTA: /panel/ veya /api/lists/ gibi sondaki eğik çizgili adresler rotayı bulamıyordu.
        $app->add(new \App\Middleware\TrailingSlash());

        return $app;
    }

    /**
     * Panelin tek sayfa uygulaması. Build alınmamışsa teknik detay değil,
     * ne yapılacağını söyleyen düz bir sayfa gösterilir (docs/07 build adımı).
     */
    private static function panelAction(string $basePath): Closure
    {
        return static function (ServerRequestInterface $request, ResponseInterface $response) use ($basePath): ResponseInterface {
            $index = $basePath . '/public/panel/index.html';
            $html = is_file($index) ? file_get_contents($index) : false;

            if ($html === false) {
                $response->getBody()->write(
                    '<!doctype html><html lang="tr"><meta charset="utf-8">'
                    . '<title>Panel derlenmemiş</title>'
                    . '<body style="font-family:system-ui;max-width:40rem;margin:4rem auto;line-height:1.6">'
                    . '<h1>Panel henüz derlenmemiş</h1>'
                    . '<p>Sürüm paketi <code>public/panel/</code> çıktısını içermelidir. '
                    . 'Geliştirmede: <code>cd frontend &amp;&amp; npm ci &amp;&amp; npm run build</code>.</p></body></html>',
                );

                return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(503);
            }

            $response->getBody()->write($html);

            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                // index.html önbelleğe alınmaz; varlık dosyaları zaten hash'li adlarla gelir.
                ->withHeader('Cache-Control', 'no-store');
        };
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

    /**
     * Beklenmeyen her hatayı docs/10 zarfına çevirir (K42).
     *
     * Kilit YOKKEN (kurulum bitmemiş — sistemde üretim sırrı yok): yanıt tam teşhis
     * taşır (redaksiyonlu). Kilit VARKEN (üretim): kullanıcıya zarif genel mesaj +
     * Request-ID döner; tam detay logger üzerinden `app_logs`a yazılır (LOG_DRIVER=db).
     * PHP display_errors'a GÜVENİLMEZ — davranış bu handler'da yaşar.
     */
    private static function errorHandler(
        ResponseFactoryInterface $responseFactory,
        LoggerInterface $logger,
        SetupLock $setupLock,
        RequestContext $requestContext,
        string $basePath,
    ): Closure {
        return static function (ServerRequestInterface $request, Throwable $exception) use ($responseFactory, $logger, $setupLock, $requestContext, $basePath): ResponseInterface {
            $response = $responseFactory->createResponse();
            if ($exception instanceof HttpNotFoundException) {
                // K45: tarayıcıdan açılan yanlış adres JSON hata değil PANELE yönlendirme alır.
                if ($request->getMethod() === 'GET'
                    && str_contains($request->getHeaderLine('Accept'), 'text/html')) {
                    return $response->withHeader('Location', '/panel')->withStatus(302);
                }

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

            // K42: kilit durumu okunamıyorsa (DB düşmüş olabilir) GÜVENLİ taraf seçilir —
            // kurulu olabilecek sistemde teşhis GÖSTERİLMEZ, generic + Request-ID döner.
            try {
                $unlocked = $setupLock->status() === SetupLock::STATE_UNLOCKED;
            } catch (Throwable) {
                $unlocked = false;
            }

            $requestId = $requestContext->id() ?? 'yok';
            $meta = ['request_id' => $requestId];
            if ($unlocked) {
                $diagnostics = new SetupDiagnostics($basePath);
                $meta['diagnostics'] = [
                    'environment' => $diagnostics->environment(),
                    'failure' => $diagnostics->failure($request->getUri()->getPath(), $exception),
                ];
            }

            return Response::error(
                $response,
                'SERVER_ERROR',
                $unlocked
                    ? 'Beklenmeyen bir hata oluştu; teknik teşhis meta.diagnostics içinde.'
                    : sprintf('Beklenmeyen bir hata oluştu. Destek için bu kodu iletin: %s', $requestId),
                500,
                [],
                $meta,
            );
        };
    }
}
