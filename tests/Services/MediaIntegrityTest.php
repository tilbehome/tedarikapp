<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\SettingsRepository;
use App\Services\MediaIntegrity;
use App\Services\MediaService;
use App\Services\UrlGuard;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * İE#10 5d — medya bütünlük denetimi: DB↔disk ayrışması onarılır (canlı vaka).
 * Kurallar: kayıp dosya kaydı BOZMAZ; onarım kayıtlı kaynaktan indirir; kaynağı
 * olmayan kayıt raporlanır; sağlam kayda dokunulmaz; idempotent.
 */
final class MediaIntegrityTest extends AuthTestCase
{
    use TempDirectory;

    private FakeMediaFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetcher = new FakeMediaFetcher();
        mkdir($this->tempPath('public/media'), 0775, true);
    }

    private function integrity(): MediaIntegrity
    {
        $media = new MediaService(
            $this->tempRoot(),
            new UrlGuard(['alicdn.com', '1688.com']),
            $this->fetcher,
            new SettingsRepository($this->connection),
            8 * 1024 * 1024,
        );

        return new MediaIntegrity($this->connection, $media);
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(20, 20);
        self::assertNotFalse($image);
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    private function seedProduct(?string $mainImage, ?string $source = null): int
    {
        $this->pdo->exec("INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at) VALUES ('L', '7', '41', '2026-08-19', '2026-08-19')");
        $listId = (int) $this->pdo->lastInsertId();
        $statement = $this->pdo->prepare(
            "INSERT INTO products (list_id, name, main_image, main_image_source, created_at, updated_at) VALUES (:l, 'Ürün', :m, :s, '2026-08-19', '2026-08-19')",
        );
        $statement->execute(['l' => $listId, 'm' => $mainImage, 's' => $source]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testKayipDosyaKaynaktanOnarilir(): void
    {
        $source = 'https://cbu01.alicdn.com/img/ibank/kayip.jpg';
        $this->fetcher->respondWith($source, $this->jpeg(), 'image/jpeg');
        // DB "arşivlendi" diyor ama dosya diskte YOK (canlı vaka).
        $productId = $this->seedProduct('/media/' . str_repeat('0', 32) . '.jpg', $source);

        $result = $this->integrity()->repairBatch();

        self::assertSame(1, $result['missing']);
        self::assertSame(1, $result['repaired']);
        $newPath = (string) $this->pdo->query('SELECT main_image FROM products WHERE id = ' . $productId)->fetchColumn();
        self::assertStringStartsWith('/media/', $newPath);
        self::assertFileExists($this->tempPath('public' . $newPath), 'Onarılan dosya diskte OLMALI.');
    }

    public function testKaynaksizKayipKayitBozulmadanRaporlanir(): void
    {
        $broken = '/media/' . str_repeat('1', 32) . '.jpg';
        $productId = $this->seedProduct($broken, null);

        $result = $this->integrity()->repairBatch();

        self::assertSame(0, $result['repaired']);
        self::assertCount(1, $result['failed']);
        self::assertStringContainsString('Orijinal adres kayıtlı değil', $result['failed'][0]['error']);
        self::assertSame($broken, (string) $this->pdo->query('SELECT main_image FROM products WHERE id = ' . $productId)->fetchColumn(), 'Kayıt BOZULMAMALI.');
    }

    public function testSaglamKaydaDokunulmaz(): void
    {
        // Gerçekten var olan dosya: önce arşive normal yoldan alınmış gibi diske yaz.
        $name = str_repeat('2', 32) . '.jpg';
        file_put_contents($this->tempPath('public/media/' . $name), $this->jpeg());
        $this->seedProduct('/media/' . $name, 'https://cbu01.alicdn.com/img/ibank/saglam.jpg');

        $result = $this->integrity()->repairBatch();

        self::assertSame(1, $result['checked']);
        self::assertSame(0, $result['missing']);
        self::assertSame(0, $this->fetcher->callCount, 'Sağlam kayıt için indirme YAPILMAMALI.');
    }

    public function testTekUrunOnarimi_UzakGorselArsiveAlinir(): void
    {
        $url = 'https://cbu01.alicdn.com/img/ibank/uzak.jpg';
        $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');
        $productId = $this->seedProduct($url, null);

        $result = $this->integrity()->repairProduct($productId);

        self::assertTrue($result['repaired']);
        self::assertStringStartsWith('/media/', (string) $result['main_image']);
        // Kaynak adres de kaydedildi — bir daha kaybolursa onarılabilir.
        self::assertSame($url, (string) $this->pdo->query('SELECT main_image_source FROM products WHERE id = ' . $productId)->fetchColumn());
    }

    public function testTekUrunOnarimi_KaynaksizYerelKayipAnlasilirHataDoner(): void
    {
        $productId = $this->seedProduct('/media/' . str_repeat('3', 32) . '.jpg', null);

        $result = $this->integrity()->repairProduct($productId);

        self::assertFalse($result['repaired']);
        self::assertStringContainsString('Orijinal adres kayıtlı değil', (string) $result['error']);
    }
}
