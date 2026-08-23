<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Models\CategoryRepository;
use App\Services\KategoriIceAktarim;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * KATEGORİ İÇE AKTARIMI (İE#21 B10).
 *
 * İki söz sınanır: BİÇİM TOLERANSI (üç şekil de kabul edilir) ve İDEMPOTANLIK
 * (iki kez koşmak kategorileri ikiye katlamaz — kategoriler ürünlere bağlıdır,
 * mükerrer kayıt raporları sessizce böler).
 */
final class KategoriIceAktarimTest extends TestCase
{
    private PDO $pdo;
    private KategoriIceAktarim $aktarim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            sort INTEGER NOT NULL DEFAULT 0
        )');
        $this->pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NULL,
            deleted_at TEXT NULL
        )');

        $this->aktarim = new KategoriIceAktarim(
            new CategoryRepository(Connection::fromCallable(fn (): PDO => $this->pdo)),
        );
    }

    /** @return list<string> */
    private function adlar(): array
    {
        /** @var list<string> $rows */
        $rows = $this->pdo->query('SELECT name FROM categories ORDER BY sort, name')->fetchAll(PDO::FETCH_COLUMN);

        return $rows;
    }

    public function testDuzListeIceAktarilir(): void
    {
        $sonuc = $this->aktarim->calistir(['Mutfak', 'Ev', 'Banyo']);

        self::assertSame(3, $sonuc['eklenen']);
        self::assertSame(['Mutfak', 'Ev', 'Banyo'], $this->adlar());
    }

    public function testNesneListesiIceAktarilir(): void
    {
        $sonuc = $this->aktarim->calistir([
            ['ad' => 'Mutfak'],
            ['name' => 'Ev'],
        ]);

        self::assertSame(2, $sonuc['eklenen']);
        self::assertContains('Mutfak', $this->adlar());
        self::assertContains('Ev', $this->adlar());
    }

    public function testAGACYOLADINADUZLESTIRILIR(): void
    {
        $this->aktarim->calistir([
            ['ad' => 'Mutfak', 'alt' => [['ad' => 'Pişirme'], ['ad' => 'Saklama']]],
            ['ad' => 'Ev'],
        ]);

        $adlar = $this->adlar();
        self::assertContains('Mutfak', $adlar);
        self::assertContains('Mutfak > Pişirme', $adlar);
        self::assertContains('Mutfak > Saklama', $adlar);
        self::assertContains('Ev', $adlar);
    }

    public function testANAHTARLIAGACDAKABULEDILIR(): void
    {
        $this->aktarim->calistir(['Mutfak' => ['Pişirme' => []], 'Ev' => []]);

        self::assertContains('Mutfak > Pişirme', $this->adlar());
    }

    public function testUCSEVIYEDERINLIK(): void
    {
        $this->aktarim->calistir([
            ['ad' => 'Ev', 'alt' => [['ad' => 'Mutfak', 'alt' => [['ad' => 'Tencere']]]]],
        ]);

        self::assertContains('Ev > Mutfak > Tencere', $this->adlar());
    }

    public function testIDEMPOTENT_IKINCIKOSUMEKLEMEZ(): void
    {
        $this->aktarim->calistir(['Mutfak', 'Ev']);
        $ikinci = $this->aktarim->calistir(['Mutfak', 'Ev']);

        self::assertSame(0, $ikinci['eklenen']);
        self::assertSame(2, $ikinci['atlanan']);
        self::assertCount(2, $this->adlar());
    }

    public function testMEVCUTKATEGORIKORUNUR(): void
    {
        $this->pdo->exec("INSERT INTO categories (name, sort) VALUES ('MUTFAK', 5)");

        $sonuc = $this->aktarim->calistir(['Mutfak', 'Ev']);

        // Büyük/küçük harf farkı AYNI kategoridir: "MUTFAK" ve "Mutfak" iki
        // kategori olursa ürünler ikiye bölünür.
        self::assertSame(1, $sonuc['eklenen']);
        self::assertSame(1, $sonuc['atlanan']);
        self::assertCount(2, $this->adlar());
    }

    public function testAYNIADDOSYADAIKIKEZGECERSETEKEKLENIR(): void
    {
        $sonuc = $this->aktarim->calistir(['Mutfak', 'Mutfak', 'Ev']);

        self::assertSame(2, $sonuc['eklenen']);
        self::assertCount(2, $this->adlar());
    }

    public function testADSIZDUGUMATLANIRVEUYARIRAPORLANIR(): void
    {
        $sonuc = $this->aktarim->calistir([['sira' => 10], ['ad' => 'Ev']]);

        self::assertSame(1, $sonuc['eklenen']);
        self::assertNotSame([], $sonuc['uyarilar']);
    }

    public function testBOSVEBOSLUKLUADLARELENIR(): void
    {
        $sonuc = $this->aktarim->calistir(['', '   ', 'Ev']);

        self::assertSame(1, $sonuc['eklenen']);
        self::assertSame(['Ev'], $this->adlar());
    }

    public function testUSTSINIRUYGULANIRVEBILDIRILIR(): void
    {
        $liste = [];
        for ($i = 0; $i < KategoriIceAktarim::UST_SINIR + 20; $i++) {
            $liste[] = 'Kategori ' . $i;
        }

        $sonuc = $this->aktarim->calistir($liste);

        self::assertSame(KategoriIceAktarim::UST_SINIR, $sonuc['eklenen']);
        // Sessiz kırpma yasak: ne kadarının alındığı SÖYLENİR.
        self::assertNotSame([], $sonuc['uyarilar']);
    }

    public function testUZUNADKIRPILIRAMAKAYBOLMAZ(): void
    {
        $uzun = str_repeat('a', 150);
        $this->aktarim->calistir([$uzun]);

        self::assertSame(100, mb_strlen($this->adlar()[0]));
    }
}
