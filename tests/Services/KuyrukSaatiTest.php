<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Clock;
use App\Core\Connection;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\JobRunner;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * SERTLEŞTİRME v1.2.1 BLOK A3 — SAAT HER DURUM GEÇİŞİNDE OKUNUR (TDR-005).
 *
 * KORUNAN FELAKET: `kos()` turun BAŞINDA bir `$now` alıyor ve turun tamamı
 * boyunca — 50 saniyeye kadar — o tek anı kullanıyordu. Sonuçları:
 *
 *   · 48. saniyede alınan bir işin KİRASI, 48 saniyesi çoktan yanmış hâlde
 *     başlıyordu. 300 saniyelik kira fiilen 252 saniyeydi ve iş, daha
 *     işleyici koşarken devralınabiliyordu — tam da A1'in kapattığı yarışı
 *     kuyruk kendi eliyle üretiyordu.
 *   · `bitti_at` ve `calisacak_at` damgaları turun BAŞINI gösteriyordu;
 *     geri çekilme (backoff) penceresi olduğundan kısa hesaplanıyordu.
 *   · Shutdown kancası da aynı `$now`'u kapatıyordu: süreç 49. saniyede
 *     ölse bile bırakma kaydı 0. saniyeye yazılıyordu.
 *
 * Zaman tek bir yerden okunur (`Clock`), ama HER GEÇİŞTE YENİDEN okunur.
 */
final class KuyrukSaatiTest extends TestCase
{
    private PDO $pdo;
    private JobQueue $kuyruk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        foreach (['0024_create_jobs', '0028_kuyruk_sertlestirme'] as $ad) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $ad . '.php';
            $migration->up($this->pdo);
        }

        $this->kuyruk = new JobQueue(Connection::fromCallable(fn (): PDO => $this->pdo));
    }

    /** Her `now()` çağrısında ilerleyen saat — turun içindeki zaman akışını taklit eder. */
    private function ilerleyenSaat(DateTimeImmutable $baslangic, int $adimSaniye): Clock
    {
        return new class ($baslangic, $adimSaniye) implements Clock {
            private int $cagri = 0;

            public function __construct(
                private readonly DateTimeImmutable $baslangic,
                private readonly int $adim,
            ) {
            }

            public function now(): DateTimeImmutable
            {
                $an = $this->baslangic->modify('+' . ($this->cagri * $this->adim) . ' seconds');
                $this->cagri++;

                return $an;
            }
        };
    }

    public function testBITISDAMGASITURBASINIDEGILGERCEKANIGOSTERIR(): void
    {
        $baslangic = new DateTimeImmutable('2026-08-31 12:00:00');
        $this->kuyruk->ekle('deneme', 'a', [], $baslangic);

        $kosucu = new JobRunner(
            $this->kuyruk,
            new NullLogger(),
            50,
            25,
            $this->ilerleyenSaat($baslangic, 10),
        );
        // İşleyici hiçbir şey yapmaz; ölçülen şey damgalardır.
        $kosucu->kaydet('deneme', static function (): void {
        });

        $kosucu->kos();

        $bittiAt = (string) $this->pdo->query('SELECT bitti_at FROM jobs LIMIT 1')->fetchColumn();

        self::assertNotSame(
            '2026-08-31 12:00:00',
            $bittiAt,
            'bitti_at turun BAŞINI gösteriyor — saat her geçişte okunmuyor.',
        );
        self::assertGreaterThan('2026-08-31 12:00:00', $bittiAt);
    }

    public function testKIRAISINALINDIGIANDANBASLAR(): void
    {
        // İKİNCİ iş ölçülür: ilk iş t=0'da alınır (orada fark yoktur), ama
        // ikincisi turun ilerlemiş bir anında alınır. Kirası O ANDAN başlamalı;
        // tur başına sabitlenseydi ikisinin kira bitişi AYNI olurdu ve ikinci
        // işin kirası daha doğarken saniyeler yanmış olurdu.
        $baslangic = new DateTimeImmutable('2026-08-31 12:00:00');
        $this->kuyruk->ekle('bekleyen', 'a', [], $baslangic);
        $this->kuyruk->ekle('bekleyen', 'b', [], $baslangic);

        $saat = $this->ilerleyenSaat($baslangic, 30);
        $kosucu = new JobRunner($this->kuyruk, new NullLogger(), 50, 25, $saat);

        $kilitBitisleri = [];
        $kosucu->kaydet('bekleyen', function (array $yuk, array $is) use (&$kilitBitisleri): void {
            $kilitBitisleri[] = (string) $this->pdo
                ->query('SELECT kilit_bitis FROM jobs WHERE id = ' . (int) $is['id'])
                ->fetchColumn();
        });

        $kosucu->kos();

        self::assertCount(2, $kilitBitisleri, 'İki iş de işlenmeli.');
        self::assertNotSame(
            $kilitBitisleri[0],
            $kilitBitisleri[1],
            'İki işin kira bitişi AYNI — saat tur başına sabitlenmiş, iş koşarken devralınabilir.',
        );
        self::assertGreaterThan($kilitBitisleri[0], $kilitBitisleri[1]);
    }

    public function testSAATVERILMEZSEGERCEKZAMANKULLANILIR(): void
    {
        // Geriye dönük uyum: saat opsiyoneldir, verilmezse sistem saati.
        $kosucu = new JobRunner($this->kuyruk, new NullLogger());
        $sonuc = $kosucu->kos();

        self::assertSame(0, $sonuc['islenen'], 'Boş kuyrukta tur işsiz döner.');
    }
}
