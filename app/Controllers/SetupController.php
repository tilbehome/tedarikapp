<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\PasswordHasher;
use App\Auth\RecoveryCodeService;
use App\Auth\TotpService;
use App\Auth\UserRepository;
use App\Core\Clock;
use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Encrypter;
use App\Core\Migrator;
use App\Core\Response;
use App\Models\SettingsRepository;
use App\Services\ActivityLog;
use App\Services\CurlMediaFetcher;
use App\Services\MediaService;
use App\Services\UrlGuard;
use App\Setup\DatabaseProbe;
use App\Setup\EnvWriter;
use App\Setup\QrCodeSvg;
use App\Setup\RequirementChecker;
use App\Setup\SetupDiagnostics;
use App\Setup\SetupLock;
use App\Setup\SetupState;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

/**
 * Kurulum sihirbazı uçları (İE#5 §11).
 *
 * Akış: gereksinimler → DB → .env → migration → admin + 2FA → kurtarma kodları → kilit.
 * Her uç kendi adımının sırası gelmeden çalışmaz (SetupState).
 *
 * GÜVENLİK: DB şifresi ve kurtarma kodları hiçbir log satırına yazılmaz; kurtarma
 * kodları yalnızca üretildikleri yanıtta BİR KEZ döner.
 */
final class SetupController
{
    private const string DATA_DB = 'database';
    private const string DATA_ADMIN = 'pending_admin';
    private const string DATA_ENV_KEY = 'env_app_key';
    private const string DATA_APP_URL = 'env_app_url';

    private ?Config $config = null;
    private ?Connection $connection = null;

    public function __construct(
        private readonly string $basePath,
        private readonly SetupState $state,
        private readonly SetupLock $lock,
        private readonly Clock $clock,
        private readonly ?EnvWriter $envWriter = null,
        private readonly string $appEnv = 'production',
    ) {
    }

    private function envWriter(): EnvWriter
    {
        return $this->envWriter ?? new EnvWriter($this->basePath);
    }

    /**
     * GET /api/setup/diagnostics — ortam özeti (K42).
     * "TANILAMA RAPORUNU KOPYALA" düğmesinin veri kaynağı; sır içermez.
     */
    public function diagnostics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Response::success($response, (new SetupDiagnostics($this->basePath))->environment($this->connectionIfConfigured()));
    }

    /**
     * Başarısız adımın yanıtına eklenen teşhis bloğu (K42): ortam + hata detayı.
     *
     * @return array<string, mixed>
     */
    private function diagnosticsFor(string $step, Throwable $exception): array
    {
        $diagnostics = new SetupDiagnostics($this->basePath);

        return [
            'environment' => $diagnostics->environment($this->connectionIfConfigured()),
            'failure' => $diagnostics->failure($step, $exception),
        ];
    }

    /** `.env` henüz yokken bağlantı KURULMAYA ÇALIŞILMAZ — teşhis üretimi hata üretemez. */
    private function connectionIfConfigured(): ?Connection
    {
        if (!$this->envWriter()->exists()) {
            return null;
        }

        try {
            return $this->connection();
        } catch (Throwable) {
            return null;
        }
    }

    /** GET /api/setup/state — sihirbaz açılışında adım + CSRF token. */
    public function state(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Response::success($response, [
            'step' => $this->state->currentStep(),
            'steps' => SetupState::ORDER,
            'csrf_token' => $this->state->csrfToken(),
            'env_exists' => $this->envWriter()->exists(),
        ]);
    }

    /** GET /api/setup/requirements — PHP, eklentiler, yazılabilirlik, HTTPS. */
    public function requirements(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = (new RequirementChecker($this->basePath, $this->appEnv))->check($request);
        if ($result['ok']) {
            $this->state->complete(SetupState::STEP_REQUIREMENTS);
        }

        return Response::success($response, $result + ['step' => $this->state->currentStep()]);
    }

    /** POST /api/setup/database — bağlantı testi + SELECT VERSION(). */
    public function database(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->state->canRun(SetupState::STEP_DATABASE)) {
            return $this->outOfOrder($response, 'Önce gereksinim denetimini tamamlayın.');
        }

        $body = $this->body($request);
        $host = $this->str($body, 'host');
        $port = $this->str($body, 'port');
        $name = $this->str($body, 'name');
        $user = $this->str($body, 'user');
        $pass = is_string($body['pass'] ?? null) ? $body['pass'] : '';

        $fields = [];
        if ($host === '') {
            $fields['host'] = 'Sunucu adı zorunludur (paylaşımlı hostingde genelde "localhost").';
        }
        if ($name === '') {
            $fields['name'] = 'Veritabanı adı zorunludur.';
        }
        if ($user === '') {
            $fields['user'] = 'Veritabanı kullanıcı adı zorunludur.';
        }
        if ($port !== '' && preg_match('/^\d+$/', $port) !== 1) {
            $fields['port'] = 'Port yalnızca rakamlardan oluşmalı.';
        }
        if ($fields !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $fields);
        }

        $portNumber = $port === '' ? 3306 : (int) $port;
        $probe = (new DatabaseProbe())->probe($host, $portNumber, $name, $user, $pass);

        if ($probe['ok'] !== true) {
            return Response::error($response, 'VALIDATION', $probe['error'] ?? 'Bağlantı kurulamadı.', 422, [
                'host' => $probe['error'] ?? 'Bağlantı kurulamadı.',
            ]);
        }

        $this->state->put(self::DATA_DB, [
            'host' => $host,
            'port' => $portNumber,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
        ]);
        $this->state->complete(SetupState::STEP_DATABASE);

        return Response::success($response, [
            'version' => $probe['version'] ?? 'bilinmiyor',
            'charset' => $probe['charset'] ?? 'bilinmiyor',
            'step' => $this->state->currentStep(),
        ]);
    }

    /** POST /api/setup/env — .env üretimi (APP_KEY ve token tuzu kriptografik). */
    public function env(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->state->canRun(SetupState::STEP_ENV)) {
            return $this->outOfOrder($response, 'Önce veritabanı bağlantısını doğrulayın.');
        }

        /** @var array{host: string, port: int, name: string, user: string, pass: string}|null $database */
        $database = $this->state->get(self::DATA_DB);
        if ($database === null) {
            return $this->outOfOrder($response, 'Veritabanı bilgileri oturumda bulunamadı; adımı tekrarlayın.');
        }

        // K37 §A2: mevcut .env'in üzerine HTTP akışından ASLA yazılmaz/yeniden üretilmez.
        if ($this->envWriter()->exists()) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'env' => '.env dosyası sunucuda zaten var; sihirbaz üzerine yazmaz (K37). '
                    . 'Bu dosyayı siz kaydettiyseniz doğrulama adımıyla devam edin; '
                    . 'yeniden üretmek için dosyayı sunucudan elle silin.',
            ]);
        }

        $appUrl = $this->str($this->body($request), 'app_url');
        if ($appUrl === '') {
            $appUrl = $this->guessAppUrl($request);
        }
        if (preg_match('#^https?://[^\s/]+#i', $appUrl) !== 1) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'app_url' => 'Panel adresi http:// veya https:// ile başlamalı.',
            ]);
        }

        $writer = $this->envWriter();
        $appUrl = rtrim($appUrl, '/');

        // K33: paylaşımlı sunucuda PHP `nobody` ile çalışır ve uygulama kökü YAZILAMAZ.
        // Bu durumda içerik ekranda gösterilir; kullanıcı Dosya Yöneticisi'nden kaydeder.
        if (!$writer->canWrite()) {
            try {
                $generated = $writer->generate($appUrl, $database);
            } catch (RuntimeException $e) {
                return Response::error($response, 'SERVER_ERROR', $e->getMessage(), 500);
            }

            // Beklenen APP_KEY oturumda tutulur: doğrulama adımı dosyanın BİZİM
            // ürettiğimiz içerik olduğunu bununla anlar.
            $this->state->put(self::DATA_ENV_KEY, $generated['app_key']);
            $this->state->put(self::DATA_APP_URL, $appUrl);

            return Response::success($response, [
                'manual' => true,
                'app_url' => $appUrl,
                'filename' => '.env',
                'target_path' => $this->basePath . '/.env',
                'content' => $generated['content'],
                'instructions' => 'Uygulama klasörü yazılabilir olmadığı için .env dosyasını sihirbaz '
                    . 'oluşturamadı. Yukarıdaki içeriği kopyalayın, cPanel > Dosya Yöneticisi ile '
                    . 'uygulama kökünde ".env" adıyla kaydedin, sonra "Kaydettim" deyin.',
                'step' => $this->state->currentStep(),
            ]);
        }

        try {
            $appKey = $writer->write($appUrl, $database);
        } catch (RuntimeException $e) {
            return Response::error($response, 'SERVER_ERROR', $e->getMessage(), 500);
        }

        $this->state->put(self::DATA_ENV_KEY, $appKey);
        $this->state->put(self::DATA_APP_URL, $appUrl);
        $this->state->complete(SetupState::STEP_ENV);

        return Response::success($response, [
            'manual' => false,
            'app_url' => $appUrl,
            'step' => $this->state->currentStep(),
        ]);
    }

    /** POST /api/setup/env/verify — elle kaydedilen .env doğrulanır (K33). */
    public function verifyEnv(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->state->canRun(SetupState::STEP_ENV)) {
            return $this->outOfOrder($response, 'Önce veritabanı bağlantısını doğrulayın.');
        }

        $expected = $this->state->get(self::DATA_ENV_KEY);
        if (!is_string($expected) || $expected === '') {
            return $this->outOfOrder($response, 'Önce ayar dosyasını üretin.');
        }

        $writer = $this->envWriter();
        if (!$writer->exists()) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'env' => '.env dosyası bulunamadı. Dosyayı uygulama kökünde ".env" adıyla '
                    . 'kaydettiğinizden emin olun (adın başındaki nokta dahil).',
            ]);
        }
        if (!$writer->verify($expected)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'env' => '.env bulundu ama içeriği beklenenle uyuşmuyor. İçeriğin tamamını '
                    . 'eksiksiz kopyaladığınızdan emin olup tekrar deneyin.',
            ]);
        }

        $this->state->complete(SetupState::STEP_ENV);

        return Response::success($response, ['verified' => true, 'step' => $this->state->currentStep()]);
    }

    /** POST /api/setup/migrate — bekleyen migration'ları koşar, süreleriyle döner. */
    public function migrate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->state->canRun(SetupState::STEP_MIGRATE)) {
            return $this->outOfOrder($response, 'Önce .env dosyasını oluşturun.');
        }

        try {
            $migrator = new Migrator($this->connection()->pdo(), $this->basePath . '/migrations');
            $applied = $migrator->run();
        } catch (Throwable $e) {
            // K42: dostane mesaj + kopyalanabilir teşhis — "500 verdi" ile kalınmaz.
            return Response::error(
                $response,
                'SERVER_ERROR',
                'Veritabanı tabloları oluşturulamadı. Teknik detay aşağıda; '
                . '"Tanılama raporunu kopyala" ile destek için hazır rapor alabilirsiniz.',
                500,
                [],
                ['diagnostics' => $this->diagnosticsFor('migrate', $e)],
            );
        }

        $this->state->complete(SetupState::STEP_MIGRATE);

        return Response::success($response, [
            'applied' => $applied,
            'migrations' => $this->migrationReport(),
            'step' => $this->state->currentStep(),
        ]);
    }

    /** POST /api/setup/admin — yönetici bilgileri + TOTP QR (kullanıcı HENÜZ oluşturulmaz). */
    public function admin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->state->canRun(SetupState::STEP_ADMIN)) {
            return $this->outOfOrder($response, 'Önce veritabanı tablolarını oluşturun.');
        }

        $body = $this->body($request);
        $email = strtolower($this->str($body, 'email'));
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';

        $fields = [];
        if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fields['email'] = 'Geçerli bir e-posta adresi girin (en çok 190 karakter).';
        }
        foreach ($this->passwordProblems($password) as $problem) {
            $fields['password'] = $problem;

            break;
        }
        if ($fields !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $fields);
        }

        $users = new UserRepository($this->connection());
        if ($users->findByEmail($email) !== null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'email' => 'Bu e-posta ile bir kullanıcı zaten var.',
            ]);
        }

        $totp = $this->totp();
        $secret = $totp->createSecret();

        // Kullanıcı DB'ye HENÜZ yazılmaz: TOTP kodu doğrulanmadan hesap açılırsa
        // authenticator kurulumu başarısız olduğunda giriş yapılamayan bir hesap kalır.
        $this->state->put(self::DATA_ADMIN, [
            'email' => $email,
            'password_hash' => (new PasswordHasher())->hash($password),
            'secret' => $secret,
        ]);

        $uri = $totp->provisioningUri($email, $secret);

        return Response::success($response, [
            'email' => $email,
            'otpauth_uri' => $uri,
            'qr_svg' => QrCodeSvg::dataUri($uri),
            'manual_key' => $secret,
            'step' => $this->state->currentStep(),
        ]);
    }

    /** POST /api/setup/admin/verify — kod doğruysa kullanıcı oluşur; kurtarma kodları BİR KEZ döner. */
    public function verifyAdmin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->state->canRun(SetupState::STEP_ADMIN)) {
            return $this->outOfOrder($response, 'Önce yönetici bilgilerini girin.');
        }

        /** @var array{email: string, password_hash: string, secret: string}|null $pending */
        $pending = $this->state->get(self::DATA_ADMIN);
        if ($pending === null) {
            return $this->outOfOrder($response, 'Yönetici bilgileri oturumda bulunamadı; adımı tekrarlayın.');
        }

        $code = $this->str($this->body($request), 'code');
        $totp = $this->totp();

        if (!$totp->verify($totp->encryptSecret($pending['secret']), $code)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'code' => 'Kod doğrulanamadı. Authenticator uygulamanızdaki güncel 6 haneli kodu girin.',
            ]);
        }

        $now = $this->clock->now();
        $connection = $this->connection();
        $hasher = new PasswordHasher();

        $userId = (new UserRepository($connection))->create(
            $pending['email'],
            $pending['password_hash'],
            $totp->encryptSecret($pending['secret']),
            $now,
        );

        $recovery = new RecoveryCodeService($connection, $hasher);
        $codes = $recovery->generate();
        $recovery->replaceForUser($userId, $codes);

        (new ActivityLog($connection))->recordAuth(ActivityLog::USER_CREATED, $pending['email'], 'setup', $now, $userId);

        // Bekleyen kayıt oturumdan SİLİNİR: şifre hash'i ve secret gereğinden uzun yaşamaz.
        $this->state->pull(self::DATA_ADMIN);
        $this->state->put('admin_email', $pending['email']);
        $this->state->complete(SetupState::STEP_ADMIN);

        return Response::success($response, [
            'user' => ['id' => $userId, 'email' => $pending['email']],
            'recovery_codes' => $codes,
            'step' => $this->state->currentStep(),
        ]);
    }

    /** POST /api/setup/finish — kilidi yazar; sihirbaz kalıcı olarak kapanır. */
    public function finish(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->state->canRun(SetupState::STEP_RECOVERY)) {
            return $this->outOfOrder($response, 'Önce yönetici hesabını ve 2FA kurulumunu tamamlayın.');
        }

        if (($this->body($request)['codes_saved'] ?? false) !== true) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'codes_saved' => 'Kurtarma kodlarını kaydettiğinizi onaylamadan kurulum tamamlanamaz. '
                    . 'Kodlar bir daha GÖSTERİLMEZ.',
            ]);
        }

        $database = $this->state->get(self::DATA_DB);
        $version = is_array($database) ? ($this->probeVersion()) : null;

        // K33: medya modu kurulum anında ölçülür ve ayara yazılır — panel rozeti bunu okur.
        $mediaMode = $this->rememberMediaMode();

        try {
            $this->lock->write($this->clock->now(), array_filter([
                'db_version' => $version,
                'php_version' => PHP_VERSION,
                'media_mode' => $mediaMode,
                'admin_email' => is_string($this->state->get('admin_email')) ? $this->state->get('admin_email') : null,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (RuntimeException $e) {
            return Response::error($response, 'SERVER_ERROR', $e->getMessage(), 500, [], [
                'diagnostics' => $this->diagnosticsFor('finish', $e),
            ]);
        }

        $this->state->complete(SetupState::STEP_RECOVERY);

        $summary = [
            'php_version' => PHP_VERSION,
            'db_version' => $version,
            'media_mode' => $mediaMode,
            'migrations' => $this->migrationReport(),
            'panel_url' => '/',
            'step' => SetupState::STEP_DONE,
        ];

        // Oturumdaki DB şifresi dahil tüm kurulum verisi silinir.
        $this->state->reset();

        return Response::success($response, $summary);
    }

    // ─────────────── yardımcılar ───────────────

    /** @return list<string> */
    private function passwordProblems(string $password): array
    {
        $problems = [];
        if (strlen($password) < PasswordHasher::MIN_LENGTH) {
            $problems[] = sprintf('Şifre en az %d karakter olmalı (docs/04 §2d).', PasswordHasher::MIN_LENGTH);

            return $problems;
        }
        // "aaaaaaaaaa" / "1111111111" uzunluk şartını geçer ama tahmin edilebilir.
        if (count(array_unique(str_split($password))) < 6) {
            $problems[] = 'Şifre en az 6 farklı karakter içermeli (tekrar eden karakterler yeterli değil).';
        }

        return $problems;
    }

    /** @return list<array{name: string, execution_ms: int}> */
    private function migrationReport(): array
    {
        try {
            $statement = $this->connection()->pdo()->query('SELECT name, execution_ms FROM migrations ORDER BY id');
        } catch (Throwable) {
            return [];
        }
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        $report = [];
        foreach ($rows as $row) {
            $report[] = ['name' => (string) $row['name'], 'execution_ms' => (int) $row['execution_ms']];
        }

        return $report;
    }

    /**
     * `public/media/` yazılabilir mi ölçülür ve mod ayara yazılır (K33).
     * Yazılamıyorsa hotlink moduna düşülür: görseller indirilmez, orijinal URL saklanır.
     */
    private function rememberMediaMode(): ?string
    {
        try {
            $config = $this->config();
            $hosts = array_map('trim', explode(',', $config->get('MEDIA_ALLOWED_HOSTS', 'alicdn.com,1688.com')));
            $guard = new UrlGuard($hosts);
            $media = new MediaService(
                $this->basePath,
                $guard,
                new CurlMediaFetcher($guard, $config->getPositiveInt('MEDIA_DOWNLOAD_TIMEOUT', 25)),
                new SettingsRepository($this->connection()),
                $config->getPositiveInt('MEDIA_MAX_MB', 8) * 1024 * 1024,
                $config->get('MEDIA_PATH', 'public/media'),
            );

            $mode = $media->detectMode();
            $media->rememberMode($mode);

            return $mode;
        } catch (Throwable) {
            // Mod belirlenemezse kurulum durmaz; panel /api/system/status ile yeniden ölçer.
            return null;
        }
    }

    private function probeVersion(): ?string
    {
        try {
            $statement = $this->connection()->pdo()->query('SELECT VERSION() AS version');
            $row = $statement === false ? false : $statement->fetch();

            return is_array($row) ? (string) $row['version'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function config(): Config
    {
        return $this->config ??= Config::load($this->basePath);
    }

    private function connection(): Connection
    {
        return $this->connection ??= Connection::fromCallable(fn (): PDO => Database::connect($this->config()));
    }

    private function totp(): TotpService
    {
        $config = $this->config();

        return new TotpService($config, new Encrypter($config), $this->clock);
    }

    private function guessAppUrl(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $port = $uri->getPort();
        $authority = $uri->getHost() . ($port === null || in_array($port, [80, 443], true) ? '' : ':' . $port);

        return $uri->getScheme() . '://' . $authority;
    }

    private function outOfOrder(ResponseInterface $response, string $message): ResponseInterface
    {
        return Response::error($response, 'STATE_TRANSITION', $message, 422, [], [
            'current_step' => $this->state->currentStep(),
        ]);
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /** @param array<string, mixed> $body */
    private function str(array $body, string $key): string
    {
        $value = $body[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}
