<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Config;
use App\Core\Encrypter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * K27: TOTP secret'ı geri okunabilir olmalı ama DB'de düz durmamalı.
 * Hem sodium (birincil) hem AES-256-GCM (yedek) yolu doğrulanır.
 */
final class EncrypterTest extends TestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXP';
    private const string VALID_KEY = 'a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4';

    private function config(string $appKey = self::VALID_KEY): Config
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

    /** @return list<array{bool, string}> algoritma bayrağı → beklenen sürüm etiketi */
    public static function algoritmalar(): array
    {
        return [
            'sodium' => [true, 'v1s'],
            'aes-gcm' => [false, 'v1a'],
        ];
    }

    #[DataProvider('algoritmalar')]
    public function testSifrelenenVeriAyniSekildeGeriOkunur(bool $useSodium, string $beklenenSurum): void
    {
        if ($useSodium && !Encrypter::sodiumAvailable()) {
            self::markTestSkipped('Bu PHP kurulumunda libsodium yok.');
        }

        $encrypter = new Encrypter($this->config(), $useSodium);
        $payload = $encrypter->encrypt(self::SECRET);

        self::assertStringStartsWith($beklenenSurum . ':', $payload, 'Sürüm etiketi kayda gömülü olmalı.');
        self::assertSame(self::SECRET, $encrypter->decrypt($payload));
    }

    #[DataProvider('algoritmalar')]
    public function testSifreliMetinDuzSecretIcermez(bool $useSodium, string $beklenenSurum): void
    {
        if ($useSodium && !Encrypter::sodiumAvailable()) {
            self::markTestSkipped('Bu PHP kurulumunda libsodium yok.');
        }
        unset($beklenenSurum);

        $payload = (new Encrypter($this->config(), $useSodium))->encrypt(self::SECRET);

        self::assertStringNotContainsString(self::SECRET, $payload);
    }

    public function testSodiumVarsaVarsayilanOlarakKullanilir(): void
    {
        if (!Encrypter::sodiumAvailable()) {
            self::markTestSkipped('Bu PHP kurulumunda libsodium yok.');
        }

        self::assertStringStartsWith('v1s:', (new Encrypter($this->config()))->encrypt(self::SECRET));
    }

    public function testAyniGirdiHerSeferindeFarkliSifreliMetinUretir(): void
    {
        $encrypter = new Encrypter($this->config());

        // Rastgele nonce: aynı secret iki kez şifrelendiğinde çıktı eşleşmemeli.
        self::assertNotSame($encrypter->encrypt(self::SECRET), $encrypter->encrypt(self::SECRET));
    }

    #[DataProvider('algoritmalar')]
    public function testKurcalanmisVeriReddedilir(bool $useSodium, string $beklenenSurum): void
    {
        if ($useSodium && !Encrypter::sodiumAvailable()) {
            self::markTestSkipped('Bu PHP kurulumunda libsodium yok.');
        }
        unset($beklenenSurum);

        $encrypter = new Encrypter($this->config(), $useSodium);
        [$version, $nonce, $ciphertext] = explode(':', $encrypter->encrypt(self::SECRET), 3);

        $raw = base64_decode($ciphertext, true);
        self::assertIsString($raw);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] === 'A' ? 'B' : 'A';

        $this->expectException(RuntimeException::class);
        $encrypter->decrypt($version . ':' . $nonce . ':' . base64_encode($raw));
    }

    public function testBaskaAnahtarlaCozulemez(): void
    {
        $payload = (new Encrypter($this->config()))->encrypt(self::SECRET);

        $this->expectException(RuntimeException::class);
        (new Encrypter($this->config(str_repeat('f9e8d7c6', 8))))->decrypt($payload);
    }

    public function testBilinmeyenSurumAnlasilirHataVerir(): void
    {
        $encrypter = new Encrypter($this->config());
        [, $nonce, $ciphertext] = explode(':', $encrypter->encrypt(self::SECRET), 3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bilinmeyen şifreleme sürümü');
        $encrypter->decrypt('v9z:' . $nonce . ':' . $ciphertext);
    }

    public function testBicimsizKayitReddedilir(): void
    {
        $encrypter = new Encrypter($this->config());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('beklenen biçim');
        $encrypter->decrypt('bicimsiz-veri');
    }

    public function testGecersizAppKeyAnlasilirHataVerir(): void
    {
        $encrypter = new Encrypter($this->config('kisa-anahtar'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY 64 haneli');
        $encrypter->encrypt(self::SECRET);
    }
}
