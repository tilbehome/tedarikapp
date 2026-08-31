<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\SettingsRepository;
use App\Services\MediaMigrator;
use App\Services\MediaService;
use App\Services\MedyaEksik;
use App\Services\UrlGuard;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * SERTLEŞTİRME v1.2.1 BLOK A4 — KISMİ MEDYA HATASI İŞİ BİTİRMEZ (TDR-004).
 *
 * KORUNAN FELAKET: medya işi beş görselden üçünü indirir, ikisi zaman aşımına
 * uğrar — ve iş BAŞARILI biter. `MediaMigrator::urununMedyasi()` başarısız
 * listesini DÖNDÜRÜYORDU ama kuyruk işleyicisi dönüşü hiç okumuyordu.
 * Sonuç: ürün "medyası indirildi" sayılır, iki görsel sonsuza kadar uzak
 * kalır ve kimse eksikliği fark etmez — çünkü ortada hata YOK.
 *
 * YENİ SÖZLEŞME: eksik kalan görsel varsa iş BİTMEZ.
 *   · GEÇİCİ hata (ağ, zaman aşımı) → `MedyaEksik` atılır, kuyruk geri
 *     çekilmeyle yeniden dener; ikinci tur YALNIZ eksikleri indirir çünkü
 *     başarılı satırlar artık `local`.
 *   · KALICI hata (güvenlik reddi, desteklenmeyen tür) → tekrar denemek
 *     düzeltmez; iş ölü rafına gider ve panelde GÖRÜNÜR.
 */
final class MedyaKismiHataTest extends AuthTestCase
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
            new UrlGuard(['alicdn.com']),
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

    /** Beş görselli ürün: üçü inecek, ikisi (indirici tanımsız) düşecek. */
    private function besGorselliUrun(): int
    {
        $this->pdo->exec(
            "INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at)
             VALUES ('L', '7', '41', '2026-08-31', '2026-08-31')",
        );
        $listId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO products (list_id, name, main_image, created_at, updated_at)
             VALUES (:l, 'Ürün', '/media/yerel.jpg', '2026-08-31', '2026-08-31')",
        )->execute(['l' => $listId]);
        $urunId = (int) $this->pdo->lastInsertId();

        for ($i = 1; $i <= 5; $i++) {
            $url = 'https://cbu01.alicdn.com/img/g' . $i . '.jpg';
            $this->pdo->prepare(
                "INSERT INTO product_images (product_id, path, storage_mode, source_url, sort)
                 VALUES (:p, :u, 'remote', :u2, :s)",
            )->execute(['p' => $urunId, 'u' => $url, 'u2' => $url, 's' => $i]);

            if ($i <= 3) {
                $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');
            }
            // 4 ve 5 için yanıt TANIMSIZ → indirici hata atar (geçici).
        }

        return $urunId;
    }

    public function testUCUINERIKISIDUSERSEISBITMEZ(): void
    {
        $urunId = $this->besGorselliUrun();

        try {
            $this->migrator()->urununMedyasi($urunId);
            self::fail('İki görsel eksikken iş BAŞARILI sayılamaz.');
        } catch (MedyaEksik $eksik) {
            self::assertSame(2, $eksik->eksikSayisi, 'Eksik sayısı bildirilmeli.');
            self::assertSame(3, $eksik->indirilenSayisi, 'İnen görseller KORUNMALI.');
        }

        $kalan = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM product_images WHERE product_id = {$urunId} AND storage_mode = 'remote'",
        )->fetchColumn();
        self::assertSame(2, $kalan, 'Yalnız eksik ikisi uzak kalmalı.');
    }

    public function testIKINCITURYALNIZEKSIKLERIINDIRIR(): void
    {
        $urunId = $this->besGorselliUrun();

        try {
            $this->migrator()->urununMedyasi($urunId);
        } catch (MedyaEksik) {
            // beklenen
        }
        $ilkTurCagri = $this->fetcher->callCount;

        // Ağ düzelir: eksik ikisi artık yanıt veriyor.
        foreach ([4, 5] as $i) {
            $this->fetcher->respondWith('https://cbu01.alicdn.com/img/g' . $i . '.jpg', $this->jpeg(), 'image/jpeg');
        }

        $sonuc = $this->migrator()->urununMedyasi($urunId);

        self::assertSame(2, $sonuc['indirilen'], 'İkinci tur YALNIZ eksik ikisini indirmeli.');
        self::assertSame(
            $ilkTurCagri + 2,
            $this->fetcher->callCount,
            'Zaten inmiş üç görsel YENİDEN indirilmemeli.',
        );
        self::assertSame(0, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM product_images WHERE product_id = {$urunId} AND storage_mode = 'remote'",
        )->fetchColumn());
    }

    public function testHEPSIINERSEISTISNAATILMAZ(): void
    {
        $urunId = $this->besGorselliUrun();
        foreach ([4, 5] as $i) {
            $this->fetcher->respondWith('https://cbu01.alicdn.com/img/g' . $i . '.jpg', $this->jpeg(), 'image/jpeg');
        }

        $sonuc = $this->migrator()->urununMedyasi($urunId);

        self::assertSame(5, $sonuc['indirilen']);
        self::assertSame([], $sonuc['basarisiz']);
    }

    public function testGUVENLIKREDDIKALICIDIR(): void
    {
        // İzinli olmayan host: tekrar denemek DÜZELTMEZ. Geçici sayılırsa üç
        // deneme hakkı boşa yanar ve gerçek arıza üç kat gecikmeyle görünür.
        $this->pdo->exec(
            "INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at)
             VALUES ('L', '7', '41', '2026-08-31', '2026-08-31')",
        );
        $listId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO products (list_id, name, main_image, created_at, updated_at)
             VALUES (:l, 'Ürün', '/media/yerel.jpg', '2026-08-31', '2026-08-31')",
        )->execute(['l' => $listId]);
        $urunId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare(
            "INSERT INTO product_images (product_id, path, storage_mode, source_url, sort)
             VALUES (:p, :u, 'remote', :u2, 1)",
        )->execute(['p' => $urunId, 'u' => 'https://kotu-site.example/x.jpg', 'u2' => 'https://kotu-site.example/x.jpg']);

        try {
            $this->migrator()->urununMedyasi($urunId);
            self::fail('MedyaEksik bekleniyordu.');
        } catch (MedyaEksik $eksik) {
            self::assertTrue($eksik->kalici, 'Güvenlik reddi KALICI sınıflanmalı.');
        }
    }
}
