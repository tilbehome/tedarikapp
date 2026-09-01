<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthServices;
use App\Auth\RememberTokenService;
use App\Auth\RememberTokenStatus;
use App\Core\ClientIp;
use App\Core\Cookie;
use App\Core\Response;
use App\Services\ActivityLog;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Korumalı uçların kapısı (İE#4 §3).
 *
 * Sıra: aktif oturum → TOTP aşaması → "beni hatırla" çerezinden sessiz giriş.
 * Oturumu olan istekte boşta kalma aşımı denetlenir ve `last_activity` tazelenir.
 */
final class Auth implements MiddlewareInterface
{
    /** Doğrulanmış kullanıcının isteğe iliştirildiği anahtar. */
    public const USER_ATTRIBUTE = 'auth_user';

    public function __construct(
        private readonly AuthServices $services,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $this->services->session;

        if ($session->isLoggedIn()) {
            return $this->handleActiveSession($request, $handler);
        }

        if ($session->isAwaitingTotp()) {
            return $this->totpRequired();
        }

        return $this->attemptRememberLogin($request, $handler);
    }

    private function handleActiveSession(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $this->services->session;

        if ($session->isIdle($this->services->sessionLifetimeMinutes())) {
            $session->destroy();

            return $this->unauthenticated('Oturum süresi doldu. Lütfen yeniden giriş yapın.');
        }

        $userId = $session->userId();
        $user = $userId === null ? null : $this->services->users->findById($userId);
        if ($user === null) {
            // Kullanıcı silinmiş ama oturum ayakta: oturumu düşür.
            $session->destroy();

            return $this->unauthenticated('Oturum geçersiz. Lütfen yeniden giriş yapın.');
        }

        $session->touch();

        return $handler->handle($request->withAttribute(self::USER_ATTRIBUTE, $user));
    }

    private function attemptRememberLogin(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $raw = $cookies[RememberTokenService::COOKIE_NAME] ?? null;
        $now = $this->services->clock->now();
        $ip = ClientIp::from($request);

        $match = $this->services->rememberTokens->validate(is_string($raw) ? $raw : null, $now);

        return match ($match->status) {
            RememberTokenStatus::Absent, RememberTokenStatus::Unknown => $this->unauthenticated(
                'Bu işlem için giriş yapmanız gerekiyor.',
            ),
            RememberTokenStatus::Expired => $this->expiredToken($match->tokenId, $match->userId),
            RememberTokenStatus::Stolen => $this->stolenToken($match->userId, $ip),
            RememberTokenStatus::Valid => $this->silentLogin(
                $request,
                $handler,
                $match->userId,
                $match->tokenId,
                $ip,
                is_string($raw) ? $raw : null,
            ),
        };
    }

    private function silentLogin(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        ?int $userId,
        ?int $tokenId,
        string $ip,
        /** D4: rotasyon CAS'i için ELDEKİ çerez değeri (`selector:validator`). */
        ?string $mevcutCerez = null,
    ): ResponseInterface {
        $user = $userId === null ? null : $this->services->users->findById($userId);
        if ($user === null || $tokenId === null) {
            return $this->unauthenticated('Bu işlem için giriş yapmanız gerekiyor.');
        }

        $now = $this->services->clock->now();
        $this->services->session->loginFromRemember($user->id, $tokenId);
        $this->services->activity->recordAuth(
            ActivityLog::REMEMBER_LOGIN,
            $user->email,
            $ip,
            $now,
            $user->id,
        );

        // G8: kullanım başına rotasyon — çerez her sessiz girişte tazelenir.
        //
        // v1.2.1 D4: ELİMİZDEKİ token da koşula girer (CAS). Aynı çerezle
        // paralel gelen ikinci istek rotasyonu KAYBEDER ve `null` alır; eskiden
        // ikisi de kazanıyor ve ikisi de geçerli yeni çerez alıyordu.
        [$mevcutSelector, $mevcutValidator] = str_contains((string) $mevcutCerez, ':')
            ? explode(':', (string) $mevcutCerez, 2)
            : ['', ''];
        $yeniCerez = $this->services->rememberTokens->rotate(
            $tokenId,
            $user->id,
            $now,
            $mevcutSelector,
            $mevcutValidator,
        );
        $sonKullanma = $this->services->rememberTokens->expiresAt($tokenId, $now->getTimezone());

        $response = $handler->handle($request->withAttribute(self::USER_ATTRIBUTE, $user));

        if ($yeniCerez !== null && $sonKullanma !== null) {
            $response = Cookie::write(
                $response,
                RememberTokenService::COOKIE_NAME,
                $yeniCerez,
                $sonKullanma,
                $this->services->cookiesAreSecure(),
            );
        }

        return $response;
    }

    private function expiredToken(?int $tokenId, ?int $userId): ResponseInterface
    {
        if ($tokenId !== null && $userId !== null) {
            $this->services->rememberTokens->revoke($tokenId, $userId);
        }

        return $this->clearRememberCookie(
            $this->unauthenticated('Oturum hatırlama süresi doldu. Lütfen yeniden giriş yapın.'),
        );
    }

    /**
     * Selector doğru, validator yanlış: çerez kopyalanmış demektir.
     * Kullanıcının TÜM token'ları iptal edilir ve olay loglanır (İE#4 §3/§5).
     */
    private function stolenToken(?int $userId, string $ip): ResponseInterface
    {
        if ($userId !== null) {
            $user = $this->services->users->findById($userId);
            $this->services->rememberTokens->revokeAllForUser($userId);
            $this->services->activity->recordAuth(
                ActivityLog::REMEMBER_THEFT,
                $user?->email,
                $ip,
                $this->services->clock->now(),
                $userId,
            );
            $this->services->logger->warning('Çalıntı "beni hatırla" token\'ı: tüm oturum hatırlama kayıtları silindi.', [
                'user_id' => $userId,
                'ip' => $ip,
            ]);
        }

        return $this->clearRememberCookie(
            $this->unauthenticated('Oturum hatırlama kaydı geçersiz kılındı. Lütfen yeniden giriş yapın.'),
        );
    }

    private function clearRememberCookie(ResponseInterface $response): ResponseInterface
    {
        return Cookie::clear($response, RememberTokenService::COOKIE_NAME, $this->services->cookiesAreSecure());
    }

    private function unauthenticated(string $message): ResponseInterface
    {
        return Response::error($this->responseFactory->createResponse(), 'UNAUTHENTICATED', $message, 401);
    }

    private function totpRequired(): ResponseInterface
    {
        return Response::error(
            $this->responseFactory->createResponse(),
            'TOTP_REQUIRED',
            'İki adımlı doğrulama kodu bekleniyor.',
            401,
        );
    }
}
