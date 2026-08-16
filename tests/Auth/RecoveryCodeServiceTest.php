<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\PasswordHasher;
use App\Auth\RecoveryCodeService;
use Tests\Support\AuthTestCase;

final class RecoveryCodeServiceTest extends AuthTestCase
{
    private function service(): RecoveryCodeService
    {
        return new RecoveryCodeService($this->connection, new PasswordHasher());
    }

    public function testOnAdetKodUretilirVeBicimXXXXXXXXtir(): void
    {
        $codes = $this->service()->generate();

        self::assertCount(RecoveryCodeService::CODE_COUNT, $codes);
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);
        }
        self::assertCount(10, array_unique($codes), 'Kodlar birbirinden farklı olmalı.');
    }

    public function testKodlarVeritabaninaDuzMetinDegilHashliYazilir(): void
    {
        $user = $this->createUser();
        $codes = $this->service()->generate();
        $this->service()->replaceForUser($user['id'], $codes);

        $stored = $this->pdo->query('SELECT code_hash FROM recovery_codes')?->fetchAll();
        self::assertIsArray($stored);
        self::assertCount(10, $stored);

        $hashes = array_map(static fn (array $row): string => (string) $row['code_hash'], $stored);
        foreach ($codes as $code) {
            self::assertNotContains($code, $hashes, 'Kurtarma kodu düz metin saklanmamalı.');
        }
        self::assertStringStartsWith('$argon2id$', $hashes[0]);
    }

    public function testKodTekKullanimliktirIkinciKullanimReddedilir(): void
    {
        $user = $this->createUser();
        $code = $user['codes'][0];

        self::assertTrue($this->service()->consume($user['id'], $code, $this->clock->now()));
        self::assertFalse($this->service()->consume($user['id'], $code, $this->clock->now()));
    }

    public function testKullanilanKodUsedAtIleIsaretlenir(): void
    {
        $user = $this->createUser();
        $this->service()->consume($user['id'], $user['codes'][0], $this->clock->now());

        $statement = $this->pdo->query('SELECT COUNT(*) AS total FROM recovery_codes WHERE used_at IS NOT NULL');
        $row = $statement === false ? [] : $statement->fetch();
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['total']);
    }

    public function testKalanKodSayisiDuser(): void
    {
        $user = $this->createUser();
        self::assertSame(10, $this->service()->remainingCount($user['id']));

        $this->service()->consume($user['id'], $user['codes'][0], $this->clock->now());
        self::assertSame(9, $this->service()->remainingCount($user['id']));
    }

    public function testKodKucukHarfVeBosluklaDaKabulEdilir(): void
    {
        $user = $this->createUser();
        $karisik = ' ' . strtolower(str_replace('-', ' ', $user['codes'][0])) . ' ';

        self::assertTrue($this->service()->consume($user['id'], $karisik, $this->clock->now()));
    }

    public function testBaskaKullanicininKoduKabulEdilmez(): void
    {
        $birinci = $this->createUser('bir@tedarikapp.test');
        $ikinci = $this->createUser('iki@tedarikapp.test');

        self::assertFalse($this->service()->consume($ikinci['id'], $birinci['codes'][0], $this->clock->now()));
    }

    public function testGecersizBicimliKodReddedilir(): void
    {
        $user = $this->createUser();

        self::assertFalse($this->service()->consume($user['id'], 'ABC', $this->clock->now()));
        self::assertFalse($this->service()->consume($user['id'], '', $this->clock->now()));
    }

    public function testYenidenUretimEskiKodlariGecersizKilar(): void
    {
        $user = $this->createUser();
        $eski = $user['codes'][0];

        $this->service()->replaceForUser($user['id'], $this->service()->generate());

        self::assertFalse($this->service()->consume($user['id'], $eski, $this->clock->now()));
        self::assertSame(10, $this->service()->remainingCount($user['id']));
    }
}
