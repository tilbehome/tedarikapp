<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Services\Bildirim\BildirimKatalogu;
use App\Services\Bildirim\BildirimRepository;
use App\Services\Bildirim\BildirimYayinci;
use App\Services\Bildirim\GrupAnahtariCozucu;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\FrozenClock;

/**
 * V3-B A1/A2 — BİLDİRİM YAYINI VE BİRLEŞTİRME DAVRANIŞI.
 *
 * Sınanan asıl şey birleştirmedir. `rate_snapshots`ta aynı saniyede gelen
 * ikinci değişikliğin KAYBOLDUĞUNU görmüştük (İE#22 A3); aynı hatanın burada
 * tekrarlanmadığı doğrulanıyor: pencere içindeki ikinci olay yeni satır
 * açmaz, sayacı artırır ve HİÇBİR olay kaybolmaz.
 */
final class BildirimYayinciTest extends TestCase
{
    private PDO $pdo;
    private Connection $connection;
    private FrozenClock $clock;
    private BildirimRepository $depo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Migration dosyasının kendisi koşulur: şema ile test arasında ikinci
        // bir gerçek kaynak oluşmaz (0033 dersi).
        /** @var \App\Core\Migration $migration */
        $migration = require dirname(__DIR__, 2) . '/migrations/0035_bildirimler.php';
        $migration->up($this->pdo);

        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);
        $this->clock = new FrozenClock();
        $this->depo = new BildirimRepository($this->connection);
    }

    private function yayinci(): BildirimYayinci
    {
        return new BildirimYayinci(
            $this->depo,
            new BildirimKatalogu(dirname(__DIR__, 2)),
            new GrupAnahtariCozucu(),
            $this->clock,
        );
    }

    public function testBIRLESTIRMEACIKOLAYTEKSATIRDATOPLANIR(): void
    {
        // NTF-CAPTURE-ACCEPTED: izinli=true, pencere 5 dk, anahtar kullanici_id+platform.
        $yayinci = $this->yayinci();
        for ($i = 0; $i < 3; $i++) {
            $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', [
                'kullanici_id' => 1,
                'platform' => '1688',
                'urun_adi' => 'Ürün ' . $i,
                'urun_id' => 100 + $i,
            ]);
        }

        $satirlar = $this->depo->listele();
        self::assertCount(1, $satirlar, 'Aynı pencere ve anahtarda üç olay TEK satır olmalı.');
        self::assertSame(3, $satirlar[0]['birlesen_sayi']);
    }

    public function testBIRLESENSATIRTOPLUGOVDEYIKULLANIR(): void
    {
        $yayinci = $this->yayinci();
        $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => '1688', 'urun_adi' => 'İlk']);
        $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => '1688', 'urun_adi' => 'İkinci']);

        $govde = (string) $this->depo->listele()[0]['govde'];

        // Katalogdaki toplu gövde: "{n} ürün panele kabul edildi…"
        self::assertStringContainsString('2', $govde, 'Toplu gövde birleşen sayıyı yazmalı.');
        self::assertStringNotContainsString('İkinci', $govde, 'Birleşen satırda tekil ürün adı kalmamalı.');
    }

    public function testFARKLIANAHTARFARKLISATIRACAR(): void
    {
        $yayinci = $this->yayinci();
        $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => '1688']);
        $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => 'alibaba']);

        self::assertCount(2, $this->depo->listele(), 'Platform değişince birleştirme anahtarı da değişir.');
    }

    public function testPENCEREDISINDAYENISATIRACILIR(): void
    {
        $yayinci = $this->yayinci();
        $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => '1688']);

        // 5 dakikalık pencere kapandı.
        $this->clock->advance('+7 minutes');
        $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => '1688']);

        self::assertCount(2, $this->depo->listele());
    }

    public function testBIRLESTIRMESIKAPALIOLAYAUDITISTER(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(BildirimYayinci::AUDIT_ZORUNLU_MESAJI);

        // NTF-LIST-CREATED: izinli=false.
        $this->yayinci()->yayimla('NTF-LIST-CREATED', ['liste_id' => 5]);
    }

    public function testAUDITVERILIRSEKAPALIOLAYYAZILIR(): void
    {
        $id = $this->yayinci()->yayimla('NTF-LIST-CREATED', ['liste_id' => 5, 'liste_adi' => 'Ağustos'], 42);

        self::assertNotNull($id);
        $satir = $this->depo->listele()[0];
        self::assertSame(42, $satir['audit_id'], 'Denetim bağlantısı kaydedilmeli.');
        self::assertSame(1, $satir['birlesen_sayi']);
    }

    public function testKAPALIOLAYLARBIRLESMEZ(): void
    {
        $yayinci = $this->yayinci();
        $yayinci->yayimla('NTF-LIST-CREATED', ['liste_id' => 5], 10);
        $yayinci->yayimla('NTF-LIST-CREATED', ['liste_id' => 6], 11);

        self::assertCount(2, $this->depo->listele(), 'Birleştirmesi kapalı olay her seferinde yeni satırdır.');
    }

    public function testBILINMEYENOLAYKODUPATLAR(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->yayinci()->yayimla('NTF-UYDURMA-OLAY', [], 1);
    }

    public function testDOLMAYANYERTUTUCULINKIDUSURUR(): void
    {
        // NTF-CAPTURE-ACCEPTED eylem linki `/panel/urunler/{urun_id}` — ürün
        // kimliği yoksa link "/panel/urunler/—" olurdu; böyle bir adres YOKTUR.
        $this->yayinci()->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => '1688']);

        self::assertNull($this->depo->listele()[0]['eylem_linki']);
    }

    public function testYERTUTUCUDOLARSALINKYAZILIR(): void
    {
        $this->yayinci()->yayimla('NTF-CAPTURE-ACCEPTED', [
            'kullanici_id' => 1,
            'platform' => '1688',
            'urun_id' => 77,
        ]);

        self::assertSame('/panel/urunler/77', $this->depo->listele()[0]['eylem_linki']);
    }

    public function testOKUNMUSSATIRAYENIOLAYGELIRSEOKUNMAMISOLUR(): void
    {
        $yayinci = $this->yayinci();
        $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => '1688']);

        $id = (int) $this->depo->listele()[0]['id'];
        self::assertTrue($this->depo->okunduIsaretle($id, $this->clock->now()));
        self::assertSame(0, $this->depo->okunmamisSayisi());

        // "Aynı şey bir daha oldu" GÖRÜLMELİDİR.
        $yayinci->yayimla('NTF-CAPTURE-ACCEPTED', ['kullanici_id' => 1, 'platform' => '1688']);

        self::assertSame(1, $this->depo->okunmamisSayisi());
    }

    public function testHEPSIOKUNDUSAYACISIFIRLAR(): void
    {
        $yayinci = $this->yayinci();
        $yayinci->yayimla('NTF-LIST-CREATED', ['liste_id' => 1], 1);
        $yayinci->yayimla('NTF-LIST-CREATED', ['liste_id' => 2], 2);

        self::assertSame(2, $this->depo->hepsiniOkunduIsaretle($this->clock->now()));
        self::assertSame(0, $this->depo->okunmamisSayisi());
    }

    public function testGOVDESABLONUDOLDURULUR(): void
    {
        $this->yayinci()->yayimla('NTF-LIST-CREATED', ['liste_id' => 3, 'liste_adi' => 'Ağustos Listesi'], 9);

        self::assertStringContainsString('Ağustos Listesi', (string) $this->depo->listele()[0]['govde']);
    }
}
