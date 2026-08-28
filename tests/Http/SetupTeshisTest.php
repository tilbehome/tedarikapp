<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Setup\SetupLock;
use App\Setup\SetupSituation;
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
 * TEŞHİS MERKEZİ — SEKİZ DURUM (İE#20 D2-REV).
 *
 * Her test bir BOZUKLUĞU gerçekten üretir (config'i siler, dosyayı bozar,
 * migration'ı yarıda keser, şemayı eskitir) ve sihirbazın doğru durumu bulup
 * doğru YOLU sunduğunu doğrular. "Ekranda ne yazıyor"u değil, kullanıcının
 * elindeki SEÇENEĞİ sınarız: D2-REV'in sözü buydu — her arıza sihirbaz
 * içinden çözülebilmeli.
 */
final class SetupTeshisTest extends TestCase
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

    // ─────────────────────────── yardımcılar ───────────────────────────

    private function lock(): SetupLock
    {
        return new SetupLock($this->connection, $this->tempPath('storage'));
    }

    private function call(string $method, string $path): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path, ['REMOTE_ADDR' => '203.0.113.7']);

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
    private function teshis(): array
    {
        $response = $this->call('GET', '/api/setup/situation');
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        /** @var array{data: array<string, mixed>} $payload */
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['data'];
    }

    /**
     * Teşhis motorunu doğrudan koşar — HTTP kapısına takılmadan.
     *
     * @param (\Closure(): Connection)|null $baglanti
     *
     * @return array<string, mixed>
     */
    private function motor(?\Closure $baglanti = null): array
    {
        return (new SetupSituation(
            $this->tempRoot(),
            $this->lock(),
            new \App\Setup\ConfigWriter($this->tempRoot()),
            $baglanti,
        ))->analyze();
    }

    /** Gerçek bir SQLite dosyası + ona işaret eden config.php üretir. */
    private function sqliteKurulumuYaz(string $dosyaAdi = 'test.sqlite'): string
    {
        $dbPath = $this->tempPath($dosyaAdi);
        touch($dbPath);

        file_put_contents($this->tempPath('config.php'), "<?php\n\nreturn [\n"
            . "    'DB_DRIVER' => 'sqlite',\n"
            . "    'DB_HOST' => 'localhost',\n"
            . "    'DB_PORT' => '3306',\n"
            . "    'DB_NAME' => " . var_export($dbPath, true) . ",\n"
            . "    'DB_USER' => 'test',\n"
            . "    'DB_PASS' => '',\n"
            . "    'APP_KEY' => '" . str_repeat('ab', 32) . "',\n"
            . "];\n");

        return $dbPath;
    }

    private function migrationYaz(string $ad, string $sql): void
    {
        file_put_contents(
            $this->tempPath('migrations/' . $ad . '.php'),
            "<?php\n\nreturn new class () implements \\App\\Core\\Migration {\n"
            . "    public function up(PDO \$pdo): void\n    {\n"
            . '        $pdo->exec(' . var_export($sql, true) . ");\n"
            . "    }\n};\n",
        );
    }

    // ─────────────────────── 1) HİÇ KURULUM YOK ───────────────────────

    public function testDurum1KurulumYok(): void
    {
        $teshis = $this->teshis();

        self::assertSame(SetupSituation::KURULUM_YOK, $teshis['durum']);
        self::assertSame('normal_kurulum', $teshis['secenekler'][0]['kod']);
        self::assertFalse($teshis['config']['var']);
    }

    // ─────────────────────── 2) SAĞLIKLI KURULUM ───────────────────────

    public function testDurum2SaglikliKurulum(): void
    {
        // Kilit yazılı + tablolar var + bekleyen migration yok.
        $this->lock()->write(new DateTimeImmutable('2026-08-23 10:00:00'));
        $this->sqliteKurulumuYaz();

        $motor = $this->motorIle(function (PDO $db): void {
            $db->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, name TEXT, checksum TEXT, '
                . 'execution_ms INTEGER, applied_at TEXT)');
            $db->exec('CREATE TABLE settings (`key` TEXT PRIMARY KEY, value TEXT)');
            $db->exec("INSERT INTO settings (`key`, value) VALUES ('"
                . SetupSituation::SETTING_VERSION . "', '9.9.9')");
        });

        self::assertSame(SetupSituation::SAGLIKLI, $motor['durum']);
        self::assertSame('iyi', $motor['rozet']);
        $kodlar = array_column($motor['secenekler'], 'kod');
        self::assertContains('panele_git', $kodlar);
        self::assertContains('temiz_kurulum', $kodlar);
    }

    /**
     * İE#22 E1 (Blok H · seçenek B) — DAMGA GERİDEYSE KOŞULLU EYLEM ÇIKAR.
     *
     * Yukarıdaki senaryoda kurulu sürüm "9.9.9", dosya sürümü ise gerçek
     * `AppVersion::VALUE` — yani FARKLI. Sihirbaz bunu görmezden geliyordu:
     * "SAĞLIKLI" der, tek sürüm basar ve kullanıcı damganın geride kaldığını
     * hiçbir yerde göremezdi. D2-REV sözleşmesi bozulmaz: yeni DURUM yok,
     * yalnız fark varken görünen bir EYLEM ve iki değerli açıklama var.
     */
    public function testDAMGAGERIDEYSEESITLEMEEYLEMICIKAR(): void
    {
        $this->lock()->write(new DateTimeImmutable('2026-08-23 10:00:00'));
        $this->sqliteKurulumuYaz();

        $motor = $this->motorIle(function (PDO $db): void {
            $db->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, name TEXT, checksum TEXT, '
                . 'execution_ms INTEGER, applied_at TEXT)');
            $db->exec('CREATE TABLE settings (`key` TEXT PRIMARY KEY, value TEXT)');
            $db->exec("INSERT INTO settings (`key`, value) VALUES ('"
                . SetupSituation::SETTING_VERSION . "', '9.9.9')");
        });

        self::assertSame(SetupSituation::SAGLIKLI, $motor['durum'], 'Durum sözleşmesi DEĞİŞMEZ.');
        self::assertContains('damgayi_esitle', array_column($motor['secenekler'], 'kod'));
        // Açıklama İKİ DEĞERİ birden basmalı: kullanıcı farkı okuyabilmeli.
        self::assertStringContainsString('9.9.9', $motor['aciklama']);
        self::assertStringContainsString(\App\Core\AppVersion::VALUE, $motor['aciklama']);
    }

    public function testDAMGAAYNIYSAESITLEMEEYLEMIYOK(): void
    {
        $this->lock()->write(new DateTimeImmutable('2026-08-23 10:00:00'));
        $this->sqliteKurulumuYaz();

        $motor = $this->motorIle(function (PDO $db): void {
            $db->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, name TEXT, checksum TEXT, '
                . 'execution_ms INTEGER, applied_at TEXT)');
            $db->exec('CREATE TABLE settings (`key` TEXT PRIMARY KEY, value TEXT)');
            $db->exec("INSERT INTO settings (`key`, value) VALUES ('"
                . SetupSituation::SETTING_VERSION . "', '" . \App\Core\AppVersion::VALUE . "')");
        });

        // Fark yokken eylem GÖRÜNMEZ: gereksiz düğme, kullanıcıya "bir şey
        // yapmam mı lazım?" dedirtir.
        self::assertNotContains('damgayi_esitle', array_column($motor['secenekler'], 'kod'));
    }

    // ─────────────────────── 3) KURULUM YARIM ───────────────────────

    public function testDurum3KurulumYarim(): void
    {
        // config var, kilit YOK → kurulum tamamlanmamış.
        $this->sqliteKurulumuYaz();

        $motor = $this->motorIle(static function (PDO $db): void {
            $db->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, name TEXT, checksum TEXT, '
                . 'execution_ms INTEGER, applied_at TEXT)');
        });

        self::assertSame(SetupSituation::YARIM, $motor['durum']);
        self::assertSame('devam_et', $motor['secenekler'][0]['kod']);
    }

    // ─────────────────────── 4) CONFIG KAYIP/BOZUK ───────────────────────

    public function testDurum4ConfigBozuk(): void
    {
        // Dosya VAR ama içi eksik — "yok" ile "bozuk" farklı durumlardır.
        file_put_contents($this->tempPath('config.php'), "<?php\n\nreturn ['DB_HOST' => 'localhost'];\n");

        $teshis = $this->teshis();

        self::assertSame(SetupSituation::CONFIG_KAYIP, $teshis['durum']);
        self::assertTrue($teshis['config']['var']);
        self::assertFalse($teshis['config']['saglam']);
        self::assertContains('DB_NAME', $teshis['config']['eksik_alanlar']);
        self::assertFalse($teshis['config']['app_key_var']);
        self::assertSame('config_onar', $teshis['secenekler'][0]['kod']);
    }

    public function testDurum4UyarisiAppKeyKaybiniSoyler(): void
    {
        file_put_contents($this->tempPath('config.php'), "<?php\n\nreturn ['DB_HOST' => 'localhost'];\n");

        $teshis = $this->teshis();

        self::assertStringContainsString('YENİ anahtar', $teshis['secenekler'][0]['aciklama']);
        self::assertStringContainsString('2FA', $teshis['secenekler'][0]['aciklama']);
    }

    // ─────────────────────── 5) DOSYA EKSİK/BOZUK ───────────────────────

    public function testDurum5DosyaEksik(): void
    {
        // MANIFEST bir dosya vaat eder, dosya yoktur.
        file_put_contents(
            $this->tempPath('MANIFEST.txt'),
            str_repeat('a', 64) . "  app/Core/Yok.php\n",
        );

        $teshis = $this->teshis();

        self::assertSame(SetupSituation::DOSYA_EKSIK, $teshis['durum']);
        self::assertSame('kötü', $teshis['rozet']);
        self::assertSame(1, $teshis['dosyalar']['eksik_sayisi']);
        self::assertContains('app/Core/Yok.php', $teshis['dosyalar']['eksik']);
        self::assertSame('yeniden_tara', $teshis['secenekler'][0]['kod']);
    }

    public function testDurum5BozukDosyaDaYakalanir(): void
    {
        // Dosya VAR ama içeriği değişmiş (checksum tutmuyor).
        file_put_contents($this->tempPath('ornek.txt'), 'gercek icerik');
        file_put_contents(
            $this->tempPath('MANIFEST.txt'),
            hash('sha256', 'baska icerik') . "  ornek.txt\n",
        );

        $teshis = $this->teshis();

        self::assertSame(SetupSituation::DOSYA_EKSIK, $teshis['durum']);
        self::assertSame(1, $teshis['dosyalar']['bozuk_sayisi']);
    }

    public function testManifestYoksaButunlukDenetimiAtlanir(): void
    {
        // Geliştirme kurulumunda MANIFEST yoktur; bu bir arıza DEĞİLDİR.
        $teshis = $this->teshis();

        self::assertFalse($teshis['dosyalar']['manifest_var']);
        self::assertSame(SetupSituation::KURULUM_YOK, $teshis['durum']);
    }

    // ─────────────────────── 6) MIGRATION YARIM ───────────────────────

    public function testDurum6MigrationYarim(): void
    {
        $this->lock()->write(new DateTimeImmutable('2026-08-23 10:00:00'));
        $this->sqliteKurulumuYaz();
        $this->migrationYaz('0001_ilk', 'CREATE TABLE a (id INTEGER PRIMARY KEY)');
        $this->migrationYaz('0002_ikinci', 'CREATE TABLE b (id INTEGER PRIMARY KEY)');

        // Defterde yalnız BİRİ var → ikincisi bekliyor.
        $motor = $this->motorIle(static function (PDO $db): void {
            $db->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, '
                . 'checksum TEXT, execution_ms INTEGER, applied_at TEXT)');
            $db->exec("INSERT INTO migrations (name, checksum, execution_ms, applied_at) "
                . "VALUES ('0001_ilk', 'x', 1, '2026-08-23 10:00:00')");
            $db->exec('CREATE TABLE a (id INTEGER PRIMARY KEY)');
        });

        self::assertSame(SetupSituation::MIGRATION_YARIM, $motor['durum']);
        self::assertSame(1, $motor['sema']['uygulanan_sayisi']);
        self::assertContains('0002_ikinci', $motor['sema']['bekleyen']);
        $kodlar = array_column($motor['secenekler'], 'kod');
        self::assertContains('bekleyenleri_tamamla', $kodlar);
    }

    // ─────────────────────── 7) SÜRÜM UYUŞMAZLIĞI ───────────────────────

    public function testDurum7SurumUyusmazligi(): void
    {
        $this->lock()->write(new DateTimeImmutable('2026-08-23 10:00:00'));
        $this->sqliteKurulumuYaz();
        $this->migrationYaz('0001_ilk', 'CREATE TABLE a (id INTEGER PRIMARY KEY)');
        $this->migrationYaz('0002_yeni', 'CREATE TABLE b (id INTEGER PRIMARY KEY)');

        $motor = $this->motorIle(static function (PDO $db): void {
            $db->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, '
                . 'checksum TEXT, execution_ms INTEGER, applied_at TEXT)');
            $db->exec("INSERT INTO migrations (name, checksum, execution_ms, applied_at) "
                . "VALUES ('0001_ilk', 'x', 1, '2026-08-23 10:00:00')");
            $db->exec('CREATE TABLE a (id INTEGER PRIMARY KEY)');
            // DB'de KAYITLI sürüm eskidir → bu bir güncellemedir, yarım kurulum değil.
            $db->exec('CREATE TABLE settings (`key` TEXT PRIMARY KEY, value TEXT)');
            $db->exec("INSERT INTO settings (`key`, value) VALUES ('"
                . SetupSituation::SETTING_VERSION . "', '0.0.1-eski')");
        });

        self::assertSame(SetupSituation::SURUM_UYUSMAZLIGI, $motor['durum']);
        self::assertSame('0.0.1-eski', $motor['surum']['kurulu']);
        self::assertFalse($motor['surum']['ayni']);
        self::assertSame('guncelle', $motor['secenekler'][0]['kod']);
        self::assertStringContainsString('Veri korunur', $motor['secenekler'][0]['aciklama']);
    }

    // ─────────────────────── 8) DB'YE ERİŞİLEMİYOR ───────────────────────

    public function testDurum8VeritabaninaErisilemiyor(): void
    {
        // config sağlam ama MySQL yok — sınıflandırılmış hata beklenir.
        file_put_contents($this->tempPath('config.php'), "<?php\n\nreturn [\n"
            . "    'DB_HOST' => '203.0.113.199',\n"
            . "    'DB_PORT' => '3306',\n"
            . "    'DB_NAME' => 'yok_boyle_bir_db',\n"
            . "    'DB_USER' => 'yok',\n"
            . "    'DB_PASS' => 'yok',\n"
            . "    'APP_KEY' => '" . str_repeat('cd', 32) . "',\n"
            . "];\n");

        $motor = $this->motor();

        self::assertSame(SetupSituation::DB_ERISILEMIYOR, $motor['durum']);
        self::assertFalse($motor['veritabani']['erisim']);
        self::assertNotNull($motor['veritabani']['hata_kodu']);
        self::assertSame('db_bilgilerini_duzelt', $motor['secenekler'][0]['kod']);
        self::assertStringContainsString('APP_KEY KORUNARAK', $motor['secenekler'][0]['aciklama']);
    }

    public function testHataSiniflandirmasiAlanaOdaklanir(): void
    {
        $kimlik = \App\Setup\DatabaseProbe::classify('SQLSTATE[HY000] [1045] Access denied for user');
        self::assertSame('KIMLIK', $kimlik['kod']);
        self::assertSame('user', $kimlik['alan']);

        $dbYok = \App\Setup\DatabaseProbe::classify('SQLSTATE[HY000] [1049] Unknown database "x"');
        self::assertSame('DB_YOK', $dbYok['kod']);
        self::assertSame('name', $dbYok['alan']);

        $sunucu = \App\Setup\DatabaseProbe::classify('php_network_getaddresses: getaddrinfo failed');
        self::assertSame('SUNUCU_YOK', $sunucu['kod']);
        self::assertSame('host', $sunucu['alan']);
    }

    // ─────────────────────── ORTAK KURALLAR ───────────────────────

    public function testTeshisKilitliSistemdeDeCalisir(): void
    {
        // D2-REV: kilitliyken de teşhis GÖRÜLEBİLMELİ — yoksa kullanıcının elinde
        // yalnız 403 metni kalır.
        $this->lock()->write(new DateTimeImmutable('2026-08-23 10:00:00'));

        $response = $this->call('GET', '/api/setup/situation');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testTeshisSirIcermez(): void
    {
        $this->sqliteKurulumuYaz();

        $govde = (string) $this->call('GET', '/api/setup/situation')->getBody();

        self::assertStringNotContainsString(str_repeat('ab', 32), $govde, 'APP_KEY sızdı');
        self::assertStringNotContainsString('DB_PASS', $govde);
        self::assertStringNotContainsString('password', $govde);
    }

    public function testYikiciSecenekYikiciOlarakIsaretlenir(): void
    {
        $this->lock()->write(new DateTimeImmutable('2026-08-23 10:00:00'));
        $this->sqliteKurulumuYaz();

        $motor = $this->motorIle(static function (PDO $db): void {
            $db->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, name TEXT, checksum TEXT, '
                . 'execution_ms INTEGER, applied_at TEXT)');
            $db->exec('CREATE TABLE settings (`key` TEXT PRIMARY KEY, value TEXT)');
        });

        foreach ($motor['secenekler'] as $secenek) {
            if ($secenek['kod'] === 'temiz_kurulum') {
                self::assertTrue($secenek['yikici']);
                self::assertStringContainsString('SIFIRLA', $secenek['aciklama']);

                return;
            }
        }

        self::fail('Temiz kurulum seçeneği bulunamadı.');
    }

    public function testHerDurumunBirSecenegiVardir(): void
    {
        // D2-REV sözü: hiçbir durum çıkmaz sokak değildir.
        $durumlar = [
            SetupSituation::KURULUM_YOK,
            SetupSituation::SAGLIKLI,
            SetupSituation::YARIM,
            SetupSituation::CONFIG_KAYIP,
            SetupSituation::DOSYA_EKSIK,
            SetupSituation::MIGRATION_YARIM,
            SetupSituation::SURUM_UYUSMAZLIGI,
            SetupSituation::DB_ERISILEMIYOR,
        ];

        $sinif = new \ReflectionClass(SetupSituation::class);
        $metot = $sinif->getMethod('secenekler');
        $ornek = $sinif->newInstanceWithoutConstructor();

        foreach ($durumlar as $durum) {
            /** @var list<array<string, mixed>> $secenekler */
            $secenekler = $metot->invoke($ornek, $durum);
            self::assertNotSame([], $secenekler, $durum . ' durumunda seçenek yok');
        }
    }

    /**
     * Verilen kurulumu SQLite üzerinde hazırlayıp teşhis motorunu koşar.
     *
     * Not: teşhis motoru bağlantıyı config.php üzerinden KENDİSİ açar; testte de
     * gerçek dosya tabanlı SQLite kullanılır ki "bağlanabildi mi" sorusu sahte
     * olmasın.
     *
     * @param callable(PDO): void $hazirla
     *
     * @return array<string, mixed>
     */
    private function motorIle(callable $hazirla): array
    {
        $dbPath = $this->tempPath('test.sqlite');
        if (!is_file($this->tempPath('config.php'))) {
            $dbPath = $this->sqliteKurulumuYaz();
        }

        $db = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $hazirla($db);

        return $this->motor(static fn (): Connection => Connection::fromCallable(static fn (): PDO => $db));
    }
}
