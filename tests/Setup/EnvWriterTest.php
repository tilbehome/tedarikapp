<?php

declare(strict_types=1);

namespace Tests\Setup;

use App\Core\Config;
use App\Setup\EnvWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempDirectory;

final class EnvWriterTest extends TestCase
{
    use TempDirectory;

    /** @var array{host: string, port: int, name: string, user: string, pass: string} */
    private const array DATABASE = [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'tedarikapp',
        'user' => 'tedarik_user',
        'pass' => 'gizli-sifre',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Gerçek şablonla çalış: üretilen .env, .env.example'ın belgelerini korumalı.
        copy(dirname(__DIR__, 2) . '/.env.example', $this->tempPath('.env.example'));
    }

    private function writer(): EnvWriter
    {
        return new EnvWriter($this->tempRoot());
    }

    private function writeAndRead(): string
    {
        $this->writer()->write('https://ornek.test', self::DATABASE);

        return (string) file_get_contents($this->tempPath('.env'));
    }

    public function testDosyaOlusturulur(): void
    {
        self::assertFalse($this->writer()->exists());

        $this->writer()->write('https://ornek.test', self::DATABASE);

        self::assertTrue($this->writer()->exists());
    }

    public function testVeritabaniBilgileriYazilir(): void
    {
        $content = $this->writeAndRead();

        self::assertStringContainsString('DB_HOST=localhost', $content);
        self::assertStringContainsString('DB_PORT=3306', $content);
        self::assertStringContainsString('DB_NAME=tedarikapp', $content);
        self::assertStringContainsString('DB_USER=tedarik_user', $content);
        self::assertStringContainsString('DB_PASS=gizli-sifre', $content);
        self::assertStringContainsString('APP_URL=https://ornek.test', $content);
    }

    public function testUretimAyarlariZorlanir(): void
    {
        $content = $this->writeAndRead();

        self::assertStringContainsString('APP_ENV=production', $content);
        self::assertStringContainsString('APP_DEBUG=false', $content);
    }

    public function testAnahtarlarKriptografikUretilir(): void
    {
        $content = $this->writeAndRead();

        self::assertMatchesRegularExpression('/^APP_KEY=[0-9a-f]{64}$/m', $content);
        self::assertMatchesRegularExpression('/^EXTENSION_TOKEN_SALT=.{32,}$/m', $content);
        self::assertStringNotContainsString('APP_KEY=' . PHP_EOL, $content, 'APP_KEY boş bırakılmamalı.');
    }

    public function testHerKurulumFarkliAnahtarUretir(): void
    {
        $first = $this->writeAndRead();
        $second = $this->writeAndRead();

        preg_match('/^APP_KEY=(.*)$/m', $first, $a);
        preg_match('/^APP_KEY=(.*)$/m', $second, $b);

        self::assertNotSame($a[1], $b[1]);
    }

    public function testSablonBelgeleriKorunur(): void
    {
        $content = $this->writeAndRead();

        // .env.example'daki açıklama satırları kurulan sistemde de kalmalı.
        self::assertStringContainsString('utf8mb4 ZORUNLU', $content);
        self::assertStringContainsString('# ─────────────── Veritabanı (MySQL) ───────────────', $content);
    }

    public function testBosluklarIceriyorsaTirnaklanir(): void
    {
        $this->writer()->write('https://ornek.test', [...self::DATABASE, 'pass' => 'bosluk iceren sifre']);
        $content = (string) file_get_contents($this->tempPath('.env'));

        self::assertStringContainsString('DB_PASS="bosluk iceren sifre"', $content);
    }

    public function testUretilenEnvConfigTarafindanOkunabilir(): void
    {
        $this->writer()->write('https://ornek.test', self::DATABASE);

        // Üretim modunda Config, APP_KEY ve EXTENSION_TOKEN_SALT biçimini de doğrular (K27).
        $config = Config::load($this->tempRoot());

        self::assertTrue($config->isProduction());
        self::assertSame('tedarikapp', $config->get('DB_NAME'));
        self::assertSame(3306, $config->getPositiveInt('DB_PORT'));
    }

    public function testSablonYoksaAnlasilirHataVerir(): void
    {
        unlink($this->tempPath('.env.example'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('.env.example okunamadı');
        $this->writer()->write('https://ornek.test', self::DATABASE);
    }

    public function testGeciciDosyaBirakmaz(): void
    {
        $this->writer()->write('https://ornek.test', self::DATABASE);

        $leftovers = glob($this->tempRoot() . '/.env.*.tmp');

        self::assertSame([], $leftovers === false ? [] : $leftovers);
    }
}
