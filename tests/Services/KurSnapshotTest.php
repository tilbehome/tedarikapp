<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Models\RateSnapshotRepository;
use App\Models\SettingsRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * İE#22 BLOK A — KUR SNAPSHOT OMURGASI.
 *
 * Sınanan sözleşme üç cümledir:
 *   1. Aktif satır `superseded_at IS NULL` olandır; yeni sürüm öncekini kapatır.
 *   2. Okuma TEK NOKTADAN yapılır: `SettingsRepository::yuanRate()` önce
 *      snapshot'a bakar, yoksa ayardaki kopyaya düşer.
 *   3. Kaynak (elle/TCMB) ve geçerlilik başlangıcı kayıtta durur — "bu kuru kim,
 *      ne zaman, nereden koydu" sorusu veriden yanıtlanabilmeli.
 */
final class KurSnapshotTest extends TestCase
{
    private PDO $pdo;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NULL)');
        $this->pdo->exec(
            'CREATE TABLE rate_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                currency TEXT NOT NULL, rate TEXT NOT NULL,
                source TEXT NOT NULL DEFAULT "elle",
                effective_from TEXT NOT NULL, superseded_at TEXT NULL,
                created_by INTEGER NULL, created_at TEXT NOT NULL,
                UNIQUE (currency, effective_from)
            )',
        );
        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);
    }

    private function depo(): RateSnapshotRepository
    {
        return new RateSnapshotRepository($this->connection);
    }

    private function an(string $zaman): DateTimeImmutable
    {
        return new DateTimeImmutable($zaman);
    }

    public function testYENISURUMONCEKINIKAPATIR(): void
    {
        $depo = $this->depo();
        $depo->yeniSurum('CNY', '7.0400', $this->an('2026-08-01 10:00:00'));
        $depo->yeniSurum('CNY', '7.1200', $this->an('2026-08-05 09:00:00'));

        $aktif = $depo->aktif('CNY');
        self::assertSame('7.1200', $aktif['rate'] ?? null, 'Aktif satır SON yazılandır.');

        $gecmis = $depo->gecmis('CNY');
        self::assertCount(2, $gecmis);
        self::assertTrue($gecmis[0]['aktif'], 'En yeni satır aktif.');
        self::assertFalse($gecmis[1]['aktif'], 'Eski satır kapatılmış olmalı.');
        self::assertSame('2026-08-05 09:00:00', $gecmis[1]['superseded_at'], 'Bitiş, sonrakinin başlangıcıdır.');
    }

    public function testAKTIFSATIRTEKTIR(): void
    {
        $depo = $this->depo();
        foreach (['7.0400', '7.1200', '7.2500'] as $sira => $kur) {
            $depo->yeniSurum('CNY', $kur, $this->an('2026-08-0' . ($sira + 1) . ' 10:00:00'));
        }

        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM rate_snapshots WHERE currency = 'CNY' AND superseded_at IS NULL",
        );
        self::assertSame(1, (int) $statement->fetchColumn(), 'Aynı anda YALNIZ BİR aktif satır olabilir.');
    }

    public function testPARABIRIMLERIBIRBIRINIETKILEMEZ(): void
    {
        $depo = $this->depo();
        $depo->yeniSurum('CNY', '7.0400', $this->an('2026-08-01 10:00:00'));
        $depo->yeniSurum('USD', '41.5000', $this->an('2026-08-02 10:00:00'));
        $depo->yeniSurum('CNY', '7.1200', $this->an('2026-08-03 10:00:00'));

        self::assertSame('41.5000', $depo->aktifDeger('USD'), 'USD, CNY güncellenince kapanmamalı.');
        self::assertSame('7.1200', $depo->aktifDeger('CNY'));
    }

    public function testKAYNAKVEKULLANICIKAYITTADURUR(): void
    {
        $this->depo()->yeniSurum('USD', '42.0000', $this->an('2026-08-10 12:00:00'), RateSnapshotRepository::KAYNAK_TCMB, 7);

        $satir = $this->pdo->query('SELECT source, created_by FROM rate_snapshots')->fetch();
        self::assertSame('tcmb', $satir['source']);
        self::assertSame(7, (int) $satir['created_by']);
    }

    public function testOKUMATEKNOKTADAN_SNAPSHOTONCELIKLI(): void
    {
        // Ayarda ESKİ kopya, snapshot'ta YENİ değer: okuma snapshot'ı görmeli.
        $this->pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)')
            ->execute(['k' => SettingsRepository::KEY_YUAN_RATE, 'v' => '6.0000']);
        $this->depo()->yeniSurum('CNY', '7.3300', $this->an('2026-08-11 08:00:00'));

        self::assertSame('7.3300', (new SettingsRepository($this->connection))->yuanRate());
    }

    public function testSNAPSHOTYOKSAAYARDAKIKOPYAYADUSULUR(): void
    {
        // Migration henüz koşmamış ya da tablo boş: sistem kursuz kalmaz.
        $this->pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)')
            ->execute(['k' => SettingsRepository::KEY_USD_RATE, 'v' => '40.1000']);

        self::assertSame('40.1000', (new SettingsRepository($this->connection))->usdRate());
    }

    public function testTABLOYOKSAOKUMACOKMEZ(): void
    {
        $this->pdo->exec('DROP TABLE rate_snapshots');
        $this->pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)')
            ->execute(['k' => SettingsRepository::KEY_YUAN_RATE, 'v' => '7.0400']);

        // Kur okuması bir migration gecikmesi yüzünden paneli çökertmemeli.
        self::assertSame('7.0400', (new SettingsRepository($this->connection))->yuanRate());
    }

    public function testAKTIFYASSAATBRF013ICINHESAPLANIR(): void
    {
        $depo = $this->depo();
        $depo->yeniSurum('CNY', '7.0400', $this->an('2026-08-10 08:00:00'));

        self::assertSame(26, $depo->aktifYasSaat('CNY', $this->an('2026-08-11 10:00:00')));
        // Aktif satır yoksa "0 saat" DEĞİL, "bilinmiyor" (K67).
        self::assertNull($depo->aktifYasSaat('USD', $this->an('2026-08-11 10:00:00')));
    }
}
