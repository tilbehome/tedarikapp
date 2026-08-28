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
use Psr\Http\Message\ServerRequestInterface;
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

    /** Testin sahte indirici kullanması için (K47) — null ise gerçek cURL indiricisi. */
    protected ?\App\Services\MediaFetcher $mediaFetcher = null;

    /** Testlerin sahte çevirmen kullanması için (İE#13 C1) — null ise gerçek cURL sağlayıcısı. */
    protected ?\App\Services\Translation\TranslationClient $translationClient = null;

    /** rc8/E1: taşıma başarısızlığını taklit eden süitler kendi servisini verir. */
    protected ?\App\Services\MediaService $mediaService = null;

    /** @return \Slim\App<\Psr\Container\ContainerInterface|null> */
    /**
     * @param \Psr\Log\LoggerInterface|null $logger İE#17 G6: teşhis logunu sınayan
     *                                               testler kendi kayıtçısını verir
     */
    protected function app(?\Psr\Log\LoggerInterface $logger = null): \Slim\App
    {
        return AppBuilder::build(
            $this->config(),
            fn (): PDO => $this->pdo,
            $logger ?? new NullLogger(),
            $this->session,
            $this->clock,
            null,
            null,
            null,
            $this->mediaFetcher,
            $this->translationClient,
            $this->mediaService,
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

    /** Gövdesiz, başlıksız ham istek — middleware testleri kendi gövdesini ekler. */
    protected function rawRequest(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7']);
    }

    /**
    /**
     * İE#18 G6 (K62) — PAYLAŞIM SAYFASI ÇEREZİ.
     *
     * Erişim anahtarı kapısı varsayılan AÇIK olduğu için paylaşım sayfasını
     * sınayan testler önce anahtarı doğrular. Kapıyı kapatmak da mümkündü ama o
     * zaman CANLIDAKİ varsayılan yol test dışı kalırdı; bu yardımcı gerçek yolu
     * kullanır: anahtarı panelden okur, doğrular, dönen çerezi verir.
     *
     * @return array<string, string> `call()` metoduna verilecek çerez dizisi
     */
    protected function paylasimCerezi(string $token, int $listId, string $csrf): array
    {
        $anahtar = (string) $this->json(
            $this->call('GET', '/api/lists/' . $listId . '/share-key', null, [\App\Middleware\Csrf::HEADER => $csrf]),
        )['data']['key'];

        $yanit = $this->call('POST', '/liste/' . $token . '/anahtar', ['anahtar' => $anahtar]);
        preg_match(
            '/' . \App\Services\Share\ShareKeyService::CEREZ_ADI . '=([^;]+)/',
            $yanit->getHeaderLine('Set-Cookie'),
            $eslesme,
        );

        return [\App\Services\Share\ShareKeyService::CEREZ_ADI => (string) ($eslesme[1] ?? '')];
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
        $request = $this->rawRequest($method, $path);

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
        $this->createDataSchema();
    }

    /** İE#6 tabloları — MySQL DDL'inin SQLite karşılığı (aynı sütun adları ve NULL kuralları). */
    private function createDataSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE settings (
                key TEXT NOT NULL PRIMARY KEY,
                value TEXT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE rate_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                currency TEXT NOT NULL,
                rate TEXT NOT NULL,
                set_at TEXT NOT NULL
            )',
        );
        // İE#22 A1: kur snapshot omurgası — aktif satır `superseded_at IS NULL`.
        $this->pdo->exec(
            'CREATE TABLE rate_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                currency TEXT NOT NULL,
                rate TEXT NOT NULL,
                source TEXT NOT NULL DEFAULT "elle",
                effective_from TEXT NOT NULL,
                superseded_at TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT NOT NULL,
                UNIQUE (currency, effective_from)
            )',
        );
        // V3-B A1: bildirim merkezi. Şema `migrations/0035_bildirimler.php` ile
        // AYNI olmalı — UNIQUE üçlüsü birleştirmenin motorudur ve testte
        // eksik olursa "birleşme çalışıyor" yanılsaması doğar.
        $this->pdo->exec(
            'CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                olay_kodu TEXT NOT NULL,
                onem TEXT NOT NULL DEFAULT "bilgi",
                grup TEXT NOT NULL,
                baslik TEXT NOT NULL,
                govde TEXT NOT NULL,
                eylem_linki TEXT NULL,
                kullanici_id INTEGER NULL,
                grup_anahtari TEXT NOT NULL,
                pencere_baslangic TEXT NOT NULL,
                birlesen_sayi INTEGER NOT NULL DEFAULT 1,
                audit_id INTEGER NULL,
                okundu_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (olay_kodu, grup_anahtari, pencere_baslangic)
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                sort INTEGER NOT NULL DEFAULT 0
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                period TEXT NULL,
                supplier_name TEXT NULL,
                status TEXT NOT NULL DEFAULT \'draft\',
                note TEXT NULL,
                visibility TEXT NOT NULL DEFAULT \'active\',
                yuan_rate TEXT NOT NULL,
                usd_rate TEXT NOT NULL,
                rate_locked_at TEXT NULL,
                revision INTEGER NOT NULL DEFAULT 0,
                share_token_hash TEXT NULL,
                share_token_prefix TEXT NULL,
                share_expires_at TEXT NULL,
                -- İE#18 G6 (K62): erişim anahtarı kapısı.
                share_key_hash TEXT NULL,
                share_key_plain TEXT NULL,
                share_key_enabled INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                archived_at TEXT NULL,
                deleted_at TEXT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                sort_no INTEGER NOT NULL DEFAULT 0,
                category_id INTEGER NULL,
                platform TEXT NULL,
                external_id TEXT NULL,
                name TEXT NOT NULL,
                name_original TEXT NULL,
                name_elle INTEGER NOT NULL DEFAULT 0,
                source_lang TEXT NULL,
                detail TEXT NULL,
                url TEXT NULL,
                vendor_name TEXT NULL,
                vendor_url TEXT NULL,
                sku_selection TEXT NULL,
                sku_matrix TEXT NULL,
                main_image TEXT NULL,
                main_image_source TEXT NULL,
                video_url TEXT NULL,
                qty INTEGER NOT NULL DEFAULT 1,
                price_yuan TEXT NOT NULL DEFAULT \'0\',
                price_ddp_usd TEXT NOT NULL DEFAULT \'0\',
                price_target_try TEXT NULL,
                units_per_carton INTEGER NULL,
                raw_attributes TEXT NULL,
                country_of_origin TEXT NULL,
                country_of_dispatch TEXT NULL,
                tracking_no TEXT NULL,
                status TEXT NOT NULL DEFAULT \'to_order\',
                note TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                deleted_at TEXT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE product_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                path TEXT NOT NULL,
                sort INTEGER NOT NULL DEFAULT 0,
                storage_mode TEXT NOT NULL DEFAULT \'local\',
                source_url TEXT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE inbox_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                capture_id TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT \'pending\',
                platform TEXT NOT NULL,
                external_id TEXT NULL,
                name TEXT NULL,
                price_yuan TEXT NULL,
                image_url TEXT NULL,
                url TEXT NULL,
                payload_json TEXT NOT NULL,
                error_note TEXT NULL,
                assigned_product_id INTEGER NULL,
                assigned_at TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE translation_cache (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_hash TEXT NOT NULL UNIQUE,
                source_lang TEXT NOT NULL DEFAULT \'zh\',
                target_lang TEXT NOT NULL DEFAULT \'tr\',
                source_text TEXT NOT NULL,
                suggested_text TEXT NOT NULL,
                provider TEXT NOT NULL,
                surum TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE product_status_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                from_status TEXT NULL,
                to_status TEXT NOT NULL,
                actor_type TEXT NOT NULL DEFAULT \'admin\',
                actor_id INTEGER NULL,
                changed_at TEXT NOT NULL,
                request_id TEXT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE exports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                format TEXT NOT NULL,
                snapshot_json TEXT NULL,
                sha256 TEXT NULL,
                file_size INTEGER NULL,
                status TEXT NOT NULL DEFAULT \'ready\',
                list_revision INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )',
        );

        $this->ilanSemasi();
    }

    /**
     * İLAN TARAFI GERÇEK MİGRATION'LARDAN KURULUR (İE#21 B3 dersi).
     *
     * Taban şema elle yazılmıştır ve C2'den (ürün≠ilan) önceki dünyayı yansıtır.
     * Yakalama artık ilan kaydı da açtığı için `platforms`/`listings` tabloları
     * HER HTTP testinde bulunmalı: yoksa testler üretimde olmayan bir dünyayı
     * sınar ve "no such table: platforms" gibi hatalar ancak canlıda görünürdü.
     *
     * Elle kopyalamak yerine migration dosyaları koşulur — şema tek kaynaktan
     * gelir ve ileride bir kolon eklendiğinde testler kendiliğinden izler.
     */
    private function ilanSemasi(): void
    {
        foreach ([
            '0022_create_platforms',
            '0023_create_listings',
            '0025_add_listings_skor',
            '0026_arama_ve_kalite',
            '0029_ilan_satis_toplam',
            '0030_kesif_havuzu',
            // D9: kuyruk tabloları da HTTP testlerinde bulunmalı — "toplu çevir"
            // düğmesi iş yazar; iş yazılamıyorsa uç test edilmiş sayılmaz.
            '0024_create_jobs',
            '0028_kuyruk_sertlestirme',
        ] as $ad) {
            /** @var \App\Core\Migration $migration */
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $ad . '.php';
            $migration->up($this->pdo);
        }
    }
}
