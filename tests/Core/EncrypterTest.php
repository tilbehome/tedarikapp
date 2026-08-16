<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Config;
use App\Core\Encrypter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncrypterTest extends TestCase
{
    private function config(string $appKey): Config
    {
        // Bilinçli olarak 'local': testlerden biri geçersiz APP_KEY'i Encrypter'ın kendi
        // hatasıyla doğrular; production modunda Config zaten kurucuda reddederdi (K27).
        return new Config([
            'APP_ENV' => 'local',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => $appKey,
        ]);
    }

    public function testSifrelenenVeriAyniSekildeGeriOkunur(): void
    {
        $encrypter = new Encrypter($this->config(str_repeat('a1b2c3d4', 8)));

        self::assertSame('JBSWY3DPEHPK3PXP', $encrypter->decrypt($encrypter->encrypt('JBSWY3DPEHPK3PXP')));
    }

    public function testAyniGirdiHerSeferindeFarkliSifreliMetinUretir(): void
    {
        $encrypter = new Encrypter($this->config(str_repeat('a1b2c3d4', 8)));

        // Rastgele IV: aynı secret iki kez şifrelendiğinde çıktı eşleşmemeli.
        self::assertNotSame($encrypter->encrypt('JBSWY3DPEHPK3PXP'), $encrypter->encrypt('JBSWY3DPEHPK3PXP'));
    }

    public function testKurcalanmisVeriReddedilir(): void
    {
        $encrypter = new Encrypter($this->config(str_repeat('a1b2c3d4', 8)));
        $payload = $encrypter->encrypt('JBSWY3DPEHPK3PXP');

        $raw = base64_decode($payload, true);
        self::assertIsString($raw);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] === 'A' ? 'B' : 'A';

        $this->expectException(RuntimeException::class);
        $encrypter->decrypt(base64_encode($raw));
    }

    public function testBaskaAnahtarlaCozulemez(): void
    {
        $payload = (new Encrypter($this->config(str_repeat('a1b2c3d4', 8))))->encrypt('JBSWY3DPEHPK3PXP');

        $this->expectException(RuntimeException::class);
        (new Encrypter($this->config(str_repeat('f9e8d7c6', 8))))->decrypt($payload);
    }

    public function testGecersizAppKeyAnlasilirHataVerir(): void
    {
        $encrypter = new Encrypter($this->config('kisa-anahtar'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY 64 haneli');
        $encrypter->encrypt('JBSWY3DPEHPK3PXP');
    }
}
