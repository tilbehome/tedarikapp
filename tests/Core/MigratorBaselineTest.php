<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * K49 — defter eşitleme (İE#9.8). KRİTİK kurallar:
 *  1. Baseline HİÇBİR DDL çalıştırmaz — yalnız var olduğu doğrulanan kayıtları işler.
 *  2. Nesnesi olmayan kayıt İŞLENMEZ ve nedeniyle raporlanır.
 *  3. İdempotent; eşitleme sonrası YENİ migration normal koşar.
 */
final class MigratorBaselineTest extends TestCase
{
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

    /** Fikstür migration klasörü: 0001 fixture_a, 0002 fixture_b tablolarını yaratır. */
    private function fixturesDir(): string
    {
        return __DIR__ . '/../fixtures/migrations';
    }

    /** @var array<string, list<array{table?: string, column?: array{string, string}}>> */
    private const FIXTURE_MAP = [
        '0001_first' => [['table' => 'fixture_a']],
        '0002_second' => [['table' => 'fixture_b']],
    ];

    public function testNesneleriVarolanKayitlarKosulmadanDeftereIslenir(): void
    {
        $pdo = $this->memoryPdo();
        // Tablolar "defter dışı yolla" gelmiş gibi elle oluşturulur (canlı vaka).
        $pdo->exec('CREATE TABLE fixture_a (id INTEGER)');
        $pdo->exec('CREATE TABLE fixture_b (id INTEGER)');

        $migrator = new Migrator($pdo, $this->fixturesDir(), self::FIXTURE_MAP);
        $result = $migrator->baseline();

        self::assertSame(['0001_first', '0002_second'], $result['recorded']);
        self::assertSame([], $result['skipped']);
        self::assertSame([], $migrator->pending(), 'Eşitleme sonrası bekleyen KALMAMALI.');

        // Deftere checksum'la işlendi; execution_ms=0 (koşulmadı).
        $rows = $pdo->query('SELECT name, checksum, execution_ms FROM migrations ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $rows);
        self::assertSame(64, strlen((string) $rows[0]['checksum']));
        self::assertSame(0, (int) $rows[0]['execution_ms']);
    }

    public function testEksikNesneliKayitIslenmezVeRaporlanir(): void
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE fixture_a (id INTEGER)'); // fixture_b BİLEREK yok

        $result = (new Migrator($pdo, $this->fixturesDir(), self::FIXTURE_MAP))->baseline();

        self::assertSame(['0001_first'], $result['recorded']);
        self::assertCount(1, $result['skipped']);
        self::assertSame('0002_second', $result['skipped'][0]['name']);
        self::assertStringContainsString('fixture_b', $result['skipped'][0]['reason']);

        // KRİTİK: baseline DDL ÇALIŞTIRMAZ — fixture_b hâlâ yok.
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotContains('fixture_b', $tables);
    }

    public function testHaritadaOlmayanMigrationBaselinelanmaz(): void
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE fixture_a (id INTEGER)');
        $pdo->exec('CREATE TABLE fixture_b (id INTEGER)');

        // Harita yalnız 0001'i tanıyor — 0002 "yeni migration" gibi davranır.
        $result = (new Migrator($pdo, $this->fixturesDir(), ['0001_first' => [['table' => 'fixture_a']]]))->baseline();

        self::assertSame(['0001_first'], $result['recorded']);
        self::assertCount(1, $result['skipped']);
        self::assertStringContainsString('normal koşumla', $result['skipped'][0]['reason']);
    }

    public function testIdempotent_IkinciKosumdaHicbirSeyIslenmez(): void
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE fixture_a (id INTEGER)');
        $pdo->exec('CREATE TABLE fixture_b (id INTEGER)');
        $migrator = new Migrator($pdo, $this->fixturesDir(), self::FIXTURE_MAP);

        $migrator->baseline();
        $second = $migrator->baseline();

        self::assertSame([], $second['recorded']);
        self::assertSame([], $second['skipped']);
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn(), 'Defterde çift kayıt OLMAMALI.');
    }

    public function testEsitlemeSonrasiYeniMigrationNormalKosar(): void
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE fixture_a (id INTEGER)');
        $pdo->exec('CREATE TABLE fixture_b (id INTEGER)');

        // Fikstürler + YENİ bir migration içeren geçici klasör.
        $this->scratchDir = sys_get_temp_dir() . '/baseline-' . bin2hex(random_bytes(4));
        mkdir($this->scratchDir);
        foreach (glob($this->fixturesDir() . '/*.php') ?: [] as $file) {
            copy($file, $this->scratchDir . '/' . basename($file));
        }
        file_put_contents(
            $this->scratchDir . '/0003_third.php',
            "<?php\nreturn new class () implements \\App\\Core\\Migration {\n"
            . "    public function up(PDO \$pdo): void { \$pdo->exec('CREATE TABLE fixture_c (id INTEGER)'); }\n};\n",
        );

        $migrator = new Migrator($pdo, $this->scratchDir, self::FIXTURE_MAP);
        $baseline = $migrator->baseline();
        self::assertSame(['0001_first', '0002_second'], $baseline['recorded']);
        self::assertSame(['0003_third'], array_column($baseline['skipped'], 'name'), 'Yeni migration baseline\'lanmaz.');

        $applied = $migrator->run();

        self::assertSame(['0003_third'], $applied, 'Eşitleme sonrası YALNIZ yeni migration koşmalı.');
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('fixture_c', $tables);
    }

    public function testGercekHaritaTumMigrationlariKapsar(): void
    {
        // Gerçek migrations/ klasöründeki HER dosya haritada olmalı — yoksa canlıdaki
        // defter eşitlemesi o kaydı atlar ve "bekleyen" hiç sıfırlanmaz. Yeni migration
        // ekleyen geliştirici bu test kırmızı olunca haritayı da güncelleyecek.
        $files = glob(__DIR__ . '/../../migrations/[0-9][0-9][0-9][0-9]_*.php') ?: [];
        self::assertNotEmpty($files);

        $reflection = new \ReflectionClassConstant(Migrator::class, 'BASELINE_OBJECTS');
        /** @var array<string, mixed> $map */
        $map = $reflection->getValue();

        foreach ($files as $file) {
            $name = basename($file, '.php');
            self::assertArrayHasKey($name, $map, sprintf('Migration "%s" BASELINE_OBJECTS haritasında eksik.', $name));
        }
    }
}
