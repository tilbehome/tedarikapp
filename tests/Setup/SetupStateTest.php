<?php

declare(strict_types=1);

namespace Tests\Setup;

use App\Setup\SetupState;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySession;

final class SetupStateTest extends TestCase
{
    private function state(): SetupState
    {
        return new SetupState(new ArraySession());
    }

    public function testIlkAdimGereksinimlerdir(): void
    {
        self::assertSame(SetupState::STEP_REQUIREMENTS, $this->state()->currentStep());
    }

    public function testSiradakiAdimlarKilitlidir(): void
    {
        $state = $this->state();

        self::assertTrue($state->canRun(SetupState::STEP_REQUIREMENTS));
        self::assertFalse($state->canRun(SetupState::STEP_DATABASE));
        self::assertFalse($state->canRun(SetupState::STEP_ADMIN));
    }

    public function testAdimTamamlanincaSonrakiAcilir(): void
    {
        $state = $this->state();
        $state->complete(SetupState::STEP_REQUIREMENTS);

        self::assertSame(SetupState::STEP_DATABASE, $state->currentStep());
        self::assertTrue($state->canRun(SetupState::STEP_DATABASE));
        self::assertFalse($state->canRun(SetupState::STEP_ENV));
    }

    public function testTamamlanmisAdimTekrarCalistirilabilir(): void
    {
        $state = $this->state();
        $state->complete(SetupState::STEP_REQUIREMENTS);
        $state->complete(SetupState::STEP_DATABASE);

        // Geriye dönüp tekrar denetim yapmak serbesttir (kullanıcı eksik giderip döner).
        self::assertTrue($state->canRun(SetupState::STEP_REQUIREMENTS));
    }

    public function testEskiAdimiTekrarTamamlamakSirayiGeriSarmaz(): void
    {
        $state = $this->state();
        $state->complete(SetupState::STEP_REQUIREMENTS);
        $state->complete(SetupState::STEP_DATABASE);
        self::assertSame(SetupState::STEP_ENV, $state->currentStep());

        $state->complete(SetupState::STEP_REQUIREMENTS);

        self::assertSame(SetupState::STEP_ENV, $state->currentStep(), 'İlerleme geri gitmemeli.');
    }

    public function testTumAdimlarTamamlanincaBiter(): void
    {
        $state = $this->state();
        foreach (SetupState::ORDER as $step) {
            $state->complete($step);
        }

        self::assertTrue($state->isFinished());
        self::assertSame(SetupState::STEP_DONE, $state->currentStep());
    }

    public function testCsrfTokenUretilirVeSabitKalir(): void
    {
        $state = $this->state();
        $token = $state->csrfToken();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $state->csrfToken(), 'Token her çağrıda değişmemeli.');
    }

    public function testVeriAdimlarArasindaTasinir(): void
    {
        $state = $this->state();
        $state->put('database', ['host' => 'localhost']);

        self::assertSame(['host' => 'localhost'], $state->get('database'));
    }

    public function testPullVeriyiOkurVeSiler(): void
    {
        $state = $this->state();
        $state->put('pending_admin', ['email' => 'admin@tedarikapp.test']);

        self::assertSame(['email' => 'admin@tedarikapp.test'], $state->pull('pending_admin'));
        self::assertNull($state->get('pending_admin'), 'Hassas veri okunduktan sonra oturumda kalmamalı.');
    }

    public function testResetHerSeyiSiler(): void
    {
        $state = $this->state();
        $state->complete(SetupState::STEP_REQUIREMENTS);
        $state->put('database', ['pass' => 'gizli']);

        $state->reset();

        self::assertSame(SetupState::STEP_REQUIREMENTS, $state->currentStep());
        self::assertNull($state->get('database'));
    }
}
