<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\SessionInterface;
use App\Controllers\SetupController;
use App\Middleware\JsonRequest;
use App\Middleware\RequestId;
use App\Middleware\SecurityHeaders;
use App\Middleware\SetupAudit;
use App\Middleware\SetupCookieSession;
use App\Middleware\SetupCsrf;
use App\Middleware\SetupGuard;
use App\Middleware\SetupHttpsGate;
use App\Setup\ConfigWriter;
use App\Setup\CookieSession;
use App\Setup\SetupDiagnostics;
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
        ?ConfigWriter $configWriter = null,
        ?string $appEnv = null,
    ): App {
        $requestContext ??= new RequestContext();
        $clock ??= new SystemClock(new \DateTimeZone('Europe/Istanbul'));
        $configWriter ??= new ConfigWriter($basePath);
        // config henüz yokken APP_ENV yalnız sunucu ortamından okunabilir.
        // Hiçbiri yoksa GÜVENLİ varsayılan production'dur (K37 §A3 — fail-safe).
        $appEnv ??= self::detectAppEnv();

        // K33: kilit veritabanındadır. K37: bağlantı yalnızca sistem YAPILANDIRILMIŞSA
        // (config.php veya legacy .env) verilir — varken okunamayan kilit "kilitli"
        // sayılır (fail-closed), yokken kurulum yapılmamış demektir.
        $lock = $setupLock ?? new SetupLock(
            $configWriter->configured()
                ? Connection::fromCallable(static fn (): \PDO => Database::connect(Config::load($basePath)))
                : null,
            $basePath . '/storage',
        );

        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $responseFactory = $app->getResponseFactory();

        // K44 DİSKSİZ OTURUM: sihirbaz state'i artık native session'da DEĞİL (üretimde
        // save_path yazılamıyor → state her istekte sıfırlanıyordu — kanıtlı kök neden).
        // State şifreli+doğrulamalı ÇEREZDE taşınır; diske ve DB'ye ihtiyaç yoktur.
        $cookieSession = null;
        if ($session === null) {
            $cookieSession = new CookieSession($basePath, secure: self::serverIsHttps());
            $session = $cookieSession;
        }
        $state = new SetupState($session);
        $controller = new SetupController($basePath, $state, $lock, $clock, $configWriter, $appEnv);

        // Sihirbaz sayfası ve varlıkları (kilit denetimi bunlara da uygulanır).
        $app->get('/setup', self::viewAction($basePath . '/setup/views/wizard.html', 'text/html; charset=utf-8'));
        $app->get('/setup/wizard.js', self::viewAction($basePath . '/setup/views/wizard.js', 'application/javascript; charset=utf-8'));
        $app->get('/setup/wizard.css', self::viewAction($basePath . '/setup/views/wizard.css', 'text/css; charset=utf-8'));

        // K43: kurulum bütünlüğü — MANIFEST.txt'e göre eksik/bozuk dosya listesi.
        // Kurulumdan ÖNCE de çalışır; sihirbazın gereksinim adımı bunu gösterir. Sır içermez.
        $app->get('/api/system/integrity', static function (ServerRequestInterface $request, ResponseInterface $response) use ($basePath): ResponseInterface {
            return Response::success($response, (new \App\Services\IntegrityChecker($basePath))->check());
        });

        $app->group('/api/setup', static function (RouteCollectorProxy $group) use ($controller): void {
            $group->get('/state', [$controller, 'state']);
            $group->get('/requirements', [$controller, 'requirements']);
            $group->get('/diagnostics', [$controller, 'diagnostics']);
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
            ->setDefaultErrorHandler(self::errorHandler($responseFactory, $logger, $basePath));

        // En dıştan içe: RequestId → SecurityHeaders → CookieSession → JsonRequest → Guard → Audit → rotalar.
        $app->add(new SetupAudit($logger)); // K42: adım adı/sonuç/süre günlüğü (kapıyı geçen istekler)
        $app->add(new SetupGuard($lock, $responseFactory, $logger, $clock, $configWriter, $state));
        $app->add(new JsonRequest($responseFactory));
        if ($cookieSession !== null) {
            // Oturuma bakan her katmandan (guard dahil) ÖNCE bağlanmalı, yanıtı en sonda yazmalı.
            $app->add(new SetupCookieSession($cookieSession));
        }
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
                // K42/K43: "setup/ eksik açılmış" üretim vakasının cevabı — sessiz
                // NOT_FOUND yerine NE YAPILACAĞINI söyleyen 503 HTML sayfası.
                $eksik = basename(dirname($file, 2)) . '/' . basename(dirname($file)) . '/' . basename($file);
                $html = '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
                    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                    . '<title>Kurulum dosyaları eksik</title>'
                    . '<style>body{font-family:system-ui,sans-serif;max-width:44rem;margin:4rem auto;'
                    . 'padding:0 1rem;line-height:1.6;color:#1f2430}code{background:#f4f5f7;padding:.1rem .35rem;'
                    . 'border-radius:4px}.badge{display:inline-block;background:#fde8e8;color:#9b1c1c;'
                    . 'border-radius:4px;padding:.1rem .5rem;font-size:.8rem;font-weight:700}</style></head><body>'
                    . '<p class="badge">tedarikapp — 503</p>'
                    . '<h1>Kurulum dosyaları eksik yüklenmiş</h1>'
                    . '<p>Sihirbaz dosyası bulunamadı: <code>' . htmlspecialchars($eksik, ENT_QUOTES, 'UTF-8') . '</code></p>'
                    . '<p>Release zip\'i büyük olasılıkla EKSİK açıldı (<code>setup/</code> klasörü yüklenmemiş). '
                    . 'Çözüm: zip\'i sunucuda uygulama köküne <strong>üzerine yazarak yeniden açın</strong>; '
                    . 'tüm klasörler (<code>app/ bin/ bootstrap/ migrations/ public/ setup/ vendor/</code>) çıkmalı.</p>'
                    . '<p>Eksiklerin tam listesi: <code>/api/system/integrity</code> adresi, MANIFEST\'e göre '
                    . 'eksik/bozuk her dosyayı isim isim verir.</p></body></html>';
                $response->getBody()->write($html);

                return $response
                    ->withHeader('Content-Type', 'text/html; charset=utf-8')
                    ->withHeader('Cache-Control', 'no-store')
                    ->withStatus(503);
            }

            $response->getBody()->write($content);

            return $response
                ->withHeader('Content-Type', $contentType)
                ->withHeader('Cache-Control', 'no-store');
        };
    }

    private static function errorHandler(ResponseFactoryInterface $responseFactory, LoggerInterface $logger, string $basePath): Closure
    {
        return static function (ServerRequestInterface $request, Throwable $exception) use ($responseFactory, $logger, $basePath): ResponseInterface {
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

            // K42: kurulum evresinde sır yoktur → teşhis yanıtta taşınır; sihirbaz
            // bunu teknik detay bölümü + kopyalanabilir rapor olarak gösterir.
            $diagnostics = new SetupDiagnostics($basePath);

            return Response::error(
                $response,
                'SERVER_ERROR',
                'Kurulum sırasında beklenmeyen bir hata oluştu. Teknik detay aşağıda; '
                . '"Tanılama raporunu kopyala" ile destek için hazır rapor alabilirsiniz.',
                500,
                [],
                ['diagnostics' => [
                    'environment' => $diagnostics->environment(),
                    'failure' => $diagnostics->failure($request->getUri()->getPath(), $exception),
                ]],
            );
        };
    }
}
