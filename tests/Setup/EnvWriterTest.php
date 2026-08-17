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
        // K37: mevcut .env'in üzerine yazılamaz — yeniden kurulum dosyanın elle
        // silinmesini gerektirir; test de aynı yolu izler.
        unlink($this->tempPath('.env'));
        $second = $this->writeAndRead();

        preg_match('/^APP_KEY=(.*)$/m', $first, $a);
        preg_match('/^APP_KEY=(.*)$/m', $second, $b);

        self::assertNotSame($a[1], $b[1]);
    }

    public function testMevcutDosyaninUzerineYazmayiReddeder(): void
    {
        $original = $this->writeAndRead();

        try {
            $this->writer()->write('https://saldirgan.test', self::DATABASE);
            self::fail('K37: mevcut .env üzerine yazma istisna fırlatmalıydı.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('üzerine yazmaz', $e->getMessage());
        }

        self::assertSame($original, file_get_contents($this->tempPath('.env')), 'Dosya DEĞİŞMEMELİ.');
    }

    public function testSablonBelgeleriKorunur(): void
    {
        $content = $this->writeAndRead();

        // .env.example'daki açıklama satırları kurulan sistemde de kalmalı.
        self::assertStringContainsString('utf8mb4 SABİTTİR', $content);
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

    /**
     * Regresyon: aynı istekte Config İKİ KEZ kurulabilmeli.
     *
     * K33 ile kurulum kilidi veritabanına taşınınca, tek bir istekte hem kilit denetimi
     * hem controller Config kuruyor. `Dotenv::createImmutable` ikinci `load()` çağrısında
     * BOŞ dizi döndürdüğü için sihirbaz "zorunlu anahtar eksik" hatasıyla düşüyordu —
     * canlı koşumda yakalandı. Çözüm: array-backed adaptör.
     */
    public function testConfigAyniSurecteIkiKezYuklenebilir(): void
    {
        $this->writer()->write('https://ornek.test', self::DATABASE);

        $first = Config::load($this->tempRoot());
        $second = Config::load($this->tempRoot());

        self::assertSame($first->get('DB_NAME'), $second->get('DB_NAME'));
        self::assertSame('tedarikapp', $second->get('DB_NAME'));
        self::assertSame($first->get('APP_KEY'), $second->get('APP_KEY'));
    }

    public function testSirlarSurecOrtaminaSIZMAZ(): void
    {
        $this->writer()->write('https://ornek.test', self::DATABASE);
        $config = Config::load($this->tempRoot());

        // Değer Config üzerinden okunabilir...
        self::assertSame('gizli-sifre', $config->get('DB_PASS'));
        // ...ama süreç ortamına yazılmaz: getenv/phpinfo dökümünde görünmez (CLAUDE.md §5).
        self::assertFalse(getenv('DB_PASS'), 'DB_PASS süreç ortamına sızmamalı.');
        self::assertArrayNotHasKey('DB_PASS', $_ENV);
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
