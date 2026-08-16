<?php

declare(strict_types=1);

namespace Tests\Setup;

use App\Setup\SetupLock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

final class SetupLockTest extends TestCase
{
    use TempDirectory;

    private function lock(): SetupLock
    {
        return new SetupLock($this->tempPath('storage'));
    }

    public function testBastaKilitliDegildir(): void
    {
        self::assertFalse($this->lock()->isLocked());
    }

    public function testYazildiktanSonraKilitlidir(): void
    {
        $lock = $this->lock();
        $lock->write(new DateTimeImmutable('2026-08-16 18:00:00'));

        self::assertTrue($lock->isLocked());
        self::assertFileExists($lock->path());
    }

    public function testKilitDosyasiKurulumBilgisiTasir(): void
    {
        $lock = $this->lock();
        $lock->write(new DateTimeImmutable('2026-08-16 18:00:00'), [
            'db_version' => '8.4.9',
            'php_version' => '8.4.24',
        ]);

        $details = $lock->read();

        self::assertIsArray($details);
        self::assertSame('8.4.9', $details['db_version']);
        self::assertSame('8.4.24', $details['php_version']);
        self::assertStringStartsWith('2026-08-16T18:00:00', (string) $details['installed_at']);
    }

    public function testStorageKlasoruYoksaOlusturulur(): void
    {
        $lock = new SetupLock($this->tempPath('storage/derin/klasor'));
        $lock->write(new DateTimeImmutable('2026-08-16 18:00:00'));

        self::assertTrue($lock->isLocked());
    }

    public function testGeciciDosyaBirakmaz(): void
    {
        $lock = $this->lock();
        $lock->write(new DateTimeImmutable('2026-08-16 18:00:00'));

        // Atomik yazım: rename sonrası .tmp artığı kalmamalı.
        $leftovers = glob($this->tempPath('storage') . '/*.tmp');

        self::assertSame([], $leftovers === false ? [] : $leftovers);
    }

    public function testKilitYokkenOkumaNullDoner(): void
    {
        self::assertNull($this->lock()->read());
    }
}
