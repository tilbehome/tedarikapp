<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Setup\ReSetupTicket;
use App\Setup\SetupLock;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * İE#19 G2 + E6 — kilit SİLİNMEZ, bilet verilir; bilet olmadan kapı açılmaz.
 *
 * Bu süitin cevapladığı soru: "sahiplik kanıtı gösteren kişi kapıyı HERKESE mi
 * açıyor, yoksa yalnız KENDİ tarayıcısına mı?" Eski davranışta birincisiydi.
 */
final class YenidenKurulumBiletiTest extends TestCase
{
    use TempDirectory;

    private ArraySession $session;
    private FrozenClock $clock;
    private PDO $pdo;
    private string $appKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySession();
        $this->clock = new FrozenClock();
        $this->appKey = str_repeat('a1b2c3d4', 8);

        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }

        // Sahiplik kanıtı config.php'deki APP_KEY'dir.
        file_put_contents(
            $this->tempPath('config.php'),
            "<?php\nreturn ['DB_HOST' => 'localhost', 'DB_NAME' => 'db', 'DB_USER' => 'u', 'DB_PASS' => 'p', 'APP_KEY' => '"
            . $this->appKey . "'];\n",
        );

        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $this->pdo->exec('CREATE TABLE activity_log (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER NULL,
            action TEXT, detail TEXT NULL, ip TEXT NULL, actor_type TEXT, actor_id INTEGER NULL, request_id TEXT NULL,
            user_agent TEXT NULL, created_at TEXT)');
    }

    private function lock(): SetupLock
    {
        return new SetupLock(Connection::fromCallable(fn (): PDO => $this->pdo), $this->tempPath('storage'));
    }

    /** @param array<string, mixed>|null $body @param array<string, string> $cookies */
    private function call(string $method, string $path, ?array $body = null, array $cookies = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7']);
        if ($body !== null) {
            $request = $request->withParsedBody($body)->withHeader('Content-Type', 'application/json');
        }
        if ($cookies !== []) {
            $request = $request->withCookieParams($cookies);
        }

        return SetupAppBuilder::build(
            $this->tempRoot(),
            new NullLogger(),
            $this->session,
            $this->clock,
            setupLock: $this->lock(),
            appEnv: 'local',
        )->handle($request);
    }

    private function kilitle(): void
    {
        $this->lock()->write($this->clock->now());
    }

    private function cerezDegeri(ResponseInterface $yanit): ?string
    {
        foreach ($yanit->getHeader('Set-Cookie') as $header) {
            if (str_starts_with($header, ReSetupTicket::COOKIE_NAME . '=')) {
                $value = substr($header, strlen(ReSetupTicket::COOKIE_NAME) + 1);
                $end = strpos($value, ';');

                return rawurldecode($end === false ? $value : substr($value, 0, $end));
            }
        }

        return null;
    }

    public function testUnlockKILIDISILMEZBILETVERIR(): void
    {
        $this->kilitle();

        $yanit = $this->call('POST', '/api/setup/unlock', ['app_key' => $this->appKey]);

        self::assertSame(200, $yanit->getStatusCode());
        self::assertSame(
            SetupLock::STATE_LOCKED,
            $this->lock()->status(),
            'Kilit SİLİNMİŞ — sihirbaz herkese açık kaldı (eski davranış).',
        );
        self::assertNotNull($this->cerezDegeri($yanit), 'Bilet çerezi yazılmadı.');
    }

    public function testBiletsizIstekKILITLIKAPIDAN403ALIR(): void
    {
        $this->kilitle();

        self::assertSame(403, $this->call('GET', '/api/setup/state')->getStatusCode());
    }

    public function testBiletTasiyanIstekGECER(): void
    {
        $this->kilitle();

        $bilet = $this->cerezDegeri($this->call('POST', '/api/setup/unlock', ['app_key' => $this->appKey]));
        self::assertNotNull($bilet);

        $yanit = $this->call('GET', '/api/setup/state', null, [ReSetupTicket::COOKIE_NAME => $bilet]);

        self::assertSame(200, $yanit->getStatusCode(), 'Geçerli bilet kapıdan geçmeliydi.');
    }

    public function testIKINCITARAYICIBILETSIZGIREMEZ(): void
    {
        // E6'nın özü: kanıt bir kez gösterildi diye kapı HERKESE açılmaz.
        $this->kilitle();
        $this->call('POST', '/api/setup/unlock', ['app_key' => $this->appKey]);

        // "İkinci tarayıcı" = çerezsiz istek.
        self::assertSame(403, $this->call('GET', '/api/setup/state')->getStatusCode());
    }

    public function testYanlisAnahtarBILETVERMEZ(): void
    {
        $this->kilitle();

        $yanit = $this->call('POST', '/api/setup/unlock', ['app_key' => str_repeat('f', 64)]);

        self::assertSame(403, $yanit->getStatusCode());
        self::assertNull($this->cerezDegeri($yanit));
        self::assertSame(SetupLock::STATE_LOCKED, $this->lock()->status());
    }

    public function testSuresiDolanBiletGECMEZ(): void
    {
        $this->kilitle();
        $bilet = $this->cerezDegeri($this->call('POST', '/api/setup/unlock', ['app_key' => $this->appKey]));
        self::assertNotNull($bilet);

        $this->clock->advance('+16 minutes');

        self::assertSame(
            403,
            $this->call('GET', '/api/setup/state', null, [ReSetupTicket::COOKIE_NAME => $bilet])->getStatusCode(),
            '15 dakikayı geçen bilet hâlâ geçiyor.',
        );
    }
}
