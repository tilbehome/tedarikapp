<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Middleware\SetupCsrf;
use App\Setup\SetupLock;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * SERTLEŞTİRME v1.2.1 BLOK C5 — OWNER-CHECK SABİT YANIT (TDR-019).
 *
 * KUSUR: `/api/setup/owner-check` KİMLİKSİZ bir oracle'dı. Bir e-posta gönderip
 * "bu hesapta 2FA var mı?" sorusunun cevabını alabiliyordunuz. Bu iki şey
 * sızdırır:
 *
 *   · HESABIN VARLIĞI — 2FA'lı hesaplar `true` döner, olmayan hesap `false`.
 *     Bir e-posta listesini tarayan biri hangi adreslerin sistemde olduğunu
 *     daraltabilir.
 *   · SAVUNMA DURUMU — "bu yönetici 2FA KULLANMIYOR" bilgisi, şifre denemesi
 *     yapacak biri için hedef seçme ölçütüdür.
 *
 * Üstelik uçta HIZ SINIRI ve DENETİM KAYDI yoktu: tarama ne yavaşlıyor ne de
 * iz bırakıyordu.
 *
 * YENİ SÖZLEŞME: yanıt SABİTTİR — hesap var mı, 2FA açık mı, hiçbiri
 * ayırt edilemez. Kod alanı her zaman gösterilir ve İSTEĞE BAĞLIDIR; gerçek
 * zorunluluk doğrulama adımında SUNUCUDA uygulanır (orada zaten kimlik var).
 *
 * UX BEDELİ BİLİNÇLİ: 2FA kullanmayan kullanıcı boş bir kod kutusu görür.
 * Alternatifi, kimliksiz bir kişiye "bu hesapta 2FA yok" demekti.
 */
final class OwnerCheckSabitYanitTest extends TestCase
{
    use TempDirectory;

    private PDO $pdo;
    private ArraySession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }

        $this->session = new ArraySession();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, totp_secret TEXT NULL)');
        $this->pdo->exec("INSERT INTO users (email, totp_secret) VALUES ('ikili@ornek.test', 'GIZLISIR')");
        $this->pdo->exec("INSERT INTO users (email, totp_secret) VALUES ('yalin@ornek.test', NULL)");
        // ŞEMA GERÇEĞİN AYNISI OLMALI: kolon adı `detail` (tekil). Testin
        // uydurduğu bir şema, sessizce yazamayan bir denetim kaydını "yazıldı"
        // sanmama yol açardı — ActivityLog hatayı yutar (tablo yoksa kapı yine
        // karar verebilsin diye).
        $this->pdo->exec(
            'CREATE TABLE activity_log (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT NOT NULL,
             entity_id INTEGER NULL, action TEXT NOT NULL, detail TEXT NULL, ip TEXT NULL,
             actor_type TEXT NOT NULL DEFAULT "admin", actor_id INTEGER NULL, request_id TEXT NULL,
             user_agent TEXT NULL, created_at TEXT NOT NULL)',
        );
    }

    private function sorgula(string $email, string $ip = '203.0.113.9'): ResponseInterface
    {
        // Sihirbaz kimlik doğrulaması olmayan tek yüzeydir; CSRF token'ı
        // oturumda yaşar ve gerçek arayüz onu sayfa açılınca alır. Test de
        // aynı yolu izler — token'ı elle uydurmak, kapının varlığını
        // doğrulamayı atlardı.
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/setup/owner-check', ['REMOTE_ADDR' => $ip])
            ->withParsedBody(['email' => $email])
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(SetupCsrf::HEADER, $this->csrfToken());

        return $this->uygulama()->handle($request);
    }

    private function uygulama(): \Slim\App
    {
        $lock = new SetupLock(
            Connection::fromCallable(fn (): PDO => $this->pdo),
            $this->tempPath('storage'),
        );

        return SetupAppBuilder::build(
            $this->tempRoot(),
            new NullLogger(),
            $this->session,
            new FrozenClock(),
            setupLock: $lock,
            appEnv: 'local',
        );
    }

    private function csrfToken(): string
    {
        return (new \App\Setup\SetupState($this->session))->csrfToken();
    }

    /** @return array<string, mixed> */
    private function govde(ResponseInterface $yanit): array
    {
        /** @var array<string, mixed> $cozulmus */
        $cozulmus = json_decode((string) $yanit->getBody(), true) ?: [];

        return $cozulmus;
    }

    public function testUCDURUMDAAYNIYANIT(): void
    {
        // 2FA'lı hesap · 2FA'sız hesap · HİÇ OLMAYAN hesap → aynı gövde.
        $ikili = (string) $this->sorgula('ikili@ornek.test')->getBody();
        $yalin = (string) $this->sorgula('yalin@ornek.test')->getBody();
        $yok = (string) $this->sorgula('hicyok@ornek.test')->getBody();

        self::assertSame($ikili, $yalin, '2FA\'lı ve 2FA\'sız hesap AYIRT EDİLEMEMELİ.');
        self::assertSame($yalin, $yok, 'Var olan ve olmayan hesap AYIRT EDİLEMEMELİ.');
    }

    public function testYANITHEPKODALANINIISTER(): void
    {
        // Sabit yanıt "her zaman kod alanını göster" yönünde olmalı: ters yön
        // (her zaman gizle) 2FA'lı kullanıcıyı kod giremez hâle getirirdi.
        $govde = $this->govde($this->sorgula('ikili@ornek.test'));

        self::assertTrue($govde['data']['iki_adimli'] ?? null, json_encode($govde, JSON_UNESCAPED_UNICODE));
    }

    public function testHIZSINIRIVAR(): void
    {
        // Tarama yavaşlamalı. Sınır IP başınadır; hesap adı değişse de sayar —
        // yoksa saldırgan her istekte başka e-posta yazıp sınırı atlardı.
        $sonYanit = null;
        for ($i = 0; $i < 25; $i++) {
            $sonYanit = $this->sorgula('deneme' . $i . '@ornek.test', '198.51.100.4');
        }

        self::assertNotNull($sonYanit);
        self::assertSame(429, $sonYanit->getStatusCode(), 'Kimliksiz tarama hız sınırına takılmalı.');
    }

    public function testDENETIMKAYDIYAZILIR(): void
    {
        $this->sorgula('ikili@ornek.test');

        $sayi = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM activity_log WHERE action = 'owner_check'",
        )->fetchColumn();

        self::assertGreaterThan(0, $sayi, 'Kimliksiz sorgu iz BIRAKMALI.');
    }

    public function testDENETIMKAYDIEPOSTAYIHAMYAZMAZ(): void
    {
        // İz gerekli ama günlüğe ham e-posta dökmek, günlüğü okuyabilen birine
        // hazır bir hesap listesi verir (K51 log disiplini).
        $this->sorgula('ikili@ornek.test');

        $detay = (string) $this->pdo->query(
            "SELECT detail FROM activity_log WHERE action = 'owner_check' LIMIT 1",
        )->fetchColumn();

        self::assertStringNotContainsString('ikili@ornek.test', $detay);
    }
}
