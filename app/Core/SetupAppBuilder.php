<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\NativeSession;
use App\Auth\SessionInterface;
use App\Controllers\SetupController;
use App\Middleware\JsonRequest;
use App\Middleware\RequestId;
use App\Middleware\SecurityHeaders;
use App\Middleware\SetupCsrf;
use App\Middleware\SetupGuard;
use App\Middleware\SetupHttpsGate;
use App\Setup\EnvWriter;
use App\Setup\SetupLock;
use App\Setup\SetupState;
use Closure;
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
 * Kurulum sihirbazı uygulaması (İE#5 §C).
 *
 * Ana uygulamadan AYRI kurulur çünkü sihirbaz `.env` YOKKEN çalışmak zorundadır:
 * `Config::load()` bu aşamada istisna fırlatır. Bu yüzden burada Config'e bağımlı
 * hiçbir bileşen önyüklenmez; DB ve şifreleme yalnızca ilgili adım geldiğinde,
 * `.env` yazıldıktan SONRA kurulur.
 *
 * Arayüz sunucu tarafında render edilen tek sayfadır; JS ve CSS ayrı uçlardan servis
 * edilir — güvenlik başlıkları (`default-src 'self'`) satır içi script/stil'e izin vermez.
 */
final class SetupAppBuilder
{
    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    public static function build(
        string $basePath,
        LoggerInterface $logger,
        ?SessionInterface $session = null,
        ?Clock $clock = null,
        ?RequestContext $requestContext = null,
        ?SetupLock $setupLock = null,
        ?EnvWriter $envWriter = null,
        ?string $appEnv = null,
    ): App {
        $requestContext ??= new RequestContext();
        $clock ??= new SystemClock(new \DateTimeZone('Europe/Istanbul'));
        $envWriter ??= new EnvWriter($basePath);
        // `.env` henüz yokken Config kurulamaz; sunucu ortam değişkeni varsa o okunur.
        // Hiçbiri yoksa GÜVENLİ varsayılan production'dur (K37 §A3 — fail-safe).
        $appEnv ??= self::detectAppEnv();

        // K33: kilit veritabanındadır. K37: bağlantı yalnızca `.env` VARSA verilir —
        // varken okunamayan kilit "kilitli" sayılır (fail-closed), yokken kurulum
        // yapılmamış demektir ve dosya denetimi yeterlidir.
        $lock = $setupLock ?? new SetupLock(
            $envWriter->exists()
                ? Connection::fromCallable(static fn (): \PDO => Database::connect(Config::load($basePath)))
                : null,
            $basePath . '/storage',
        );

        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $responseFactory = $app->getResponseFactory();
        $state = new SetupState($session ?? NativeSession::forSetup(secure: self::serverIsHttps()));
        $controller = new SetupController($basePath, $state, $lock, $clock, $envWriter, $appEnv);

        // Sihirbaz sayfası ve varlıkları (kilit denetimi bunlara da uygulanır).
        $app->get('/setup', self::viewAction($basePath . '/setup/views/wizard.html', 'text/html; charset=utf-8'));
        $app->get('/setup/wizard.js', self::viewAction($basePath . '/setup/views/wizard.js', 'application/javascript; charset=utf-8'));
        $app->get('/setup/wizard.css', self::viewAction($basePath . '/setup/views/wizard.css', 'text/css; charset=utf-8'));

        $app->group('/api/setup', static function (RouteCollectorProxy $group) use ($controller): void {
            $group->get('/state', [$controller, 'state']);
            $group->get('/requirements', [$controller, 'requirements']);
            $group->post('/database', [$controller, 'database']);
            $group->post('/env', [$controller, 'env']);
            $group->post('/env/verify', [$controller, 'verifyEnv']);
            $group->post('/migrate', [$controller, 'migrate']);
            $group->post('/admin', [$controller, 'admin']);
            $group->post('/admin/verify', [$controller, 'verifyAdmin']);
            $group->post('/finish', [$controller, 'finish']);
        })
            ->add(new SetupCsrf($state, $responseFactory))
            // En son eklenen en dışta koşar: HTTPS kapısı CSRF'ten ÖNCE karar verir (K37 §A3).
            ->add(new SetupHttpsGate($responseFactory, $appEnv));

        // Kurulmamış sistemde kök adres sihirbaza yönlendirir.
        $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            return $response->withHeader('Location', '/setup')->withStatus(302);
        });

        $app->addErrorMiddleware(displayErrorDetails: false, logErrors: true, logErrorDetails: true, logger: $logger)
            ->setDefaultErrorHandler(self::errorHandler($responseFactory, $logger));

        // En dıştan içe: RequestId → SecurityHeaders → JsonRequest → SetupGuard → rotalar.
        $app->add(new SetupGuard($lock, $responseFactory, $logger, $clock, $envWriter, $state));
        $app->add(new JsonRequest($responseFactory));
        $app->add(new SecurityHeaders());
        $app->add(new RequestId($requestContext));

        return $app;
    }

    /** `.env` yokken APP_ENV yalnızca sunucu ortamından okunabilir; yoksa production varsayılır. */
    private static function detectAppEnv(): string
    {
        $fromServer = $_SERVER['APP_ENV'] ?? null;
        if (is_string($fromServer) && $fromServer !== '') {
            return $fromServer;
        }

        $fromEnv = getenv('APP_ENV');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : 'production';
    }

    /** Sihirbaz oturum çerezinin Secure bayrağı — istek HTTPS ise işaretlenir (K37 §A3). */
    private static function serverIsHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    /** Sihirbaz dosyalarını (HTML/JS/CSS) docroot dışından servis eder. */
    private static function viewAction(string $file, string $contentType): Closure
    {
        return static function (ServerRequestInterface $request, ResponseInterface $response) use ($file, $contentType): ResponseInterface {
            $content = @file_get_contents($file);
            if ($content === false) {
                return Response::error($response, 'NOT_FOUND', 'Kurulum dosyası bulunamadı.', 404);
            }

            $response->getBody()->write($content);

            return $response
                ->withHeader('Content-Type', $contentType)
                ->withHeader('Cache-Control', 'no-store');
        };
    }

    private static function errorHandler(ResponseFactoryInterface $responseFactory, LoggerInterface $logger): Closure
    {
        return static function (ServerRequestInterface $request, Throwable $exception) use ($responseFactory, $logger): ResponseInterface {
            $response = $responseFactory->createResponse();
            if ($exception instanceof HttpNotFoundException) {
                return Response::error($response, 'NOT_FOUND', 'İstenen kaynak bulunamadı.', 404);
            }
            if ($exception instanceof HttpMethodNotAllowedException) {
                $allowed = $exception->getAllowedMethods();

                return Response::error(
                    $response->withHeader('Allow', implode(', ', $allowed)),
                    'METHOD_NOT_ALLOWED',
                    sprintf('Bu uç bu HTTP metodunu desteklemiyor. İzin verilenler: %s.', implode(', ', $allowed)),
                    405,
                );
            }
            $logger->error('Kurulum sırasında beklenmeyen hata', [
                'hata' => $exception->getMessage(),
                'iz' => $exception->getTraceAsString(),
            ]);

            return Response::error($response, 'SERVER_ERROR', 'Kurulum sırasında beklenmeyen bir hata oluştu.', 500);
        };
    }
}
