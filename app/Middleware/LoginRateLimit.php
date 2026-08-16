<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthServices;
use App\Core\ClientIp;
use App\Core\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Giriş uçlarının hız/kilit kapısı (K16, İE#4 §3).
 *
 * Anahtar IP + e-postadır. `login` isteğinde e-posta gövdeden, `totp`/`recovery`
 * isteğinde şifre aşamasını geçmiş oturumun kullanıcısından okunur — böylece
 * art arda yanlış TOTP denemeleri de aynı sayaca işler.
 *
 * Kilitliyken 403 `LOCKED` + `meta.retry_after_seconds` döner (docs/10 §2).
 */
final class LoginRateLimit implements MiddlewareInterface
{
    /** Çözümlenen e-posta, controller'ın aynı işi tekrar yapmaması için isteğe iliştirilir. */
    public const string EMAIL_ATTRIBUTE = 'auth_throttle_email';

    public function __construct(
        private readonly AuthServices $services,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $email = $this->resolveEmail($request);
        if ($email === '') {
            // E-posta belirlenemiyorsa sayaç anahtarı da yok; doğrulama hatasını uç kendisi döndürür.
            return $handler->handle($request);
        }

        $ip = ClientIp::from($request);
        $retryAfter = $this->services->throttle->retryAfterSeconds($email, $ip, $this->services->clock->now());

        if ($retryAfter > 0) {
            return Response::error(
                $this->responseFactory->createResponse(),
                'LOCKED',
                sprintf(
                    'Çok fazla hatalı deneme yapıldı. %d dakika sonra tekrar deneyin.',
                    (int) ceil($retryAfter / 60),
                ),
                403,
                [],
                ['retry_after_seconds' => $retryAfter],
            );
        }

        return $handler->handle($request->withAttribute(self::EMAIL_ATTRIBUTE, $email));
    }

    private function resolveEmail(ServerRequestInterface $request): string
    {
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['email']) && is_string($body['email'])) {
            return trim($body['email']);
        }

        $userId = $this->services->session->userId();
        if ($userId === null) {
            return '';
        }

        $user = $this->services->users->findById($userId);

        return $user === null ? '' : $user->email;
    }
}
