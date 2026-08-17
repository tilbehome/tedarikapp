<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Middleware\SetupCsrf;
use App\Setup\EnvWriter;
use App\Setup\SetupLock;
use App\Setup\SetupState;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * Kurulum sihirbazı sözleşme testleri (İE#5 §10–11).
 *
 * Mutlu yol (gerçek DB bağlantısı + migration + admin) MySQL gerektirir ve
 * ÇIKTI RAPORU'ndaki canlı uçtan uca koşumda doğrulanır; burada kapı kuralları
 * (kilit, CSRF, adım sırası) ve DB'ye dokunmayan adımlar sınanır.
 */
final class SetupEndpointsTest extends TestCase
{
    use TempDirectory;

    private ArraySession $session;
    private FrozenClock $clock;
    private \PDO $pdo;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySession();
        $this->clock = new FrozenClock();

        // K33: kilit veritabanında tutuluyor; sihirbaz testleri de DB'li koşar.
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $this->connection = Connection::fromCallable(fn (): \PDO => $this->pdo);

        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }
    }

    private function lock(): SetupLock
    {
        return new SetupLock($this->connection, $this->tempPath('storage'));
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     */
    private function call(string $method, string $path, ?array $body = null, array $headers = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7']);
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
            setupLock: $this->lock(),
            appEnv: 'local', // HTTPS kapısı (K37 §A3) ayrı testte production ile sınanır
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

    private function token(): string
    {
        /** @var array{csrf_token: string} $data */
        $data = $this->json($this->call('GET', '/api/setup/state'))['data'];

        return $data['csrf_token'];
    }

    // ─────────────── Kilit (İE#5 §10) ───────────────

    public function testKilitYokkenSihirbazAcilir(): void
    {
        $response = $this->call('GET', '/setup');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('tedarikapp kurulumu', (string) $response->getBody());
    }

    public function testKilitliykenSihirbazSayfasi403Doner(): void
    {
        $this->lock()->write(new DateTimeImmutable('2026-08-16 18:00:00'));

        $response = $this->call('GET', '/setup');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('FORBIDDEN', $this->json($response)['error']['code']);
    }

    public function testKilitliykenTumSetupUclari403Doner(): void
    {
        $this->lock()->write(new DateTimeImmutable('2026-08-16 18:00:00'));

        foreach ([
            ['GET', '/api/setup/state', null],
            ['GET', '/api/setup/requirements', null],
            ['POST', '/api/setup/database', []],
            ['POST', '/api/setup/env', []],
            ['POST', '/api/setup/migrate', []],
            ['POST', '/api/setup/admin', []],
            ['POST', '/api/setup/admin/verify', []],
            ['POST', '/api/setup/finish', []],
            ['GET', '/setup/wizard.js', null],
        ] as [$method, $path, $body]) {
            $response = $this->call($method, $path, $body);
            self::assertSame(403, $response->getStatusCode(), $method . ' ' . $path . ' 403 dönmeli.');
        }
    }

    // ─────────────── CSRF (İE#5 §11) ───────────────

    public function testTokensizYazmaIstegi403Doner(): void
    {
        $response = $this->call('POST', '/api/setup/database', ['host' => 'localhost']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('CSRF', $this->json($response)['error']['code']);
    }

    public function testYanlisTokenla403Doner(): void
    {
        $response = $this->call('POST', '/api/setup/database', ['host' => 'localhost'], [
            SetupCsrf::HEADER => str_repeat('0', 64),
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDurumUcuTokenVeAdimDoner(): void
    {
        $body = $this->json($this->call('GET', '/api/setup/state'));

        self::assertTrue($body['success']);
        self::assertSame(SetupState::STEP_REQUIREMENTS, $body['data']['step']);
        self::assertSame(64, strlen((string) $body['data']['csrf_token']));
        self::assertFalse($body['data']['env_exists']);
    }

    // ─────────────── Adım sırası ───────────────

    public function testSirasiGelmemisAdim422StateTransitionDoner(): void
    {
        $token = $this->token();

        $response = $this->call('POST', '/api/setup/env', ['app_url' => 'https://ornek.test'], [
            SetupCsrf::HEADER => $token,
        ]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->json($response);
        self::assertSame('STATE_TRANSITION', $body['error']['code']);
        self::assertSame(SetupState::STEP_REQUIREMENTS, $body['meta']['current_step']);
    }

    public function testAdminAdimiVeritabanindanOnceCalismaz(): void
    {
        $response = $this->call('POST', '/api/setup/admin', [
            'email' => 'admin@tedarikapp.test',
            'password' => 'cok-gizli-sifre',
        ], [SetupCsrf::HEADER => $this->token()]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('STATE_TRANSITION', $this->json($response)['error']['code']);
    }

    public function testFinishKurtarmaAdimindanOnceCalismaz(): void
    {
        $response = $this->call('POST', '/api/setup/finish', ['codes_saved' => true], [
            SetupCsrf::HEADER => $this->token(),
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($this->lock()->isLocked(), 'Sıra dışı çağrı kilidi YAZMAMALI.');
    }

    // ─────────────── Gereksinim denetimi ───────────────

    public function testGereksinimDenetimiEksikleriIsimIsimListeler(): void
    {
        $body = $this->json($this->call('GET', '/api/setup/requirements'));
        $data = $body['data'];

        self::assertArrayHasKey('php', $data);
        self::assertSame(PHP_VERSION, $data['php']['current']);

        $names = array_column($data['extensions'], 'name');
        foreach (['pdo_mysql', 'curl', 'gd', 'mbstring', 'zip', 'intl', 'bcmath', 'fileinfo', 'openssl'] as $required) {
            self::assertContains($required, $names, $required . ' denetlenmeli.');
        }

        $paths = array_column($data['writable'], 'path');
        self::assertContains('storage/', $paths);
        self::assertContains('public/media/', $paths);
    }

    public function testEksikYazilabilirlikKurulumuBloklamaz(): void
    {
        // storage/ yerine yazılamaz bir dosya koyarak klasör oluşumunu engelle.
        file_put_contents($this->tempPath('storage'), 'bu bir dosya, klasor degil');

        $data = $this->json($this->call('GET', '/api/setup/requirements'))['data'];

        $storage = null;
        foreach ($data['writable'] as $entry) {
            if ($entry['path'] === 'storage/') {
                $storage = $entry;
            }
        }
        self::assertIsArray($storage);
        self::assertFalse($storage['ok']);
        self::assertFalse($storage['required'], 'K37 §D10: yazılabilirlik zorunlu gereksinim DEĞİL.');
        self::assertStringContainsString('yazma izni', $storage['hint'], 'Eksik, çözüm önerisiyle anlatılmalı.');

        // K37 §D10: yazılamazlık ok bayrağını DÜŞÜRMEZ; hotlink/DB modu önerilir.
        $phpAndExtensionsOk = $data['php']['ok'];
        foreach ($data['extensions'] as $extension) {
            if ($extension['required'] && !$extension['ok']) {
                $phpAndExtensionsOk = false;
            }
        }
        if ($phpAndExtensionsOk) {
            self::assertTrue($data['ok'], 'Yazılamayan klasör kurulumu bloklamamalı (K37 §D10).');
        }
        self::assertStringContainsString('hotlink', implode(' ', $data['warnings']));
    }

    public function testHttpsDegilseUyariVerilirAmaBloklamaz(): void
    {
        $data = $this->json($this->call('GET', '/api/setup/requirements'))['data'];

        $warnings = implode(' ', $data['warnings']);
        self::assertStringContainsString('HTTPS', $warnings);
    }

    public function testGereksinimlerTamamsaSonrakiAdimAcilir(): void
    {
        $data = $this->json($this->call('GET', '/api/setup/requirements'))['data'];

        if ($data['ok'] !== true) {
            self::markTestSkipped('Bu ortamda gereksinimler eksik; adım ilerlemesi ayrıca sınanır.');
        }

        self::assertSame(SetupState::STEP_DATABASE, $data['step']);
    }

    // ─────────────── Veritabanı adımı ───────────────

    public function testEksikVeritabaniAlanlari422Doner(): void
    {
        $this->call('GET', '/api/setup/requirements');
        $response = $this->call('POST', '/api/setup/database', [], [SetupCsrf::HEADER => $this->token()]);

        if ($response->getStatusCode() === 422 && $this->json($response)['error']['code'] === 'STATE_TRANSITION') {
            self::markTestSkipped('Bu ortamda gereksinim adımı geçilemedi.');
        }

        self::assertSame(422, $response->getStatusCode());
        $fields = $this->json($response)['error']['fields'];
        self::assertArrayHasKey('name', $fields);
        self::assertArrayHasKey('user', $fields);
    }

    public function testUlasilamayanVeritabaniAnlasilirHataVerir(): void
    {
        $this->call('GET', '/api/setup/requirements');
        $response = $this->call('POST', '/api/setup/database', [
            'host' => '203.0.113.201',
            'port' => '3306',
            'name' => 'yok',
            'user' => 'yok',
            'pass' => 'yok',
        ], [SetupCsrf::HEADER => $this->token()]);

        if ($this->json($response)['error']['code'] === 'STATE_TRANSITION') {
            self::markTestSkipped('Bu ortamda gereksinim adımı geçilemedi.');
        }

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse(is_file($this->tempPath('.env')), 'Başarısız bağlantıda .env YAZILMAMALI.');
    }

    // ─────────────── K33: yazılamayan docroot ───────────────

    /** Yazılamayan kök simülasyonu — üretimdeki DSO durumunun testteki karşılığı. */
    private function unwritableApp(): \Slim\App
    {
        return SetupAppBuilder::build(
            $this->tempRoot(),
            new NullLogger(),
            $this->session,
            $this->clock,
            setupLock: $this->lock(),
            envWriter: new EnvWriter($this->tempRoot(), writableOverride: false),
            appEnv: 'local',
        );
    }

    /** @param array<string, mixed>|null $body @param array<string, string> $headers */
    private function callUnwritable(string $method, string $path, ?array $body = null, array $headers = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7']);
        if ($body !== null) {
            $request = $request->withParsedBody($body)->withHeader('Content-Type', 'application/json');
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->unwritableApp()->handle($request);
    }

    /** Adım sırasını .env'e kadar ilerletir. */
    private function advanceToEnvStep(): void
    {
        $state = new SetupState($this->session);
        $state->complete(SetupState::STEP_REQUIREMENTS);
        $state->put('database', ['host' => 'localhost', 'port' => 3306, 'name' => 'db', 'user' => 'u', 'pass' => 'p']);
        $state->complete(SetupState::STEP_DATABASE);
    }

    public function testYazilamazKokteEnvIcerigiEkrandaGosterilir(): void
    {
        $this->advanceToEnvStep();

        $response = $this->callUnwritable('POST', '/api/setup/env', ['app_url' => 'https://ornek.test'], [
            SetupCsrf::HEADER => $this->token(),
        ]);
        $data = $this->json($response)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($data['manual'], 'Yazılamayan kökte manuel akış devreye girmeli.');
        self::assertStringContainsString('APP_KEY=', $data['content']);
        self::assertStringContainsString('DB_NAME=db', $data['content']);
        self::assertStringContainsString('.env', $data['target_path']);
        self::assertFileDoesNotExist($this->tempPath('.env'), 'Dosya YAZILMAMALI.');
        self::assertSame(SetupState::STEP_ENV, $data['step'], 'Doğrulama yapılmadan adım ilerlememeli.');
    }

    public function testDosyaKaydedilmedenDogrulama422Doner(): void
    {
        $this->advanceToEnvStep();
        $this->callUnwritable('POST', '/api/setup/env', ['app_url' => 'https://ornek.test'], [
            SetupCsrf::HEADER => $this->token(),
        ]);

        $response = $this->callUnwritable('POST', '/api/setup/env/verify', [], [SetupCsrf::HEADER => $this->token()]);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('env', $this->json($response)['error']['fields']);
    }

    public function testYanlisIcerikKaydedilirseDogrulamaReddedilir(): void
    {
        $this->advanceToEnvStep();
        $this->callUnwritable('POST', '/api/setup/env', ['app_url' => 'https://ornek.test'], [
            SetupCsrf::HEADER => $this->token(),
        ]);

        // Kullanıcı başka bir dosya kaydetti (veya içeriği eksik yapıştırdı).
        file_put_contents($this->tempPath('.env'), "APP_KEY=" . str_repeat('0', 64) . "\n");

        $response = $this->callUnwritable('POST', '/api/setup/env/verify', [], [SetupCsrf::HEADER => $this->token()]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('uyuşmuyor', $this->json($response)['error']['fields']['env']);
    }

    public function testDogruIcerikKaydedilirseAdimIlerler(): void
    {
        $this->advanceToEnvStep();
        $generated = $this->json($this->callUnwritable('POST', '/api/setup/env', ['app_url' => 'https://ornek.test'], [
            SetupCsrf::HEADER => $this->token(),
        ]))['data'];

        // Kullanıcı içeriği Dosya Yöneticisi'nden kaydediyor.
        file_put_contents($this->tempPath('.env'), $generated['content']);

        $response = $this->callUnwritable('POST', '/api/setup/env/verify', [], [SetupCsrf::HEADER => $this->token()]);
        $data = $this->json($response)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($data['verified']);
        self::assertSame(SetupState::STEP_MIGRATE, $data['step']);
    }

    // ─────────────── Yönlendirme ───────────────

    public function testKurulmamisSistemdeKokAdresSihirbazaYonlendirir(): void
    {
        $response = $this->call('GET', '/');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/setup', $response->getHeaderLine('Location'));
    }

    public function testGuvenlikBasliklariSihirbazdaDaVar(): void
    {
        $response = $this->call('GET', '/setup');

        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertNotSame('', $response->getHeaderLine('X-Request-Id'));
    }
}
