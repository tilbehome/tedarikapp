<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\SettingsRepository;
use App\Services\Kuyruk\BellekButcesi;
use App\Services\Kuyruk\HataSinifi;
use App\Services\Kuyruk\IsErtelendi;
use App\Services\MediaMigrator;
use App\Services\MediaService;
use App\Services\MedyaEksik;
use App\Services\UrlGuard;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * v1.2.2 BLOK D6 — İŞÇİ BAŞINA BELLEK BÜTÇESİ.
 *
 * PAYLAŞIMLI HOSTİNG GERÇEĞİ: PHP süreci `memory_limit`e çarpınca ÖLÜR —
 * istisna yok, `finally` yok, günlük satırı yok. Kirası dolana kadar iş
 * "çalışıyor" görünür, sonra devralınır ve aynı 8 MB'lık görselleri yeniden
 * indirmeye başlar; üçüncü devralmada ölü rafına düşer. Operatör "işleyici
 * sonuç yazmadan düştü" görür ve nedenini asla öğrenemez.
 *
 * BÜTÇE: işçi kendi tüketimini ölçer ve sınıra yaklaşınca DURUR — çökmeden.
 * Kalan görseller bir sonraki tura kalır; iş ERTELENİR, başarısız olmaz.
 * İnenler korunur (satırlar artık `local`), ikinci tur yalnız kalanları alır.
 */
final class MedyaBellekButcesiTest extends AuthTestCase
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

    private function besGorselliUrun(): int
    {
        $this->pdo->exec(
            "INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at)
             VALUES ('L', '7', '41', '2026-09-04', '2026-09-04')",
        );
        $listId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO products (list_id, name, main_image, created_at, updated_at)
             VALUES (:l, 'Ürün', '/media/yerel.jpg', '2026-09-04', '2026-09-04')",
        )->execute(['l' => $listId]);
        $urunId = (int) $this->pdo->lastInsertId();

        for ($i = 1; $i <= 5; $i++) {
            $url = 'https://cbu01.alicdn.com/img/b' . $i . '.jpg';
            $this->pdo->prepare(
                "INSERT INTO product_images (product_id, path, storage_mode, source_url, sort)
                 VALUES (:p, :u, 'remote', :u2, :s)",
            )->execute(['p' => $urunId, 'u' => $url, 'u2' => $url, 's' => $i]);
            $this->fetcher->respondWith($url, $this->jpeg(), 'image/jpeg');
        }

        return $urunId;
    }

    public function testBUTCEDOLUNCAISERTELENIRINENLERKORUNUR(): void
    {
        $urunId = $this->besGorselliUrun();

        // Sahte ölçüm: ilk iki görselden sonra bütçe "dolar".
        $sayac = 0;
        $butce = new BellekButcesi(1000, static function () use (&$sayac): int {
            return ++$sayac > 2 ? 2000 : 100;
        });

        try {
            $this->migrator()->urununMedyasi($urunId, butce: $butce);
            self::fail('Bütçe dolunca iş ERTELENMELİ.');
        } catch (IsErtelendi $ertelendi) {
            self::assertStringContainsString('bellek', mb_strtolower($ertelendi->getMessage()));
            self::assertGreaterThan(0, $ertelendi->saniye);
        }

        $yerel = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM product_images WHERE product_id = {$urunId} AND storage_mode = 'local'",
        )->fetchColumn();
        self::assertGreaterThan(0, $yerel, 'Bütçe dolmadan inenler KORUNMALI.');
        self::assertLessThan(5, $yerel, 'Bütçe dolunca indirme DURMALI.');
    }

    public function testERTELEMEBASARISIZDEGILDIR(): void
    {
        // Erteleme, MedyaEksik (hata) değildir: kuyruk onu geçici hata sayıp
        // deneme hakkı yakmamalı.
        $urunId = $this->besGorselliUrun();
        $butce = new BellekButcesi(1000, static fn (): int => 5000); // baştan dolu

        try {
            $this->migrator()->urununMedyasi($urunId, butce: $butce);
            self::fail('Erteleme bekleniyordu.');
        } catch (MedyaEksik) {
            self::fail('Bütçe dolması bir HATA değildir; MedyaEksik atılmamalı.');
        } catch (IsErtelendi) {
            self::assertTrue(true);
        }
    }

    public function testIKINCITURKALANLARIINDIRIR(): void
    {
        $urunId = $this->besGorselliUrun();
        $sayac = 0;
        $dar = new BellekButcesi(1000, static function () use (&$sayac): int {
            return ++$sayac > 2 ? 2000 : 100;
        });

        try {
            $this->migrator()->urununMedyasi($urunId, butce: $dar);
        } catch (IsErtelendi) {
            // beklenen
        }
        $ilkTur = $this->fetcher->callCount;

        // Yeni tur, taze süreç: bütçe bol.
        $sonuc = $this->migrator()->urununMedyasi($urunId, butce: new BellekButcesi(1000, static fn (): int => 100));

        self::assertSame(5 - $ilkTur, $sonuc['indirilen'], 'İkinci tur yalnız KALANLARI indirmeli.');
        self::assertSame(0, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM product_images WHERE product_id = {$urunId} AND storage_mode = 'remote'",
        )->fetchColumn());
    }

    public function testBUTCEYOKSAESKIDAVRANIS(): void
    {
        // Bütçe verilmezse sınır yoktur — mevcut çağıranlar kırılmaz.
        $urunId = $this->besGorselliUrun();

        $sonuc = $this->migrator()->urununMedyasi($urunId);

        self::assertSame(5, $sonuc['indirilen']);
    }

    public function testBUTCEZIRVEYIOLCER(): void
    {
        $butce = new BellekButcesi(64 * 1024 * 1024);

        self::assertGreaterThan(0, $butce->zirveMb());
        self::assertFalse($butce->asildi(), 'Test süreci 64 MB bütçeyi aşmamalı.');
    }

    public function testMEDYAEKSIKKALICILIGITIPTENOKUNUR(): void
    {
        // A8 ilkesi kuyruk katına da uzanır: `MedyaEksik` kalıcı olduğunu
        // kendisi söyler; mesajda "kalıcı hata" geçmesi tesadüftür ve
        // HataSinifi'nin kalıcı sözcük listesinde yer almaz. Tipten okunmazsa
        // kalıcı bir medya hatası üç kez boşuna denenir.
        $kalici = new MedyaEksik(1, 0, 1, true);
        $gecici = new MedyaEksik(1, 0, 1, false);

        self::assertSame(HataSinifi::KALICI, HataSinifi::siniflandir($kalici)['sinif']);
        self::assertSame(HataSinifi::GECICI, HataSinifi::siniflandir($gecici)['sinif']);
    }
}
