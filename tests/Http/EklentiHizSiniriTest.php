<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Middleware\ExtensionAuth;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * SERTLEŞTİRME v1.2.1 BLOK D5 — EKLENTİ HIZ SINIRI (TDR-021).
 *
 * İKİ KUSUR:
 *
 * 1. SAYIM YARIŞA AÇIKTI. Önce `SELECT COUNT(*)`, sonra `INSERT` yapılıyordu.
 *    Elli paralel istek AYNI sayıyı okur ve hepsi sınırın altında görünür;
 *    sonra hepsi kendi satırını ekler. Sınır 10 iken 50 istek geçebiliyordu —
 *    yani sınır pratikte YOKTU, yalnız seri trafikte çalışıyordu.
 *
 *    Düzeltme sırayı TERSİNE çevirir: ÖNCE KENDİ SATIRINI EKLE, SONRA SAY.
 *    Böylece her istek kendi kaydını görür ve eşzamanlı N istekten N'incisi
 *    en az N sayar. Sınır kenarında biraz FAZLA engelleme olabilir; bu GÜVENLİ
 *    yöndeki hatadır ve yeni tablo/dialekt-özel upsert gerektirmez.
 *
 * 2. DB HATASI KAPIYI AÇIYORDU. `catch (\Throwable) { }` — sayaç okunamazsa
 *    istek geçiyordu. Gerekçe "hız sınırı koruma katmanıdır, kapı değil" idi;
 *    doğru bir ilke ama YANLIŞ UÇTA: capture ucu kimliksiz dünyaya bakan ve
 *    veri YAZAN bir uçtur. Veritabanını bir an düşürebilen biri, sınırsız
 *    yazma hakkı elde ediyordu. Bu uçta fail-CLOSED doğrudur.
 */
final class EklentiHizSiniriTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec(
            'CREATE TABLE activity_log (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT NOT NULL,
             entity_id INTEGER NULL, action TEXT NOT NULL, detail TEXT NULL, ip TEXT NULL,
             actor_type TEXT NOT NULL DEFAULT "admin", actor_id INTEGER NULL, request_id TEXT NULL,
             user_agent TEXT NULL, created_at TEXT NOT NULL)',
        );
        $this->pdo->exec('CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NULL)');
        // Token DB'de yalnız HASH olarak durur (K34).
        $this->pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)')
            ->execute(['k' => 'extension_token_hash', 'v' => hash('sha256', 'gizli-token')]);
    }

    private function kapi(?Connection $baglanti = null, int $dakikadaKac = 3): ExtensionAuth
    {
        return new ExtensionAuth(
            $baglanti ?? Connection::fromCallable(fn (): PDO => $this->pdo),
            new ResponseFactory(),
            '*',
            $dakikadaKac,
            new \DateTimeZone('Europe/Istanbul'),
        );
    }

    private function istek(): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/extension/capture', ['REMOTE_ADDR' => '203.0.113.20'])
            ->withHeader('Authorization', 'Bearer gizli-token');
    }

    private function isleyici(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(204);
            }
        };
    }

    public function testSINIRAKADAROLANISTEKLERGECER(): void
    {
        $kapi = $this->kapi(dakikadaKac: 3);

        for ($i = 0; $i < 3; $i++) {
            self::assertNotSame(429, $kapi->process($this->istek(), $this->isleyici())->getStatusCode());
        }
    }

    public function testSINIRUSTUISTEK429(): void
    {
        $kapi = $this->kapi(dakikadaKac: 3);
        for ($i = 0; $i < 3; $i++) {
            $kapi->process($this->istek(), $this->isleyici());
        }

        self::assertSame(429, $kapi->process($this->istek(), $this->isleyici())->getStatusCode());
    }

    public function testHERISTEKKENDISATIRINISAYAR(): void
    {
        $kapi = $this->kapi(dakikadaKac: 1);

        self::assertNotSame(429, $kapi->process($this->istek(), $this->isleyici())->getStatusCode());
        self::assertSame(
            429,
            $kapi->process($this->istek(), $this->isleyici())->getStatusCode(),
            'İkinci istek, birincinin kaydını GÖRMELİ.',
        );
    }

    public function testYAZIMSAYIMDANONCEGELIR(): void
    {
        // DÜRÜST SINIR: tek süreçli bir test GERÇEK eşzamanlılığı üretemez —
        // yukarıdaki test eski (yarışa açık) sıralamayla da yeşil kalırdı.
        // Yarışı kapatan şey SIRALAMANIN KENDİSİDİR, o yüzden sıra doğrudan
        // sınanır: INSERT, SELECT COUNT'tan ÖNCE gelmeli.
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Middleware/ExtensionAuth.php');
        $ekleme = strpos($kaynak, 'INSERT INTO activity_log');
        $sayim = strpos($kaynak, 'SELECT COUNT(*) FROM activity_log');

        self::assertIsInt($ekleme);
        self::assertIsInt($sayim);
        self::assertLessThan(
            $sayim,
            $ekleme,
            'Sayım yazımdan ÖNCE yapılıyor: eşzamanlı istekler aynı sayıyı okur ve hepsi geçer.',
        );
    }

    public function testDBHATASINDAFAILCLOSED(): void
    {
        // ASIL KORUMA: veritabanını bir an düşürebilen biri, sınırsız yazma
        // hakkı elde ETMEMELİ. Capture ucu kimliksiz dünyaya bakar ve veri yazar.
        $bozuk = Connection::fromCallable(static function (): PDO {
            throw new RuntimeException('DB yok (test)');
        });

        $yanit = $this->kapi($bozuk)->process($this->istek(), $this->isleyici());

        // 401 (token doğrulanamadı) ya da 429 (sayaç okunamadı) — ikisi de
        // REDDETMEDİR. Sınanan şey kod değil, isteğin GEÇMEMESİ; hangi kapının
        // önce kapandığını çivilemek testi kırılgan yapardı.
        self::assertContains(
            $yanit->getStatusCode(),
            [401, 429],
            'Veritabanı düştüğünde capture ucu istek GEÇİRMEMELİ.',
        );
        self::assertNotSame(204, $yanit->getStatusCode());
    }

    public function testYETKISIZTOKENSINIRDANONCEREDDEDILIR(): void
    {
        // Sıra önemli: yetkisiz istek sayaç yazmadan düşmeli, yoksa saldırgan
        // geçersiz token'la meşru kullanıcının kotasını doldurabilirdi.
        $istek = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/extension/capture', ['REMOTE_ADDR' => '203.0.113.20'])
            ->withHeader('Authorization', 'Bearer yanlis');

        $yanit = $this->kapi()->process($istek, $this->isleyici());

        self::assertSame(401, $yanit->getStatusCode());
        self::assertSame(
            0,
            (int) $this->pdo->query("SELECT COUNT(*) FROM activity_log WHERE action = 'capture_request'")->fetchColumn(),
            'Yetkisiz istek kota TÜKETMEMELİ.',
        );
    }
}
