<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\TotpService;
use App\Auth\UserRepository;
use App\Core\Encrypter;
use Tests\Support\AuthTestCase;

final class TotpServiceTest extends AuthTestCase
{
    private function totp(): TotpService
    {
        $config = $this->config();

        return new TotpService($config, new Encrypter($config), $this->clock);
    }

    // ─────────────── K39: sodium'suz sunucuda 2FA (İE#9.1, KRİTİK) ───────────────

    public function testSodiumsuzOrtamdaTotpKurulupDogrulanir(): void
    {
        // Üretim senaryosu (K39): ext-sodium yüklenemeyen ea-php84. 2FA'nın TAMAMI
        // (secret üret → şifrele → sakla → kod doğrula) OpenSSL yedeğiyle dönmeli.
        $config = $this->config();
        $sodiumsuzEncrypter = new Encrypter($config, useSodium: null, sodiumSupported: false);
        $totp = new TotpService($config, $sodiumsuzEncrypter, $this->clock);

        $secret = $totp->createSecret();
        $sifreli = $totp->encryptSecret($secret);

        self::assertStringStartsWith('v1a:', $sifreli, 'Sodium\'suz sunucuda kayıt AES-GCM formatında olmalı.');
        self::assertTrue($totp->verify($sifreli, $this->totpCodeFor($secret)), 'Doğru kod doğrulanmalı.');
        self::assertFalse($totp->verify($sifreli, '000000'), 'Yanlış kod reddedilmeli.');
    }

    public function testUretilenSecretBase32Bicimindedir(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $this->totp()->createSecret());
    }

    public function testHerCagriFarkliSecretUretir(): void
    {
        $totp = $this->totp();

        self::assertNotSame($totp->createSecret(), $totp->createSecret());
    }

    public function testVeritabanindakiSecretDuzBase32DegildirSifrelidir(): void
    {
        $user = $this->createUser();

        $statement = $this->pdo->query('SELECT totp_secret FROM users');
        $row = $statement === false ? [] : $statement->fetch();
        self::assertIsArray($row);
        $stored = (string) $row['totp_secret'];

        self::assertNotSame($user['secret'], $stored);
        self::assertStringNotContainsString($user['secret'], $stored, 'Düz secret DB\'de görünmemeli (K27).');
        self::assertMatchesRegularExpression('/^v1[sa]:/', $stored, 'Kayıt sürüm etiketi taşımalı.');
    }

    public function testDogruKodDogrulanir(): void
    {
        $user = $this->createUser();
        $sifreli = $this->kullanicininSifreliSecreti($user['id']);

        self::assertTrue($this->totp()->verify($sifreli, $this->totpCodeFor($user['secret'])));
    }

    public function testYanlisKodReddedilir(): void
    {
        $user = $this->createUser();
        $sifreli = $this->kullanicininSifreliSecreti($user['id']);

        self::assertFalse($this->totp()->verify($sifreli, '000000'));
    }

    public function testBicimsizKodReddedilir(): void
    {
        $user = $this->createUser();
        $sifreli = $this->kullanicininSifreliSecreti($user['id']);
        $totp = $this->totp();

        self::assertFalse($totp->verify($sifreli, ''));
        self::assertFalse($totp->verify($sifreli, '12345'));
        self::assertFalse($totp->verify($sifreli, 'abcdef'));
        self::assertFalse($totp->verify($sifreli, '1234567'));
    }

    public function testSecretYokVeyaBozuksaDogrulamaBasarisizSayilir(): void
    {
        $totp = $this->totp();

        // Fail-closed: 2FA kurulmamış ya da secret çözülemiyorsa giriş AÇILMAZ.
        self::assertFalse($totp->verify(null, '123456'));
        self::assertFalse($totp->verify('', '123456'));
        self::assertFalse($totp->verify('bozuk-veri', '123456'));
        self::assertFalse($totp->verify('v1s:AAAA:BBBB', '123456'));
    }

    public function testKodPeriyotDisindaGecersizlesir(): void
    {
        $user = $this->createUser();
        $sifreli = $this->kullanicininSifreliSecreti($user['id']);
        $kod = $this->totpCodeFor($user['secret']);

        self::assertTrue($this->totp()->verify($sifreli, $kod));

        // ±1 periyot tolerans var; 5 dakika sonra kod kesinlikle geçersiz olmalı.
        $this->clock->advance('+5 minutes');
        self::assertFalse($this->totp()->verify($sifreli, $kod));
    }

    public function testProvisioningUriOtpauthBicimindedir(): void
    {
        $uri = $this->totp()->provisioningUri('admin@tedarikapp.test', 'JBSWY3DPEHPK3PXP');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        self::assertStringContainsString('issuer=tedarikapp', $uri);
    }

    private function kullanicininSifreliSecreti(int $userId): string
    {
        $user = (new UserRepository($this->connection))->findById($userId);
        self::assertNotNull($user);
        self::assertNotNull($user->totpSecret);

        return $user->totpSecret;
    }
}
