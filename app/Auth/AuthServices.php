<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Connection;
use App\Core\Encrypter;
use App\Core\RequestContext;
use App\Services\ActivityLog;
use App\Services\Bildirim\BildirimKatalogu;
use App\Services\Bildirim\BildirimRepository;
use App\Services\Bildirim\BildirimYayinci;
use App\Services\Bildirim\GrupAnahtariCozucu;
use DateTimeZone;
use Psr\Log\LoggerInterface;

/**
 * Auth bileşenlerinin kompozisyon kökü.
 *
 * Controller ve middleware'ler tek bir bağımlılık alır; nesnelerin nasıl kurulduğu
 * (ve hepsinin AYNI oturum/saat/bağlantıyı paylaştığı) burada tek yerde görünür.
 * Bağlantı tembeldir (Connection) — bu nesneyi kurmak veritabanına dokunmaz.
 */
final class AuthServices
{
    public readonly AuthSession $session;
    public readonly UserRepository $users;
    public readonly PasswordHasher $passwords;
    public readonly TotpService $totp;
    public readonly RecoveryCodeService $recoveryCodes;
    public readonly RememberTokenService $rememberTokens;
    public readonly LoginThrottle $throttle;
    public readonly ActivityLog $activity;
    /**
     * BİLDİRİM YAYINCISI (V3-B A2) — servis kabında durur çünkü olaylar HER
     * KATMANDA doğar: denetleyici, kuyruk işleyicisi, gece süpürmesi. Ayrı ayrı
     * kurulsaydı katalog dosyası her seferinde yeniden okunur ve iki farklı
     * çözücü örneği ortaya çıkardı.
     */
    public readonly BildirimYayinci $bildirim;
    public readonly DateTimeZone $timezone;

    public function __construct(
        public readonly Config $config,
        public readonly Connection $connection,
        SessionInterface $session,
        public readonly Clock $clock,
        public readonly LoggerInterface $logger,
        ?RequestContext $requestContext = null,
        ?string $basePath = null,
    ) {
        $this->timezone = new DateTimeZone($config->get('TZ', 'Europe/Istanbul'));
        $this->session = new AuthSession($session, $clock);
        $this->users = new UserRepository($connection);
        $this->passwords = new PasswordHasher();
        $this->totp = new TotpService($config, new Encrypter($config), $clock);
        $this->recoveryCodes = new RecoveryCodeService($connection, $this->passwords);
        $this->rememberTokens = new RememberTokenService($connection);
        $this->activity = new ActivityLog($connection, $requestContext);
        $this->bildirim = new BildirimYayinci(
            new BildirimRepository($connection),
            new BildirimKatalogu($basePath ?? dirname(__DIR__, 2)),
            new GrupAnahtariCozucu(),
            $clock,
        );
        $this->throttle = new LoginThrottle(
            $connection,
            $this->timezone,
            $config->getInt('LOGIN_MAX_ATTEMPTS', 5),
            $config->getInt('LOGIN_LOCKOUT_MINUTES', 15),
        );
    }

    public function sessionLifetimeMinutes(): int
    {
        return $this->config->getInt('SESSION_LIFETIME', 120);
    }

    public function rememberLifetimeMinutes(): int
    {
        return $this->config->getInt('REMEMBER_ME_LIFETIME', 43200);
    }

    public function cookiesAreSecure(): bool
    {
        return str_starts_with($this->config->get('APP_URL', ''), 'https://');
    }
}
