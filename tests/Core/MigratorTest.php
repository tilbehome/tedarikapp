<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigratorTest extends TestCase
{
    /** Değiştirilebilir fikstür klasörü — checksum testleri dosyayı yerinde bozar. */
    private ?string $scratchDir = null;

    protected function tearDown(): void
    {
        if ($this->scratchDir !== null && is_dir($this->scratchDir)) {
            foreach (glob($this->scratchDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->scratchDir);
        }
        $this->scratchDir = null;

        parent::tearDown();
    }

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

    // ─────────────── K23: checksum ve süre kaydı ───────────────

    public function testUygulananMigrationinChecksumVeSuresiKaydedilir(): void
    {
        $pdo = $this->memoryPdo();
        (new Migrator($pdo, __DIR__ . '/../fixtures/migrations'))->run();

        /** @var list<array<string, mixed>> $rows */
        $rows = $pdo->query('SELECT name, checksum, execution_ms FROM migrations ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $row['checksum']);
            self::assertGreaterThanOrEqual(0, (int) $row['execution_ms']);
        }

        $expected = hash_file('sha256', __DIR__ . '/../fixtures/migrations/0001_first.php');
        self::assertSame($expected, $rows[0]['checksum']);
    }

    public function testUygulanmisMigrationDegistirilirseKosumDurur(): void
    {
        $dir = $this->scratchFixtures();
        $pdo = $this->memoryPdo();
        $migrator = new Migrator($pdo, $dir);
        $migrator->run();

        // Uygulanmış dosyayı yerinde değiştir (yorum eklemek bile checksum'ı bozar).
        file_put_contents($dir . '/0001_scratch.php', PHP_EOL . '// sonradan eklenen satir', FILE_APPEND);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum uyuşmuyor');
        $migrator->run();
    }

    public function testUygulanmisMigrationSilinirseKosumDurur(): void
    {
        $dir = $this->scratchFixtures();
        $pdo = $this->memoryPdo();
        $migrator = new Migrator($pdo, $dir);
        $migrator->run();

        unlink($dir . '/0001_scratch.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Uygulanmış migration dosyası bulunamadı');
        $migrator->run();
    }

    public function testEskiSemadakiMigrationsTablosuAnlasilirHataVerir(): void
    {
        $pdo = $this->memoryPdo();
        // K23 öncesi şema: checksum/execution_ms yok.
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(190) NOT NULL UNIQUE, applied_at DATETIME NOT NULL)');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('eski şemada');
        (new Migrator($pdo, __DIR__ . '/../fixtures/migrations'))->run();
    }

    /** Testin bozabileceği, tek migration içeren geçici bir klasör üretir. */
    private function scratchFixtures(): string
    {
        $dir = sys_get_temp_dir() . '/tedarikapp-migrator-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->scratchDir = $dir;

        file_put_contents($dir . '/0001_scratch.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            return new class () implements \App\Core\Migration {
                public function up(PDO $pdo): void
                {
                    $pdo->exec('CREATE TABLE scratch_table (id INTEGER PRIMARY KEY)');
                }
            };
            PHP);

        return $dir;
    }
}
