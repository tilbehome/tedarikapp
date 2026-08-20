<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * migration 0020 İDEMPOTANS KANITI (PM şartı, İE#13) — gerçek MySQL.
 *
 * Neden gerekli: 0020 iki DDL taşır (CREATE TABLE + ALTER TABLE). MySQL'de DDL
 * örtük commit yapar; ikinci DDL düşerse birincisi geri ALINMAZ ama defter kaydı
 * yazılmadığı için migration "bekleyen" kalır. Canlıda migrate ELLE tetiklendiğinden
 * dosya ikinci kez koşar — düz bir CREATE orada "tablo zaten var" ile kilitlenirdi.
 *
 * Bu test dosyayı ART ARDA İKİ KEZ çalıştırır ve ikincisinin hata vermediğini,
 * şemanın tek kopya kaldığını doğrular.
 *
 * @group mysql
 */
#[Group('mysql')]
final class Migration0020IdempotentTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $veritabani = '';

    protected function setUp(): void
    {
        $dsn = getenv('TEDARIKAPP_TEST_DB_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('MySQL DSN yok (TEDARIKAPP_TEST_DB_DSN) — mysql grubu atlandı.');
        }

        $this->pdo = new PDO($dsn, (string) getenv('TEDARIKAPP_TEST_DB_USER'), (string) getenv('TEDARIKAPP_TEST_DB_PASS'));
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // İzole veritabanı: gerçek test şemasına dokunulmaz.
        $this->veritabani = 'tdk_migr_' . bin2hex(random_bytes(4));
        $this->pdo->exec('CREATE DATABASE ' . $this->veritabani . ' CHARACTER SET utf8mb4');
        $this->pdo->exec('USE ' . $this->veritabani);

        // 0020'nin dokunduğu tablo: ALTER hedefi olan products'ın küçük bir örneği yeter.
        $this->pdo->exec(
            'CREATE TABLE products (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(300) NOT NULL,
                price_ddp_usd DECIMAL(12,4) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && $this->veritabani !== '') {
            $this->pdo->exec('DROP DATABASE IF EXISTS ' . $this->veritabani);
        }
        parent::tearDown();
    }

    public function testMigrationIkiKezKosarVeHATA_VERMEZ(): void
    {
        assert($this->pdo instanceof PDO);
        $migration = require dirname(__DIR__, 2) . '/migrations/0020_create_translation_cache.php';

        $migration->up($this->pdo);
        // İKİNCİ koşum: canlıda elle tetiklenen migrate'in tekrarı.
        $migration->up($this->pdo);

        self::assertSame(
            1,
            (int) $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'translation_cache'",
            )->fetchColumn(),
            'translation_cache tek kopya olmalı.',
        );
        self::assertSame(
            1,
            (int) $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'price_target_try'",
            )->fetchColumn(),
            'price_target_try tek kez eklenmiş olmalı.',
        );
    }

    /** Yarım kalmış koşum senaryosu: tablo VAR, kolon YOK → ikinci koşum tamamlar. */
    public function testYarimKalmisKosumTamamlanir(): void
    {
        assert($this->pdo instanceof PDO);
        $migration = require dirname(__DIR__, 2) . '/migrations/0020_create_translation_cache.php';

        // Birinci DDL geçmiş, ikincisi düşmüş gibi davran: tabloyu elle kur.
        $this->pdo->exec(
            "CREATE TABLE translation_cache (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_hash CHAR(64) NOT NULL,
                source_lang VARCHAR(10) NOT NULL DEFAULT 'zh',
                target_lang VARCHAR(10) NOT NULL DEFAULT 'tr',
                source_text VARCHAR(1000) NOT NULL,
                suggested_text VARCHAR(1000) NOT NULL,
                provider VARCHAR(40) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY idx_translation_hash (source_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        );

        $migration->up($this->pdo);

        self::assertSame(
            1,
            (int) $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'price_target_try'",
            )->fetchColumn(),
            'Yarım kalan koşum ikinci çağrıda tamamlanmalı.',
        );
    }
}
