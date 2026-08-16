<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Config;
use App\Core\DatabaseLogHandler;
use App\Core\Logger;
use App\Core\LogRedactor;
use App\Core\RequestContext;
use App\Core\Ulid;
use Monolog\Level;
use Tests\Support\AuthTestCase;
use Tests\Support\TempDirectory;

/**
 * K33 KRİTİK — üretimde loglar VERİTABANINA yazılır.
 *
 * Sunucuda PHP `nobody` ile çalışıyor ve diske yazamıyor; dosya hedefi orada sessizce
 * kaybolur. Redaction (K27) bu hedefte de geçerlidir — sır DB'ye de yazılmaz.
 */
final class DatabaseLogHandlerTest extends AuthTestCase
{
    use TempDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo->exec(
            'CREATE TABLE app_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel TEXT NOT NULL,
                level_name TEXT NOT NULL,
                level INTEGER NOT NULL,
                message TEXT NOT NULL,
                context TEXT NULL,
                extra TEXT NULL,
                request_id TEXT NULL,
                logged_at TEXT NOT NULL
            )',
        );
    }

    /** @return list<array<string, mixed>> */
    private function logs(): array
    {
        $statement = $this->pdo->query('SELECT * FROM app_logs ORDER BY id');

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement === false ? [] : $statement->fetchAll();

        return $rows;
    }

    private function logConfig(string $driver): Config
    {
        return new Config([
            'APP_ENV' => 'local',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'LOG_DRIVER' => $driver,
            'LOG_LEVEL' => 'warning',
            'LOG_PATH' => 'storage/logs',
        ]);
    }

    public function testKayitVeritabaninaYazilir(): void
    {
        $context = new RequestContext();
        $context->fill(Ulid::generate(), 'Mozilla/5.0 Test');

        $logger = Logger::create($this->logConfig('db'), $this->tempRoot(), $context, $this->connection);
        $logger->error('Beklenmeyen hata', ['user_id' => 7]);

        $rows = $this->logs();

        self::assertCount(1, $rows);
        self::assertSame('tedarikapp', $rows[0]['channel']);
        self::assertSame('ERROR', $rows[0]['level_name']);
        self::assertSame('Beklenmeyen hata', $rows[0]['message']);
        self::assertStringContainsString('"user_id":7', (string) $rows[0]['context']);
        self::assertSame($context->id(), $rows[0]['request_id']);
    }

    public function testHassasAlanlarVeritabaninaDaGizlenerekYazilir(): void
    {
        $logger = Logger::create($this->logConfig('db'), $this->tempRoot(), null, $this->connection);
        $logger->error('Giriş denemesi', [
            'email' => 'admin@tedarikapp.test',
            'password' => 'cok-gizli-sifre',
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'error_code' => 'VALIDATION',
        ]);

        $context = (string) $this->logs()[0]['context'];

        self::assertStringNotContainsString('cok-gizli-sifre', $context);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $context);
        self::assertStringContainsString(LogRedactor::PLACEHOLDER, $context);
        // Beyaz liste (İE#5) DB hedefinde de geçerli.
        self::assertStringContainsString('VALIDATION', $context);
        self::assertStringContainsString('admin@tedarikapp.test', $context, 'E-posta sır değildir.');
    }

    public function testSeviyeAltindakiKayitlarYazilmaz(): void
    {
        $logger = Logger::create($this->logConfig('db'), $this->tempRoot(), null, $this->connection);
        $logger->info('Bu bilgi kaydı');
        $logger->warning('Bu uyarı');

        $rows = $this->logs();

        self::assertCount(1, $rows, 'LOG_LEVEL=warning altındaki kayıtlar yazılmamalı.');
        self::assertSame('WARNING', $rows[0]['level_name']);
    }

    public function testVeritabaniErisilemezseUygulamaDUSMEZ(): void
    {
        // Tablo yok → yazma patlar. Log kaybedilir ama istisna DIŞARI ÇIKMAZ.
        $this->pdo->exec('DROP TABLE app_logs');

        $handler = new DatabaseLogHandler($this->connection, null, Level::Warning);
        $logger = new \Monolog\Logger('test');
        $logger->pushHandler($handler);

        $logger->error('Bu kayıt kaybolacak');

        self::assertTrue(true, 'Log yazamamak uygulamayı düşürmemeli.');
    }

    public function testDosyaSurucusuSecilirseVeritabaninaYazilmaz(): void
    {
        $logger = Logger::create($this->logConfig('file'), $this->tempRoot(), null, $this->connection);
        $logger->error('Dosyaya gitmeli');

        self::assertSame([], $this->logs());
        self::assertFileExists($this->tempPath('storage/logs/app-' . date('Y-m-d') . '.log'));
    }

    public function testBaglantiVerilmezseDosyayaDuser(): void
    {
        // LOG_DRIVER=db ama bağlantı yok (ör. kurulum öncesi) → dosya hedefi kullanılır.
        $logger = Logger::create($this->logConfig('db'), $this->tempRoot(), null, null);
        $logger->error('Yedek yol');

        self::assertSame([], $this->logs());
        self::assertFileExists($this->tempPath('storage/logs/app-' . date('Y-m-d') . '.log'));
    }
}
