<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\SettingsRepository;
use App\Services\MediaException;
use App\Services\MediaService;
use App\Services\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * K33/K34 KRİTİK — medya alma zinciri.
 *
 * Sunucu dışarıdan gelen bir URL'yi kendisi çektiği için burası SSRF yüzeyi; indirilen
 * dosya yazılabilir ve webden erişilebilir bir klasöre düştüğü için de kod çalıştırma
 * yüzeyi. Bu yüzden dört şey ayrı ayrı sınanır: adres denetimi, gerçek tür denetimi,
 * yeniden kodlama, ad üretimi. Ayrıca yazılamayan sunucuda hotlink moduna düşüş.
 */
final class MediaServiceTest extends AuthTestCase
{
    use TempDirectory;

    private FakeMediaFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetcher = new FakeMediaFetcher();
        mkdir($this->tempPath('public/media'), 0775, true);
    }

    private function media(?string $mediaPath = 'public/media'): MediaService
    {
        return new MediaService(
            $this->tempRoot(),
            new UrlGuard(['alicdn.com', '1688.com']),
            $this->fetcher,
            new SettingsRepository($this->connection),
            8 * 1024 * 1024,
            $mediaPath ?? 'public/media',
        );
    }

    /** Gerçek, geçerli bir JPEG üretir (GD ile). */
    private function jpeg(int $width = 40, int $height = 30): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 12, 200, 90));
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    // ─────────────── Mod seçimi ───────────────

    public function testYazilabilirKlasordeIndirmeModu(): void
    {
        self::assertSame(MediaService::MODE_DOWNLOAD, $this->media()->detectMode());
        self::assertTrue($this->media()->isWritable());
    }

    public function testYazilamazKlasordeHotlinkModu(): void
    {
        // Var olmayan klasör = yazılamaz (üretimdeki DSO durumunun testteki karşılığı).
        $media = $this->media('public/olmayan-klasor');

        self::assertFalse($media->isWritable());
        self::assertSame(MediaService::MODE_HOTLINK, $media->detectMode());
    }

    public function testModAyardaSaklanirVeOkunur(): void
    {
        $media = $this->media();
        $media->rememberMode(MediaService::MODE_HOTLINK);

        // Klasör yazılabilir olsa BİLE ayarda hotlink yazıyorsa o geçerlidir.
        self::assertSame(MediaService::MODE_HOTLINK, $media->mode());
        self::assertSame(MediaService::MODE_HOTLINK, (new SettingsRepository($this->connection))->get('media_mode'));
    }

    public function testBilinmeyenModReddedilir(): void
    {
        $this->expectException(MediaException::class);
        $this->media()->rememberMode('bulut');
    }

    // ─────────────── İndirme ve yeniden kodlama ───────────────

    /**
     * K37 §C8: kaydedilen dosyanın UZANTISI ile GERÇEK içerik türü her zaman uyuşur.
     * Encoder eksikliğinde (webp/avif olmayan GD) jpeg'e düşülür ve ad da .jpg olur —
     * "webp adında jpeg" yapısal olarak üretilemez.
     */
    public function testKaydedilenDosyaninUzantisiGercekIcerikleUyusur(): void
    {
        // PNG kaynak: png olarak yeniden kodlanır.
        $png = (function (): string {
            $image = imagecreatetruecolor(10, 10);
            ob_start();
            imagepng($image);

            return (string) ob_get_clean();
        })();

        $url = 'https://cbu01.alicdn.com/img/ibank/ornek.png';
        $this->fetcher->respondWith($url, $png, 'image/png');

        $result = $this->media()->store($url);

        self::assertIsString($result['path']);
        $extension = pathinfo($result['path'], PATHINFO_EXTENSION);
        $info = getimagesizefromstring((string) file_get_contents($this->tempPath($result['path'])));
        self::assertNotFalse($info);

        $mimeByExtension = [
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'avif' => 'image/avif',
        ];
        self::assertSame($mimeByExtension[$extension], $info['mime'], 'Uzantı ile gerçek tür UYUŞMALI (K37 §C8).');
    }

    public function testGorselIndirilirYenidenKodlanirVeRastgeleAdlaKaydedilir(): void
    {
        $url = 'https://cbu01.alicdn.com/img/ibank/ornek.jpg';
        $original = $this->jpeg();
        $this->fetcher->respondWith($url, $original, 'image/jpeg');

        $result = $this->media()->store($url);

        self::assertSame(MediaService::MODE_DOWNLOAD, $result['mode']);
        self::assertIsString($result['path']);
        self::assertMatchesRegularExpression('#^public/media/[0-9a-f]{32}\.jpg$#', $result['path']);
        self::assertFileExists($this->tempPath($result['path']));

        // Web adresi `public/` önekini TAŞIMAZ: Apache'nin docroot'u zaten `public/`.
        // Önekli adres panelde 404 döner (İE#8 canlı koşumunda yakalandı).
        self::assertMatchesRegularExpression('#^/media/[0-9a-f]{32}\.jpg$#', $result['url']);

        $stored = (string) file_get_contents($this->tempPath($result['path']));
        // Yeniden kodlama: çıktı kaynağın baytlarından BAĞIMSIZ olmalı.
        self::assertNotSame($original, $stored, 'Dosya olduğu gibi kopyalanmamalı, yeniden üretilmeli.');
        // Ama hâlâ geçerli ve aynı boyutta bir görsel olmalı.
        $info = getimagesizefromstring($stored);
        self::assertIsArray($info);
        self::assertSame([40, 30], [$info[0], $info[1]]);
    }

    public function testDosyaAdiKaynaktanTasinmaz(): void
    {
        // Saldırgan adı: yol kaçışı + çalıştırılabilir uzantı. Ad sunucuda üretilir.
        $url = 'https://cbu01.alicdn.com/img/ibank/..%2F..%2Fshell.php.jpg';
        $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');

        $result = $this->media()->store($url);

        self::assertIsString($result['path']);
        self::assertStringNotContainsString('shell', $result['path']);
        self::assertStringNotContainsString('..', $result['path']);
        self::assertStringEndsWith('.jpg', $result['path']);
    }

    public function testHerIndirmeFarkliAdUretir(): void
    {
        $urlA = 'https://cbu01.alicdn.com/a.jpg';
        $urlB = 'https://cbu01.alicdn.com/b.jpg';
        $this->fetcher->respondWith($urlA, $this->jpeg(), 'image/jpeg');
        $this->fetcher->respondWith($urlB, $this->jpeg(), 'image/jpeg');

        $media = $this->media();

        self::assertNotSame($media->store($urlA)['path'], $media->store($urlB)['path']);
    }

    public function testGorselOlmayanDosyaReddedilir(): void
    {
        $url = 'https://cbu01.alicdn.com/kotu.jpg';
        $this->fetcher->respondWith($url, '<?php echo "calisirim"; ?>', 'image/jpeg');

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('geçerli bir görsel değil');
        $this->media()->store($url);
    }

    public function testSvgReddedilir(): void
    {
        $url = 'https://cbu01.alicdn.com/kotu.svg';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->fetcher->respondWith($url, $svg, 'image/svg+xml');

        // SVG'nin içine script gömülebilir ve tarayıcıda çalışır (docs/04 §2d).
        $this->expectException(MediaException::class);
        $this->media()->store($url);
    }

    public function testHotlinkModundaIndirmeDENENMEZ(): void
    {
        $url = 'https://cbu01.alicdn.com/img/ibank/ornek.jpg';
        $media = $this->media();
        $media->rememberMode(MediaService::MODE_HOTLINK);

        $result = $media->store($url);

        self::assertSame(MediaService::MODE_HOTLINK, $result['mode']);
        self::assertNull($result['path']);
        self::assertSame($url, $result['url'], 'Hotlink modunda orijinal URL saklanır.');
        self::assertSame(0, $this->fetcher->callCount, 'Hotlink modunda ağa çıkılmamalı.');
    }

    public function testYonlendirmeHedefiDeDenetlenir(): void
    {
        $url = 'https://cbu01.alicdn.com/img/ibank/ornek.jpg';
        // Kaynak izinli ama yönlendirme başka bir alan adına gidiyor.
        $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg', 'https://evil.example.com/x.jpg');

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Bu alan adından indirme yapılmaz');
        $this->media()->store($url);
    }

    // ─────────────── SSRF kapısı ───────────────

    /** @return list<array{string, string}> */
    public static function reddedilecekAdresler(): array
    {
        return [
            'http (şifresiz)' => ['http://cbu01.alicdn.com/a.jpg', 'https'],
            'izinsiz alan adı' => ['https://evil.example.com/a.jpg', 'indirme yapılmaz'],
            'sonek tuzağı' => ['https://alicdn.com.evil.com/a.jpg', 'indirme yapılmaz'],
            // file:// adresinde host yoktur; şema denetimine bile gelmeden reddedilir.
            'file şeması' => ['file:///etc/passwd', 'Geçersiz adres'],
            'ftp şeması' => ['ftp://cbu01.alicdn.com/a.jpg', 'https'],
            'yerel adres' => ['https://127.0.0.1/a.jpg', 'indirme yapılmaz'],
            'bozuk adres' => ['bu-adres-degil', 'Geçersiz adres'],
        ];
    }

    #[DataProvider('reddedilecekAdresler')]
    public function testTehlikeliAdreslerReddedilir(string $url, string $beklenenMesaj): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($beklenenMesaj, '/') . '/u');
        $this->media()->store($url);
    }

    public function testAltAlanAdiIzinlidir(): void
    {
        $url = 'https://cbu01.alicdn.com/img/ibank/ornek.jpg';
        $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');

        self::assertSame(MediaService::MODE_DOWNLOAD, $this->media()->store($url)['mode']);
    }

    public function testSsrfDenetimiHotlinkModundaDaYapilir(): void
    {
        // Hotlink modunda URL kullanıcıya servis edilecek — denetim atlanamaz.
        $media = $this->media();
        $media->rememberMode(MediaService::MODE_HOTLINK);

        $this->expectException(MediaException::class);
        $media->store('https://evil.example.com/a.jpg');
    }
}
