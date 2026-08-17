<?php

declare(strict_types=1);

namespace App\Setup;

use App\Auth\SessionInterface;

/**
 * Sihirbazın adım durumu — kendi oturumu ve kendi CSRF token'ı vardır (İE#5 §11).
 *
 * Sıra ZORLANIR: bir adımın ucu, kendisinden önceki adımlar tamamlanmadan çalışmaz.
 * Aksi hâlde kurulum yarıda kalmış bir sistemde (ör. .env yazılmadan migration)
 * anlaşılmaz hatalar üretirdi; kullanıcı da hangi adımda olduğunu kaybederdi.
 */
final class SetupState
{
    public const string STEP_REQUIREMENTS = 'requirements';
    public const string STEP_DATABASE = 'database';
    public const string STEP_ENV = 'env';
    public const string STEP_MIGRATE = 'migrate';
    public const string STEP_ADMIN = 'admin';
    public const string STEP_RECOVERY = 'recovery';
    public const string STEP_DONE = 'done';

    /** Adımların kesin sırası. */
    public const array ORDER = [
        self::STEP_REQUIREMENTS,
        self::STEP_DATABASE,
        self::STEP_ENV,
        self::STEP_MIGRATE,
        self::STEP_ADMIN,
        self::STEP_RECOVERY,
        self::STEP_DONE,
    ];

    private const string KEY_STEP = 'setup_step';
    private const string KEY_CSRF = 'setup_csrf';
    private const string KEY_DATA = 'setup_data';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function currentStep(): string
    {
        $step = $this->session->get(self::KEY_STEP);

        return is_string($step) && in_array($step, self::ORDER, true) ? $step : self::STEP_REQUIREMENTS;
    }

    /** Verilen adım şu an çalıştırılabilir mi (tam sırası geldi mi)? */
    public function canRun(string $step): bool
    {
        return $this->indexOf($step) <= $this->indexOf($this->currentStep());
    }

    /** Adımı tamamlar ve bir sonrakine geçer (geri sarmaz — tekrar çalıştırma sırayı bozmaz). */
    public function complete(string $step): void
    {
        $next = self::ORDER[min($this->indexOf($step) + 1, count(self::ORDER) - 1)];
        if ($this->indexOf($next) > $this->indexOf($this->currentStep())) {
            $this->session->set(self::KEY_STEP, $next);
        }
    }

    public function isFinished(): bool
    {
        return $this->currentStep() === self::STEP_DONE;
    }

    /**
     * `.env` dosyasını BU oturum mu üretti? (K37)
     *
     * Sihirbaz `.env`i yazdığı/ürettiği anda beklenen APP_KEY oturuma konur.
     * SetupGuard, diskte `.env` varken yalnızca bu işarete sahip oturumun devam
     * etmesine izin verir — başka herkes için kurulum kilitlidir.
     */
    public function ownsEnvFile(): bool
    {
        $key = $this->get('env_app_key');

        return is_string($key) && $key !== '';
    }

    public function csrfToken(): string
    {
        $token = $this->session->get(self::KEY_CSRF);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $fresh = bin2hex(random_bytes(32));
        $this->session->set(self::KEY_CSRF, $fresh);

        return $fresh;
    }

    /** Adımlar arasında taşınan veri (DB bilgileri, bekleyen admin kaydı). */
    public function put(string $key, mixed $value): void
    {
        $data = $this->data();
        $data[$key] = $value;
        $this->session->set(self::KEY_DATA, $data);
    }

    public function pull(string $key): mixed
    {
        $data = $this->data();
        $value = $data[$key] ?? null;
        unset($data[$key]);
        $this->session->set(self::KEY_DATA, $data);

        return $value;
    }

    public function get(string $key): mixed
    {
        return $this->data()[$key] ?? null;
    }

    public function reset(): void
    {
        $this->session->destroy();
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        $data = $this->session->get(self::KEY_DATA);

        return is_array($data) ? $data : [];
    }

    private function indexOf(string $step): int
    {
        $index = array_search($step, self::ORDER, true);

        return $index === false ? 0 : $index;
    }
}
