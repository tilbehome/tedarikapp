<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthSession;
use App\Core\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF koruması (K16, İE#4 §3).
 *
 * Okuma metotları (GET/HEAD/OPTIONS) muaftır; diğer tüm isteklerde `X-CSRF-Token`
 * başlığı oturumdaki token ile eşleşmelidir. Token girişte üretilir ve
 * `GET /api/auth/me` yanıtında döner.
 *
 * Bu middleware KORUMALI uçlara takılır: giriş uçlarında (login/totp/recovery) oturumda
 * henüz token yoktur — token "girişte üretilir" kuralı gereği o uçlar kapsam dışıdır.
 */
final class Csrf implements MiddlewareInterface
{
    public const HEADER = 'X-CSRF-Token';

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly AuthSession $session,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $expected = $this->session->csrfToken();
        $provided = $request->getHeaderLine(self::HEADER);

        if ($expected === null || $provided === '' || !hash_equals($expected, $provided)) {
            return Response::error(
                $this->responseFactory->createResponse(),
                'CSRF',
                'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.',
                403,
            );
        }

        return $handler->handle($request);
    }
}
