<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\SettingsRepository;
use App\Services\MediaMigrator;
use App\Services\MediaService;
use App\Services\UrlGuard;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * K47 Görev 2/5 — toplu arşive geçiş: idempotens, kayıt bütünlüğü, mod kapısı.
 *
 * Ağ yok (FakeMediaFetcher); disk geçici klasördür. KRİTİK üç kural:
 *  1. Başarıyla taşınan kayıt bir daha İŞLENMEZ (idempotens).
 *  2. Başarısız indirme kaydı BOZMAZ: URL aynen kalır, sonraki koşumda yeniden denenir.
 *  3. Yazılamaz diskte araç hiç indirme YAPMAZ.
 */
final class MediaMigratorTest extends AuthTestCase
{
    use TempDirectory;

    private FakeMediaFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetcher = new FakeMediaFetcher();
        mkdir($this->tempPath('public/media'), 0775, true);
    }

    private function migrator(?string $mediaPath = 'public/media'): MediaMigrator
    {
        $media = new MediaService(
            $this->tempRoot(),
            new UrlGuard(['alicdn.com', '1688.com']),
            $this->fetcher,
            new SettingsRepository($this->connection),
            8 * 1024 * 1024,
            $mediaPath ?? 'public/media',
        );

        return new MediaMigrator($this->connection, $media);
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(20, 20);
        self::assertNotFalse($image);
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    private function seedProduct(string $mainImage): int
    {
        $this->pdo->exec("INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at) VALUES ('L', '7', '41', '2026-08-17', '2026-08-17')");
        $listId = (int) $this->pdo->lastInsertId();
        $statement = $this->pdo->prepare(
            "INSERT INTO products (list_id, name, main_image, created_at, updated_at) VALUES (:l, 'Ürün', :m, '2026-08-17', '2026-08-17')",
        );
        $statement->execute(['l' => $listId, 'm' => $mainImage]);

        return (int) $this->pdo->lastInsertId();
    }

    private function seedGalleryImage(int $productId, string $sourceUrl): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO product_images (product_id, path, storage_mode, source_url) VALUES (:p, :path, 'remote', :src)",
        );
        $statement->execute(['p' => $productId, 'path' => $sourceUrl, 'src' => $sourceUrl]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testUzakGorselIndirilirVeYereleCevrilir(): void
    {
        $url = 'https://cbu01.alicdn.com/img/ibank/ana.jpg';
        $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');
        $productId = $this->seedProduct($url);
        $imageId = $this->seedGalleryImage($productId, 'https://cbu01.alicdn.com/img/ibank/galeri.jpg');
        $this->fetcher->respondWith('https://cbu01.alicdn.com/img/ibank/galeri.jpg', $this->jpeg(), 'image/jpeg');

        $result = $this->migrator()->migrateBatch();

        self::assertSame(2, $result['migrated']);
        self::assertSame([], $result['failed']);
        self::assertSame(0, $result['remaining']);

        $main = (string) $this->pdo->query('SELECT main_image FROM products WHERE id = ' . $productId)->fetchColumn();
        self::assertStringStartsWith('/media/', $main);
        self::assertFileExists($this->tempPath('public' . $main));

        $row = $this->pdo->query('SELECT path, storage_mode, source_url FROM product_images WHERE id = ' . $imageId)->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('local', $row['storage_mode']);
        self::assertStringStartsWith('public/media/', (string) $row['path']);
        self::assertSame('https://cbu01.alicdn.com/img/ibank/galeri.jpg', $row['source_url']);
    }

    public function testIdempotens_TasinanKayitTekrarIslenmez(): void
    {
        $url = 'https://cbu01.alicdn.com/img/ibank/bir.jpg';
        $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');
        $this->seedProduct($url);

        $first = $this->migrator()->migrateBatch();
        $second = $this->migrator()->migrateBatch();

        self::assertSame(1, $first['migrated']);
        self::assertSame(0, $second['scanned'], 'İkinci koşum hiçbir kaydı işlememeli.');
        self::assertSame(1, $this->fetcher->callCount, 'İndirme yalnız BİR kez yapılmalı.');
    }

    public function testBasarisizIndirmeKaydiBozmazVeRaporlanir(): void
    {
        // Yanıt tanımlanmadı → FakeMediaFetcher MediaException fırlatır (403/404 eşdeğeri).
        $url = 'https://cbu01.alicdn.com/img/ibank/olmayan.jpg';
        $productId = $this->seedProduct($url);

        $result = $this->migrator()->migrateBatch();

        self::assertSame(0, $result['migrated']);
        self::assertCount(1, $result['failed']);
        self::assertSame($productId, $result['failed'][0]['product_id']);
        self::assertSame($url, $result['failed'][0]['url']);
        self::assertSame(1, $result['remaining'], 'Başarısız kayıt bekleyen sayısında kalmalı.');

        // KAYIT BÜTÜNLÜĞÜ: URL aynen durur, sonraki koşumda yeniden denenebilir.
        self::assertSame($url, (string) $this->pdo->query('SELECT main_image FROM products WHERE id = ' . $productId)->fetchColumn());
    }

    public function testYazilamazDisktemodKapisiIndirmeYapmaz(): void
    {
        $url = 'https://cbu01.alicdn.com/img/ibank/x.jpg';
        $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');
        $this->seedProduct($url);

        $result = $this->migrator('public/olmayan-klasor')->migrateBatch();

        self::assertSame(MediaService::MODE_HOTLINK, $result['mode']);
        self::assertSame(0, $result['scanned']);
        self::assertSame(0, $this->fetcher->callCount, 'Yazılamaz diskte indirme hiç denenmemeli.');
        self::assertSame(1, $result['remaining']);
    }

    public function testPartiSinirinaUyulur(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $url = sprintf('https://cbu01.alicdn.com/img/ibank/p%d.jpg', $i);
            $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');
            $this->seedProduct($url);
        }

        $result = $this->migrator()->migrateBatch(2);

        self::assertSame(2, $result['scanned']);
        self::assertSame(1, $result['remaining']);
    }
}
