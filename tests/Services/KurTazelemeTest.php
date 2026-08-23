<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Models\ListRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * KUR TAZELEME — canlı sapmanın testi (İE#21 B5).
 *
 * SAHA BULGUSU: Ayarlar'dan kur güncellendi, taslak listeler 7,04/41,50'de kaldı.
 * KÖK NEDEN: `lists.yuan_rate` oluşturma anında kopyalanıyor, bir daha okunmuyordu.
 *
 * Bu testler kuralı sabitler: KİLİTLENMEMİŞ liste güncel kuru izler, KİLİTLİ liste
 * ASLA değişmez. İkincisi birincisi kadar önemlidir — firmaya gitmiş bir belgenin
 * kurunun arkadan değişmesi, gönderilmiş fiyatın değişmesi demektir.
 */
final class KurTazelemeTest extends TestCase
{
    private PDO $pdo;
    private ListRepository $lists;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE lists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "draft",
            yuan_rate TEXT NOT NULL,
            usd_rate TEXT NOT NULL,
            rate_locked_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $this->lists = new ListRepository(Connection::fromCallable(fn (): PDO => $this->pdo));
    }

    private function liste(string $ad, string $yuan, string $usd, ?string $kilit, ?string $silinme = null): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO lists (name, yuan_rate, usd_rate, rate_locked_at, updated_at, deleted_at)
             VALUES (:ad, :yuan, :usd, :kilit, :guncelleme, :silinme)',
        );
        $statement->execute([
            'ad' => $ad,
            'yuan' => $yuan,
            'usd' => $usd,
            'kilit' => $kilit,
            'guncelleme' => '2026-08-01 10:00:00',
            'silinme' => $silinme,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function oku(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM lists WHERE id = :id');
        $statement->execute(['id' => $id]);
        /** @var array<string, mixed> $row */
        $row = $statement->fetch();

        return $row;
    }

    public function testKilitlenmemisListeGuncelKuruAlir(): void
    {
        $id = $this->liste('Taslak', '7.0400', '41.5000', null);

        $etkilenen = $this->lists->kilitsizKurlariTazele('7.1500', '48.0500');

        self::assertSame(1, $etkilenen);
        $liste = $this->oku($id);
        self::assertSame('7.1500', $liste['yuan_rate']);
        self::assertSame('48.0500', $liste['usd_rate']);
    }

    public function testKilitliListeASLADEGISMEZ(): void
    {
        // Firmaya gitmiş belgenin kuru geriye dönük değişemez (K4).
        $id = $this->liste('İletildi', '7.0400', '41.5000', '2026-08-10 09:00:00');

        $this->lists->kilitsizKurlariTazele('7.1500', '48.0500');

        $liste = $this->oku($id);
        self::assertSame('7.0400', $liste['yuan_rate']);
        self::assertSame('41.5000', $liste['usd_rate']);
    }

    public function testCopKutusundakiListeyeDokunulmaz(): void
    {
        $id = $this->liste('Silinmiş', '7.0400', '41.5000', null, '2026-08-15 12:00:00');

        $this->lists->kilitsizKurlariTazele('7.1500', '48.0500');

        self::assertSame('7.0400', $this->oku($id)['yuan_rate']);
    }

    public function testZatenGuncelListeBOSUNAYAZILMAZ(): void
    {
        // rowCount() 0 dönmeli: "3 liste tazelendi" diye yanıltıcı kayıt düşmesin.
        $this->liste('Taslak', '7.1500', '48.0500', null);

        self::assertSame(0, $this->lists->kilitsizKurlariTazele('7.1500', '48.0500'));
    }

    public function testGuncellemeDamgasiKorunur(): void
    {
        // Kur tazeleme bir KULLANICI düzenlemesi değildir; "son güncelleme" damgası
        // değişirse panel yalan söyler (liste düzenlenmemiştir).
        $id = $this->liste('Taslak', '7.0400', '41.5000', null);

        $this->lists->kilitsizKurlariTazele('7.1500', '48.0500');

        self::assertSame('2026-08-01 10:00:00', $this->oku($id)['updated_at']);
    }

    public function testKarisikKumedeYalnizKilitsizlerTazelenir(): void
    {
        $taslak1 = $this->liste('T1', '7.0400', '41.5000', null);
        $kilitli = $this->liste('K1', '6.9000', '40.0000', '2026-08-05 09:00:00');
        $taslak2 = $this->liste('T2', '7.0000', '41.0000', null);

        $etkilenen = $this->lists->kilitsizKurlariTazele('7.1500', '48.0500');

        self::assertSame(2, $etkilenen);
        self::assertSame('7.1500', $this->oku($taslak1)['yuan_rate']);
        self::assertSame('7.1500', $this->oku($taslak2)['yuan_rate']);
        self::assertSame('6.9000', $this->oku($kilitli)['yuan_rate']);
    }

    public function testTarihAlaniDokunulmaz(): void
    {
        $id = $this->liste('Taslak', '7.0400', '41.5000', null);
        $this->lists->kilitsizKurlariTazele('7.1500', '48.0500');

        self::assertNull($this->oku($id)['rate_locked_at'], 'Tazeleme kilit koymamalı');
    }

    public function testDateTimeBagimliligiYok(): void
    {
        // Metot saat almaz: tazeleme zamana bağlı bir karar değildir.
        $yontem = new \ReflectionMethod(ListRepository::class, 'kilitsizKurlariTazele');

        foreach ($yontem->getParameters() as $parametre) {
            self::assertNotSame(
                DateTimeImmutable::class,
                (string) $parametre->getType(),
                'Tazeleme saatten bağımsız olmalı',
            );
        }
    }
}
