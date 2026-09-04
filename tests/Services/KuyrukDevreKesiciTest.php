<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Models\SettingsRepository;
use App\Services\Kuyruk\DevreKesici;
use App\Services\Kuyruk\IsErtelendi;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\JobRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\Support\FrozenClock;

/**
 * v1.2.2 BLOK D6 — ERTELEME + DEVRE KESİCİ.
 *
 * İKİ AYRI KAVRAM, İKİ AYRI DAVRANIŞ:
 *
 *   ERTELENDİ ≠ BAŞARISIZ. Bellek bütçesi dolduğu için yarım bırakılan iş
 *   hata yapmamıştır; koşullar uygun değildi. Bunu "geçici hata" saymak üç
 *   deneme hakkının birini yakar ve üçüncü seferde işi ÖLÜ rafına gönderir —
 *   oysa iş hiç yanlış bir şey yapmadı. Erteleme deneme hakkı YAKMAZ.
 *
 *   DEVRE KESİCİ: aynı türde N geçici hata art arda gelirse sebep tek tek
 *   işlerde değil, ORTAK bir kaynaktadır (kaynak site çöktü, DNS bozuldu,
 *   çıkış bant genişliği bitti). O hâlde bir sonraki işi denemek düzeltmez;
 *   yalnız hata sayısını ve sağlayıcıya vurulan darbeyi büyütür. Kesici
 *   AÇIKKEN o türde YENİ İŞ ALINMAZ; 15 dakika sonra kendiliğinden kapanır.
 *   Kesici TÜR bazlıdır: medya kaynağı çöktü diye çeviri durmaz.
 *
 * Davranış testleri ÖNCE (PM emri): refactor bunlara karşı yapılır.
 */
final class KuyrukDevreKesiciTest extends TestCase
{
    private PDO $pdo;
    private Connection $baglanti;
    private JobQueue $kuyruk;
    private SettingsRepository $ayarlar;
    private FrozenClock $saat;

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
        // settings migration'ı MySQL'e özgü DDL taşır (ENGINE/CHARSET); kesici
        // yalnız anahtar/değer okur-yazar, HTTP testlerindeki SQLite şeması yeter.
        $this->pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');

        $this->baglanti = Connection::fromCallable(fn (): PDO => $this->pdo);
        $this->kuyruk = new JobQueue($this->baglanti);
        $this->ayarlar = new SettingsRepository($this->baglanti);
        $this->saat = new FrozenClock('2026-09-04 12:00:00', 'UTC');
    }

    private function kesici(int $esik = 3, int $dakika = 15): DevreKesici
    {
        return new DevreKesici($this->ayarlar, $this->saat, esik: $esik, dakika: $dakika);
    }

    private function kosucu(?DevreKesici $kesici = null): JobRunner
    {
        return new JobRunner($this->kuyruk, new NullLogger(), saat: $this->saat, devreKesici: $kesici);
    }

    // ── ERTELEME ────────────────────────────────────────────────────────

    public function testERTELENENISDENEMEHAKKIYAKMAZ(): void
    {
        $this->kuyruk->ekle('medya', 'urun:1', ['urun_id' => 1], $this->saat->now(), maxDeneme: 3);
        $kosucu = $this->kosucu();
        $kosucu->kaydet('medya', static function (): void {
            throw new IsErtelendi('bellek bütçesi doldu', 300);
        });

        $sonuc = $kosucu->kos();

        self::assertSame(1, $sonuc['ertelenen'], 'Erteleme AYRI sayılır.');
        self::assertSame(0, $sonuc['basarisiz'], 'Ertelenen iş BAŞARISIZ değildir.');

        $satir = $this->pdo->query('SELECT durum, deneme, hata_sinifi, calisacak_at FROM jobs')->fetch();
        self::assertSame(JobQueue::BEKLIYOR, $satir['durum']);
        self::assertSame(0, (int) $satir['deneme'], 'Deneme sayacı ertelemede GERİ ALINIR.');
        self::assertSame(JobQueue::SINIF_ERTELENDI, $satir['hata_sinifi']);
        self::assertSame('2026-09-04 12:05:00', $satir['calisacak_at'], '300 sn sonra yeniden alınabilir.');
    }

    public function testERTELENENISUCKEZERTELENSEDEOLMEZ(): void
    {
        // Bellek bütçesi üç tur üst üste dolsa bile iş ölü rafına GİTMEZ:
        // "koşullar uygun değil" ile "iş bozuk" aynı şey değildir.
        $this->kuyruk->ekle('medya', 'urun:1', [], $this->saat->now(), maxDeneme: 3);
        $kosucu = $this->kosucu();
        $kosucu->kaydet('medya', static function (): void {
            throw new IsErtelendi('bellek', 60);
        });

        for ($tur = 0; $tur < 4; $tur++) {
            $kosucu->kos();
            $this->saat->advance('+61 seconds');
        }

        self::assertSame(JobQueue::BEKLIYOR, $this->pdo->query('SELECT durum FROM jobs')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM jobs WHERE durum = \'olu\'')->fetchColumn());
    }

    public function testERTELEMESAHIPLIKDENETIMLIDIR(): void
    {
        // A1 ilkesi: kirası elinden alınmış işleyici erteleme de yazamaz.
        $this->kuyruk->ekle('medya', 'urun:1', [], $this->saat->now());
        $is = $this->kuyruk->sahiplen('A', $this->saat->now());
        self::assertNotNull($is);

        $this->expectException(\App\Services\Kuyruk\KiraKaybedildi::class);
        $this->kuyruk->ertele((int) $is['id'], 'yanlis-token', $this->saat->now(), 'bellek', 60);
    }

    // ── DEVRE KESİCİ ─────────────────────────────────────────────────────

    public function testESIKALTINDAKESICIKAPALI(): void
    {
        $kesici = $this->kesici(esik: 3);
        $kesici->geciciHata('medya');
        $kesici->geciciHata('medya');

        self::assertFalse($kesici->acikMi('medya'));
    }

    public function testNGECICIHATAKESICIYIACAR(): void
    {
        $kesici = $this->kesici(esik: 3, dakika: 15);
        self::assertFalse($kesici->geciciHata('medya'));
        self::assertFalse($kesici->geciciHata('medya'));
        self::assertTrue($kesici->geciciHata('medya'), 'Üçüncü geçici hata kesiciyi AÇMALI ve bunu bildirmeli.');

        self::assertTrue($kesici->acikMi('medya'));
        $durum = $kesici->durum('medya');
        self::assertSame('2026-09-04T12:15:00+00:00', $durum['kapanma_at']);
    }

    public function testKESICITURBAZLIDIR(): void
    {
        // Medya kaynağı çöktü diye çeviri DURMAZ.
        $kesici = $this->kesici(esik: 2);
        $kesici->geciciHata('medya');
        $kesici->geciciHata('medya');

        self::assertTrue($kesici->acikMi('medya'));
        self::assertFalse($kesici->acikMi('ceviri'));
    }

    public function testONBESDAKIKASONRAKENDILIGINDENKAPANIR(): void
    {
        $kesici = $this->kesici(esik: 1, dakika: 15);
        $kesici->geciciHata('medya');
        self::assertTrue($kesici->acikMi('medya'));

        $this->saat->advance('+14 minutes');
        self::assertTrue($kesici->acikMi('medya'), '14. dakikada hâlâ açık.');

        $this->saat->advance('+61 seconds');
        self::assertFalse($kesici->acikMi('medya'), '15 dakika dolunca kapanır — elle müdahale gerekmez.');
    }

    public function testBASARIKESICISAYACINISIFIRLAR(): void
    {
        // İki hata, bir başarı, iki hata: dört hata ama ART ARDA değil.
        $kesici = $this->kesici(esik: 3);
        $kesici->geciciHata('medya');
        $kesici->geciciHata('medya');
        $kesici->basari('medya');
        $kesici->geciciHata('medya');
        $kesici->geciciHata('medya');

        self::assertFalse($kesici->acikMi('medya'), 'Araya giren başarı sayacı sıfırlar.');
    }

    public function testKESICIACIKKENOTURDEYENIISALINMAZ(): void
    {
        // ASIL KORUMA: kesici açıkken kuyruk o türü ATLAR; diğer türler akar.
        $this->kuyruk->ekle('medya', 'urun:1', [], $this->saat->now());
        $this->kuyruk->ekle('ceviri', 'urun:1', [], $this->saat->now());

        $kesici = $this->kesici(esik: 1);
        $kesici->geciciHata('medya'); // açık

        $kosucu = $this->kosucu($kesici);
        $medyaKostu = false;
        $ceviriKostu = false;
        $kosucu->kaydet('medya', static function () use (&$medyaKostu): void {
            $medyaKostu = true;
        });
        $kosucu->kaydet('ceviri', static function () use (&$ceviriKostu): void {
            $ceviriKostu = true;
        });

        $sonuc = $kosucu->kos();

        self::assertFalse($medyaKostu, 'Kesici açıkken medya işi ALINMAMALI.');
        self::assertTrue($ceviriKostu, 'Çeviri işi etkilenmemeli.');
        self::assertSame(JobQueue::BEKLIYOR, $this->pdo->query("SELECT durum FROM jobs WHERE tur = 'medya'")->fetchColumn());
        self::assertStringContainsString('devre kesici', $sonuc['durma_nedeni'] . ' ' . implode(' ', $sonuc['atlanan_turler']));
    }

    public function testGECICIHATALARKESICIYIBESLERKALICILARBESLEMEZ(): void
    {
        // Kesici GEÇİCİ hatayla açılır: kalıcı hata (yanlış adres, reddedilen
        // host) tek işin sorunudur, ortak kaynağın değil.
        $this->kuyruk->ekle('medya', 'urun:1', [], $this->saat->now());
        $this->kuyruk->ekle('medya', 'urun:2', [], $this->saat->now());
        $this->kuyruk->ekle('medya', 'urun:3', [], $this->saat->now());

        $kesici = $this->kesici(esik: 2);
        $kosucu = $this->kosucu($kesici);
        $kosucu->kaydet('medya', static function (): void {
            throw new RuntimeException('adres bulunamadı'); // KALICI sınıf
        });
        $kosucu->kos();

        self::assertFalse($kesici->acikMi('medya'), 'Kalıcı hatalar kesiciyi AÇMAMALI.');

        // Şimdi geçici hatalar.
        $this->kuyruk->ekle('medya', 'urun:4', [], $this->saat->now());
        $this->kuyruk->ekle('medya', 'urun:5', [], $this->saat->now());
        $kosucu2 = $this->kosucu($kesici);
        $kosucu2->kaydet('medya', static function (): void {
            throw new RuntimeException('bağlantı zaman aşımı'); // GEÇİCİ
        });
        $kosucu2->kos();

        self::assertTrue($kesici->acikMi('medya'), 'İki geçici hata kesiciyi açmalı.');
    }

    public function testTURSONUCUZIRVEBELLEGIRAPORLAR(): void
    {
        // memory_get_peak_usage raporda: bütçe doğru mu ayarlanmış, ancak
        // ölçülürse bilinir.
        $this->kuyruk->ekle('medya', 'urun:1', [], $this->saat->now());
        $kosucu = $this->kosucu();
        $kosucu->kaydet('medya', static function (): void {
        });

        $sonuc = $kosucu->kos();

        self::assertArrayHasKey('bellek_zirve_mb', $sonuc);
        self::assertGreaterThan(0, $sonuc['bellek_zirve_mb']);
    }

    public function testSAGLIKERTELENENVEKESICIYIGOSTERIR(): void
    {
        $this->kuyruk->ekle('medya', 'urun:1', [], $this->saat->now());
        $is = $this->kuyruk->sahiplen('A', $this->saat->now());
        self::assertNotNull($is);
        $this->kuyruk->ertele((int) $is['id'], (string) $is['kilit_token'], $this->saat->now(), 'bellek', 60);

        $saglik = $this->kuyruk->saglik($this->saat->now());

        self::assertSame(1, $saglik['ertelenen'], 'Ertelenen işler sağlıkta AYRI görünür.');
    }
}
