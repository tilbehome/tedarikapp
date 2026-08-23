<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Config;
use App\Core\Connection;
use App\Core\Cookie;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Response;
use App\Models\SettingsRepository;
use App\Setup\ConfigWriter;
use App\Setup\DatabaseProbe;
use App\Setup\ReSetupTicket;
use App\Setup\SetupLock;
use App\Setup\SetupSituation;
use App\Setup\SetupState;
use App\Setup\UnlockGate;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * TEŞHİS + ONARIM MERKEZİ (İE#20 D2-REV).
 *
 * `SetupController` yedi adımlık NORMAL akışı yürütür. Bu sınıf onun yapamadığını
 * yapar: sistemin bozuk hâllerini teşhis eder ve her birini sihirbazın İÇİNDEN
 * çözer. Ayrı dosya olması bilinçlidir — normal akış ile onarım akışı farklı
 * güvenlik varsayımlarına dayanır (onarım kilitli sistemde de çalışabilmeli,
 * bu yüzden her ucu kendi sahiplik kanıtını kendisi ister).
 *
 * DEĞİŞMEZ KURALLAR:
 *  • Hiçbir uç dosya İNDİRMEZ. Eksik dosya teşhis edilir, çözümü kullanıcı
 *    File Manager ile yapar. Uzaktan kod çekmek bir güncelleme mekanizması değil,
 *    bir arka kapıdır.
 *  • Yıkıcı her yol yazarak-onay (`SIFIRLA`) + sahiplik kanıtı ister.
 *  • Sahiplik denemeleri hız sınırlıdır ve SABİT mesaj döner (K51): hangi kısmın
 *    yanlış olduğu söylenmez.
 *  • Yanıtlarda şifre, APP_KEY, token BULUNMAZ.
 */
final class SetupRepairController
{
    public function __construct(
        private readonly string $basePath,
        private readonly SetupState $state,
        private readonly SetupLock $lock,
        private readonly Clock $clock,
        private readonly ConfigWriter $configWriter,
    ) {
    }

    // ─────────────────────────── TEŞHİS ───────────────────────────

    /**
     * GET /api/setup/situation — tam teşhis. Her ekrandaki "Teşhisi yeniden çalıştır"
     * düğmesi de buraya gelir. Salt okunur; kilitliyken ve DB düşmüşken de çalışır.
     */
    public function situation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $teshis = (new SetupSituation($this->basePath, $this->lock, $this->configWriter))->analyze();

        // `state` ucundaki K45 davranışının aynısı: yapılandırma zaten varsa ilk üç
        // adım geçilmiş sayılır. Teşhis ekranı "kaldığın yerden devam" derken doğru
        // adımı göstersin diye burada da uygulanır — iki uç aynı gerçeği söylemeli.
        if ($this->configWriter->configured() && $this->state->currentStep() === SetupState::STEP_REQUIREMENTS) {
            $this->state->complete(SetupState::STEP_REQUIREMENTS);
            $this->state->complete(SetupState::STEP_DATABASE);
            $this->state->complete(SetupState::STEP_ENV);
        }

        return Response::success($response, $teshis + [
            'bilet_var' => $this->ticketPresent($request),
            'csrf_token' => $this->state->csrfToken(),
            'oturum_adimi' => $this->state->currentStep(),
        ]);
    }

    // ─────────────────────────── SAHİPLİK ───────────────────────────

    /**
     * POST /api/setup/verify-owner — sahiplik kanıtı → yeniden kurulum bileti.
     *
     * İki yol: `yontem=admin` (e-posta + şifre, BİRİNCİL) veya `yontem=app_key`
     * (config.php'deki anahtar, YEDEK). İkisi de aynı hız sınırına tabidir.
     */
    public function verifyOwner(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $now = $this->clock->now();
        $ip = ClientIp::from($request);

        $connection = $this->connectionOrNull();
        if ($connection === null) {
            return Response::error(
                $response,
                'DB_UNREACHABLE',
                'Veritabanına ulaşılamadığı için sahiplik doğrulanamıyor. Önce "Veritabanı bilgilerini '
                . 'düzelt" adımını tamamlayın.',
                503,
            );
        }

        $gate = new UnlockGate($connection, $this->basePath, new DateTimeZone(date_default_timezone_get()));

        $retryAfter = $gate->retryAfterSeconds($ip, $now);
        if ($retryAfter > 0) {
            return $this->rateLimited($response, $retryAfter);
        }

        $body = $this->body($request);
        $yontem = is_string($body['yontem'] ?? null) ? $body['yontem'] : 'admin';

        $gecerli = $yontem === 'app_key'
            ? $gate->proofValid(is_string($body['app_key'] ?? null) ? $body['app_key'] : null)
            : $gate->adminProofValid(
                is_string($body['email'] ?? null) ? $body['email'] : null,
                is_string($body['sifre'] ?? null) ? $body['sifre'] : null,
                $connection,
            );

        if (!$gecerli) {
            $gate->recordFailure($ip, $now);

            // K51: SABİT mesaj — "e-posta yanlış" ile "şifre yanlış" ayrımı
            // saldırgana hesap listesi verirdi.
            return Response::error(
                $response,
                'FORBIDDEN',
                'Sahiplik doğrulanamadı. Yönetici e-postası ve şifresini kontrol edin; '
                . 'şifreye erişiminiz yoksa config.php içindeki APP_KEY ile deneyin.',
                403,
            );
        }

        $gate->recordSuccess($ip, $now, $yontem);
        $this->state->reset();

        try {
            $ticket = (new ReSetupTicket($connection))->issue($now, $yontem);
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', $e->getMessage(), 500);
        }

        return Cookie::write(
            Response::success($response, [
                'dogrulandi' => true,
                'yontem' => $yontem,
                'expires_in_seconds' => ReSetupTicket::LIFETIME_SECONDS,
                'mesaj' => 'Sahiplik doğrulandı. Sihirbaz bu tarayıcıda 15 dakika açık kalır.',
            ]),
            ReSetupTicket::COOKIE_NAME,
            $ticket,
            $now->modify('+' . ReSetupTicket::LIFETIME_SECONDS . ' seconds'),
            strtolower($request->getUri()->getScheme()) === 'https',
        );
    }

    // ─────────────────────────── CONFIG ONARIMI (durum 4 ve 8) ───────────────────────────

    /**
     * POST /api/setup/config-repair — ayar dosyasını yeniden üretir.
     *
     * İKİ AYRI HÂLİ TEK UÇ ÇÖZER, çünkü kullanıcı açısından soru aynıdır
     * ("veritabanı bilgilerim ne?"), ama sahiplik kanıtı farklıdır:
     *
     *  • config.php VAR ve APP_KEY okunabiliyor (durum 8 — DB erişilemiyor):
     *    kanıt APP_KEY'dir ve anahtar KORUNUR. Yalnız bağlantı bilgileri değişir,
     *    şifreli veriler açılmaya devam eder.
     *  • config.php YOK/BOZUK (durum 4): diskte kanıt kalmamıştır. Girilen
     *    veritabanında kullanıcı VARSA kanıt yönetici şifresidir; veritabanı
     *    bomboşsa bu zaten ilk kurulumdur, kanıt aranmaz.
     */
    public function configRepair(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $host = trim((string) ($body['host'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));
        $user = trim((string) ($body['user'] ?? ''));
        $pass = is_string($body['pass'] ?? null) ? $body['pass'] : '';
        $portRaw = trim((string) ($body['port'] ?? ''));

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
        if ($portRaw !== '' && preg_match('/^\d+$/', $portRaw) !== 1) {
            $fields['port'] = 'Port yalnızca rakamlardan oluşmalı.';
        }
        if ($fields !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $fields);
        }

        $port = $portRaw === '' ? 3306 : (int) $portRaw;
        $probe = (new DatabaseProbe())->probe($host, $port, $name, $user, $pass);
        if ($probe['ok'] !== true) {
            $ham = isset($probe['error']) ? (string) $probe['error'] : '';
            $siniflandirma = DatabaseProbe::classify($ham);
            $mesaj = $ham !== '' ? $ham : $siniflandirma['mesaj'];

            return Response::error($response, 'VALIDATION', $mesaj, 422, [
                $siniflandirma['alan'] => $mesaj,
            ], ['hata_kodu' => $siniflandirma['kod']]);
        }

        $mevcutAppKey = ConfigWriter::readAppKey($this->basePath . '/config.php');
        $now = $this->clock->now();
        $ip = ClientIp::from($request);

        // ── Sahiplik kanıtı ────────────────────────────────────────────────────
        if ($mevcutAppKey !== null) {
            // Diskte anahtar var → kanıt odur (DB düşmüş olabilir, throttle da düşer).
            $gate = new UnlockGate(
                $this->connectionTo($host, $port, $name, $user, $pass),
                $this->basePath,
                new DateTimeZone(date_default_timezone_get()),
            );
            if (!$gate->proofValid(is_string($body['app_key'] ?? null) ? $body['app_key'] : null)) {
                return Response::error(
                    $response,
                    'FORBIDDEN',
                    'Sahiplik kanıtı gerekli: config.php içindeki APP_KEY değerini girin. '
                    . '(Dosyayı File Manager ile açıp kopyalayabilirsiniz.)',
                    403,
                    ['app_key' => 'APP_KEY eşleşmedi.'],
                );
            }
        } else {
            $hedef = $this->connectionTo($host, $port, $name, $user, $pass);
            if ($this->kullaniciSayisi($hedef) > 0) {
                $gate = new UnlockGate(
                    $hedef,
                    $this->basePath,
                    new DateTimeZone(date_default_timezone_get()),
                );
                $retryAfter = $gate->retryAfterSeconds($ip, $now);
                if ($retryAfter > 0) {
                    return $this->rateLimited($response, $retryAfter);
                }
                $gecerli = $gate->adminProofValid(
                    is_string($body['email'] ?? null) ? $body['email'] : null,
                    is_string($body['sifre'] ?? null) ? $body['sifre'] : null,
                    $hedef,
                );
                if (!$gecerli) {
                    $gate->recordFailure($ip, $now);

                    return Response::error(
                        $response,
                        'FORBIDDEN',
                        'Bu veritabanında zaten bir kurulum var. Ayar dosyasını yeniden oluşturmak için '
                        . 'yönetici e-postası ve şifresiyle sahipliğinizi doğrulayın.',
                        403,
                    );
                }
                $gate->recordSuccess($ip, $now, 'admin');
            }
        }

        // ── Üretim ─────────────────────────────────────────────────────────────
        $database = ['host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => $pass];
        $uretilen = $this->configWriter->generate($database, $mevcutAppKey);

        // Anahtarlar SetupController ile AYNI olmalı — onarımdan sonra kullanıcı
        // normal akışa (tablolar → yönetici) kaldığı yerden devam eder.
        $this->state->put('env_app_key', $uretilen['app_key']);
        $this->state->put('database', $database);
        $this->state->complete(SetupState::STEP_REQUIREMENTS);
        $this->state->complete(SetupState::STEP_DATABASE);

        $yeniAnahtar = $mevcutAppKey === null;
        $uyari = $yeniAnahtar
            ? 'DİKKAT: dosyada APP_KEY kalmadığı için YENİ anahtar üretildi. Eski anahtarla '
                . 'şifrelenmiş veriler (2FA gizli anahtarı, çeviri API anahtarı, şifreli yedekler) '
                . 'ARTIK ÇÖZÜLEMEZ; bunları kurulumdan sonra yeniden girmeniz gerekir.'
            : 'APP_KEY korundu — şifreli verileriniz (2FA, API anahtarları) açılmaya devam eder.';

        // Kök yazılabilirse dosyayı biz yazarız; değilse (üretim sunucusu) içerik
        // ekranda gösterilir ve kullanıcı File Manager ile kaydeder.
        $eskiVar = $this->configWriter->exists();
        if ($this->configWriter->canWrite() && !$eskiVar) {
            try {
                $this->configWriter->write($database);
            } catch (Throwable $e) {
                return Response::error($response, 'SERVER_ERROR', $e->getMessage(), 500);
            }

            $this->state->complete(SetupState::STEP_ENV);

            return Response::success($response, [
                'manual' => false,
                'yeni_app_key' => $yeniAnahtar,
                'uyari' => $uyari,
            ]);
        }

        return Response::success($response, [
            'manual' => true,
            'filename' => 'config.php',
            'content' => $uretilen['content'],
            'yeni_app_key' => $yeniAnahtar,
            'uyari' => $uyari,
            'instructions' => $eskiVar
                ? 'Mevcut config.php dosyasını File Manager ile AÇIN, içeriğinin TAMAMINI silip '
                    . 'aşağıdaki içerikle değiştirin ve kaydedin. Sonra "Kaydettim, doğrula" deyin.'
                : 'Aşağıdaki içeriği kopyalayın, cPanel > File Manager ile uygulama kökünde '
                    . '"config.php" adıyla kaydedin, sonra "Kaydettim, doğrula" deyin.',
        ]);
    }

    /** POST /api/setup/config-repair/verify — elle kaydedilen dosya doğrulanır. */
    public function verifyRepair(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $beklenen = $this->state->get('env_app_key');
        if (!is_string($beklenen) || $beklenen === '') {
            return Response::error($response, 'VALIDATION', 'Önce ayar dosyasını üretin.', 409);
        }

        if (!$this->configWriter->exists()) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'config' => 'config.php dosyası bulunamadı. Uygulama kökünde "config.php" adıyla '
                    . 'kaydettiğinizden emin olun.',
            ]);
        }
        if (!$this->configWriter->verify($beklenen)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'config' => 'config.php bulundu ama içeriği beklenenle uyuşmuyor. İçeriğin tamamını '
                    . 'eksiksiz kopyaladığınızdan emin olup tekrar deneyin.',
            ]);
        }

        // Dosya yerinde: bağlantıyı GERÇEKTEN kurup doğrula — "kaydettim" demek yetmez.
        $connection = $this->connectionOrNull();
        if ($connection === null) {
            return Response::error(
                $response,
                'VALIDATION',
                'Dosya doğrulandı ama veritabanına hâlâ bağlanılamıyor. Bilgileri gözden geçirin.',
                422,
            );
        }

        $this->state->complete(SetupState::STEP_ENV);

        return Response::success($response, ['dogrulandi' => true]);
    }

    // ─────────────────────────── GÜNCELLEME (durum 6 ve 7) ───────────────────────────

    /**
     * POST /api/setup/update — bekleyen migration'ları koşar, sürüm kaydını tazeler.
     *
     * Bu, gelecekteki HER sürüm güncellemesinin resmî yoludur: zip yüklenir,
     * `/setup` açılır, teşhis "sürüm uyuşmazlığı" der, bu uç koşar. Veri korunur;
     * yıkıcı hiçbir şey yapılmaz — bu yüzden yazarak-onay istenmez, ama kilitli
     * sistemde SetupGuard zaten bilet arar.
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $connection = $this->connectionOrNull();
        if ($connection === null) {
            return Response::error($response, 'DB_UNREACHABLE', 'Veritabanına ulaşılamıyor.', 503);
        }

        $migrator = new Migrator($connection->pdo(), $this->basePath . '/migrations');
        $oncekiSurum = $this->kuruluSurum($connection);

        try {
            $bekleyen = $migrator->pending();
            $uygulanan = $migrator->run();
        } catch (Throwable $e) {
            return Response::error(
                $response,
                'SERVER_ERROR',
                'Güncelleme yarıda kaldı: ' . $e->getMessage()
                . ' — hiçbir şey silinmedi; sorunu giderip tekrar deneyebilirsiniz.',
                500,
            );
        }

        $this->surumKaydet($connection);

        return Response::success($response, [
            'onceki_surum' => $oncekiSurum,
            'yeni_surum' => \App\Core\AppVersion::VALUE,
            'bekleyen_sayisi' => count($bekleyen),
            'uygulanan' => $uygulanan,
            'kalan' => $migrator->pending(),
        ]);
    }

    // ─────────────────────────── yardımcılar ───────────────────────────

    private function rateLimited(ResponseInterface $response, int $retryAfter): ResponseInterface
    {
        return Response::error(
            $response,
            'RATE_LIMITED',
            sprintf('Çok fazla hatalı deneme. %d saniye sonra tekrar deneyin.', $retryAfter),
            429,
            [],
            ['retry_after_seconds' => $retryAfter],
        );
    }

    private function ticketPresent(ServerRequestInterface $request): bool
    {
        $connection = $this->lock->connection() ?? $this->connectionOrNull();
        if ($connection === null) {
            return false;
        }
        $raw = $request->getCookieParams()[ReSetupTicket::COOKIE_NAME] ?? null;

        return is_string($raw) && (new ReSetupTicket($connection))->validate($raw, $this->clock->now());
    }

    private function connectionOrNull(): ?Connection
    {
        if (!$this->configWriter->configured()) {
            return null;
        }

        try {
            $config = Config::load($this->basePath);
            $pdo = Database::connect($config);
            $pdo->query('SELECT 1');

            return Connection::fromCallable(static fn (): PDO => $pdo);
        } catch (Throwable) {
            return null;
        }
    }

    private function connectionTo(
        string $host,
        int $port,
        string $name,
        string $user,
        string $pass,
    ): Connection {
        return Connection::fromCallable(static function () use ($host, $port, $name, $user, $pass): PDO {
            return new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            );
        });
    }

    private function kullaniciSayisi(Connection $connection): int
    {
        try {
            $statement = $connection->pdo()->query('SELECT COUNT(*) FROM users');

            return $statement === false ? 0 : (int) $statement->fetchColumn();
        } catch (Throwable) {
            return 0; // tablo yoksa kurulum yoktur
        }
    }

    private function kuruluSurum(Connection $connection): ?string
    {
        try {
            $statement = $connection->pdo()->prepare('SELECT value FROM settings WHERE `key` = :key');
            $statement->execute(['key' => SetupSituation::SETTING_VERSION]);
            $deger = $statement->fetchColumn();

            return is_string($deger) && $deger !== '' ? $deger : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function surumKaydet(Connection $connection): void
    {
        try {
            (new SettingsRepository($connection))->set(
                SetupSituation::SETTING_VERSION,
                \App\Core\AppVersion::VALUE,
            );
        } catch (Throwable) {
            // Sürüm kaydı bir kolaylıktır; yazılamazsa güncelleme yine de geçerlidir
            // (teşhis o zaman "sürüm bilinmiyor" der, yanlış bilgi vermez).
        }
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();

        return is_array($parsed) ? $parsed : [];
    }

    /** Test kolaylığı: saat ve zaman dilimi tek yerden. */
    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
