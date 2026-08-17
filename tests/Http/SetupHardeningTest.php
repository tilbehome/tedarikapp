<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Middleware\SetupCsrf;
use App\Setup\SetupLock;
use App\Setup\SetupState;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * K37 sağlamlaştırma testleri (İE#9 §A) — KRİTİK.
 *
 *  A1: kilit DB'den okunamıyorsa sihirbaz KAPALIDIR (fail-closed).
 *  A2: `.env` diskte varken HTTP kurulum akışı asla üzerine yazamaz; varlığı setup'ı kilitler.
 *  A3: production'da sır girilen adımlar HTTPS olmadan İLERLEMEZ.
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

    /** Bağlantısı HER sorguda patlayan kilit — "DB erişimi koparılmış" senaryosu. */
    private function brokenLock(): SetupLock
    {
        return new SetupLock(
            Connection::fromCallable(static function (): \PDO {
                throw new RuntimeException('Veritabanına bağlanılamadı (test).');
            }),
            $this->tempPath('storage'),
        );
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $serverParams
     */
    private function call(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = [],
        ?SetupLock $lock = null,
        string $appEnv = 'local',
        array $serverParams = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7'] + $serverParams);
        if ($body !== null) {
            $request = $request->withParsedBody($body)->withHeader('Content-Type', 'application/json');
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $app = SetupAppBuilder::build(
            $this->tempRoot(),
            new NullLogger(),
            $this->session,
            $this->clock,
            setupLock: $lock,
            appEnv: $appEnv,
        );

        return $app->handle($request);
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    // ─────────────── A1: fail-closed kilit ───────────────

    public function testKilitOkunamiyorsaSihirbaz403Doner(): void
    {
        foreach ([
            ['GET', '/setup'],
            ['GET', '/api/setup/state'],
            ['POST', '/api/setup/database'],
            ['POST', '/api/setup/finish'],
        ] as [$method, $path]) {
            $response = $this->call($method, $path, $method === 'POST' ? [] : null, lock: $this->brokenLock());

            self::assertSame(403, $response->getStatusCode(), $method . ' ' . $path . ' fail-closed olmalı (K37).');
            self::assertSame('FORBIDDEN', $this->json($response)['error']['code']);
        }
    }

    public function testKilitStatusUcDurumluDoner(): void
    {
        self::assertSame(SetupLock::STATE_UNKNOWN, $this->brokenLock()->status());
        self::assertTrue($this->brokenLock()->isLocked(), 'unknown durum kilitli SAYILMALI (fail-closed).');

        // Bağlantısız kilit (kurulum yapılmamış sistem) dosya denetimiyle açık kalır.
        $fileOnly = new SetupLock(null, $this->tempPath('storage'));
        self::assertSame(SetupLock::STATE_UNLOCKED, $fileOnly->status());
        self::assertFalse($fileOnly->isLocked());
    }

    // ─────────────── A2: .env varlığı kilitler, üzerine yazılmaz ───────────────

    public function testMevcutEnvVarkenSetupUclari403DonerVeDosyaDegismez(): void
    {
        $original = "APP_ENV=production\nAPP_KEY=" . str_repeat('a', 64) . "\n";
        file_put_contents($this->tempPath('.env'), $original);

        // .env var → SetupAppBuilder DB'li kilit kurar; DSN geçersiz → fail-closed
        // katmanı da devrede. Sqlite'lı çalışan kilit vererek YALNIZ .env katmanını sınıyoruz.
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $workingLock = new SetupLock(Connection::fromCallable(static fn (): \PDO => $pdo), $this->tempPath('storage'));

        foreach ([
            ['GET', '/setup', null],
            ['GET', '/api/setup/state', null],
            ['POST', '/api/setup/database', ['host' => 'localhost']],
            ['POST', '/api/setup/env', ['app_url' => 'https://ornek.test']],
            ['POST', '/api/setup/finish', ['codes_saved' => true]],
        ] as [$method, $path, $body]) {
            $response = $this->call($method, $path, $body, lock: $workingLock);
            self::assertSame(403, $response->getStatusCode(), $method . ' ' . $path . ' .env katmanıyla kapanmalı.');
        }

        self::assertSame($original, file_get_contents($this->tempPath('.env')), '.env İÇERİĞİ DEĞİŞMEMELİ.');
    }

    public function testEnvUreticisiMevcutDosyaninUzerineYazmaz(): void
    {
        // Devam eden meşru kurulum oturumu bile mevcut .env'i yeniden ÜRETEMEZ.
        $state = new SetupState($this->session);
        $state->complete(SetupState::STEP_REQUIREMENTS);
        $state->put('database', ['host' => 'localhost', 'port' => 3306, 'name' => 'db', 'user' => 'u', 'pass' => 'p']);
        $state->complete(SetupState::STEP_DATABASE);
        $state->put('env_app_key', str_repeat('b', 64)); // oturum .env'in sahibi

        $original = "APP_KEY=" . str_repeat('b', 64) . "\n";
        file_put_contents($this->tempPath('.env'), $original);

        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $workingLock = new SetupLock(Connection::fromCallable(static fn (): \PDO => $pdo), $this->tempPath('storage'));

        $token = $this->json($this->call('GET', '/api/setup/state', lock: $workingLock))['data']['csrf_token'];
        $response = $this->call('POST', '/api/setup/env', ['app_url' => 'https://ornek.test'], [
            SetupCsrf::HEADER => $token,
        ], lock: $workingLock);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('env', $this->json($response)['error']['fields']);
        self::assertSame($original, file_get_contents($this->tempPath('.env')), '.env DEĞİŞMEMELİ (K37 §A2).');
    }

    public function testEnvSahibiOturumKurulumaDevamEdebilir(): void
    {
        // K33 manuel akışının K37 sonrası hâli: .env yazıldıktan sonra sihirbaz
        // yalnızca ÜRETEN oturum için açık kalır (migrate/admin adımları çalışabilmeli).
        $state = new SetupState($this->session);
        $state->put('env_app_key', str_repeat('c', 64));

        file_put_contents($this->tempPath('.env'), "APP_KEY=" . str_repeat('c', 64) . "\n");

        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $workingLock = new SetupLock(Connection::fromCallable(static fn (): \PDO => $pdo), $this->tempPath('storage'));

        $response = $this->call('GET', '/api/setup/state', lock: $workingLock);

        self::assertSame(200, $response->getStatusCode(), '.env sahibi oturum 403 ALMAMALI.');
    }

    // ─────────────── A3: HTTPS kapısı ───────────────

    public function testProductionHttpUzerindenSirAdimlari403Doner(): void
    {
        foreach (['/api/setup/database', '/api/setup/admin', '/api/setup/admin/verify'] as $path) {
            $request = (new ServerRequestFactory())
                ->createServerRequest('POST', 'http://ornek.test' . $path, ['REMOTE_ADDR' => '203.0.113.7'])
                ->withParsedBody(['host' => 'localhost'])
                ->withHeader('Content-Type', 'application/json');

            $app = SetupAppBuilder::build(
                $this->tempRoot(),
                new NullLogger(),
                $this->session,
                $this->clock,
                appEnv: 'production',
            );
            $response = $app->handle($request);

            self::assertSame(403, $response->getStatusCode(), $path . ' HTTPS olmadan ilerlememeli.');
            self::assertSame('HTTPS_REQUIRED', $this->json($response)['error']['code']);
        }
    }

    public function testProductionHttpsIleSirAdimiKapidanGecer(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://ornek.test/api/setup/database', ['REMOTE_ADDR' => '203.0.113.7'])
            ->withParsedBody([])
            ->withHeader('Content-Type', 'application/json');

        $app = SetupAppBuilder::build($this->tempRoot(), new NullLogger(), $this->session, $this->clock, appEnv: 'production');
        $response = $app->handle($request);

        // Kapıdan geçti; CSRF katmanına düştü (403 ama HTTPS_REQUIRED DEĞİL).
        self::assertNotSame('HTTPS_REQUIRED', $this->json($response)['error']['code'] ?? null);
    }

    public function testLoopbackHostProductionDaHttpIleCalisabilir(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'http://localhost/api/setup/database', ['REMOTE_ADDR' => '127.0.0.1'])
            ->withParsedBody([])
            ->withHeader('Content-Type', 'application/json');

        $app = SetupAppBuilder::build($this->tempRoot(), new NullLogger(), $this->session, $this->clock, appEnv: 'production');
        $response = $app->handle($request);

        self::assertNotSame('HTTPS_REQUIRED', $this->json($response)['error']['code'] ?? null);
    }

    public function testGelistirmeOrtamiHttpIleIlerleyebilir(): void
    {
        $token = $this->json($this->call('GET', '/api/setup/state'))['data']['csrf_token'];
        $response = $this->call('POST', '/api/setup/database', [], [SetupCsrf::HEADER => $token], appEnv: 'local');

        // HTTPS kapısına takılmadı; adım sırası/doğrulama katmanına ulaştı.
        self::assertSame(422, $response->getStatusCode());
    }
}
