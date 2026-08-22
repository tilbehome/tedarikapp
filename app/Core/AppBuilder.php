<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthServices;
use App\Auth\NativeSession;
use App\Auth\SessionInterface;
use App\Controllers\ActivityController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\ExportController;
use App\Controllers\ListController;
use App\Controllers\ProductController;
use App\Controllers\SettingsController;
use App\Controllers\ShareController;
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
     * @param \App\Services\Translation\TranslationClient|null $translationClient Testlerde sahte çevirmen (ağa çıkılmaz).
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
        ?\App\Services\MediaFetcher $mediaFetcher = null,
        ?\App\Services\Translation\TranslationClient $translationClient = null,
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
            // İE#19 G8: `sessions.ip` kolonu vardı ama HİÇ DOLMUYORDU — handler'a IP
            // verilmediği için her satır NULL yazılıyordu. Şüpheli oturum incelemesi
            // (aynı oturum başka IP'den mi kullanıldı?) bu kolon olmadan yapılamıyordu.
            $session ?? NativeSession::fromConfig($config, new \App\Auth\DbSessionHandler(
                $connection,
                \App\Core\ClientIp::fromGlobals(),
            )),
            $clock ?? SystemClock::fromConfig($config),
            $logger,
            $requestContext,
        );

        $app->get('/api/health', self::healthAction($config, $connection, $logger));

        // K43 + İE#19 G4: kurulum bütünlüğü. Kimliksiz yol yalnız ÖZET döner
        // (sayılar), dosya adları DÖNMEZ — isim isim liste oturum arkasındaki
        // /api/system/integrity/detay ucundadır. "Site tuhaf davranıyor" sorusuna
        // cevap veren sinyal (ok/kaç dosya) kimliksiz kalmaya devam eder.
        $app->get('/api/system/integrity', static function (ServerRequestInterface $request, ResponseInterface $response) use ($basePath): ResponseInterface {
            return Response::success($response, (new \App\Services\IntegrityChecker($basePath))->summary());
        });

        // Auth uçları iki gruba ayrılır (İE#4 §3):
        //   • Giriş uçları — oturum YOKken çağrılır, LoginRateLimit ile kilitlenir.
        //   • Korumalı uçlar — Auth (oturum/remember) + Csrf ile korunur.
        // Slim'de en son eklenen middleware en dışta çalışır: Auth, Csrf'ten önce koşar
        // (remember çerezinden sessiz giriş CSRF token'ını üretir).
        $controller = new AuthController($services);
        $responseFactory = $app->getResponseFactory();

        Routes\AuthRoutes::register($app, $controller, $services, $responseFactory);

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
            $mediaFetcher ?? new CurlMediaFetcher($urlGuard, $config->getPositiveInt('MEDIA_DOWNLOAD_TIMEOUT', 25)),
            $settingsRepository,
            $config->getPositiveInt('MEDIA_MAX_MB', 8) * 1024 * 1024,
            $config->get('MEDIA_PATH', 'public/media'),
        );

        // Güncelleme yolu (İE#5 §12): kurulum kilitlendikten sonra migration koşmanın
        // kimlik doğrulamalı yolu. Yazma ucu ayrıca CSRF ister.
        $system = new SystemController($basePath, $connection, $setupLock, $services->clock, $mediaService, $stateMachine, $config);
        Routes\SystemRoutes::register($app, $system, $services, $responseFactory);

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

        // İE#10 Blok 1-3: export motoru — dosya diske YAZILMAZ, snapshot'tan akıtılır (K25/K33/K44).
        // İE#14 A2/A3: sözlük (K56 Katman 1) export ve paylaşım hattında da kullanılır —
        // varyasyon/öznitelik DEĞERLERİ belirlenimci biçimde Türkçeleşir (ağa çıkmadan).
        $glossary = new \App\Services\Translation\Glossary($basePath . '/config', $basePath . '/storage');
        $valueSet = new \App\Services\Translation\ValueSet($glossary);

        $exportRenderers = [
            'csv' => new \App\Services\Export\CsvRenderer(),
            'xlsx' => new \App\Services\Export\XlsxRenderer($basePath),
            'pdf' => new \App\Services\Export\PdfRenderer($basePath),
        ];
        $exportController = new ExportController(
            $lists,
            $products,
            new CategoryRepository($connection),
            new \App\Models\ExportRepository($connection),
            new \App\Services\Export\ExportSnapshot($presenter, $valueSet),
            $exportRenderers,
            $services->activity,
            $services->clock,
            $settingsRepository,
        );

        $shareController = new ShareController(
            $lists,
            $services->activity,
            $services->clock,
            new \App\Services\Share\ShareKeyService($lists, (string) $config->get('APP_KEY', '')),
            $config,
        );

        // İE#11 Faz 3: eklenti uçları — Bearer + CORS allowlist + hız sınırı (ExtensionAuth).
        $inboxRepository = new \App\Models\InboxRepository($connection);
        $captureService = new \App\Services\CaptureService($connection, $lists, $products, $mediaService, $validator);
        // İE#19 G6: yakalamanın uygulanması TEK serviste — iki çağıran (eklenti,
        // Gelen Kutusu) aynı atomik bloğu ve aynı terminal-liste kuralını kullanır.
        $captureApplier = new \App\Services\CaptureApplier(
            $connection,
            $captureService,
            $inboxRepository,
            $products,
            new \App\Services\ListMutationPolicy(),
            $services->activity,
        );
        $extensionController = new \App\Controllers\ExtensionController($captureService, $inboxRepository, $lists, $services->clock, $basePath, $captureApplier);
        $extensionAuth = new \App\Middleware\ExtensionAuth(
            $connection,
            $responseFactory,
            $config->get('EXTENSION_ALLOWED_ORIGINS', ''),
            $config->getPositiveInt('CAPTURE_RATE_PER_MIN', 30),
            $services->timezone,
        );
        // İE#13 Blok C: çeviri ÖNERİSİ (K54) — kendi SSRF beyaz listesi vardır;
        // medya allowlist'i (alicdn/1688) GENİŞLETİLMEZ.
        // İE#14 A2 (K56): üç katmanlı çeviri. Katman 1 sözlük DOSYA tabanlıdır
        // (config/sozluk-<dil>-tr.php; zh ve en), Katman 3 mevcut MyMemory'dir.
        // Katman 2 (LLM) V3-A'da gelecek — TranslatorInterface o gün hazır olsun diye
        // bugünden çağrı noktasıdır (LayeredTranslator tek uygulamadır).
        $translationService = new \App\Services\Translation\TranslationService(
            new \App\Models\TranslationCacheRepository($connection),
            $translationClient ?? new \App\Services\Translation\MyMemoryTranslator(
                new UrlGuard(array_map('trim', explode(',', $config->get('TRANSLATE_ALLOWED_HOSTS', 'api.mymemory.translated.net')))),
                $config->getPositiveInt('TRANSLATE_TIMEOUT', 5),
            ),
            $services->clock,
            $logger,
            $config->get('TRANSLATE_ENABLED', '1') !== '0',
            'zh',
            'tr',
            $glossary,
        );
        $translator = \App\Services\Translation\TranslatorRegistry::make(
            $config->get('TRANSLATOR_PROVIDER', 'katmanli'),
            $glossary,
            $translationService,
        );
        $translationController = new \App\Controllers\TranslationController(
            $translationService,
            $glossary,
            $translator,
            $services->activity,
            $services->clock,
        );

        $app->group('', static function (\Slim\Routing\RouteCollectorProxy $group) use ($extensionController, $translationController): void {
            $group->map(['POST', 'OPTIONS'], '/api/capture', [$extensionController, 'capture']);
            $group->map(['GET', 'OPTIONS'], '/api/extension/selectors', [$extensionController, 'selectors']);
            $group->map(['GET', 'OPTIONS'], '/api/extension/lists', [$extensionController, 'lists']);
            $group->map(['POST', 'OPTIONS'], '/api/extension/translate-suggest', [$translationController, 'suggest']);
        })
            ->add($extensionAuth)
            // İE#19 G7: eklenti uçları da şema bağımlıdır (products/inbox yazar);
            // bekleyen migration varken yakalama 503 alır ve eklenti kuyruğunda
            // bekler — yarım şemaya yazmaya çalışıp veri kaybetmez.
            ->add(new \App\Middleware\MigrationGuard($connection, $basePath . '/migrations', $responseFactory));

        $inboxController = new \App\Controllers\InboxController(
            $inboxRepository,
            $lists,
            $captureService,
            $services->activity,
            $services->clock,
            $services->timezone,
            $captureApplier,
        );

        // İE#10.5 Blok 6: rota kayıtları modül dosyalarında — AppBuilder yalnız kompozisyon kökü.
        Routes\PublicRoutes::register(
            $app,
            $mediaService,
            $lists,
            $products,
            $presenter,
            $connection,
            $services,
            $config,
            new \App\Services\Export\ExportSnapshot($presenter, $valueSet),
            $exportRenderers,
            $basePath,
            $logger,
        );
        Routes\DataRoutes::register(
            $app,
            $settingsController,
            $inboxController,
            $categoryController,
            $activityController,
            $listController,
            $productController,
            $trashController,
            $exportController,
            $shareController,
            $translationController,
            $services,
            $responseFactory,
            $connection,
            $basePath . '/migrations',
        );

        // Panel (İE#8 §5): Vite çıktısı public/panel/ altındadır. Var olan dosyaları
        // Apache doğrudan sunar; /panel/listeler/5 gibi istemci tarafı rotalar buraya
        // düşer ve index.html'e verilir ki sayfa yenilendiğinde 404 alınmasın.
        $app->get('/panel[/{path:.*}]', self::panelAction($basePath, $connection));

        $app->addErrorMiddleware(
            displayErrorDetails: !$config->isProduction(),
            logErrors: true,
            logErrorDetails: true,
            logger: $logger,
        )->setDefaultErrorHandler(self::errorHandler($app->getResponseFactory(), $logger, $setupLock, $requestContext, $basePath));

        // Bunlar hata middleware'inden SONRA eklenir, yani EN DIŞTA koşar:
        // 404/405/415/500 gibi hata yanıtları da güvenlik başlıklarını ve X-Request-Id'yi alır.
        // JsonRequest gövde ayrıştırmadan ÖNCE devreye girer (docs/10 §1).
        // E7: gövde tavanı artık GERÇEKTEN uygulanır (CAPTURE_MAX_PAYLOAD_KB).
        $app->add(new JsonRequest(
            $app->getResponseFactory(),
            $config->getPositiveInt('CAPTURE_MAX_PAYLOAD_KB', JsonRequest::VARSAYILAN_SINIR_KB),
        ));
        // img-src, indirme beyaz listesinden türetilir: hotlink modunda görsellerin
        // tarayıcıda açılabilmesi için politikanın onları tanıması gerekir (K33).
        // İE#17 G11: video CDN hostları YALNIZ media-src'yi besler (indirme kapısı dar kalır).
        $videoHosts = array_values(array_filter(array_map(
            'trim',
            explode(',', $config->get('VIDEO_ALLOWED_HOSTS', 'cloud.video.taobao.com,video.alicdn.com')),
        )));
        $app->add(new SecurityHeaders($allowedHosts, $videoHosts));
        $app->add(new RequestId($requestContext));
        // EN DIŞTA: /panel/ veya /api/lists/ gibi sondaki eğik çizgili adresler rotayı bulamıyordu.
        $app->add(new \App\Middleware\TrailingSlash());

        return $app;
    }

    /**
     * Panelin tek sayfa uygulaması. Build alınmamışsa teknik detay değil,
     * ne yapılacağını söyleyen düz bir sayfa gösterilir (docs/07 build adımı).
     *
     * İE#13 EK-B: giriş ekranının vitrin rakamları ve 2FA durumu BURADA gömülür —
     * girişsiz bir API ucu açılmaz (PM şartı). Taşıyıcı bir META etiketidir, satır içi
     * script DEĞİL: K45 CSP kararı (satır içi script yok) korunur.
     */
    private static function panelAction(string $basePath, Connection $connection): Closure
    {
        return static function (ServerRequestInterface $request, ResponseInterface $response) use ($basePath, $connection): ResponseInterface {
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

            $response->getBody()->write(self::withLoginMeta($html, $connection));

            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                // index.html önbelleğe alınmaz; varlık dosyaları zaten hash'li adlarla gelir.
                ->withHeader('Cache-Control', 'no-store');
        };
    }

    /**
     * Giriş ekranı meta etiketini `<head>`e ekler (İE#13 EK-B).
     *
     * Değerler yuvarlanmış metinlerdir; ham ciro/kesin sayı taşınmaz. Kaçış
     * `htmlspecialchars` ile yapılır — içerik tümüyle sunucu üretimi olsa da meta
     * içeriğine kaçışsız veri yazma alışkanlığı bırakılmaz (K20).
     */
    private static function withLoginMeta(string $html, Connection $connection): string
    {
        $stats = new \App\Services\LoginStats($connection);
        $ozet = $stats->summary();
        $payload = json_encode([
            'products' => $ozet['products'],
            'volume' => $ozet['volume'],
            'two_factor' => $stats->twoFactorEnabled(),
            'version' => AppVersion::VALUE,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $meta = '<meta name="tedarikapp-giris" content="' . htmlspecialchars($payload, ENT_QUOTES, 'UTF-8') . '">';

        return str_contains($html, '</head>')
            ? str_replace('</head>', $meta . '</head>', $html)
            : $meta . $html;
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
