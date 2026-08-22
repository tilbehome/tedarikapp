<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Middleware\MigrationGuard;
use App\Models\SettingsRepository;
use App\Services\MediaException;
use App\Services\MediaService;
use App\Services\UrlGuard;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * İE#19 — dayanıklılık paketi: E7 (piksel sınırı, atomik medya), G7 (MigrationGuard
 * fail-closed), E9 (release kapısı kuralları kaynakta), G8 (yedek kapsamı).
 */
final class DayaniklilikPaketiTest extends TestCase
{
    use TempDirectory;

    // ── E7: piksel sınırı ve atomik yazım ───────────────────────────────────
    private function media(FakeMediaFetcher $fetcher): MediaService
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NULL)');
        mkdir($this->tempPath('public/media'), 0775, true);

        return new MediaService(
            $this->tempRoot(),
            new UrlGuard(['alicdn.com']),
            $fetcher,
            new SettingsRepository(Connection::fromCallable(static fn (): PDO => $pdo)),
            8 * 1024 * 1024,
            'public/media',
        );
    }

    public function testCokBuyukPikselliGorselREDDEDILIR(): void
    {
        // 40 MP üstü bir PNG başlığı: içerik küçük, açılınca dev. "Decompression bomb".
        $fetcher = new FakeMediaFetcher();
        // 81 MP: içerik küçük, açılınca dev. "Decompression bomb".
        $fetcher->respondWith('https://cbu01.alicdn.com/img/bomba.png', $this->sahtePngBasligi(9000, 9000), 'image/png');

        $this->expectException(MediaException::class);
        $this->expectExceptionMessageMatches('/megapiksel/');

        $this->media($fetcher)->store('https://cbu01.alicdn.com/img/bomba.png');
    }

    public function testMakulGorselKABULEDILIR(): void
    {
        $fetcher = new FakeMediaFetcher();
        $fetcher->respondWith('https://cbu01.alicdn.com/img/kucuk.png', $this->gercekKucukPng(), 'image/png');

        $sonuc = $this->media($fetcher)->store('https://cbu01.alicdn.com/img/kucuk.png');

        self::assertSame('download', $sonuc['mode']);
        self::assertNotNull($sonuc['path']);
    }

    public function testErtelenmisYazimCOMMITEKADARKALICIDEGIL(): void
    {
        $fetcher = new FakeMediaFetcher();
        $fetcher->respondWith('https://cbu01.alicdn.com/img/kucuk.png', $this->gercekKucukPng(), 'image/png');
        $media = $this->media($fetcher);

        $sonuc = $media->store('https://cbu01.alicdn.com/img/kucuk.png', ertele: true);

        self::assertNotNull($sonuc['temp']);
        self::assertFileExists($this->tempRoot() . '/' . $sonuc['temp']);
        self::assertFileDoesNotExist(
            $this->tempRoot() . '/' . (string) $sonuc['path'],
            'Kalıcı dosya commit\'ten ÖNCE oluşmuş — işlem geri sararsa yetim kalır.',
        );

        $media->commit($sonuc);

        self::assertFileExists($this->tempRoot() . '/' . (string) $sonuc['path']);
        self::assertFileDoesNotExist($this->tempRoot() . '/' . (string) $sonuc['temp']);
    }

    public function testGeriSarmadaGECICIDOSYASILINIR(): void
    {
        $fetcher = new FakeMediaFetcher();
        $fetcher->respondWith('https://cbu01.alicdn.com/img/kucuk.png', $this->gercekKucukPng(), 'image/png');
        $media = $this->media($fetcher);

        $sonuc = $media->store('https://cbu01.alicdn.com/img/kucuk.png', ertele: true);
        $media->discard($sonuc);

        self::assertFileDoesNotExist($this->tempRoot() . '/' . (string) $sonuc['temp'], 'Yetim medya bırakıldı.');
        self::assertFileDoesNotExist($this->tempRoot() . '/' . (string) $sonuc['path']);
    }

    // ── G7: MigrationGuard fail-closed ──────────────────────────────────────
    public function testVeritabaniYOKSAVeriUclariniGECIRMEZ(): void
    {
        $guard = new MigrationGuard(
            Connection::fromCallable(static function (): PDO {
                throw new \RuntimeException('DB yok (test)');
            }),
            dirname(__DIR__, 2) . '/migrations',
            new ResponseFactory(),
        );

        $yanit = $guard->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/lists', ['REMOTE_ADDR' => '127.0.0.1']),
            $this->gecirenHandler(),
        );

        self::assertSame(503, $yanit->getStatusCode());
        self::assertStringContainsString('SCHEMA_STATE_UNKNOWN', (string) $yanit->getBody());
    }

    public function testDefterYokAmaVeritabaniVarsaISTEKGECER(): void
    {
        // Taze kurulum / test şeması: `migrations` tablosu yok ama DB ayakta.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $guard = new MigrationGuard(
            Connection::fromCallable(static fn (): PDO => $pdo),
            dirname(__DIR__, 2) . '/migrations',
            new ResponseFactory(),
        );

        $yanit = $guard->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/lists', ['REMOTE_ADDR' => '127.0.0.1']),
            $this->gecirenHandler(),
        );

        self::assertSame(200, $yanit->getStatusCode(), 'Taze kurulum bloklanmamalı (K45 ruhu).');
    }

    public function testBekleyenMigrationVarsa503(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO migrations (name) VALUES ('0001_create_users')");

        $guard = new MigrationGuard(
            Connection::fromCallable(static fn (): PDO => $pdo),
            dirname(__DIR__, 2) . '/migrations',
            new ResponseFactory(),
        );

        $yanit = $guard->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/lists', ['REMOTE_ADDR' => '127.0.0.1']),
            $this->gecirenHandler(),
        );

        self::assertSame(503, $yanit->getStatusCode());
        self::assertStringContainsString('MIGRATION_PENDING', (string) $yanit->getBody());
    }

    // ── E9: release kapısı kuralları ────────────────────────────────────────
    public function testReleaseKapisiKURALLARIKAYNAKTA(): void
    {
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/release.php');

        self::assertStringContainsString('--panel-dal ZORUNLU', $kaynak, 'Parametresiz çağrı reddi yok.');
        self::assertStringContainsString('temiz=false', $kaynak, 'Kirli çalışma kopyası reddi yok.');
        self::assertStringContainsString('panel_commit', $kaynak, 'Damga özeti MANIFEST\'e girmiyor.');
        self::assertStringContainsString("locateName('config.php')", $kaynak, 'config.php sızıntı denetimi yok.');
        self::assertStringContainsString("locateName('public/tani.php')", $kaynak, 'tani.php dışlama denetimi yok.');
        self::assertStringContainsString('config.example.php', $kaynak, 'Örnek yapılandırma pakete girmiyor.');
    }

    public function testTaniPhpPAKETLEMEDENDISLANIR(): void
    {
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/release.php');

        self::assertStringContainsString("\$relative === 'public/tani.php'", $kaynak);
    }

    private function gecirenHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return (new ResponseFactory())->createResponse(200);
            }
        };
    }

    /** getimagesizefromstring'in okuyabileceği asgari PNG başlığı (içerik açılmaz). */
    private function sahtePngBasligi(int $genislik, int $yukseklik): string
    {
        $ihdr = pack('N', $genislik) . pack('N', $yukseklik) . "\x08\x06\x00\x00\x00";
        $chunk = pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));

        return "\x89PNG\r\n\x1a\n" . $chunk;
    }

    /** GD ile üretilmiş gerçek, küçük bir PNG. */
    private function gercekKucukPng(): string
    {
        $image = imagecreatetruecolor(4, 4);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
