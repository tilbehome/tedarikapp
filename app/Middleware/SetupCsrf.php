<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Setup\SetupState;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sihirbazın kendi CSRF koruması (İE#5 §11).
 *
 * Panelin `Csrf` middleware'inden ayrıdır: sihirbaz kurulum öncesi çalışır, panel
 * oturumu ve token'ı henüz yoktur. Token sihirbaz sayfası açıldığında üretilir ve
 * `GET /api/setup/state` ile alınır.
 *
 * Neden gerekli: kurulum penceresi kimlik doğrulaması olmayan tek yüzeydir; token
 * olmadan, kurulum yapan kişi başka bir sekmede kötü niyetli bir sayfaya girse o sayfa
 * kurulum uçlarını sessizce çağırabilirdi.
 */
final class SetupCsrf implements MiddlewareInterface
{
    public const string HEADER = 'X-Setup-Token';

    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly SetupState $state,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $provided = $request->getHeaderLine(self::HEADER);
        if ($provided === '' || !hash_equals($this->state->csrfToken(), $provided)) {
            return Response::error(
                $this->responseFactory->createResponse(),
                'CSRF',
                'Kurulum oturumu doğrulanamadı. Sayfayı yenileyip baştan deneyin.',
                403,
            );
        }

        return $handler->handle($request);
    }
}
