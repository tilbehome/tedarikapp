<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testSifreArgon2idIleHashlenir(): void
    {
        $hash = (new PasswordHasher())->hash('cok-gizli-sifre');

        // Argon2id hash'leri "$argon2id$" ile başlar (K16).
        self::assertStringStartsWith('$argon2id$', $hash);
    }

    public function testAyniSifreFarkliHashUretir(): void
    {
        $hasher = new PasswordHasher();

        self::assertNotSame($hasher->hash('cok-gizli-sifre'), $hasher->hash('cok-gizli-sifre'));
    }

    public function testDogruSifreDogrulanirYanlisSifreReddedilir(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('cok-gizli-sifre');

        self::assertTrue($hasher->verify('cok-gizli-sifre', $hash));
        self::assertFalse($hasher->verify('cok-gizli-sifr', $hash));
        self::assertFalse($hasher->verify('', $hash));
    }

    public function testGuncelParametrelerleUretilenHashYenidenHashlenmeIstemez(): void
    {
        $hasher = new PasswordHasher();

        self::assertFalse($hasher->needsRehash($hasher->hash('cok-gizli-sifre')));
    }

    public function testEskiAlgoritmaylaUretilenHashYenidenHashlenmeIster(): void
    {
        $bcryptHash = password_hash('cok-gizli-sifre', PASSWORD_BCRYPT);
        self::assertIsString($bcryptHash);

        self::assertTrue((new PasswordHasher())->needsRehash($bcryptHash));
    }
}
