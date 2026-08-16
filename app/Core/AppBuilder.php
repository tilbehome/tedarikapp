<?php

declare(strict_types=1);

namespace App\Core;

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
use Throwable;

/**
 * Slim uygulamasını kurar. index.php gerçek bağımlılıklarla, testler
 * sahte PDO fabrikasıyla çağırır — uçlar HTTP sunucusu olmadan test edilebilir.
 */
final class AppBuilder
{
    /**
     * @param callable(): \PDO $pdoFactory Bağlantı tembel kurulur; sağlık ucu başarısızlığı zarfla raporlar.
     *
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    public static function build(Config $config, callable $pdoFactory, LoggerInterface $logger): App
    {
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new SecurityHeaders());

        $app->get('/api/health', self::healthAction($config, $pdoFactory, $logger));

        $app->addErrorMiddleware(
            displayErrorDetails: !$config->isProduction(),
            logErrors: true,
            logErrorDetails: true,
            logger: $logger,
        )->setDefaultErrorHandler(self::errorHandler($app->getResponseFactory(), $logger));

        return $app;
    }

    /** @param callable(): \PDO $pdoFactory */
    private static function healthAction(Config $config, callable $pdoFactory, LoggerInterface $logger): Closure
    {
        return static function (ServerRequestInterface $request, ResponseInterface $response) use ($config, $pdoFactory, $logger): ResponseInterface {
            try {
                $pdoFactory()->query('SELECT 1');
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
