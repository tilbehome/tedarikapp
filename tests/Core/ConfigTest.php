<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Config;
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
        self::assertTrue((new Config([...$this->validValues(), 'APP_ENV' => 'production']))->isProduction());
    }
}
