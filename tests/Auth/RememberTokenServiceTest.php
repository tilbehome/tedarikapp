<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\RememberTokenService;
use App\Auth\RememberTokenStatus;
use Tests\Support\AuthTestCase;

final class RememberTokenServiceTest extends AuthTestCase
{
    private function service(): RememberTokenService
    {
        return new RememberTokenService($this->connection);
    }

    public function testCerezSelectorVeValidatordenOlusur(): void
    {
        $user = $this->createUser();
        $token = $this->service()->issue($user['id'], $this->clock->now(), 60);

        [$selector, $validator] = explode(':', $token['cookie'], 2);
        self::assertSame(16, strlen($selector));
        self::assertSame(64, strlen($validator));
    }

    public function testValidatorDuzMetinSaklanmaz(): void
    {
        $user = $this->createUser();
        $token = $this->service()->issue($user['id'], $this->clock->now(), 60);
        [, $validator] = explode(':', $token['cookie'], 2);

        $statement = $this->pdo->query('SELECT token_hash FROM remember_tokens');
        $row = $statement === false ? [] : $statement->fetch();
        self::assertIsArray($row);
        self::assertNotSame($validator, $row['token_hash']);
        self::assertSame(hash('sha256', $validator), $row['token_hash']);
    }

    public function testGecerliCerezDogrulanir(): void
    {
        $user = $this->createUser();
        $token = $this->service()->issue($user['id'], $this->clock->now(), 60);

        $match = $this->service()->validate($token['cookie'], $this->clock->now());

        self::assertSame(RememberTokenStatus::Valid, $match->status);
        self::assertSame($user['id'], $match->userId);
        self::assertSame($token['id'], $match->tokenId);
    }

    public function testCerezYoksaVeyaBozuksaAbsentDoner(): void
    {
        self::assertSame(RememberTokenStatus::Absent, $this->service()->validate(null, $this->clock->now())->status);
        self::assertSame(RememberTokenStatus::Absent, $this->service()->validate('bicimsiz', $this->clock->now())->status);
        self::assertSame(RememberTokenStatus::Absent, $this->service()->validate(':', $this->clock->now())->status);
    }

    public function testBilinmeyenSelectorUnknownDoner(): void
    {
        $match = $this->service()->validate('0123456789abcdef:' . str_repeat('f', 64), $this->clock->now());

        self::assertSame(RememberTokenStatus::Unknown, $match->status);
    }

    public function testDogruSelectorYanlisValidatorCalintiSayilir(): void
    {
        $user = $this->createUser();
        $token = $this->service()->issue($user['id'], $this->clock->now(), 60);
        [$selector] = explode(':', $token['cookie'], 2);

        $match = $this->service()->validate($selector . ':' . str_repeat('0', 64), $this->clock->now());

        self::assertSame(RememberTokenStatus::Stolen, $match->status);
        self::assertSame($user['id'], $match->userId);
    }

    public function testSuresiDolanTokenExpiredDoner(): void
    {
        $user = $this->createUser();
        $token = $this->service()->issue($user['id'], $this->clock->now(), 60);

        $this->clock->advance('+61 minutes');

        self::assertSame(RememberTokenStatus::Expired, $this->service()->validate($token['cookie'], $this->clock->now())->status);
    }

    public function testTokenIptalEdilebilirVeBaskasininTokeniSilinemez(): void
    {
        $sahip = $this->createUser('sahip@tedarikapp.test');
        $baskasi = $this->createUser('baskasi@tedarikapp.test');
        $token = $this->service()->issue($sahip['id'], $this->clock->now(), 60);

        self::assertFalse($this->service()->revoke($token['id'], $baskasi['id']));
        self::assertTrue($this->service()->revoke($token['id'], $sahip['id']));
        self::assertSame(RememberTokenStatus::Unknown, $this->service()->validate($token['cookie'], $this->clock->now())->status);
    }

    public function testKullanicininTumTokenlariSilinebilir(): void
    {
        $user = $this->createUser();
        $this->service()->issue($user['id'], $this->clock->now(), 60);
        $this->service()->issue($user['id'], $this->clock->now(), 60);

        self::assertCount(2, $this->service()->listForUser($user['id']));

        $this->service()->revokeAllForUser($user['id']);

        self::assertCount(0, $this->service()->listForUser($user['id']));
    }

    public function testSuresiDolanlarTemizlenirGecerliOlanKalir(): void
    {
        $user = $this->createUser();
        $kisaOmurlu = $this->service()->issue($user['id'], $this->clock->now(), 10);
        $uzunOmurlu = $this->service()->issue($user['id'], $this->clock->now(), 600);

        $this->clock->advance('+11 minutes');
        $this->service()->purgeExpired($this->clock->now());

        $kalanlar = array_column($this->service()->listForUser($user['id']), 'id');
        self::assertSame([$uzunOmurlu['id']], $kalanlar);
        self::assertNotContains($kisaOmurlu['id'], $kalanlar);
    }
}
