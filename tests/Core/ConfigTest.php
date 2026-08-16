<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    /** @return array<string, string> */
    private function validValues(): array
    {
        return [
            'APP_ENV' => 'local',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'tedarikapp_test',
            'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
        ];
    }

    public function testZorunluAnahtarEksikseAnlasilirHataFirlatir(): void
    {
        $values = $this->validValues();
        unset($values['DB_NAME'], $values['TZ']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB_NAME, TZ/');

        new Config($values);
    }

    public function testBosDegerliZorunluAnahtarEksikSayilir(): void
    {
        $values = $this->validValues();
        $values['DB_USER'] = '   ';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB_USER/');

        new Config($values);
    }

    public function testGetVarsayilanDegerDondurur(): void
    {
        $config = new Config($this->validValues());

        self::assertSame('storage/logs', $config->get('LOG_PATH', 'storage/logs'));
    }

    public function testGetTanimsizAnahtarVarsayilansizHataFirlatir(): void
    {
        $config = new Config($this->validValues());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/OLMAYAN_ANAHTAR/');

        $config->get('OLMAYAN_ANAHTAR');
    }

    public function testGetIntSayiOlmayanDegerdeHataFirlatir(): void
    {
        $config = new Config([...$this->validValues(), 'DB_PORT' => 'abc']);

        $this->expectException(RuntimeException::class);

        $config->getInt('DB_PORT');
    }

    public function testGetIntVeGetBoolTipliDegerDondurur(): void
    {
        $config = new Config([...$this->validValues(), 'DB_PORT' => '3307', 'APP_DEBUG' => 'true']);

        self::assertSame(3307, $config->getInt('DB_PORT'));
        self::assertSame(120, $config->getInt('SESSION_LIFETIME', 120));
        self::assertTrue($config->getBool('APP_DEBUG'));
        self::assertFalse($config->getBool('OLMAYAN', false));
    }

    public function testIsProductionOrtamaGoreDondurur(): void
    {
        self::assertFalse((new Config($this->validValues()))->isProduction());
        self::assertTrue((new Config($this->productionValues()))->isProduction());
    }

    // ─────────────── K27: katı tam sayı doğrulama ───────────────

    /** @return list<array{string}> */
    public static function gecersizTamSayilar(): array
    {
        return [['1.5'], ['12abc'], ['abc'], ['1e3'], ['0x10'], ['１２３'], ['3,5'], ['+'], ['-']];
    }

    #[DataProvider('gecersizTamSayilar')]
    public function testGetIntKatiDogrulamaYapar(string $deger): void
    {
        $config = new Config([...$this->validValues(), 'DB_PORT' => $deger]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB_PORT/');

        $config->getInt('DB_PORT');
    }

    public function testGetIntGecerliDegerleriKabulEder(): void
    {
        $config = new Config([...$this->validValues(), 'A' => '0', 'B' => '-5', 'C' => ' 42 ']);

        self::assertSame(0, $config->getInt('A'));
        self::assertSame(-5, $config->getInt('B'));
        self::assertSame(42, $config->getInt('C'), 'Baştaki/sondaki boşluk kırpılmalı.');
    }

    public function testGetPositiveIntSifirVeNegatifiReddeder(): void
    {
        $config = new Config([...$this->validValues(), 'SESSION_LIFETIME' => '0']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/SESSION_LIFETIME.*0'dan büyük/");

        $config->getPositiveInt('SESSION_LIFETIME');
    }

    public function testGetPositiveIntPozitifDegeriDondurur(): void
    {
        $config = new Config([...$this->validValues(), 'SESSION_LIFETIME' => '120']);

        self::assertSame(120, $config->getPositiveInt('SESSION_LIFETIME'));
        self::assertSame(30, $config->getPositiveInt('OLMAYAN', 30));
    }

    // ─────────────── K27: üretim sır zorunlulukları ───────────────

    public function testUretimdeAppKeyEksikseUygulamaAcilistaDurur(): void
    {
        $values = $this->productionValues();
        unset($values['APP_KEY']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_KEY/');

        new Config($values);
    }

    public function testUretimdeGecersizAppKeyBicimiReddedilir(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_KEY/');

        new Config([...$this->productionValues(), 'APP_KEY' => 'kisa']);
    }

    public function testUretimdeKisaExtensionTokenSaltReddedilir(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/EXTENSION_TOKEN_SALT/');

        new Config([...$this->productionValues(), 'EXTENSION_TOKEN_SALT' => str_repeat('a', 31)]);
    }

    public function testYereldeSirlarOpsiyoneldir(): void
    {
        $values = $this->validValues(); // APP_ENV = local
        unset($values['APP_KEY'], $values['EXTENSION_TOKEN_SALT']);

        self::assertFalse((new Config($values))->isProduction());
    }

    /** @return array<string, string> */
    private function productionValues(): array
    {
        return [
            ...$this->validValues(),
            'APP_ENV' => 'production',
            'APP_KEY' => str_repeat('a1b2c3d4', 8),
            'EXTENSION_TOKEN_SALT' => str_repeat('s', 32),
        ];
    }
}
