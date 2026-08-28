<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Services\Kuyruk\HataSinifi;
use App\Services\Kuyruk\JobQueue;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * KUYRUK SERTLEŞTİRME (İE#21 B11 · #12).
 *
 * Dört başlık, dördü de somut bir arızayı kapatır:
 *   kira sahipliği · yeniden deneme sınıfları · ölü mektup eylemleri · adalet.
 */
final class KuyrukSertlestirmeTest extends TestCase
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
        $this->simdi = new DateTimeImmutable('2026-08-23 12:00:00');
    }

    /** @return array<string, mixed> */
    private function satir(int $id): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->pdo->query('SELECT * FROM jobs WHERE id = ' . $id)->fetch();

        return $row;
    }

    // ─────────────────────── KİRA SAHİPLİĞİ ───────────────────────

    public function testSahiplenmeBENZERSIZTOKENURETIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 1], $this->simdi);
        $this->kuyruk->ekle('ceviri', 'b', ['urun_id' => 2], $this->simdi);

        $ilk = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        $ikinci = $this->kuyruk->sahiplen('isci-1', $this->simdi);

        self::assertNotNull($ilk);
        self::assertNotNull($ikinci);
        self::assertNotSame('', (string) $ilk['kilit_token']);
        self::assertNotSame($ilk['kilit_token'], $ikinci['kilit_token']);
    }

    public function testKIRABITISIACIKYAZILIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);

        self::assertNotNull($is);
        self::assertSame(
            $this->simdi->modify('+' . JobQueue::KILIT_OMRU_SANIYE . ' seconds')->format('Y-m-d H:i:s'),
            (string) $is['kilit_bitis'],
        );
    }

    public function testKALPATISIKIRAYIUZATIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $sonra = $this->simdi->modify('+10 minutes');
        self::assertTrue($this->kuyruk->kalpAtisi((int) $is['id'], (string) $is['kilit_token'], $sonra));

        self::assertSame(
            $sonra->modify('+' . JobQueue::KILIT_OMRU_SANIYE . ' seconds')->format('Y-m-d H:i:s'),
            (string) $this->satir((int) $is['id'])['kilit_bitis'],
        );
    }

    public function testYANLISTOKENLAKALPATISIREDDEDILIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        self::assertFalse($this->kuyruk->kalpAtisi((int) $is['id'], 'baska-token', $this->simdi));
    }

    public function testKIRASIDOLANISDEVRALINIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $ilk = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($ilk);

        // Kira dolduktan sonra ikinci işleyici devralabilmeli.
        $sonra = $this->simdi->modify('+' . (JobQueue::KILIT_OMRU_SANIYE + 60) . ' seconds');
        $ikinci = $this->kuyruk->sahiplen('isci-2', $sonra);

        self::assertNotNull($ikinci);
        self::assertSame((int) $ilk['id'], (int) $ikinci['id']);
        self::assertNotSame($ilk['kilit_token'], $ikinci['kilit_token']);
    }

    public function testDEVRALINANISINESKISAHIBISONUCYAZAMAZ(): void
    {
        // #12'nin asıl kapattığı açık: A takılır, kirası dolar, B devralır ve
        // bitirir; sonra A uyanıp "başarılı" der. Token olmasa B'nin sonucu ezilirdi.
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $a = $this->kuyruk->sahiplen('isci-A', $this->simdi);
        self::assertNotNull($a);

        $sonra = $this->simdi->modify('+' . (JobQueue::KILIT_OMRU_SANIYE + 60) . ' seconds');
        $b = $this->kuyruk->sahiplen('isci-B', $sonra);
        self::assertNotNull($b);

        // A geç kalmış sonucu yazmaya çalışır — YAZAMAZ.
        $this->kuyruk->basarili((int) $a['id'], $sonra, (string) $a['kilit_token']);
        self::assertSame(JobQueue::CALISIYOR, (string) $this->satir((int) $a['id'])['durum']);

        // B'nin sonucu geçerlidir.
        $this->kuyruk->basarili((int) $b['id'], $sonra, (string) $b['kilit_token']);
        self::assertSame(JobQueue::BITTI, (string) $this->satir((int) $b['id'])['durum']);
    }

    public function testESKISAHIPBASARISIZDAYAZAMAZ(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $a = $this->kuyruk->sahiplen('isci-A', $this->simdi);
        self::assertNotNull($a);
        $sonra = $this->simdi->modify('+' . (JobQueue::KILIT_OMRU_SANIYE + 60) . ' seconds');
        $this->kuyruk->sahiplen('isci-B', $sonra);

        $this->kuyruk->basarisiz((int) $a['id'], 'geç kalan hata', $sonra, HataSinifi::GECICI, null, (string) $a['kilit_token']);

        self::assertSame(JobQueue::CALISIYOR, (string) $this->satir((int) $a['id'])['durum']);
    }

    // ─────────────────────── YENİDEN DENEME SINIFLARI ───────────────────────

    public function testKALICIHATATEKRARDENENMEZ(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        // İlk denemede kalıcı hata → doğrudan ölü rafı (3 hak harcanmaz).
        $this->kuyruk->basarisiz(
            (int) $is['id'],
            'Ürün bulunamadı (silinmiş olabilir)',
            $this->simdi,
            HataSinifi::KALICI,
        );

        $satir = $this->satir((int) $is['id']);
        self::assertSame(JobQueue::OLU, (string) $satir['durum']);
        self::assertSame(HataSinifi::KALICI, (string) $satir['hata_sinifi']);
        self::assertSame(1, (int) $satir['deneme'], 'Deneme hakları yakılmamalı');
    }

    public function testGECICIHATAYENIDENDENENIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $this->kuyruk->basarisiz((int) $is['id'], 'bağlantı zaman aşımı', $this->simdi, HataSinifi::GECICI);

        $satir = $this->satir((int) $is['id']);
        self::assertSame(JobQueue::BEKLIYOR, (string) $satir['durum']);
        self::assertSame(HataSinifi::GECICI, (string) $satir['hata_sinifi']);
        self::assertGreaterThan((string) $this->simdi->format('Y-m-d H:i:s'), (string) $satir['calisacak_at']);
    }

    public function testHIZSINIRINDASAGLAYICININSURESINESAYGI(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $this->kuyruk->basarisiz((int) $is['id'], '429 rate limit', $this->simdi, HataSinifi::HIZ_SINIRI, 300);

        $bekleyen = (string) $this->satir((int) $is['id'])['calisacak_at'];
        // En az 300 saniye (üstüne küçük pay eklenir), en fazla 5 dk + 10 sn.
        self::assertGreaterThanOrEqual($this->simdi->modify('+301 seconds')->format('Y-m-d H:i:s'), $bekleyen);
        self::assertLessThanOrEqual($this->simdi->modify('+310 seconds')->format('Y-m-d H:i:s'), $bekleyen);
    }

    public function testSINIFLANDIRMAMESAJDANTURER(): void
    {
        self::assertSame(
            HataSinifi::HIZ_SINIRI,
            HataSinifi::siniflandir(new RuntimeException('HTTP 429 Too Many Requests'))['sinif'],
        );
        self::assertSame(
            HataSinifi::KALICI,
            HataSinifi::siniflandir(new RuntimeException('Ürün bulunamadı: #5'))['sinif'],
        );
        self::assertSame(
            HataSinifi::KALICI,
            HataSinifi::siniflandir(new RuntimeException('model_not_found'))['sinif'],
        );
        // Tanınmayan hata GEÇİCİ sayılır: yanlış tarafa düşmek gerekiyorsa
        // "tekrar dene" tarafına düşmek veriyi kaybetmekten iyidir.
        self::assertSame(
            HataSinifi::GECICI,
            HataSinifi::siniflandir(new RuntimeException('bilinmeyen bir şey oldu'))['sinif'],
        );
    }

    public function testRETRYAFTERMESAJDANOKUNUR(): void
    {
        $sonuc = HataSinifi::siniflandir(new RuntimeException('429 rate limit, retry-after: 90'));

        self::assertSame(HataSinifi::HIZ_SINIRI, $sonuc['sinif']);
        self::assertSame(90, $sonuc['bekleme']);
    }

    public function testJITTERBEKLEMEYIDAGITIR(): void
    {
        // Aynı anda patlayan işler aynı saniyede geri dönmemeli.
        $degerler = [];
        for ($i = 0; $i < 40; $i++) {
            $degerler[] = HataSinifi::bekleme(HataSinifi::GECICI, 2);
        }

        self::assertGreaterThan(1, count(array_unique($degerler)), 'Bekleme süreleri dağılmalı');
        foreach ($degerler as $deger) {
            self::assertGreaterThanOrEqual(120, $deger);
            self::assertLessThanOrEqual(150, $deger);
        }
    }

    // ─────────────────────── ÖLÜ MEKTUP EYLEMLERİ ───────────────────────

    public function testVAZGECOLUISISILER(): void
    {
        $id = $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $this->kuyruk->oldur($id, 'kalıcı hata', $this->simdi);

        self::assertTrue($this->kuyruk->vazgec($id));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
    }

    public function testVAZGECBEKLEYENISESILMEZ(): void
    {
        // Bekleyen bir işi silmek, kuyruğu sessizce eksiltmek olurdu.
        $id = $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);

        self::assertFalse($this->kuyruk->vazgec($id));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
    }

    public function testDUZELTYUKUDEGISTIRIPKUYRUGAALIR(): void
    {
        $id = $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 999], $this->simdi);
        $this->kuyruk->oldur($id, 'Ürün bulunamadı: #999', $this->simdi);

        self::assertTrue($this->kuyruk->yukuDuzelt($id, ['urun_id' => 12], $this->simdi));

        $satir = $this->satir($id);
        self::assertSame(JobQueue::BEKLIYOR, (string) $satir['durum']);
        self::assertSame(0, (int) $satir['deneme']);
        self::assertStringContainsString('"urun_id":12', (string) $satir['yuk']);
        // Denetim izi KOPMAZ: aynı satırda kalır, id değişmez.
        self::assertSame($id, (int) $satir['id']);
    }

    // ─────────────────────── ADALET + METRİKLER ───────────────────────

    public function testTURLERDONUSUMLUSECILIR(): void
    {
        // 5 çeviri + 1 skor işi. Adalet olmasa skor işi en sona kalırdı.
        for ($i = 0; $i < 5; $i++) {
            $this->kuyruk->ekle('ceviri', 'c' . $i, ['urun_id' => $i], $this->simdi);
        }
        $this->kuyruk->ekle('skor', 's1', ['urun_id' => 1], $this->simdi);

        $ilkIkiTur = [];
        for ($i = 0; $i < 2; $i++) {
            $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
            self::assertNotNull($is);
            $ilkIkiTur[] = (string) $is['tur'];
        }

        self::assertContains('skor', $ilkIkiTur, 'Skor işi 5 çevirinin arkasında açlıktan ölmemeli');
    }

    public function testONCELIKADALETINUSTUNDEDIR(): void
    {
        // Adalet EŞİT öncelikler arasında paylaştırır; önceliğin yerine geçmez.
        $this->kuyruk->ekle('ceviri', 'c1', [], $this->simdi, oncelik: 10);
        $this->kuyruk->ekle('skor', 's1', [], $this->simdi, oncelik: 200);

        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);

        self::assertNotNull($is);
        self::assertSame('ceviri', (string) $is['tur']);
    }

    public function testSAGLIKMETRIKLERIDOLU(): void
    {
        $biten = $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $this->kuyruk->sahiplen('isci-1', $this->simdi);
        $this->kuyruk->basarili($biten, $this->simdi);

        $olen = $this->kuyruk->ekle('ceviri', 'b', [], $this->simdi);
        $this->kuyruk->oldur($olen, 'kalıcı', $this->simdi);

        $saglik = $this->kuyruk->saglik($this->simdi);

        self::assertSame(1, $saglik['saatlik_biten']);
        self::assertSame(1, $saglik['saatlik_olen']);
        self::assertSame(50, $saglik['hata_orani_yuzde']);
        self::assertSame(1, $saglik['olu']);
    }

    public function testHATAORANIISYOKKENSIFIR(): void
    {
        // Sıfıra bölme değil, "sorun yok" cevabı beklenir.
        self::assertSame(0, $this->kuyruk->saglik($this->simdi)['hata_orani_yuzde']);
    }
}
