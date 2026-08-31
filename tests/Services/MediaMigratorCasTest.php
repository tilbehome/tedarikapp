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
 * SERTLEŞTİRME v1.2.1 BLOK A5 — MEDYA GÖÇÜ SATIR CAS'İ (TDR-004).
 *
 * KORUNAN FELAKET: göç aracı görseli indirir, SONRA satırı `WHERE id = ?` ile
 * günceller. İndirme ile yazım arasında saniyeler geçer (ağ) ve o aralıkta:
 *
 *   · Aynı görev cron'dan VE panel düğmesinden aynı anda koşabilir; ikisi de
 *     indirir, ikisi de yazar. İkincinin yazımı birincinin yolunu ezer ve
 *     birincinin indirdiği dosya diskte ÖKSÜZ kalır — kimse silmez.
 *   · Ürün bu arada yeniden yakalanmış olabilir: satırda ARTIK BAŞKA bir URL
 *     vardır. Göç aracı, ESKİ url'den indirdiği dosyayı YENİ url'nin üstüne
 *     yazar. Sonuç sessizdir: ürünün görseli yanlış ama "yerel" görünür.
 *
 * KURAL: yazım satır CAS'idir — `WHERE id AND storage_mode='remote' AND <eski
 * URL>`. Tutmazsa indirilen dosya SİLİNİR (öksüz bırakmayız) ve kayıt
 * atlanmış sayılır. Ana görsel için de aynısı geçerlidir.
 */
final class MediaMigratorCasTest extends AuthTestCase
{
    use TempDirectory;

    private FakeMediaFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetcher = new FakeMediaFetcher();
        mkdir($this->tempPath('public/media'), 0775, true);
    }

    private function migrator(): MediaMigrator
    {
        $media = new MediaService(
            $this->tempRoot(),
            new UrlGuard(['alicdn.com', '1688.com']),
            $this->fetcher,
            new SettingsRepository($this->connection),
            8 * 1024 * 1024,
            'public/media',
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
        $this->pdo->exec(
            "INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at)
             VALUES ('L', '7', '41', '2026-08-31', '2026-08-31')",
        );
        $listId = (int) $this->pdo->lastInsertId();
        $statement = $this->pdo->prepare(
            "INSERT INTO products (list_id, name, main_image, created_at, updated_at)
             VALUES (:l, 'Ürün', :m, '2026-08-31', '2026-08-31')",
        );
        $statement->execute(['l' => $listId, 'm' => $mainImage]);

        return (int) $this->pdo->lastInsertId();
    }

    private function seedGalleryImage(int $productId, string $sourceUrl): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO product_images (product_id, path, storage_mode, source_url)
             VALUES (:p, :path, 'remote', :src)",
        );
        $statement->execute(['p' => $productId, 'path' => $sourceUrl, 'src' => $sourceUrl]);

        return (int) $this->pdo->lastInsertId();
    }

    /** İndirme sırasında satırı değiştiren "başka koşum"u taklit eder. */
    private function indirmeSirasindaDegistir(callable $degisiklik): void
    {
        $this->fetcher->indirmedenOnce($degisiklik);
    }

    private function medyaDosyaSayisi(): int
    {
        return count(glob($this->tempPath('public/media') . '/*/*') ?: [])
            + count(glob($this->tempPath('public/media') . '/*.*') ?: []);
    }

    public function testGALERIYAZIMISATIRDEGISMISSEUYGULANMAZ(): void
    {
        $eski = 'https://cbu01.alicdn.com/img/ibank/eski.jpg';
        $yeni = 'https://cbu01.alicdn.com/img/ibank/yeni.jpg';
        $urunId = $this->seedProduct('/media/zaten-yerel.jpg');
        $gorselId = $this->seedGalleryImage($urunId, $eski);
        $this->fetcher->respondWith($eski, $this->jpeg(), 'image/jpeg');

        // İndirme sürerken ürün YENİDEN YAKALANIR: satırda artık başka URL var.
        $this->indirmeSirasindaDegistir(function () use ($gorselId, $yeni): void {
            $this->pdo->prepare('UPDATE product_images SET path = :p, source_url = :p2 WHERE id = :id')
                ->execute(['p' => $yeni, 'p2' => $yeni, 'id' => $gorselId]);
        });

        $sonuc = $this->migrator()->migrateBatch();

        $satir = $this->pdo->query('SELECT path, storage_mode FROM product_images WHERE id = ' . $gorselId)
            ->fetch(\PDO::FETCH_ASSOC);

        self::assertSame($yeni, (string) $satir['path'], 'Yeni URL ESKİ indirmeyle ezilmemeli.');
        self::assertSame('remote', (string) $satir['storage_mode'], 'Satır hâlâ uzak; göç uygulanmadı.');
        self::assertSame(0, $sonuc['migrated'], 'CAS tutmayan yazım "taşındı" sayılmamalı.');
    }

    public function testCASTUTMAZSAINDIRILENDOSYASILINIR(): void
    {
        // Öksüz dosya diskte birikirse kimse fark etmez; medya klasörü
        // sessizce şişer ve hangi dosyanın kime ait olduğu bilinemez.
        $eski = 'https://cbu01.alicdn.com/img/ibank/eski.jpg';
        $urunId = $this->seedProduct('/media/zaten-yerel.jpg');
        $gorselId = $this->seedGalleryImage($urunId, $eski);
        $this->fetcher->respondWith($eski, $this->jpeg(), 'image/jpeg');

        $this->indirmeSirasindaDegistir(function () use ($gorselId): void {
            $this->pdo->prepare("UPDATE product_images SET storage_mode = 'local' WHERE id = :id")
                ->execute(['id' => $gorselId]);
        });

        $this->migrator()->migrateBatch();

        self::assertSame(0, $this->medyaDosyaSayisi(), 'CAS tutmadı; indirilen dosya diskte KALMAMALI.');
    }

    public function testANAGORSELDEAYNIKORUMAYITASIR(): void
    {
        $eski = 'https://cbu01.alicdn.com/img/ibank/ana-eski.jpg';
        $yeni = 'https://cbu01.alicdn.com/img/ibank/ana-yeni.jpg';
        $urunId = $this->seedProduct($eski);
        $this->fetcher->respondWith($eski, $this->jpeg(), 'image/jpeg');

        $this->indirmeSirasindaDegistir(function () use ($urunId, $yeni): void {
            $this->pdo->prepare('UPDATE products SET main_image = :m WHERE id = :id')
                ->execute(['m' => $yeni, 'id' => $urunId]);
        });

        $sonuc = $this->migrator()->migrateBatch();

        $ana = (string) $this->pdo->query('SELECT main_image FROM products WHERE id = ' . $urunId)->fetchColumn();
        self::assertSame($yeni, $ana, 'Ana görselin YENİ URL\'si eski indirmeyle ezilmemeli.');
        self::assertSame(0, $sonuc['migrated']);
    }

    public function testDEGISMEYENSATIRNORMALTASINIR(): void
    {
        // Koruma, normal yolu bozmamalı: kimse araya girmezse göç işler.
        $url = 'https://cbu01.alicdn.com/img/ibank/sakin.jpg';
        $urunId = $this->seedProduct('/media/zaten-yerel.jpg');
        $gorselId = $this->seedGalleryImage($urunId, $url);
        $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');

        $sonuc = $this->migrator()->migrateBatch();

        $satir = $this->pdo->query('SELECT path, storage_mode FROM product_images WHERE id = ' . $gorselId)
            ->fetch(\PDO::FETCH_ASSOC);

        self::assertSame(1, $sonuc['migrated']);
        self::assertSame('local', (string) $satir['storage_mode']);
        self::assertStringStartsWith('public/media/', (string) $satir['path']);
    }
}
