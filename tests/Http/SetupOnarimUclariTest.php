<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Auth\PasswordHasher;
use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Setup\ReSetupTicket;
use App\Setup\SetupLock;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * ONARIM UÇLARI — kapı kuralları ve sahiplik (İE#20 D2-REV).
 *
 * `SetupTeshisTest` durumun DOĞRU OKUNDUĞUNU sınar; bu dosya okunan durumun
 * karşısındaki KAPILARI sınar: kim geçebilir, kim geçemez, hangi kanıtla.
 */
final class SetupOnarimUclariTest extends TestCase
{
    use TempDirectory;

    private ArraySession $session;
    private FrozenClock $clock;
    private PDO $pdo;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySession();
        $this->clock = new FrozenClock();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);

        $root = dirname(__DIR__, 2);
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }
        mkdir($this->tempPath('migrations'), 0775, true);
    }

    private function lock(): SetupLock
    {
        return new SetupLock($this->connection, $this->tempPath('storage'));
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $cookies
     */
    private function call(
        string $method,
        string $path,
        ?array $body = null,
        array $cookies = [],
        bool $csrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.9']);
        if ($body !== null) {
            $request = $request->withParsedBody($body)->withHeader('Content-Type', 'application/json');
        }
        if ($csrf) {
            $request = $request->withHeader('X-Setup-Token', $this->token());
        }
        if ($cookies !== []) {
            $request = $request->withCookieParams($cookies);
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

    private function token(): string
    {
        $mevcut = $this->session->get('setup_csrf');
        if (is_string($mevcut) && $mevcut !== '') {
            return $mevcut;
        }
        $this->session->set('setup_csrf', 'test-csrf-token');

        return 'test-csrf-token';
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** Kurulu bir sistemi taklit eder: config + kilit + yönetici hesabı. */
    private function kuruluSistem(): void
    {
        file_put_contents($this->tempPath('config.php'), "<?php\n\nreturn [\n"
            . "    'DB_HOST' => 'localhost',\n"
            . "    'DB_PORT' => '3306',\n"
            . "    'DB_NAME' => 'tedarik',\n"
            . "    'DB_USER' => 'tedarik',\n"
            . "    'DB_PASS' => 'x',\n"
            . "    'APP_KEY' => '" . str_repeat('ab', 32) . "',\n"
            . "];\n");
        $this->lock()->write(new DateTimeImmutable('2026-08-23 10:00:00'));
    }

    // ─────────────────────── kapı kuralları ───────────────────────

    public function testTeshisUcuKilitliykenDeAcik(): void
    {
        $this->kuruluSistem();

        self::assertSame(200, $this->call('GET', '/api/setup/situation')->getStatusCode());
    }

    public function testYazanUclarKilitliykenBiletsizGecemez(): void
    {
        $this->kuruluSistem();

        $response = $this->call('POST', '/api/setup/migrate', ['fresh' => false]);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testGuncellemeUcuKilitliykenBiletsizGecemez(): void
    {
        $this->kuruluSistem();

        self::assertSame(403, $this->call('POST', '/api/setup/update', [])->getStatusCode());
    }

    public function testConfigOnarimiKilitliykenAcikAmaKanitIster(): void
    {
        // Kapıdan geçer (403 DEĞİL) ama sahiplik kanıtı olmadan iş görmez.
        $this->kuruluSistem();

        $response = $this->call('POST', '/api/setup/config-repair', [
            'host' => 'localhost',
            'name' => 'x',
            'user' => 'y',
            'pass' => 'z',
        ]);

        // Bağlantı denemesi başarısız olacağı için 422; kapı 403 vermedi — istenen bu.
        self::assertNotSame(403, $response->getStatusCode());
        self::assertContains($response->getStatusCode(), [422, 500]);
    }

    // ─────────────────────── sahiplik ───────────────────────

    public function testYanlisSifreBiletVermez(): void
    {
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345');

        $response = $this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'admin',
            'email' => 'sahip@ornek.com',
            'sifre' => 'YanlisSifre12345',
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
    }

    public function testHataMesajiHesapVarligiSizdirmaz(): void
    {
        // K51: "e-posta yok" ile "şifre yanlış" AYNI cevabı almalı.
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345');

        $yanlisSifre = $this->json($this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'admin', 'email' => 'sahip@ornek.com', 'sifre' => 'kotu-sifre-123',
        ]));
        $olmayanHesap = $this->json($this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'admin', 'email' => 'yok@ornek.com', 'sifre' => 'kotu-sifre-123',
        ]));

        self::assertSame(
            $yanlisSifre['error']['message'],
            $olmayanHesap['error']['message'],
        );
    }

    public function testDogruSifreBiletCerezVerir(): void
    {
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345');

        $response = $this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'admin',
            'email' => 'sahip@ornek.com',
            'sifre' => 'DogruSifre12345',
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::assertStringContainsString(ReSetupTicket::COOKIE_NAME, $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly', $response->getHeaderLine('Set-Cookie'));
    }

    public function testDogruAppKeyDeBiletVerir(): void
    {
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345');

        $response = $this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'app_key',
            'app_key' => str_repeat('ab', 32),
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('app_key', $this->json($response)['data']['yontem']);
    }

    public function testKilitSilinmez(): void
    {
        // G2 sözü: kanıt kilidi SİLMEZ, yalnız süreli bilet verir.
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345');

        $this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'admin', 'email' => 'sahip@ornek.com', 'sifre' => 'DogruSifre12345',
        ]);

        self::assertSame(SetupLock::STATE_LOCKED, $this->lock()->status());
    }

    public function testYanlisDenemelerHizSinirinaTakilir(): void
    {
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345');

        for ($i = 0; $i < 3; $i++) {
            $this->call('POST', '/api/setup/verify-owner', [
                'yontem' => 'admin', 'email' => 'sahip@ornek.com', 'sifre' => 'yanlis-sifre-' . $i,
            ]);
        }

        $response = $this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'admin', 'email' => 'sahip@ornek.com', 'sifre' => 'DogruSifre12345',
        ]);

        self::assertSame(429, $response->getStatusCode());
    }

    public function testCsrfsizOnarimIstegiReddedilir(): void
    {
        $this->kuruluSistem();

        $response = $this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'app_key', 'app_key' => str_repeat('ab', 32),
        ], csrf: false);

        self::assertGreaterThanOrEqual(400, $response->getStatusCode());
    }

    // ─────────────────────── B14: 2FA ───────────────────────

    public function testIkiAdimliHesapKODSUZGECEMEZ(): void
    {
        // Panele girmek için iki faktör isteyip veritabanını silmek için tek
        // faktör istemek, korumayı en zayıf halkasından delmek olurdu.
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345', totp: true);

        $response = $this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'admin',
            'email' => 'sahip@ornek.com',
            'sifre' => 'DogruSifre12345',
        ]);

        self::assertSame(403, $response->getStatusCode(), 'Şifre doğru ama 2FA kodu yok');
    }

    public function testIkiAdimliOLMAYANHesapKodSORULMAZ(): void
    {
        // Olmayan bir faktörü dayatmak kullanıcıyı kilitler.
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345');

        $response = $this->call('POST', '/api/setup/verify-owner', [
            'yontem' => 'admin',
            'email' => 'sahip@ornek.com',
            'sifre' => 'DogruSifre12345',
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    }

    public function testOwnerCheckSABITYANITVERIR(): void
    {
        // v1.2.1 C5 — DAVRANIŞ DEĞİŞTİ. Bu test eskiden 2FA'lı hesap için
        // `true`, olmayan hesap için `false` bekliyordu ve yorumu "uç hesabın
        // VARLIĞINI sızdırmaz" diyordu. Varlığı sızdırmıyordu ama SAVUNMA
        // DURUMUNU sızdırıyordu: "bu yönetici 2FA kullanmıyor" bilgisi,
        // kimliksiz bir sorguyla öğrenilebiliyordu ve hedef seçme ölçütüdür.
        // Üstelik 2FA'lı hesabın `true` dönmesi, varlığı da ele veriyordu.
        //
        // Artık yanıt SABİTTİR; kod alanı hep gösterilir ve isteğe bağlıdır.
        $this->kuruluSistem();
        $this->kullaniciTablosu('sahip@ornek.com', 'DogruSifre12345', totp: true);

        $var = $this->json($this->call('POST', '/api/setup/owner-check', ['email' => 'sahip@ornek.com']));
        $yok = $this->json($this->call('POST', '/api/setup/owner-check', ['email' => 'baska@ornek.com']));

        self::assertSame($var['data'], $yok['data'], 'Var olan ve olmayan hesap AYIRT EDİLEMEMELİ.');
        self::assertTrue($var['data']['iki_adimli'], 'Sabit yanıt kod alanını GÖSTERİR.');
    }

    /** activity_log + users tabloları — throttle ve kanıt bunları okur. */
    private function kullaniciTablosu(string $email, string $sifre, bool $totp = false): void
    {
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, '
            . 'password_hash TEXT, totp_secret TEXT, created_at TEXT, updated_at TEXT)');
        // Kolon adları ActivityLog'un INSERT'iyle BİREBİR aynı olmalı: eksik kolon
        // sessizce yutulur (kayıt try/catch içinde) ve throttle hiç çalışmaz —
        // yani hız sınırı testi "geçiyor" görünürken korumasız kalırdık.
        $this->pdo->exec('CREATE TABLE activity_log (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'entity_type TEXT, entity_id INTEGER, action TEXT, detail TEXT, ip TEXT, '
            . 'actor_type TEXT, actor_id INTEGER, request_id TEXT, user_agent TEXT, created_at TEXT)');

        $statement = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, totp_secret, created_at, updated_at) '
            . "VALUES (:email, :hash, :totp, '2026-08-23 10:00:00', '2026-08-23 10:00:00')",
        );
        $statement->execute([
            'email' => $email,
            'hash' => (new PasswordHasher())->hash($sifre),
            // Gerçek bir şifreli secret gerekmiyor: kapı "secret VAR MI" diye bakar
            // ve varsa kod ister. Kodun doğruluğu TotpServiceTest'in işidir.
            'totp' => $totp ? 'sifreli-secret' : null,
        ]);
    }
}
