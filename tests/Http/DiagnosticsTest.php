<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\AppBuilder;
use App\Core\Config;
use App\Core\Logger;
use App\Core\RequestContext;
use App\Core\SetupAppBuilder;
use App\Middleware\Csrf;
use App\Middleware\SetupCsrf;
use App\Setup\SetupDiagnostics;
use App\Setup\SetupLock;
use App\Setup\SetupState;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AuthTestCase;
use Tests\Support\TempDirectory;

/**
 * K42 KRİTİK: kurulum ve açılış hataları ASLA sessiz/çıplak 500 olmaz.
 *
 *  • Sihirbazda yapay adım hatası → dostane mesaj + meta.diagnostics (ortam + hata detayı).
 *  • Kilitli (üretim) sistemde yapay hata → generic mesaj + Request-ID; tam detay app_logs'a;
 *    yanıttaki request_id, X-Request-Id başlığı ve log kaydıyla EŞLEŞİR; teşhis SIZMAZ.
 *  • Kilitsiz sistemde aynı hata → meta.diagnostics ile tam teşhis.
 *  • Sır kuralı: teşhis metinlerinde şifre/anahtar kalıpları maskelenir.
 */
final class DiagnosticsTest extends AuthTestCase
{
    use TempDirectory;

    // ─────────── Sihirbaz: yapay adım hatası ───────────

    public function testSihirbazAdimHatasiTanilamaIleDoner(): void
    {
        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }

        $session = new \Tests\Support\ArraySession();
        // Adım sırasını migrate'e getir; .env YOK → connection() adım içinde patlar (yapay hata).
        $state = new SetupState($session);
        $state->complete(SetupState::STEP_REQUIREMENTS);
        $state->put('database', ['host' => 'localhost', 'port' => 3306, 'name' => 'db', 'user' => 'u', 'pass' => 'p']);
        $state->complete(SetupState::STEP_DATABASE);
        $state->put('env_app_key', str_repeat('e', 64));
        $state->complete(SetupState::STEP_ENV);

        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $lock = new SetupLock(\App\Core\Connection::fromCallable(static fn (): \PDO => $pdo), $this->tempPath('storage'));

        $app = SetupAppBuilder::build($this->tempRoot(), new NullLogger(), $session, $this->clock, setupLock: $lock, appEnv: 'local');

        $stateResponse = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/setup/state', ['REMOTE_ADDR' => '203.0.113.7']),
        );
        $csrf = (string) json_decode((string) $stateResponse->getBody(), true)['data']['csrf_token'];

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/setup/migrate', ['REMOTE_ADDR' => '203.0.113.7'])
            ->withParsedBody([])
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(SetupCsrf::HEADER, $csrf);
        $response = $app->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Tanılama raporunu kopyala', $body['error']['message'], 'Dostane yönlendirme olmalı.');

        $diagnostics = $body['meta']['diagnostics'];
        self::assertSame('migrate', $diagnostics['failure']['step']);
        self::assertNotEmpty($diagnostics['failure']['exception']);
        self::assertNotEmpty($diagnostics['failure']['location']);
        self::assertSame(PHP_VERSION, $diagnostics['environment']['php_version']);
        self::assertArrayHasKey('sodium', $diagnostics['environment']['extensions']);
        self::assertArrayHasKey('openssl', $diagnostics['environment']['extensions']);
    }

    // ─────────── Uygulama: kilitli vs kilitsiz hata davranışı ───────────

    /** @return array{app: \Slim\App, context: RequestContext} */
    private function appWithDbLogging(bool $locked): array
    {
        $this->pdo->exec(
            'CREATE TABLE app_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel TEXT NOT NULL, level_name TEXT NOT NULL, level INTEGER NOT NULL,
                message TEXT NOT NULL, context TEXT NULL, extra TEXT NULL,
                request_id TEXT NULL, logged_at TEXT NOT NULL
            )',
        );

        $lock = new SetupLock($this->connection);
        if ($locked) {
            $lock->write($this->clock->now(), ['db_version' => 'test']);
        }

        $config = new Config([
            'APP_ENV' => 'production',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost', 'DB_NAME' => 'test', 'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('a1b2c3d4', 8),
            'EXTENSION_TOKEN_SALT' => str_repeat('s', 32),
            'SESSION_NAME' => 'tedarikapp_sid', 'SESSION_LIFETIME' => '120',
            'REMEMBER_ME_LIFETIME' => '43200', 'LOGIN_MAX_ATTEMPTS' => '5',
            'LOGIN_LOCKOUT_MINUTES' => '15', 'TOTP_ISSUER' => 'tedarikapp',
            'LOG_DRIVER' => 'db',
        ]);

        $context = new RequestContext();
        $logger = Logger::create($config, $this->tempRoot(), $context, $this->connection);

        $app = AppBuilder::build(
            $config,
            fn (): \PDO => $this->pdo,
            $logger,
            $this->session,
            $this->clock,
            setupLock: $lock,
            requestContext: $context,
            basePath: $this->tempRoot(),
        );

        return ['app' => $app, 'context' => $context];
    }

    private function login(\Slim\App $app): string
    {
        $user = $this->createUser();
        $post = function (string $path, array $body) use ($app): ResponseInterface {
            return $app->handle(
                $this->rawRequest('POST', $path)->withParsedBody($body)->withHeader('Content-Type', 'application/json'),
            );
        };
        $post('/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $post('/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $me = $app->handle($this->rawRequest('GET', '/api/auth/me'));

        return (string) json_decode((string) $me->getBody(), true)['data']['csrf_token'];
    }

    public function testKilitliSistemdeHataGenericArtiRequestIdVeAppLogs(): void
    {
        ['app' => $app] = $this->appWithDbLogging(locked: true);
        $csrf = $this->login($app);

        // Yapay hata: tablo düşürülür → uç 500'e düşer.
        $this->pdo->exec('DROP TABLE lists');
        $response = $app->handle(
            $this->rawRequest('GET', '/api/lists')->withHeader(Csrf::HEADER, $csrf),
        );
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(500, $response->getStatusCode());

        $requestId = $body['meta']['request_id'];
        self::assertNotSame('yok', $requestId);
        self::assertSame($response->getHeaderLine('X-Request-Id'), $requestId, 'Gövde ve başlık request_id eşleşmeli.');
        self::assertStringContainsString($requestId, $body['error']['message'], 'Kullanıcıya destek kodu gösterilmeli.');
        self::assertArrayNotHasKey('diagnostics', $body['meta'], 'ÜRETİMDE teşhis yanıtla SIZMAZ (K42).');

        // Tam detay app_logs'ta ve AYNI request_id ile bulunabilir.
        $statement = $this->pdo->prepare('SELECT message FROM app_logs WHERE request_id = :id');
        $statement->execute(['id' => $requestId]);
        $rows = $statement->fetchAll();
        self::assertNotEmpty($rows, 'Hata app_logs\'a request_id ile yazılmalı.');
    }

    public function testKilitsizSistemdeHataTamTeshisIleDoner(): void
    {
        ['app' => $app] = $this->appWithDbLogging(locked: false);
        $csrf = $this->login($app);

        $this->pdo->exec('DROP TABLE lists');
        $response = $app->handle(
            $this->rawRequest('GET', '/api/lists')->withHeader(Csrf::HEADER, $csrf),
        );
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(500, $response->getStatusCode());
        self::assertArrayHasKey('diagnostics', $body['meta'], 'Kurulumsuz sistemde sır yok → tam teşhis (K42).');
        self::assertSame(PHP_VERSION, $body['meta']['diagnostics']['environment']['php_version']);
        self::assertNotEmpty($body['meta']['diagnostics']['failure']['exception']);
    }

    // ─────────── Sır kuralı ───────────

    public function testTeshisMetinleriSirlariMaskeler(): void
    {
        $diagnostics = new SetupDiagnostics($this->tempRoot());

        $failure = $diagnostics->failure('database', new RuntimeException(
            'Bağlantı hatası: password=super-gizli-123 APP_KEY=' . str_repeat('a', 64) . ' host=localhost',
        ));

        self::assertStringNotContainsString('super-gizli-123', $failure['message']);
        self::assertStringNotContainsString(str_repeat('a', 64), $failure['message']);
        self::assertStringContainsString('[gizlendi]', $failure['message']);
        self::assertStringContainsString('host=localhost', $failure['message'], 'Sır olmayan bilgi KALMALI.');
    }
}
