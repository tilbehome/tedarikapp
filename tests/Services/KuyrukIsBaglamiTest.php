<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Services\Kuyruk\IsBaglami;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\JobRunner;
use App\Services\Kuyruk\KiraKaybedildi;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * SERTLEŞTİRME v1.2.1 BLOK A2 — İŞ BAĞLAMI VE KALP ATIŞI (TDR-002).
 *
 * KORUNAN FELAKET: uzun süren bir iş (10 görselli ürün, her biri 25 sn zaman
 * aşımı = 250 sn) 300 saniyelik kirayı AŞAR. Kira dolunca iş devralınır ve
 * İKİ işleyici aynı görselleri indirmeye başlar. Eski kodda işleyicinin
 * kirasını uzatmak için elinde bir şey YOKTU: `JobRunner::kalpAtisi()` vardı
 * ama işleyiciye ne koşucu ne token geçiliyordu — yani hiçbir işleyici onu
 * çağıramıyordu. Ölü bir API.
 *
 * DAHA KÖTÜSÜ: kira kaybedildikten sonra işleyici çalışmaya DEVAM ediyordu.
 * Dosya indiriyor, diske yazıyor, dış servise vuruyordu — hepsi başka bir
 * işleyicinin sahiplendiği iş için. Yan etki, sahiplik kaybından sonra
 * SÜRMEMELİDİR.
 *
 * `IsBaglami` işleyiciye üçüncü parametre olarak geçer: her dış adımdan önce
 * ve sonra `kontrolNoktasi()` çağrılır. Kira kaybedilmişse `KiraKaybedildi`
 * atılır ve döngü ORADA durur.
 */
final class KuyrukIsBaglamiTest extends TestCase
{
    private PDO $pdo;
    private JobQueue $kuyruk;
    private DateTimeImmutable $simdi;

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
        $this->simdi = new DateTimeImmutable('2026-08-31 12:00:00');
    }

    public function testISLEYICIYEBAGLAMGECILIR(): void
    {
        $this->kuyruk->ekle('deneme', 'a', ['x' => 1], $this->simdi);
        $kosucu = new JobRunner($this->kuyruk, new NullLogger());

        $gorulen = null;
        $kosucu->kaydet('deneme', static function (array $yuk, array $is, ?IsBaglami $ctx = null) use (&$gorulen): void {
            $gorulen = $ctx;
        });

        $kosucu->kos($this->simdi);

        self::assertInstanceOf(IsBaglami::class, $gorulen, 'İşleyici bağlam almalı — eski API onu hiç geçmiyordu.');
    }

    public function testKALPATISIKIRAYIUZATIR(): void
    {
        $this->kuyruk->ekle('deneme', 'a', [], $this->simdi);
        $kosucu = new JobRunner($this->kuyruk, new NullLogger());

        $oncekiBitis = null;
        $sonrakiBitis = null;
        $kosucu->kaydet('deneme', function (array $yuk, array $is, IsBaglami $ctx) use (&$oncekiBitis, &$sonrakiBitis): void {
            $oncekiBitis = (string) $this->pdo->query('SELECT kilit_bitis FROM jobs LIMIT 1')->fetchColumn();
            // Uzun iş: 200 saniye sonra kalp atışı.
            $ctx->kontrolNoktasi($this->simdi->modify('+200 seconds'));
            $sonrakiBitis = (string) $this->pdo->query('SELECT kilit_bitis FROM jobs LIMIT 1')->fetchColumn();
        });

        $kosucu->kos($this->simdi);

        self::assertNotNull($oncekiBitis);
        self::assertGreaterThan($oncekiBitis, $sonrakiBitis, 'Kalp atışı kirayı İLERİ taşımalı.');
    }

    public function testKIRAKAYBEDILINCEYANETKIDEVAMETMEZ(): void
    {
        // İşleyici üç adım atacak. İkinci adımdan önce kira devralınır.
        // Üçüncü adım HİÇ ÇALIŞMAMALI.
        $this->kuyruk->ekle('deneme', 'a', [], $this->simdi);
        $kosucu = new JobRunner($this->kuyruk, new NullLogger());

        $adimlar = [];
        $kosucu->kaydet('deneme', function (array $yuk, array $is, IsBaglami $ctx) use (&$adimlar): void {
            $adimlar[] = 1;
            $ctx->kontrolNoktasi($this->simdi);

            // Başka bir işleyici kirayı devralır (kira dolmuş gibi).
            $this->pdo->exec("UPDATE jobs SET kilit_token = 'baskasinin' WHERE id = " . (int) $is['id']);

            $adimlar[] = 2;
            $ctx->kontrolNoktasi($this->simdi);

            $adimlar[] = 3; // BURAYA GELİNMEMELİ
        });

        $kosucu->kos($this->simdi);

        self::assertSame([1, 2], $adimlar, 'Kira kaybedildikten sonra üçüncü adım çalışmamalı.');
    }

    public function testKIRAKAYBIISIBASARISIZSAYAR(): void
    {
        // Kira kaybı işleyicinin ortasında olursa iş "bitti" damgalanamaz —
        // zaten CAS de buna izin vermez; tur raporu da doğru saymalı.
        $this->kuyruk->ekle('deneme', 'a', [], $this->simdi);
        $kosucu = new JobRunner($this->kuyruk, new NullLogger());

        $kosucu->kaydet('deneme', function (array $yuk, array $is, IsBaglami $ctx): void {
            $this->pdo->exec("UPDATE jobs SET kilit_token = 'baskasinin' WHERE id = " . (int) $is['id']);
            $ctx->kontrolNoktasi($this->simdi);
        });

        $sonuc = $kosucu->kos($this->simdi);

        self::assertSame(0, $sonuc['basarili'], 'Kirası kaybedilen iş BAŞARILI sayılmamalı.');
        self::assertSame(1, $sonuc['basarisiz']);
    }

    public function testBAGLAMKIRAKAYBINDATIPLIISTISNAATAR(): void
    {
        $this->kuyruk->ekle('deneme', 'a', [], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $baglam = new IsBaglami($this->kuyruk, (int) $is['id'], 'yanlis-token');

        $this->expectException(KiraKaybedildi::class);
        $baglam->kontrolNoktasi($this->simdi);
    }
}
