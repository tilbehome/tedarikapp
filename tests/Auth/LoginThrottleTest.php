<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\LoginThrottle;
use App\Services\ActivityLog;
use DateTimeZone;
use Tests\Support\AuthTestCase;

/**
 * Giriş kilidi (K16): 5 hatalı denemede 15 dk kilit, her ek hatada süre iki katı,
 * üst sınır 60 dk, başarılı girişte sıfırlama.
 */
final class LoginThrottleTest extends AuthTestCase
{
    private const string EMAIL = 'admin@tedarikapp.test';
    private const string IP = '203.0.113.7';

    private function throttle(): LoginThrottle
    {
        return new LoginThrottle($this->connection, new DateTimeZone('Europe/Istanbul'), 5, 15);
    }

    private function hataliDeneme(string $action = ActivityLog::LOGIN_FAILED): void
    {
        (new ActivityLog($this->connection))->recordAuth($action, self::EMAIL, self::IP, $this->clock->now());
        $this->clock->advance('+1 second');
    }

    private function basariliGiris(): void
    {
        (new ActivityLog($this->connection))->recordAuth(ActivityLog::LOGIN_SUCCESS, self::EMAIL, self::IP, $this->clock->now());
        $this->clock->advance('+1 second');
    }

    public function testSinirAltindaKilitYok(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->hataliDeneme();
        }

        self::assertSame(0, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));
    }

    public function testBesinciHataylaKilitBaslar(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->hataliDeneme();
        }

        // Son hatadan 1 saniye sonrasındayız: 15 dk - 1 sn kaldı.
        self::assertSame(15 * 60 - 1, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));
    }

    public function testKilitSuresiDolduktanSonraIstekGecer(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->hataliDeneme();
        }
        $this->clock->advance('+15 minutes');

        self::assertSame(0, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));
    }

    public function testHerEkHataylaBeklemeSuresiKatlanir(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->hataliDeneme();
        }
        $this->clock->advance('+16 minutes');

        $this->hataliDeneme(); // 6. hata → 30 dk
        self::assertSame(30 * 60 - 1, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));

        $this->clock->advance('+31 minutes');
        $this->hataliDeneme(); // 7. hata → 60 dk
        self::assertSame(60 * 60 - 1, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));
    }

    public function testBeklemeSuresiAltmisDakikayiAsmaz(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->hataliDeneme();
        }

        self::assertSame(60 * 60 - 1, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));
    }

    public function testBasariliGirisSayaciSifirlar(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->hataliDeneme();
        }
        self::assertGreaterThan(0, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));

        $this->clock->advance('+16 minutes');
        $this->basariliGiris();

        for ($i = 0; $i < 4; $i++) {
            $this->hataliDeneme();
        }

        self::assertSame(0, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));
    }

    public function testTotpVeRecoveryHatalariAyniSayacaIsler(): void
    {
        $this->hataliDeneme(ActivityLog::LOGIN_FAILED);
        $this->hataliDeneme(ActivityLog::TOTP_FAILED);
        $this->hataliDeneme(ActivityLog::TOTP_FAILED);
        $this->hataliDeneme(ActivityLog::RECOVERY_FAILED);
        $this->hataliDeneme(ActivityLog::TOTP_FAILED);

        self::assertGreaterThan(0, $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()));
    }

    /**
     * Regresyon: giriş akışı bir saniyeden kısa sürebilir. Pencere `created_at` ile
     * kesilirse başarıyla AYNI saniyeye düşen hatalı denemeler sayılmaz ve kilit hiç
     * devreye girmez (canlı duman testinde yakalandı). Pencere `id` ile kesilir.
     */
    public function testBasariVeHatalarAyniSaniyedeyseDaKilitCalisir(): void
    {
        $activity = new ActivityLog($this->connection);

        // Saat HİÇ ilerletilmiyor: tüm kayıtlar aynı created_at değerini alır.
        $activity->recordAuth(ActivityLog::LOGIN_SUCCESS, self::EMAIL, self::IP, $this->clock->now());
        for ($i = 0; $i < 5; $i++) {
            $activity->recordAuth(ActivityLog::LOGIN_FAILED, self::EMAIL, self::IP, $this->clock->now());
        }

        self::assertSame(
            15 * 60,
            $this->throttle()->retryAfterSeconds(self::EMAIL, self::IP, $this->clock->now()),
            'Aynı saniyedeki hatalı denemeler de sayılmalı.',
        );
    }

    public function testKilitIpVeEpostaCiftineOzeldir(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->hataliDeneme();
        }

        self::assertSame(0, $this->throttle()->retryAfterSeconds('baska@tedarikapp.test', self::IP, $this->clock->now()));
        self::assertSame(0, $this->throttle()->retryAfterSeconds(self::EMAIL, '198.51.100.4', $this->clock->now()));
    }
}
