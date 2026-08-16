<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigratorTest extends TestCase
{
    private function memoryPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    public function testMigrationlariSirayLaUygular(): void
    {
        $pdo = $this->memoryPdo();
        $applied = (new Migrator($pdo, __DIR__ . '/../fixtures/migrations'))->run();

        self::assertSame(['0001_first', '0002_second'], $applied);

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('fixture_a', $tables);
        self::assertContains('fixture_b', $tables);
        self::assertContains('migrations', $tables);
    }

    public function testIkinciKosudaUygulanacakKalmaz(): void
    {
        $pdo = $this->memoryPdo();
        $migrator = new Migrator($pdo, __DIR__ . '/../fixtures/migrations');

        self::assertCount(2, $migrator->run());
        self::assertSame([], $migrator->run(), 'İkinci koşu hiçbir migration uygulamamalı.');

        $count = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        self::assertSame(2, $count, 'Kayıt tablosunda tekrar satır oluşmamalı.');
    }

    public function testBasarisizMigrationGeriAlinirVeKaydedilmez(): void
    {
        $pdo = $this->memoryPdo();
        $migrator = new Migrator($pdo, __DIR__ . '/../fixtures/failing');

        try {
            $migrator->run();
            self::fail('Bozuk migration istisna fırlatmalıydı.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('0002_broken', $e->getMessage());
        }

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")
            ->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('fixture_good', $tables, 'Başarılı ilk migration kalıcı olmalı.');
        self::assertNotContains('fixture_broken', $tables, 'Başarısız migration tablosu geri alınmalı.');

        $names = $pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['0001_good'], $names, 'Başarısız migration kayıt tablosuna yazılmamalı.');
    }
}
