<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Encrypter;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use SensitiveParameter;

/**
 * TOTP (2FA) sarmalayıcısı — robthree/twofactorauth üzerine (K19).
 *
 * Secret veritabanında ŞİFRELİ durur (docs/04 §2): doğrulama için geri okunması
 * gerektiğinden hash'lenemez. Şifreleme/çözme yalnızca bu sınıftan geçer.
 */
final class TotpService
{
    /** Saat kayması toleransı: ±1 periyot (varsayılan 30 sn) — telefon saati birkaç saniye kayabilir. */
    private const DISCREPANCY = 1;

    private ?TwoFactorAuth $tfa = null;

    public function __construct(
        private readonly Config $config,
        private readonly Encrypter $encrypter,
        private readonly Clock $clock,
    ) {
    }

    /** Yeni bir TOTP secret üretir (düz metin — çağıran hemen şifreleyip saklar). */
    public function createSecret(): string
    {
        return $this->tfa()->createSecret();
    }

    public function encryptSecret(#[SensitiveParameter] string $plainSecret): string
    {
        return $this->encrypter->encrypt($plainSecret);
    }

    /**
     * Kullanıcının şifreli secret'ıyla kodu doğrular.
     * Secret yoksa veya çözülemiyorsa doğrulama BAŞARISIZ sayılır (fail-closed).
     */
    public function verify(?string $encryptedSecret, string $code): bool
    {
        if ($encryptedSecret === null || $encryptedSecret === '') {
            return false;
        }
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        try {
            $secret = $this->encrypter->decrypt($encryptedSecret);
        } catch (\RuntimeException) {
            return false;
        }

        // Zaman uygulamanın saatinden gelir (Clock): testlerde sabitlenebilir, üretimde sistem saati.
        return $this->tfa()->verifyCode($secret, $code, self::DISCREPANCY, $this->clock->now()->getTimestamp());
    }

    /** Authenticator uygulamasına okutulacak `otpauth://` URI'si. */
    public function provisioningUri(string $label, #[SensitiveParameter] string $plainSecret): string
    {
        return $this->tfa()->getQRText($label, $plainSecret);
    }

    private function tfa(): TwoFactorAuth
    {
        // QR sağlayıcısı yalnızca görsel üretimi için gerekir; burada URI/doğrulama
        // kullanıldığından sağlayıcı hiç çalıştırılmaz (kurucu tembeldir).
        return $this->tfa ??= new TwoFactorAuth(
            new BaconQrCodeProvider(format: 'svg'),
            $this->config->get('TOTP_ISSUER', 'tedarikapp'),
        );
    }
}
