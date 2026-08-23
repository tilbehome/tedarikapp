<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\JobRunner;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * İE#20 C3 — iş kuyruğu.
 *
 * Kuyruğun değeri "iş sırada bekliyor" demek değil, ŞU DÖRT GARANTİDİR:
 * mükerrer iş yok, yarışta tek sahip, ölen işleyici işi rehin almaz, başarısız
 * iş kaybolmaz. Her biri ayrı sınanır — çünkü her biri ayrı ayrı bozulabilir.
 */
final class JobQueueTest extends TestCase
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
        // Şema GERÇEK migration'lardan kurulur — elle yazılmış bir test şeması,
        // üretimden sapınca testi yeşil ama sistemi kırık bırakır.
        foreach (['0024_create_jobs', '0028_kuyruk_sertlestirme'] as $ad) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $ad . '.php';
            $migration->up($this->pdo);
        }

        $this->kuyruk = new JobQueue(Connection::fromCallable(fn (): PDO => $this->pdo));
        $this->simdi = new DateTimeImmutable('2026-08-22 12:00:00');
    }

    public function testAyniIsIKIKEZKUYRUGAGIRMEZ(): void
    {
        $ilk = $this->kuyruk->ekle('ceviri', 'urun:42', ['urun_id' => 42], $this->simdi);
        $ikinci = $this->kuyruk->ekle('ceviri', 'urun:42', ['urun_id' => 42], $this->simdi);

        self::assertSame($ilk, $ikinci, 'Aynı iş ikinci kez eklenince YENİ satır açılmamalı.');
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
    }

    public function testFarkliAnahtarAYRIISTIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'urun:1', [], $this->simdi);
        $this->kuyruk->ekle('ceviri', 'urun:2', [], $this->simdi);

        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
    }

    public function testIKIISLEYICIAYNIISIALAMAZ(): void
    {
        $this->kuyruk->ekle('ceviri', 'urun:42', [], $this->simdi);

        $birinci = $this->kuyruk->sahiplen('isleyici-A', $this->simdi);
        $ikinci = $this->kuyruk->sahiplen('isleyici-B', $this->simdi);

        self::assertNotNull($birinci);
        self::assertNull($ikinci, 'İkinci işleyici aynı işi aldı — mükerrer çalışma.');
    }

    public function testOLENISLEYICIISIREHINALMAZ(): void
    {
        $this->kuyruk->ekle('ceviri', 'urun:42', [], $this->simdi);
        $this->kuyruk->sahiplen('olen-isleyici', $this->simdi);

        // Kilit ömrü dolduktan sonra iş yeniden alınabilmeli.
        $sonra = $this->simdi->modify('+' . (JobQueue::KILIT_OMRU_SANIYE + 60) . ' seconds');
        $yeni = $this->kuyruk->sahiplen('yeni-isleyici', $sonra);

        self::assertNotNull($yeni, 'Ölen işleyicinin işi sonsuza dek kilitli kaldı.');
        self::assertSame(2, (int) $yeni['deneme'], 'İkinci sahiplenme deneme sayacını artırmalı.');
    }

    public function testBASARISIZISARTANBEKLEMEYLEGERIBIRAKILIR(): void
    {
        $id = $this->kuyruk->ekle('ceviri', 'urun:42', [], $this->simdi);
        $this->kuyruk->sahiplen('isleyici', $this->simdi);
        $this->kuyruk->basarisiz($id, 'ağ hatası', $this->simdi);

        $satir = $this->pdo->query('SELECT * FROM jobs WHERE id = ' . $id)->fetch();
        self::assertSame(JobQueue::BEKLIYOR, $satir['durum']);
        self::assertSame('ağ hatası', $satir['hata'], 'Hata saklanmalı — sessiz başarısızlık yok.');

        // Hemen tekrar alınmamalı (backoff): aynı anda sahiplenme boş dönmeli.
        self::assertNull($this->kuyruk->sahiplen('isleyici', $this->simdi));
        self::assertNotNull($this->kuyruk->sahiplen('isleyici', $this->simdi->modify('+2 hours')));
    }

    public function testDENEMEHAKKIBITENISOLURAFINADUSER(): void
    {
        $id = $this->kuyruk->ekle('ceviri', 'urun:42', [], $this->simdi, maxDeneme: 2);

        $an = $this->simdi;
        for ($i = 0; $i < 2; $i++) {
            $an = $an->modify('+3 hours');
            self::assertNotNull($this->kuyruk->sahiplen('isleyici', $an));
            $this->kuyruk->basarisiz($id, 'kalıcı hata', $an);
        }

        $satir = $this->pdo->query('SELECT * FROM jobs WHERE id = ' . $id)->fetch();
        self::assertSame(JobQueue::OLU, $satir['durum'], 'Deneme hakkı biten iş ölü rafına düşmeli.');
        self::assertSame('kalıcı hata', $satir['hata']);

        // Ölü raf panelde GÖRÜNÜR.
        self::assertCount(1, $this->kuyruk->oluIsler());
    }

    public function testOLUISYENIDENISTENINCECANLANIR(): void
    {
        $id = $this->kuyruk->ekle('ceviri', 'urun:42', [], $this->simdi, maxDeneme: 1);
        $this->kuyruk->sahiplen('isleyici', $this->simdi);
        $this->kuyruk->basarisiz($id, 'hata', $this->simdi);
        self::assertSame(JobQueue::OLU, $this->pdo->query('SELECT durum FROM jobs WHERE id = ' . $id)->fetchColumn());

        // Kullanıcı "yeniden dene" dediğinde ona "zaten kuyrukta" demek hiçbir şey yapmamaktır.
        $tekrar = $this->kuyruk->ekle('ceviri', 'urun:42', [], $this->simdi);

        self::assertSame($id, $tekrar);
        self::assertSame(JobQueue::BEKLIYOR, $this->pdo->query('SELECT durum FROM jobs WHERE id = ' . $id)->fetchColumn());
    }

    public function testSaglikOzetiPANELICINDOGRU(): void
    {
        // Sahiplenme EN ESKİ işi alır; bu yüzden "çalışan" işi ÖNCE kuyruğa koyup
        // hemen sahipleniyoruz — sonra eklenen bekleyenler kuyrukta kalır.
        $calisan = $this->kuyruk->ekle('ceviri', 'c', [], $this->simdi->modify('-2 hours'));
        $this->kuyruk->sahiplen('isleyici', $this->simdi);

        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi->modify('-30 minutes'));
        $this->kuyruk->ekle('skor', 'b', [], $this->simdi);

        $saglik = $this->kuyruk->saglik($this->simdi);

        self::assertSame(2, $saglik['bekleyen']);
        self::assertSame(1, $saglik['calisan']);
        self::assertSame(0, $saglik['olu']);
        self::assertSame(30, $saglik['en_eski_bekleyen_dakika'], 'En eski bekleyen iş yaşı yanlış — tıkanma görünmez olur.');
        self::assertArrayHasKey('ceviri', $saglik['turler']);
        self::assertGreaterThan(0, $calisan);
    }

    public function testBitenIsTEMIZLENIROLUISKALIR(): void
    {
        $biten = $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $this->kuyruk->sahiplen('isleyici', $this->simdi);
        $this->kuyruk->basarili($biten, $this->simdi);

        $olu = $this->kuyruk->ekle('ceviri', 'b', [], $this->simdi, maxDeneme: 1);
        $this->kuyruk->sahiplen('isleyici', $this->simdi);
        $this->kuyruk->basarisiz($olu, 'hata', $this->simdi);

        $silinen = $this->kuyruk->temizle($this->simdi->modify('+30 days'));

        self::assertSame(1, $silinen);
        self::assertSame(
            JobQueue::OLU,
            $this->pdo->query('SELECT durum FROM jobs WHERE id = ' . $olu)->fetchColumn(),
            'Ölü iş bir arıza kaydıdır; temizlik onu SİLMEMELİ.',
        );
    }

    public function testKosucuTANINMAYANTURUOLURAFINAGONDERIR(): void
    {
        $id = $this->kuyruk->ekle('bilinmeyen_tur', 'x', [], $this->simdi);
        $kosucu = new JobRunner($this->kuyruk, new NullLogger());

        $sonuc = $kosucu->kos($this->simdi);

        self::assertSame(1, $sonuc['islenen']);
        self::assertSame(1, $sonuc['basarisiz']);
        self::assertSame(
            JobQueue::OLU,
            $this->pdo->query('SELECT durum FROM jobs WHERE id = ' . $id)->fetchColumn(),
            'Tanınmayan tür sonsuz döngüye girmemeli, GÖRÜNÜR olmalı.',
        );
    }

    public function testKosucuISLEYICIYIYUKLEBIRLIKTECAGIRIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'urun:7', ['urun_id' => 7, 'diller' => ['tr', 'en']], $this->simdi);

        $gelen = null;
        $kosucu = new JobRunner($this->kuyruk, new NullLogger());
        $kosucu->kaydet('ceviri', function (array $yuk) use (&$gelen): void {
            $gelen = $yuk;
        });

        $sonuc = $kosucu->kos($this->simdi);

        self::assertSame(1, $sonuc['basarili']);
        self::assertSame(['urun_id' => 7, 'diller' => ['tr', 'en']], $gelen);
    }

    public function testKosucuIS_SINIRINDADURUR(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->kuyruk->ekle('ceviri', 'urun:' . $i, [], $this->simdi);
        }

        $kosucu = new JobRunner($this->kuyruk, new NullLogger(), sureSiniri: 50, isSiniri: 2);
        $kosucu->kaydet('ceviri', static function (): void {
        });

        $sonuc = $kosucu->kos($this->simdi);

        self::assertSame(2, $sonuc['islenen']);
        self::assertStringContainsString('iş sınırı', $sonuc['durma_nedeni']);
    }

    public function testKosucuHATAYIISEYAZAR(): void
    {
        $id = $this->kuyruk->ekle('ceviri', 'urun:1', [], $this->simdi);
        $kosucu = new JobRunner($this->kuyruk, new NullLogger());
        $kosucu->kaydet('ceviri', static function (): void {
            throw new RuntimeException('sağlayıcı 500 döndü');
        });

        $kosucu->kos($this->simdi);

        self::assertSame(
            'sağlayıcı 500 döndü',
            $this->pdo->query('SELECT hata FROM jobs WHERE id = ' . $id)->fetchColumn(),
        );
    }
}
