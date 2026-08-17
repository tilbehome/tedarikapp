<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Clock;

/**
 * Oturum durum makinesi (İE#4 §2): anonim → sifre_dogru (TOTP bekliyor) → girisli.
 *
 * Ham oturum anahtarlarına yalnızca bu sınıf dokunur; controller ve middleware
 * anlamlı metotlarla konuşur.
 */
final class AuthSession
{
    public const STAGE_TOTP = 'sifre_dogru';
    public const STAGE_LOGGED_IN = 'girisli';

    private const KEY_STAGE = 'auth_stage';
    private const KEY_USER_ID = 'auth_user_id';
    private const KEY_REMEMBER = 'auth_remember_requested';
    private const KEY_CSRF = 'auth_csrf_token';
    private const KEY_LAST_ACTIVITY = 'auth_last_activity';
    private const KEY_REMEMBER_TOKEN_ID = 'auth_remember_token_id';

    public function __construct(
        private readonly SessionInterface $session,
        private readonly Clock $clock,
    ) {
    }

    public function stage(): ?string
    {
        $stage = $this->session->get(self::KEY_STAGE);

        return is_string($stage) ? $stage : null;
    }

    public function userId(): ?int
    {
        $id = $this->session->get(self::KEY_USER_ID);

        return is_int($id) ? $id : null;
    }

    public function isLoggedIn(): bool
    {
        return $this->stage() === self::STAGE_LOGGED_IN && $this->userId() !== null;
    }

    public function isAwaitingTotp(): bool
    {
        return $this->stage() === self::STAGE_TOTP && $this->userId() !== null;
    }

    /** Şifre doğrulandı; ikinci faktör bekleniyor. CSRF token'ı burada üretilir (İE#4 §3). */
    public function beginTotpStage(int $userId, bool $remember): void
    {
        $this->session->regenerate();
        $this->session->set(self::KEY_STAGE, self::STAGE_TOTP);
        $this->session->set(self::KEY_USER_ID, $userId);
        $this->session->set(self::KEY_REMEMBER, $remember);
        $this->session->set(self::KEY_CSRF, bin2hex(random_bytes(32)));
        $this->touch();
    }

    /** İkinci faktör geçildi: oturum tam yetkili. */
    public function completeLogin(): void
    {
        $this->session->regenerate();
        $this->session->set(self::KEY_STAGE, self::STAGE_LOGGED_IN);
        $this->touch();
    }

    /** "Beni hatırla" çerezinden sessiz giriş — TOTP aşaması atlanır (token zaten 2FA sonrası verildi). */
    public function loginFromRemember(int $userId, int $tokenId): void
    {
        $this->session->regenerate();
        $this->session->set(self::KEY_STAGE, self::STAGE_LOGGED_IN);
        $this->session->set(self::KEY_USER_ID, $userId);
        $this->session->set(self::KEY_REMEMBER, false);
        $this->session->set(self::KEY_REMEMBER_TOKEN_ID, $tokenId);
        $this->session->set(self::KEY_CSRF, bin2hex(random_bytes(32)));
        $this->touch();
    }

    public function rememberRequested(): bool
    {
        return $this->session->get(self::KEY_REMEMBER) === true;
    }

    public function rememberTokenId(): ?int
    {
        $id = $this->session->get(self::KEY_REMEMBER_TOKEN_ID);

        return is_int($id) ? $id : null;
    }

    public function setRememberTokenId(int $tokenId): void
    {
        $this->session->set(self::KEY_REMEMBER_TOKEN_ID, $tokenId);
    }

    public function csrfToken(): ?string
    {
        $token = $this->session->get(self::KEY_CSRF);

        return is_string($token) ? $token : null;
    }

    public function touch(): void
    {
        $this->session->set(self::KEY_LAST_ACTIVITY, $this->clock->now()->getTimestamp());
    }

    /** Boşta kalma aşımı (.env SESSION_LIFETIME, dakika). */
    public function isIdle(int $lifetimeMinutes): bool
    {
        $last = $this->session->get(self::KEY_LAST_ACTIVITY);
        if (!is_int($last)) {
            return false;
        }

        return ($this->clock->now()->getTimestamp() - $last) > ($lifetimeMinutes * 60);
    }

    public function destroy(): void
    {
        $this->session->destroy();
    }
}
