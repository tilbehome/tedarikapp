<?php

declare(strict_types=1);

namespace Tests\Production;

use App\Core\Config;
use App\Core\Encrypter;
use App\Core\Logger;
use App\Core\SetupAppBuilder;
use App\Models\SettingsRepository;
use App\Services\MediaService;
use App\Services\UrlGuard;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * ÜRETİM PROFİLİ SÜİTİ (K41) — docs/SUNUCU-PROFILI.md manifestinin otomatik bekçisi.
 *
 * Her PR'da "bu kod Bünyamin'in sunucusunda açılır mı?" sorusunu cevaplar:
 *  • sodium YOK varsayılır (Encrypter OpenSSL'e düşer — K39),
 *  • storage/ ve public/media YAZILAMAZ varsayılır (hotlink + DB-log yolları),
 *  • dış istek YALNIZ cURL (allow_url_fopen kapalı — statik tarama),
 *  • mail() ve süreç çalıştırma yasak (statik tarama).
 *
 * CI'da ayrı `uretim-profili` job'ı olarak < 1 dk hedefiyle koşar.
 */
#[Group('uretim-profili')]
final class UretimProfiliTest extends AuthTestCase
{
    use TempDirectory;

    // ─────────── Statik tarama: manifest ihlalleri PR'da yakalanır ───────────

    /** @return list<string> taranacak PHP dosyaları (çalışma zamanı kodu) */
    private function runtimePhpFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];
        foreach (['app', 'bin', 'bootstrap', 'public', 'migrations'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function testUrlAlanFileGetContentsVeFopenYok(): void
    {
        // allow_url_fopen KAPALI (docs/SUNUCU-PROFILI.md): URL'li file_get_contents/fopen
        // sunucuda çalışma anında ölür. Dış istek YALNIZ cURL (K8).
        $violations = [];
        foreach ($this->runtimePhpFiles() as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/(file_get_contents|fopen)\s*\(\s*[\'"]https?:\/\//i', $source) === 1) {
                $violations[] = $file;
            }
        }

        self::assertSame([], $violations, 'URL alan file_get_contents/fopen YASAK — cURL kullanın (K8/K41).');
    }

    public function testSessionStartYalnizNativeSessionda(): void
    {
        // K44: native session TEK kapıdan geçer (NativeSession + DbSessionHandler).
        // Başka yerde session_start = save_path'e (DİSKE) gizli bağımlılık riski.
        $violations = [];
        foreach ($this->runtimePhpFiles() as $file) {
            if (str_ends_with(str_replace('\\', '/', $file), 'app/Auth/NativeSession.php')) {
                continue;
            }
            if (preg_match('/\bsession_start\s*\(/', (string) file_get_contents($file)) === 1) {
                $violations[] = $file;
            }
        }

        self::assertSame([], $violations, 'session_start yalnız NativeSession içinde olabilir (K44).');
    }

    public function testDiskYazimiYalnizIzinliDosyalarda(): void
    {
        // K44 disksiz mod: public/media DIŞINA yazan kod yalnız izinli dosyalarda olabilir.
        // İzin gerekçeleri — ConfigWriter/EnvWriter: kök YAZILABİLİRSE kolaylık (manuel akış
        // asıl yol); MediaService: public/media; Logger: file sürücüsü (yalnız geliştirme;
        // üretimde K44 zorlaması db); RequirementChecker: yazılabilirlik PROBU (@mkdir);
        // PdfRenderer (İE#10 Blok 3): mPDF geçici dizini — sys_temp, olmazsa
        // public/media/.tmp (izinli bölge içinde; sweepOwnTemp yol önekini doğrular).
        $allowed = [
            'app/Setup/ConfigWriter.php',
            'app/Setup/EnvWriter.php',
            'app/Services/MediaService.php',
            'app/Core/Logger.php',
            'app/Setup/RequirementChecker.php',
            'app/Services/Export/PdfRenderer.php',
        ];
        $pattern = '/(?<![>\w$:])(?<!function )(file_put_contents|fwrite|mkdir|tempnam|touch)\s*\(|(?<![>\w$:])fopen\s*\([^)]*,\s*[\'"][waxc]/';

        $violations = [];
        foreach ($this->runtimePhpFiles() as $file) {
            $normalized = str_replace('\\', '/', $file);
            // bin/ CLI araçları (release zip'i, purge, user-create) web çalışma zamanı değildir.
            if (str_contains($normalized, '/bin/')) {
                continue;
            }
            $isAllowed = false;
            foreach ($allowed as $allowedFile) {
                if (str_ends_with($normalized, $allowedFile)) {
                    $isAllowed = true;

                    break;
                }
            }
            if ($isAllowed) {
                continue;
            }
            if (preg_match($pattern, (string) file_get_contents($file)) === 1) {
                $violations[] = $normalized;
            }
        }

        self::assertSame([], $violations, 'İzinsiz disk yazımı (K44 disksiz mod) — public/media dışına yazılamaz.');
    }

    public function testYasakliFonksiyonCagrisiYok(): void
    {
        // mail() KAPALI; exec/system/proc_open YASAK (docs/04 §7, SUNUCU-PROFILI).
        $violations = [];
        foreach ($this->runtimePhpFiles() as $file) {
            $source = (string) file_get_contents($file);
            // Yalnız GLOBAL fonksiyon çağrısı: `->exec(` (PDO) ve `::exec(` metodları hariç.
            if (preg_match('/(?<![>\w$:])(mail|exec|shell_exec|system|proc_open|popen|passthru)\s*\(/', $source, $match) === 1) {
                $violations[] = basename($file) . ' → ' . $match[1] . '()';
            }
        }

        self::assertSame([], $violations, 'Sunucu profiline aykırı fonksiyon çağrısı (K41).');
    }

    // ─────────── Sodium yok: şifreleme ve 2FA yolu ───────────

    public function testSodiumsuzProfilSifrelemeTuruTamamlanir(): void
    {
        $config = $this->config();
        $encrypter = new Encrypter($config, useSodium: null, sodiumSupported: false);

        $payload = $encrypter->encrypt('JBSWY3DPEHPK3PXP');

        self::assertStringStartsWith('v1a:', $payload, 'Sodium\'suz sunucuda OpenSSL AES-GCM seçilmeli (K39).');
        self::assertSame('JBSWY3DPEHPK3PXP', $encrypter->decrypt($payload));
    }

    // ─────────── Açılış (bootstrap) + guard sessiz geçiş ───────────

    public function testBootstrapVeHealthUcuAyakta(): void
    {
        $response = $this->call('GET', '/api/health');

        self::assertSame(200, $response->getStatusCode(), 'Uygulama üretim benzeri konfigde açılmalı.');
        self::assertTrue($this->json($response)['success']);
    }

    public function testAnaUygulamadaIntegrityUcuKimliksizCalisir(): void
    {
        // K43: kurulu sistemde de /api/system/integrity kimliksiz cevap verir
        // (geliştirme ağacında manifest yok → denetim atlanır, hata üretilmez).
        $response = $this->call('GET', '/api/system/integrity');

        self::assertSame(200, $response->getStatusCode());
        $data = $this->json($response)['data'];
        self::assertFalse($data['manifest_exists']);
        self::assertTrue($data['ok']);
    }

    private function setupApp(): \Slim\App
    {
        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }

        return SetupAppBuilder::build(
            $this->tempRoot(),
            new NullLogger(),
            new ArraySession(),
            $this->clock,
            appEnv: 'production', // üretim profili: HTTPS kapısı ve gereksinim kuralları gerçek modda
        );
    }

    public function testSetupKlasoruEksikseAnlasilir503Doner(): void
    {
        // İki kez yaşanan üretim vakasının g-simülasyonu (İE#9.3): setup/ hiç açılmamış.
        // Eskisi: sessiz NOT_FOUND JSON. Yenisi: NE YAPILACAĞINI söyleyen 503 HTML.
        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        // setup/views BİLEREK kopyalanmıyor.

        $app = SetupAppBuilder::build($this->tempRoot(), new NullLogger(), new ArraySession(), $this->clock, appEnv: 'production');
        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://ornek.test/setup', ['REMOTE_ADDR' => '203.0.113.7']),
        );

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $html = (string) $response->getBody();
        self::assertStringContainsString('eksik yüklenmiş', $html);
        self::assertStringContainsString('setup/', $html, 'Hangi klasörün eksik olduğu söylenmeli.');
        self::assertStringContainsString('/api/system/integrity', $html, 'Tam listeye yönlendirme olmalı.');
    }

    public function testIntegrityUcuKurulumdanOnceCalisir(): void
    {
        // K43: manifest'e göre eksik dosyalar — sihirbazın gereksinim adımının kaynağı.
        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }
        // Manifest: biri mevcut, biri EKSİK dosya.
        file_put_contents(
            $this->tempPath('MANIFEST.txt'),
            \App\Services\IntegrityChecker::manifestLine(
                (string) hash_file('sha256', $this->tempPath('.env.example')),
                '.env.example',
            ) . "\n" . \App\Services\IntegrityChecker::manifestLine(str_repeat('a', 64), 'vendor/autoload.php') . "\n",
        );

        $app = SetupAppBuilder::build($this->tempRoot(), new NullLogger(), new ArraySession(), $this->clock, appEnv: 'production');
        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://ornek.test/api/system/integrity', ['REMOTE_ADDR' => '203.0.113.7']),
        );

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true)['data'];
        self::assertTrue($data['manifest_exists']);
        self::assertFalse($data['ok']);
        self::assertContains('vendor/autoload.php', $data['missing'], 'Eksik dosya İSİM İSİM raporlanmalı.');
    }

    public function testGuardSessizGecisVeSetupDurumUcu(): void
    {
        $app = $this->setupApp();

        // Kurulmamış sistem: guard SESSİZCE geçirir (K40 mantığı), durum ucu cevap verir.
        $state = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://ornek.test/api/setup/state', ['REMOTE_ADDR' => '203.0.113.7']),
        );

        self::assertSame(200, $state->getStatusCode());
        $payload = json_decode((string) $state->getBody(), true);
        self::assertSame(64, strlen((string) $payload['data']['csrf_token']));

        $wizard = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://ornek.test/setup', ['REMOTE_ADDR' => '203.0.113.7']),
        );
        self::assertSame(200, $wizard->getStatusCode(), 'Sihirbaz sayfası açılmalı.');
    }

    public function testLoginBaslangiciCalisir(): void
    {
        $this->createUser();

        $response = $this->call('POST', '/api/auth/login', [
            'email' => 'admin@tedarikapp.test',
            'password' => 'cok-gizli-sifre',
        ]);

        // Şifre adımı geçti, 2FA bekleniyor — akışın başı üretim profilinde ayakta.
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('totp', $this->json($response)['data']['stage']);
    }

    // ─────────── Yazılamaz disk: hotlink + DB-log yolları ───────────

    public function testMediaServiceYazilamazDisktehotlinkYolunuKullanir(): void
    {
        $fetcher = new FakeMediaFetcher();
        $media = new MediaService(
            $this->tempRoot(),
            new UrlGuard(['alicdn.com']),
            $fetcher,
            new SettingsRepository($this->connection),
            8 * 1024 * 1024,
            'public/olmayan-klasor', // yazılamaz docroot simülasyonu
        );

        $url = 'https://cbu01.alicdn.com/img/ornek.jpg';
        $result = $media->store($url);

        self::assertSame(MediaService::MODE_HOTLINK, $result['mode']);
        self::assertSame($url, $result['url'], 'Hotlink modunda URL olduğu gibi saklanır.');
        self::assertSame(0, $fetcher->callCount, 'Hotlink modunda indirme DENENMEZ (yazılamaz disk).');
    }

    public function testStorageYazilamazkenLogDbYolunaGider(): void
    {
        $this->pdo->exec(
            'CREATE TABLE app_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel TEXT NOT NULL, level_name TEXT NOT NULL, level INTEGER NOT NULL,
                message TEXT NOT NULL, context TEXT NULL, extra TEXT NULL,
                request_id TEXT NULL, logged_at TEXT NOT NULL
            )',
        );

        $config = new Config([
            'APP_ENV' => 'production',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost', 'DB_NAME' => 'test', 'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('a1b2c3d4', 8),
            'EXTENSION_TOKEN_SALT' => str_repeat('s', 32),
            'LOG_DRIVER' => 'db', // K33: diske YAZMADAN loglama
        ]);

        $logger = Logger::create($config, $this->tempRoot(), null, $this->connection);
        $logger->error('Üretim profili log denemesi', ['neden' => 'test']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM app_logs')->fetch()['c'];
        self::assertSame(1, $count, 'Log diske değil app_logs tablosuna gitmeli (K33).');
        self::assertDirectoryDoesNotExist($this->tempPath('storage/logs'), 'DB sürücüsü diske dokunmamalı.');
    }
}
