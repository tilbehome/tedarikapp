<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Setup\SetupLock;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * K45 kapı davranışı (Ürün Sahibi talimatı — kurulum BASİT):
 * TEK kural: DB'de kesin kilit varsa 403; diğer her durumda sihirbaz AÇIK.
 * (K37'nin ek katmanları — config varlığı kilidi, fail-closed, HTTPS kapısı —
 * üretimde kurulumu defalarca blokladığı için K45 ile kaldırıldı.)
 */
final class SetupHardeningTest extends TestCase
{
    use TempDirectory;

    private ArraySession $session;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySession();
        $this->clock = new FrozenClock();

        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }
    }

    private function call(string $method, string $path, ?SetupLock $lock = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7']);

        $app = SetupAppBuilder::build(
            $this->tempRoot(),
            new NullLogger(),
            $this->session,
            $this->clock,
            setupLock: $lock,
            appEnv: 'local',
        );

        return $app->handle($request);
    }

    private function workingLock(): SetupLock
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');

        return new SetupLock(Connection::fromCallable(static fn (): \PDO => $pdo), $this->tempPath('storage'));
    }

    public function testKesinKilitVarsaApiKapaliSayfaSecenekli(): void
    {
        $lock = $this->workingLock();
        $lock->write(new \DateTimeImmutable('2026-08-17 12:00:00'));

        // K45/K46: sayfa seçenek ekranı için AÇIK; API uçları kilitli.
        self::assertSame(200, $this->call('GET', '/setup', $lock)->getStatusCode());
        self::assertSame(403, $this->call('GET', '/api/setup/state', $lock)->getStatusCode());
    }

    public function testKilitOKUNAMIYORSAKAPIFAILCLOSED(): void
    {
        // İE#19 G1 — DAVRANIŞ DEĞİŞTİ (PM emri). Eskiden kilit okunamıyorsa sihirbaz
        // AÇILIYORDU: kurulu bir sistemde veritabanını bir an düşürebilen biri,
        // kimliksiz bir kurulum kapısı elde ediyordu. Artık karar verilemiyorsa
        // GEÇİLMEZ: 503. "Kilit satırı hiç yazılmamış" gerçek ilk kurulum (bağlantı
        // yapılandırılmamış) bundan etkilenmez — o yol aşağıdaki testlerde.
        $broken = new SetupLock(
            Connection::fromCallable(static function (): \PDO {
                throw new RuntimeException('DB yok (test)');
            }),
            $this->tempPath('storage'),
        );

        $yanit = $this->call('GET', '/api/setup/state', $broken);
        self::assertSame(503, $yanit->getStatusCode());
        self::assertStringContainsString('SETUP_STATE_UNKNOWN', (string) $yanit->getBody());
    }

    public function testYapilandirilmamisSistemdeSihirbazACIKKALIR(): void
    {
        // G1'in sınırı: bağlantı YOKSA (config.php yok) kilit dosyadan okunur ve
        // "unlocked" döner. Gerçek ilk kurulum hiçbir koşulda bloklanmaz (K45).
        $lock = new SetupLock(null, $this->tempPath('storage'));

        self::assertSame(200, $this->call('GET', '/api/setup/state', $lock)->getStatusCode());
    }

    public function testConfigVarkenSihirbazAcikVeIlkAdimlarOtomatikGecilir(): void
    {
        // K45: config.php varlığı artık KİLİT DEĞİL — mevcut dosya aynen kullanılır,
        // sihirbaz doğrudan "Tablolar" adımından devam eder.
        file_put_contents(
            $this->tempPath('config.php'),
            "<?php\nreturn ['DB_HOST' => 'localhost', 'DB_NAME' => 'db', 'DB_USER' => 'u', 'DB_PASS' => 'p', 'APP_KEY' => '" . str_repeat('a', 64) . "'];\n",
        );

        $response = $this->call('GET', '/api/setup/state', $this->workingLock());

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true)['data'];
        self::assertSame('migrate', $data['step'], 'Config varken sihirbaz Tablolar adımından başlamalı.');
        self::assertTrue($data['env_exists']);
    }
}
