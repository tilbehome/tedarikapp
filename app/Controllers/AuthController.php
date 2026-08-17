<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServices;
use App\Auth\RememberTokenService;
use App\Auth\RememberTokenStatus;
use App\Auth\User;
use App\Core\ClientIp;
use App\Core\Cookie;
use App\Core\Dates;
use App\Core\Response;
use App\Middleware\Auth;
use App\Services\ActivityLog;
use DateTimeImmutable;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Kimlik doğrulama uçları — docs/10 §2 sözleşmesi birebir uygulanır.
 *
 * Akış: login (şifre) → totp | recovery (ikinci faktör) → girisli oturum.
 * Yanıtlara yalnızca `User::toApiArray()` çıkar; hash/secret hiçbir uçtan sızmaz.
 */
final class AuthController
{
    public function __construct(private readonly AuthServices $services)
    {
    }

    /** POST /api/auth/login — {email, password} → 200 {stage:"totp"} */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $email = $this->stringField($body, 'email');
        $password = $this->stringField($body, 'password');

        $fields = [];
        if ($email === '') {
            $fields['email'] = 'E-posta adresi zorunludur.';
        } elseif (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fields['email'] = 'Geçerli bir e-posta adresi girin (en çok 190 karakter).';
        }
        if ($password === '') {
            $fields['password'] = 'Şifre zorunludur.';
        }
        if ($fields !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $fields);
        }

        $now = $this->services->clock->now();
        $ip = ClientIp::from($request);
        $user = $this->services->users->findByEmail($email);

        if ($user === null || !$this->services->passwords->verify($password, $user->passwordHash)) {
            $this->recordFailure(ActivityLog::LOGIN_FAILED, $email, $ip, $now, $user?->id);

            return Response::error($response, 'UNAUTHENTICATED', 'E-posta veya şifre hatalı.', 401);
        }

        // Argon2id varsayılanları sertleştiyse şifreyi sessizce yeni maliyetle taşı.
        if ($this->services->passwords->needsRehash($user->passwordHash)) {
            $this->services->users->updatePasswordHash($user->id, $this->services->passwords->hash($password), $now);
        }

        $remember = ($body['remember'] ?? false) === true;
        $this->services->session->beginTotpStage($user->id, $remember);

        // K45 (Ürün Sahibi talimatı — basit giriş): 2FA tanımlı değilse şifre yeterlidir.
        if ($user->totpSecret === null) {
            $this->services->activity->recordAuth(ActivityLog::TOTP_SUCCESS, $user->email, $ip, $now, $user->id);

            return $this->completeLogin($response, $user, $ip, $now, ['user' => $this->userPayload($user)]);
        }

        return Response::success($response, ['stage' => 'totp']);
    }

    /** POST /api/auth/totp — {code} → 200 {user} */
    public function totp(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->userAwaitingSecondFactor();
        if ($user === null) {
            return Response::error($response, 'UNAUTHENTICATED', 'Önce e-posta ve şifrenizle giriş yapın.', 401);
        }

        $code = $this->stringField($this->body($request), 'code');
        if ($code === '') {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'code' => 'Doğrulama kodu zorunludur.',
            ]);
        }

        $now = $this->services->clock->now();
        $ip = ClientIp::from($request);

        if (!$this->services->totp->verify($user->totpSecret, $code)) {
            $this->recordFailure(ActivityLog::TOTP_FAILED, $user->email, $ip, $now, $user->id);

            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'code' => 'Doğrulama kodu geçersiz veya süresi geçmiş.',
            ]);
        }

        $this->services->activity->recordAuth(ActivityLog::TOTP_SUCCESS, $user->email, $ip, $now, $user->id);

        return $this->completeLogin($response, $user, $ip, $now, ['user' => $this->userPayload($user)]);
    }

    /** POST /api/auth/recovery — {code} → 200 {user, remaining_codes} */
    public function recovery(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->userAwaitingSecondFactor();
        if ($user === null) {
            return Response::error($response, 'UNAUTHENTICATED', 'Önce e-posta ve şifrenizle giriş yapın.', 401);
        }

        $code = $this->stringField($this->body($request), 'code');
        if ($code === '') {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'code' => 'Kurtarma kodu zorunludur.',
            ]);
        }

        $now = $this->services->clock->now();
        $ip = ClientIp::from($request);

        if (!$this->services->recoveryCodes->consume($user->id, $code, $now)) {
            $this->recordFailure(ActivityLog::RECOVERY_FAILED, $user->email, $ip, $now, $user->id);

            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'code' => 'Kurtarma kodu geçersiz veya daha önce kullanılmış.',
            ]);
        }

        $this->services->activity->recordAuth(ActivityLog::RECOVERY_USED, $user->email, $ip, $now, $user->id);

        return $this->completeLogin($response, $user, $ip, $now, [
            'user' => $this->userPayload($user),
            'remaining_codes' => $this->services->recoveryCodes->remainingCount($user->id),
        ]);
    }

    /** POST /api/auth/logout — 204 */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $now = $this->services->clock->now();

        $this->revokeCurrentRememberToken($request, $user->id, $now);
        $this->services->activity->recordAuth(ActivityLog::LOGOUT, $user->email, ClientIp::from($request), $now, $user->id);
        $this->services->session->destroy();

        return Cookie::clear($response->withStatus(204), RememberTokenService::COOKIE_NAME, $this->services->cookiesAreSecure());
    }

    /** GET /api/auth/me — 200 {user, csrf_token} */
    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);

        return Response::success($response, [
            'user' => $this->userPayload($user),
            'csrf_token' => $this->services->session->csrfToken(),
        ]);
    }

    /** GET /api/auth/sessions — 200 [{id, created_at, expires_at, is_current}] */
    public function sessions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $currentId = $this->services->session->rememberTokenId();

        $sessions = [];
        foreach ($this->services->rememberTokens->listForUser($user->id) as $token) {
            $sessions[] = [
                'id' => $token['id'],
                'created_at' => Dates::toIso($token['created_at'], $this->services->timezone),
                'expires_at' => Dates::toIso($token['expires_at'], $this->services->timezone),
                'is_current' => $token['id'] === $currentId,
            ];
        }

        return Response::success($response, $sessions);
    }

    /**
     * DELETE /api/auth/sessions/{id} — 204
     *
     * @param array<string, string> $args
     */
    public function revokeSession(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $id = $args['id'] ?? '';

        if (preg_match('/^\d+$/', $id) !== 1) {
            return Response::error($response, 'NOT_FOUND', 'Oturum kaydı bulunamadı.', 404);
        }

        $tokenId = (int) $id;
        if (!$this->services->rememberTokens->revoke($tokenId, $user->id)) {
            return Response::error($response, 'NOT_FOUND', 'Oturum kaydı bulunamadı.', 404);
        }

        $this->services->activity->recordAuth(
            ActivityLog::REMEMBER_REVOKED,
            $user->email,
            ClientIp::from($request),
            $this->services->clock->now(),
            $user->id,
        );

        $response = $response->withStatus(204);
        if ($this->services->session->rememberTokenId() === $tokenId) {
            $response = Cookie::clear($response, RememberTokenService::COOKIE_NAME, $this->services->cookiesAreSecure());
        }

        return $response;
    }

    /**
     * İkinci faktör geçildi: oturumu tam yetkiliye çevirir, sayaç sıfırlayan
     * `login_success` kaydını yazar ve istenmişse "beni hatırla" çerezini kurar.
     *
     * @param array<string, mixed> $payload
     */
    private function completeLogin(
        ResponseInterface $response,
        User $user,
        string $ip,
        DateTimeImmutable $now,
        array $payload,
    ): ResponseInterface {
        $rememberRequested = $this->services->session->rememberRequested();
        $this->services->session->completeLogin();
        $this->services->activity->recordAuth(ActivityLog::LOGIN_SUCCESS, $user->email, $ip, $now, $user->id);

        if (!$rememberRequested) {
            return Response::success($response, $payload);
        }

        $this->services->rememberTokens->purgeExpired($now);
        $token = $this->services->rememberTokens->issue($user->id, $now, $this->services->rememberLifetimeMinutes());
        $this->services->session->setRememberTokenId($token['id']);

        return Cookie::write(
            Response::success($response, $payload),
            RememberTokenService::COOKIE_NAME,
            $token['cookie'],
            $token['expires_at'],
            $this->services->cookiesAreSecure(),
        );
    }

    /**
     * Hatalı denemeyi yazar ve bu deneme kilidi tetiklediyse `login_locked` kaydını da düşer
     * (kilitlenme anı bir kez loglanır — her engellenen istekte değil).
     */
    private function recordFailure(string $action, string $email, string $ip, DateTimeImmutable $now, ?int $userId): void
    {
        $this->services->activity->recordAuth($action, $email, $ip, $now, $userId);

        if ($this->services->throttle->retryAfterSeconds($email, $ip, $now) > 0) {
            $this->services->activity->recordAuth(ActivityLog::LOGIN_LOCKED, $email, $ip, $now, $userId);
        }
    }

    /** Şifre aşamasını geçmiş oturumun kullanıcısı (TOTP/recovery uçları için). */
    private function userAwaitingSecondFactor(): ?User
    {
        $session = $this->services->session;
        if (!$session->isAwaitingTotp()) {
            return null;
        }
        if ($session->isIdle($this->services->sessionLifetimeMinutes())) {
            $session->destroy();

            return null;
        }

        $userId = $session->userId();

        return $userId === null ? null : $this->services->users->findById($userId);
    }

    /** Korumalı uçlarda kullanıcıyı Auth middleware iliştirir; yoksa rota yanlış kurulmuş demektir. */
    private function authenticatedUser(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(Auth::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new LogicException('Korumalı uç Auth middleware olmadan çağrıldı.');
        }

        return $user;
    }

    private function revokeCurrentRememberToken(ServerRequestInterface $request, int $userId, DateTimeImmutable $now): void
    {
        $tokenId = $this->services->session->rememberTokenId();
        if ($tokenId !== null) {
            $this->services->rememberTokens->revoke($tokenId, $userId);

            return;
        }

        $cookies = $request->getCookieParams();
        $raw = $cookies[RememberTokenService::COOKIE_NAME] ?? null;
        $match = $this->services->rememberTokens->validate(is_string($raw) ? $raw : null, $now);
        if ($match->status === RememberTokenStatus::Valid && $match->tokenId !== null) {
            $this->services->rememberTokens->revoke($match->tokenId, $userId);
        }
    }

    /** @return array{id: int, email: string, created_at: string} */
    private function userPayload(User $user): array
    {
        return $user->toApiArray($this->services->timezone);
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /** @param array<string, mixed> $body */
    private function stringField(array $body, string $key): string
    {
        $value = $body[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}
