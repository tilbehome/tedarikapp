<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Services\BackupService;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * İE#10.5 Blok 1d — RESTORE KANITI (CI, gerçek MySQL): "geri yüklenemeyen yedek
 * yedek değildir." Akış: verili tabloları yedekle → şifreyi çöz → BOŞ veritabanına
 * geri yükle → tablo ve satırların birebir geldiğini doğrula (smoke).
 *
 * v1.2.2 B1: yedek artık DOSYA değil SET. Bu dosya ŞİFRELEME kanıtını taşımaya
 * devam eder (düz metin sızmıyor, yanlış anahtar çözemiyor); uçtan uca geri
 * yükleme provası — çok parçalı vaka dahil — YedekSetiGeriYuklemeTest'tedir.
 *
 * @group mysql
 */
#[Group('mysql')]
final class BackupRestoreTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $scratchDir = '';

    protected function setUp(): void
    {
        $dsn = getenv('TEDARIKAPP_TEST_DB_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('MySQL DSN yok (TEDARIKAPP_TEST_DB_DSN) — mysql grubu atlandı.');
        }
        $this->pdo = new PDO($dsn, (string) getenv('TEDARIKAPP_TEST_DB_USER'), (string) getenv('TEDARIKAPP_TEST_DB_PASS'));
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->scratchDir = sys_get_temp_dir() . '/yedek-' . bin2hex(random_bytes(4));
        mkdir($this->scratchDir . '/storage/backups', 0775, true);
        // v1.2.2 B1: `config` zorunlu parçadır — ayarlarsız bir dökümden geri
        // dönmek "veriler geldi ama uygulama açılmıyor" demektir. Gerçek
        // kurulumda bu dosya her zaman bulunur.
        file_put_contents($this->scratchDir . '/config.php', "<?php
return ['APP_KEY' => 'prova'];
");
    }

    protected function tearDown(): void
    {
        foreach (['restore_kaynak', 'tedarikapp_restore_hedef'] as $db) {
            $this->pdo?->exec('DROP DATABASE IF EXISTS ' . $db);
        }
        foreach (glob($this->scratchDir . '/storage/backups/*') ?: [] as $yol) {
            foreach (glob($yol . '/*') ?: [] as $parca) {
                @unlink($parca);
            }
            is_dir($yol) ? @rmdir($yol) : @unlink($yol);
        }
        @unlink($this->scratchDir . '/config.php');
        parent::tearDown();
    }

    public function testYedekBosVeritabaninaGeriYuklenir(): void
    {
        assert($this->pdo instanceof PDO);
        // ── 1) Kaynak: verili küçük şema ──
        $this->pdo->exec('DROP DATABASE IF EXISTS restore_kaynak');
        $this->pdo->exec('CREATE DATABASE restore_kaynak CHARACTER SET utf8mb4');
        $this->pdo->exec('CREATE TABLE restore_kaynak.urunler (id INT PRIMARY KEY, ad VARCHAR(100)) ENGINE=InnoDB');
        $this->pdo->exec("INSERT INTO restore_kaynak.urunler VALUES (1, 'Termos ¥'), (2, 'Hoparlör ₺')");

        $config = $this->configFor('restore_kaynak');
        $service = new BackupService($config, $this->scratchDir);

        // ── 2) Yedekle (şifreli) ──
        $backup = $service->create();
        self::assertGreaterThan(0, $backup['toplam_bayt']);
        $path = $service->parcaYolu(basename($backup['set_dizini']), 'veritabani.sql.enc');
        self::assertNotNull($path);
        $encrypted = (string) file_get_contents((string) $path);
        self::assertStringStartsWith('TDKBK1', $encrypted, 'Dosya imzalı şifreli biçimde olmalı.');
        self::assertStringNotContainsString('Termos', $encrypted, 'Düz metin SIZMAMALI — şifreleme kanıtı.');
        // Özet artık SETİN MANİFESTİNDE durur: parçayı tarif eden şey, yanında
        // duran bir alan değil, ait olduğu setin kaydıdır.
        $manifest = \App\Services\Yedek\YedekManifesti::jsondan(
            (string) file_get_contents($backup['set_dizini'] . '/MANIFEST.json'),
        );
        $sqlParcasi = array_values(array_filter(
            $manifest->parcalar(),
            static fn (array $p): bool => $p['tur'] === 'sql',
        ))[0];
        self::assertSame($sqlParcasi['sha256'], hash('sha256', $encrypted), 'Özet dosyayla eşleşmeli.');

        // ── 3) Çöz + BOŞ hedefe geri yükle ──
        $sql = $service->decrypt($encrypted);
        self::assertStringContainsString('CREATE TABLE', $sql);

        $this->pdo->exec('DROP DATABASE IF EXISTS tedarikapp_restore_hedef');
        $this->pdo->exec('CREATE DATABASE tedarikapp_restore_hedef CHARACTER SET utf8mb4');
        $this->pdo->exec('USE tedarikapp_restore_hedef');
        $this->pdo->exec($sql);

        // ── 4) Smoke: tablo + satırlar birebir geldi mi? ──
        $rows = $this->pdo->query('SELECT id, ad FROM tedarikapp_restore_hedef.urunler ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([
            ['id' => 1, 'ad' => 'Termos ¥'],
            ['id' => 2, 'ad' => 'Hoparlör ₺'],
        ], array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'ad' => (string) $r['ad']], $rows));
    }

    public function testYanlisAnahtarlaCozulemez(): void
    {
        assert($this->pdo instanceof PDO);
        $this->pdo->exec('DROP DATABASE IF EXISTS restore_kaynak');
        $this->pdo->exec('CREATE DATABASE restore_kaynak CHARACTER SET utf8mb4');
        $this->pdo->exec('CREATE TABLE restore_kaynak.t (id INT)');

        $service = new BackupService($this->configFor('restore_kaynak'), $this->scratchDir);
        $backup = $service->create();
        $encrypted = (string) file_get_contents(
            (string) $service->parcaYolu(basename($backup['set_dizini']), 'veritabani.sql.enc'),
        );

        $wrongKey = new BackupService($this->configFor('restore_kaynak', str_repeat('ab', 32)), $this->scratchDir);

        $this->expectException(\RuntimeException::class);
        $wrongKey->decrypt($encrypted);
    }

    private function configFor(string $database, ?string $appKey = null): Config
    {
        $dsn = (string) getenv('TEDARIKAPP_TEST_DB_DSN');
        preg_match('/host=([^;]+)/', $dsn, $host);
        preg_match('/port=([^;]+)/', $dsn, $port);

        return new Config([
            'APP_ENV' => 'local',
            'APP_URL' => 'https://tedarikapp.test',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => $appKey ?? str_repeat('cd', 32),
            'DB_HOST' => $host[1] ?? '127.0.0.1',
            'DB_PORT' => $port[1] ?? '3306',
            'DB_NAME' => $database,
            'DB_USER' => (string) getenv('TEDARIKAPP_TEST_DB_USER'),
            'DB_PASS' => (string) getenv('TEDARIKAPP_TEST_DB_PASS'),
        ]);
    }
}
