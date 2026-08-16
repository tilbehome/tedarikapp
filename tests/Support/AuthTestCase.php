<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Auth\AuthServices;
use App\Auth\PasswordHasher;
use App\Auth\RecoveryCodeService;
use App\Auth\TotpService;
use App\Auth\UserRepository;
use App\Core\AppBuilder;
use App\Core\Config;
use App\Core\Connection;
use App\Core\Encrypter;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Auth testlerinin ortak zemini.
 *
 * Şema SQLite'ta migrations/0001 + 0002'nin auth kısmını birebir yansıtır (aynı sütun
 * adları ve NULL kuralları); migration dosyaları MySQL'e özgü DDL içerdiğinden
 * burada tekrar kurulur. Depolar yalnızca taşınabilir SQL kullandığı için ikisi de aynı kodu koşar.
 */
abstract class AuthTestCase extends TestCase
{
    protected PDO $pdo;
    protected ArraySession $session;
    protected FrozenClock $clock;
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->createSchema();

        $this->session = new ArraySession();
        $this->clock = new FrozenClock();
        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);
    }

    protected function config(): Config
    {
        return new Config([
            'APP_ENV' => 'production',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('a1b2c3d4', 8),
            'EXTENSION_TOKEN_SALT' => str_repeat('s', 32),
            'SESSION_NAME' => 'tedarikapp_sid',
            'SESSION_LIFETIME' => '120',
            'REMEMBER_ME_LIFETIME' => '43200',
            'LOGIN_MAX_ATTEMPTS' => '5',
            'LOGIN_LOCKOUT_MINUTES' => '15',
            'TOTP_ISSUER' => 'tedarikapp',
        ]);
    }

    protected function services(): AuthServices
    {
        return new AuthServices($this->config(), $this->connection, $this->session, $this->clock, new NullLogger());
    }

    /** @return \Slim\App<\Psr\Container\ContainerInterface|null> */
    protected function app(): \Slim\App
    {
        return AppBuilder::build(
            $this->config(),
            fn (): PDO => $this->pdo,
            new NullLogger(),
            $this->session,
            $this->clock,
        );
    }

    /**
     * Test kullanıcısı oluşturur ve düz TOTP secret'ı ile kurtarma kodlarını döndürür.
     *
     * @return array{id: int, secret: string, codes: list<string>}
     */
    protected function createUser(string $email = 'admin@tedarikapp.test', string $password = 'cok-gizli-sifre'): array
    {
        $config = $this->config();
        $hasher = new PasswordHasher();
        $totp = new TotpService($config, new Encrypter($config), $this->clock);
        $users = new UserRepository($this->connection);
        $recovery = new RecoveryCodeService($this->connection, $hasher);

        $secret = $totp->createSecret();
        $id = $users->create($email, $hasher->hash($password), $totp->encryptSecret($secret), $this->clock->now());

        $codes = $recovery->generate();
        $recovery->replaceForUser($id, $codes);

        return ['id' => $id, 'secret' => $secret, 'codes' => $codes];
    }

    /** Verilen secret için o anki geçerli TOTP kodunu üretir. */
    protected function totpCodeFor(string $secret): string
    {
        return (new TwoFactorAuth(new BaconQrCodeProvider(format: 'svg'), 'tedarikapp'))
            ->getCode($secret, $this->clock->now()->getTimestamp());
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    protected function call(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = [],
        array $cookies = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7']);

        if ($body !== null) {
            $request = $request->withParsedBody($body)->withHeader('Content-Type', 'application/json');
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($cookies !== []) {
            $request = $request->withCookieParams($cookies);
        }

        return $this->app()->handle($request);
    }

    /** @return array<string, mixed> */
    protected function json(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** Yanıttaki `Set-Cookie` başlıkları arasından adı verilen çerezin değerini çeker. */
    protected function cookieValue(ResponseInterface $response, string $name): ?string
    {
        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (!str_starts_with($header, $name . '=')) {
                continue;
            }
            $value = substr($header, strlen($name) + 1);
            $end = strpos($value, ';');

            return rawurldecode($end === false ? $value : substr($value, 0, $end));
        }

        return null;
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                totp_secret TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE recovery_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                code_hash TEXT NOT NULL,
                used_at TEXT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE remember_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                selector TEXT NOT NULL UNIQUE,
                token_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT NOT NULL,
                entity_id INTEGER NULL,
                action TEXT NOT NULL,
                detail TEXT NULL,
                ip TEXT NULL,
                actor_type TEXT NOT NULL DEFAULT \'admin\',
                actor_id INTEGER NULL,
                request_id TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
    }
}
