<?php

declare(strict_types=1);

namespace Tests\Setup;

use App\Setup\SetupLock;
use DateTimeImmutable;
use RuntimeException;
use Tests\Support\AuthTestCase;
use Tests\Support\TempDirectory;

/**
 * K33 KRİTİK: kurulum kilidi artık DOSYADA değil VERİTABANINDA.
 *
 * Üretim sunucusunda PHP `nobody` (DSO) ile çalışıyor ve uygulama diske yazamıyor —
 * dosya tabanlı kilit yazılamadığı için sihirbaz asla kapanmazdı. Kilit-sonrası-403
 * davranışının korunduğu ayrıca `SetupEndpointsTest`'te sınanır.
 */
final class SetupLockTest extends AuthTestCase
{
    use TempDirectory;

    private function lock(): SetupLock
    {
        return new SetupLock($this->connection, $this->tempPath('storage'));
    }

    private function moment(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-16 18:00:00');
    }

    public function testBastaKilitliDegildir(): void
    {
        self::assertFalse($this->lock()->isLocked());
        self::assertNull($this->lock()->read());
    }

    public function testYazildiktanSonraKilitlidir(): void
    {
        $lock = $this->lock();
        $lock->write($this->moment());

        self::assertTrue($lock->isLocked());
    }

    public function testKilitVeritabaninaYazilirDiskeDEGIL(): void
    {
        $lock = $this->lock();
        $lock->write($this->moment());

        $statement = $this->pdo->prepare('SELECT value FROM settings WHERE key = :key');
        $statement->execute(['key' => SetupLock::SETTING_KEY]);
        $row = $statement->fetch();

        self::assertIsArray($row, 'Kilit settings tablosunda olmalı.');
        self::assertStringContainsString('installed_at', (string) $row['value']);

        $path = $lock->legacyFilePath();
        self::assertIsString($path);
        self::assertFileDoesNotExist($path, 'K33: kilit artık diske YAZILMAZ.');
    }

    public function testKilitKurulumBilgisiTasir(): void
    {
        $lock = $this->lock();
        $lock->write($this->moment(), [
            'db_version' => '8.4.9',
            'php_version' => '8.4.24',
            'media_mode' => 'hotlink',
        ]);

        $details = $lock->read();

        self::assertIsArray($details);
        self::assertSame('8.4.9', $details['db_version']);
        self::assertSame('hotlink', $details['media_mode']);
        self::assertStringStartsWith('2026-08-16T18:00:00', (string) $details['installed_at']);
    }

    public function testTekrarYazmaKilidiBozmaz(): void
    {
        $lock = $this->lock();
        $lock->write($this->moment(), ['db_version' => '8.4.9']);
        $lock->write($this->moment(), ['db_version' => '9.0.0']);

        $details = $lock->read();

        self::assertIsArray($details);
        self::assertSame('9.0.0', $details['db_version']);
        self::assertTrue($lock->isLocked());
    }

    public function testVeritabaniYoksaKilitYokSayilir(): void
    {
        // DB erişilemiyorsa kurulum zaten yapılmamıştır — sihirbaz açılmalı.
        $lock = new SetupLock(null, $this->tempPath('storage'));

        self::assertFalse($lock->isLocked());
        self::assertFalse($lock->storesInDatabase());
    }

    public function testVeritabanisizYazmaAnlasilirHataVerir(): void
    {
        $lock = new SetupLock(null, $this->tempPath('storage'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('veritabanı bağlantısı yok');
        $lock->write($this->moment());
    }

    // ─────────────── Geriye dönük uyum ───────────────

    public function testEskiDosyaKilidiOkunmayaDevamEder(): void
    {
        // K33 öncesi kurulmuş bir sistem: kilit dosyada. Kilitli KALMALI,
        // aksi hâlde güncelleme sonrası sihirbaz yeniden açılırdı.
        $storage = $this->tempPath('storage');
        mkdir($storage, 0775, true);
        file_put_contents($storage . '/setup.lock', json_encode(['installed_at' => '2026-08-01T10:00:00+03:00']));

        $lock = $this->lock();

        self::assertTrue($lock->isLocked());
        $details = $lock->read();
        self::assertIsArray($details);
        self::assertSame('legacy_file', $details['source']);
    }

    public function testVeritabaniKaydiDosyaKilidininOnundedir(): void
    {
        $storage = $this->tempPath('storage');
        mkdir($storage, 0775, true);
        file_put_contents($storage . '/setup.lock', json_encode(['installed_at' => '2026-08-01T10:00:00+03:00']));

        $lock = $this->lock();
        $lock->write($this->moment(), ['db_version' => '8.4.9']);

        $details = $lock->read();
        self::assertIsArray($details);
        self::assertArrayNotHasKey('source', $details, 'DB kaydı varsa dosya okunmamalı.');
        self::assertSame('8.4.9', $details['db_version']);
    }

    public function testBozukDosyaKilidiYokSayilir(): void
    {
        $storage = $this->tempPath('storage');
        mkdir($storage, 0775, true);
        file_put_contents($storage . '/setup.lock', 'bu json degil');

        self::assertFalse($this->lock()->isLocked());
    }
}
