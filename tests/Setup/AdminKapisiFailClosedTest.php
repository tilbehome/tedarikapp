<?php

declare(strict_types=1);

namespace Tests\Setup;

use App\Core\GizliHata;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * SERTLEŞTİRME v1.2.1 BLOK C3 — YÖNETİCİ KAPISI FAIL-CLOSED (TDR-012).
 *
 * KUSUR: `SetupController::adminGateBlocked()` kullanıcı sayısını okurken
 * `catch (Throwable) { return false; }` yapıyordu — yani HERHANGİ bir hata
 * kapıyı AÇIYORDU. Bağlantıyı bir an düşürebilen, izinleri bozabilen ya da
 * sorguyu zaman aşımına uğratabilen biri, KİMLİKSİZ bir yönetici oluşturma
 * kapısı elde ediyordu.
 *
 * Gerekçe olarak "users tablosu yoksa kurulum gerçekten ilk kurulumdur"
 * yazıyordu ve bu DOĞRU — ama yalnız TABLO YOKSA doğru. "Tablo yok" ile
 * "veritabanı yanıt vermiyor" aynı `catch` bloğunda toplandığı an, doğru
 * gerekçe yanlış bir kapıya dönüşür.
 *
 * K37 zaten bu deseni tanımlamıştı (SetupLock: "karar verilemiyorsa GEÇİLMEZ").
 * Bu test ayrımın ortak yardımcıda (`GizliHata::tabloYokMu`) doğru yapıldığını
 * zorlar — kapının kendisi `SetupEndpointsTest`te davranış olarak sınanıyor.
 */
final class AdminKapisiFailClosedTest extends TestCase
{
    private function pdoHatasi(string $sqlstate, int $surucuKodu, string $mesaj): PDOException
    {
        $hata = new PDOException($mesaj, 0);
        $hata->errorInfo = [$sqlstate, $surucuKodu, $mesaj];

        return $hata;
    }

    public function testTABLOYOKTANINIR(): void
    {
        // Gerçek ilk kurulum: tablo yok. Bu hâlde kapı açık kalmalı.
        self::assertTrue(GizliHata::tabloYokMu(
            $this->pdoHatasi('42S02', 1146, "Base table or view not found: 1146 Table 'app.users' doesn't exist"),
        ));
    }

    public function testSQLITETABLOYOKTANINIR(): void
    {
        self::assertTrue(GizliHata::tabloYokMu(
            $this->pdoHatasi('HY000', 1, 'no such table: users'),
        ));
    }

    public function testBAGLANTIHATASITABLOYOKSAYILMAZ(): void
    {
        // ASIL KORUMA: bağlantı düştüğünde kapı AÇILMAMALI.
        self::assertFalse(GizliHata::tabloYokMu(
            $this->pdoHatasi('HY000', 2002, 'SQLSTATE[HY000] [2002] Connection refused'),
        ));
    }

    public function testIZINHATASITABLOYOKSAYILMAZ(): void
    {
        self::assertFalse(GizliHata::tabloYokMu(
            $this->pdoHatasi('42000', 1142, "SELECT command denied to user 'app'@'localhost' for table 'users'"),
        ));
    }

    public function testPDODISIISTISNATABLOYOKSAYILMAZ(): void
    {
        // Program hatası hiçbir koşulda "tablo yok" olamaz.
        self::assertFalse(GizliHata::tabloYokMu(new RuntimeException('beklenmedik')));
    }

    public function testGERCEKSQLITEHATASITANINIR(): void
    {
        // Kalıp uydurmadığımızın kanıtı: gerçek bir sürücü hatası kullanılır.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        try {
            $pdo->query('SELECT COUNT(*) FROM users');
            self::fail('Olmayan tabloya sorgu istisna atmalıydı.');
        } catch (PDOException $hata) {
            self::assertTrue(GizliHata::tabloYokMu($hata), 'Gerçek sürücü hatası tanınmalı.');
        }
    }

    public function testKAPIKAYNAGIFAILCLOSED(): void
    {
        // Bekçi: `catch (Throwable) { return false; }` bir daha belirmesin.
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/SetupController.php');

        self::assertStringContainsString(
            'GizliHata::tabloYokMu(',
            $kaynak,
            'Yönetici kapısı "tablo yok" ile "veritabanı düştü"yü AYIRMALI.',
        );
    }
}
