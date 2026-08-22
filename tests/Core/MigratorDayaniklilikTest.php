<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * İE#19 G7 — migration dayanıklılığı.
 *
 *  • 0016 YARIM kalırsa tekrar koşum tamamlar (idempotent kolon denetimi),
 *  • uygulanmış dosyanın checksum'u KEYFİ değişmişse koşum yine DURUR (K23 korunur),
 *  • kayıtlı (belgelenmiş) değişiklikte defter tazelenir ve raporlanır.
 */
final class MigratorDayaniklilikTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function testYarimKalan0016TekrarKosumdaTAMAMLANIR(): void
    {
        // Gerçek 0016 dosyasını yükle (üretimdeki kodun ta kendisi).
        $migration = require dirname(__DIR__, 2) . '/migrations/0016_media_storage_columns.php';

        $this->pdo->exec('CREATE TABLE product_images (id INTEGER PRIMARY KEY, product_id INTEGER, path TEXT)');
        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, main_image TEXT)');

        // YARIM DDL SİMÜLASYONU: ilk ALTER geçmiş, ikincisi düşmüş.
        $this->pdo->exec("ALTER TABLE product_images ADD COLUMN storage_mode VARCHAR(10) NOT NULL DEFAULT 'local'");

        // Eski (idempotent olmayan) hâlinde bu çağrı "duplicate column" ile patlardı.
        $migration->up($this->pdo);

        $kolonlar = array_column($this->pdo->query('PRAGMA table_info(product_images)')->fetchAll(), 'name');
        self::assertContains('storage_mode', $kolonlar);
        self::assertContains('source_url', $kolonlar, 'Yarım kalan koşum tamamlanmadı.');

        // Üstüne bir kez daha koşmak da hata vermemeli (tam idempotans).
        $migration->up($this->pdo);
        self::assertTrue(true);
    }

    public function testKeyfiDegistirilmisMigrationKOSUMUDURDURUR(): void
    {
        $dizin = sys_get_temp_dir() . '/tedarikapp-mig-' . bin2hex(random_bytes(4));
        mkdir($dizin);
        $dosya = $dizin . '/0001_deneme.php';
        file_put_contents($dosya, "<?php return new class () implements \\App\\Core\\Migration {\n"
            . "    public function up(PDO \$pdo): void { \$pdo->exec('CREATE TABLE deneme (id INTEGER)'); }\n};\n");

        $migrator = new Migrator($this->pdo, $dizin);
        $migrator->run();

        // Dosya sonradan DEĞİŞTİRİLİYOR ve bu değişiklik kayıtlı değil.
        file_put_contents($dosya, "<?php return new class () implements \\App\\Core\\Migration {\n"
            . "    public function up(PDO \$pdo): void { /* baska bir sey */ }\n};\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/değiştirilmiş/');

        (new Migrator($this->pdo, $dizin))->run();

        unlink($dosya);
        rmdir($dizin);
    }

    public function testDefterdekiEskiChecksumKayitliDegisiklikteTAZELENIR(): void
    {
        // Gerçek migrations klasörüyle çalış: 0016 için kayıtlı eski checksum var.
        $dizin = dirname(__DIR__, 2) . '/migrations';

        $this->pdo->exec(
            'CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(190) UNIQUE,
             checksum CHAR(64) NOT NULL, execution_ms INT NOT NULL, applied_at DATETIME NOT NULL)',
        );
        // Bu sistem 0016'yı ESKİ hâliyle uygulamış gibi davranır.
        $eski = '7e37b3dfe43eca4aac0625e439c944dd4de37a79f0b325f7471da8432a706b13';
        $this->pdo->prepare('INSERT INTO migrations (name, checksum, execution_ms, applied_at) VALUES (?, ?, 0, ?)')
            ->execute(['0016_media_storage_columns', $eski, '2026-01-01 00:00:00']);

        $migrator = new Migrator($this->pdo, $dizin);
        try {
            $migrator->run();
        } catch (\Throwable $e) {
            // Diğer migration'lar SQLite'ta koşmayabilir; bizi ilgilendiren checksum kapısıdır.
            self::assertStringNotContainsString('değiştirilmiş', $e->getMessage(), 'Kayıtlı değişiklik koşumu durdurmamalı: ' . $e->getMessage());
        }

        $guncel = (string) $this->pdo->query("SELECT checksum FROM migrations WHERE name = '0016_media_storage_columns'")->fetchColumn();
        self::assertNotSame($eski, $guncel, 'Defter tazelenmedi — sonraki her koşum aynı yerde takılır.');
        self::assertSame(
            hash_file('sha256', $dizin . '/0016_media_storage_columns.php'),
            $guncel,
            'Defterdeki checksum diskteki dosyayla eşleşmeli.',
        );
        self::assertContains('0016_media_storage_columns', $migrator->tazelenenChecksumlar(), 'Tazeleme RAPORLANMALI (sessiz olmamalı).');
    }
}
