<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Setup\SetupLock;
use App\Setup\SetupState;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * K46 KRİTİK GÜVENLİK: kilit kaldırma SAHİPLİK KANITI ister.
 *
 * K45'teki kimliksiz unlock internetteki herkese açıktı (K34/K37 ihlali).
 * Artık: kanıtsız 403 · yanlış APP_KEY 403 + artan bekleme (429) · doğru
 * APP_KEY 200 + kilit temiz · yıkıcı temiz kurulum "SIFIRLA" yazılmadan 422.
 * K45 amacı korunur: kilitliyken /setup sayfası yine açılır (seçenek ekranda).
 */
final class SetupUnlockTest extends TestCase
{
    use TempDirectory;

    private ArraySession $session;
    private FrozenClock $clock;
    private \PDO $pdo;
    private string $appKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySession();
        $this->clock = new FrozenClock();

        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $this->pdo->exec(
            'CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT NOT NULL, entity_id INTEGER NULL, action TEXT NOT NULL,
                detail TEXT NULL, ip TEXT NULL, actor_type TEXT NOT NULL DEFAULT \'admin\',
                actor_id INTEGER NULL, request_id TEXT NULL, user_agent TEXT NULL, created_at TEXT NOT NULL
            )',
        );

        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }

        // Kurulu sistem: config.php + DB'de kilit.
        $this->appKey = str_repeat('ab12', 16);
        file_put_contents(
            $this->tempPath('config.php'),
            "<?php\nreturn ['DB_HOST' => 'localhost', 'DB_NAME' => 'db', 'DB_USER' => 'u', 'DB_PASS' => 'p', 'APP_KEY' => '{$this->appKey}'];\n",
        );
        $this->lock()->write(new \DateTimeImmutable('2026-08-17 12:00:00'));
    }

    private function lock(): SetupLock
    {
        return new SetupLock(
            Connection::fromCallable(fn (): \PDO => $this->pdo),
            $this->tempPath('storage'),
        );
    }

    /** @param array<string, mixed>|null $body */
    private function call(string $method, string $path, ?array $body = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7']);
        if ($body !== null) {
            $request = $request->withParsedBody($body)->withHeader('Content-Type', 'application/json');
        }

        $app = SetupAppBuilder::build(
            $this->tempRoot(),
            new NullLogger(),
            $this->session,
            $this->clock,
            setupLock: $this->lock(),
            appEnv: 'local',
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

    // ─────────────── Kanıt kapısı ───────────────

    public function testKanitsizUnlock403DonerVeKilitKalir(): void
    {
        $response = $this->call('POST', '/api/setup/unlock', []);

        self::assertSame(403, $response->getStatusCode());
        self::assertTrue($this->lock()->isLocked(), 'Kanıtsız istek kilidi SİLEMEZ.');
    }

    public function testYanlisAppKey403DonerVeLoglanir(): void
    {
        $response = $this->call('POST', '/api/setup/unlock', ['app_key' => str_repeat('f', 64)]);

        self::assertSame(403, $response->getStatusCode());
        self::assertTrue($this->lock()->isLocked());

        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) AS c FROM activity_log WHERE action = 'setup_unlock_failed'",
        )->fetch()['c'];
        self::assertSame(1, $count, 'Başarısız deneme activity_log\'a yazılmalı.');
    }

    public function testArtanBeklemeKabaKuvvetiKeser(): void
    {
        foreach ([1, 2, 3] as $i) {
            $this->call('POST', '/api/setup/unlock', ['app_key' => str_repeat('f', 64)]);
        }

        // 4. deneme: eşik aşıldı → doğru anahtar bile beklemeye takılır (429).
        $response = $this->call('POST', '/api/setup/unlock', ['app_key' => $this->appKey]);

        self::assertSame(429, $response->getStatusCode());
        $body = $this->json($response);
        self::assertSame('RATE_LIMITED', $body['error']['code']);
        self::assertGreaterThan(0, $body['meta']['retry_after_seconds']);
        self::assertTrue($this->lock()->isLocked(), 'Bekleme sırasında kilit YERİNDE kalır.');
    }

    public function testDogruAppKeyKilidiKaldirir(): void
    {
        $response = $this->call('POST', '/api/setup/unlock', ['app_key' => $this->appKey]);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->json($response)['data']['unlocked']);
        self::assertFalse($this->lock()->isLocked(), 'Doğru kanıt kilidi kaldırır.');

        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) AS c FROM activity_log WHERE action = 'setup_unlock'",
        )->fetch()['c'];
        self::assertSame(1, $count, 'Başarılı kaldırma da loglanır.');
    }

    // ─────────────── K45 amacı bozulmadı ───────────────

    public function testKilitliykenSetupSayfasiAcilirVeSecenekKutusunuIcerir(): void
    {
        $page = $this->call('GET', '/setup');

        self::assertSame(200, $page->getStatusCode(), 'Kilitliyken sihirbaz SAYFASI açılır (K45).');
        $html = (string) $page->getBody();
        self::assertStringContainsString('locked-box', $html);
        self::assertStringContainsString('unlock-app-key', $html, 'APP_KEY kanıt alanı ekranda olmalı (K46).');

        // API uçları ise kilitli kalır.
        self::assertSame(403, $this->call('GET', '/api/setup/state')->getStatusCode());
    }

    // ─────────────── Yıkıcı işlem onayı ───────────────

    public function testTemizKurulumSifirlaYazilmadan422Doner(): void
    {
        // Kilidi kanıtla kaldır, adımı migrate'e getir (config mevcut → otomatik ilerler).
        $this->call('POST', '/api/setup/unlock', ['app_key' => $this->appKey]);
        $state = new SetupState($this->session);
        $state->complete(SetupState::STEP_REQUIREMENTS);
        $state->complete(SetupState::STEP_DATABASE);
        $state->complete(SetupState::STEP_ENV);
        $csrf = $state->csrfToken();

        $response = $this->call('POST', '/api/setup/migrate', ['fresh' => true, 'app_key' => $this->appKey]);
        // CSRF başlığı yok → 403 CSRF önce gelir; başlıkla tekrar:
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/setup/migrate', ['REMOTE_ADDR' => '203.0.113.7'])
            ->withParsedBody(['fresh' => true, 'app_key' => $this->appKey])
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(\App\Middleware\SetupCsrf::HEADER, $csrf);
        $app = SetupAppBuilder::build($this->tempRoot(), new NullLogger(), $this->session, $this->clock, setupLock: $this->lock(), appEnv: 'local');
        $response = $app->handle($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('confirm', $this->json($response)['error']['fields'], 'SIFIRLA yazılmadan sıfırlama YOK (K46).');
    }

    public function testTemizKurulumYanlisAnahtarla403Doner(): void
    {
        $this->call('POST', '/api/setup/unlock', ['app_key' => $this->appKey]);
        $state = new SetupState($this->session);
        $state->complete(SetupState::STEP_REQUIREMENTS);
        $state->complete(SetupState::STEP_DATABASE);
        $state->complete(SetupState::STEP_ENV);
        $csrf = $state->csrfToken();

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/setup/migrate', ['REMOTE_ADDR' => '203.0.113.7'])
            ->withParsedBody(['fresh' => true, 'confirm' => 'SIFIRLA', 'app_key' => str_repeat('e', 64)])
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(\App\Middleware\SetupCsrf::HEADER, $csrf);
        $app = SetupAppBuilder::build($this->tempRoot(), new NullLogger(), $this->session, $this->clock, setupLock: $this->lock(), appEnv: 'local');
        $response = $app->handle($request);

        self::assertSame(403, $response->getStatusCode(), 'Yanlış anahtarla yıkıcı işlem ÇALIŞMAZ (K46).');
    }
}
