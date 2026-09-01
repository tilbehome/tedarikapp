<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Services\Kuyruk\HataSinifi;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\KiraKaybedildi;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * SERTLEŞTİRME v1.2.1 BLOK A1 — SONUÇ YAZIMI TEK CAS'TIR (TDR-001..003).
 *
 * KORUNAN FELAKET: bir iş 300 saniyelik kirasını aşar, kira devralınır ve
 * İKİNCİ bir işleyici aynı işi baştan koşmaya başlar. Bu sırada BİRİNCİ
 * işleyici — hâlâ hayatta, yalnız yavaş — işini bitirir ve sonucu yazar.
 * Sonuç: ikinci işleyicinin çalışan işi "bitti" damgalanır, kirası silinir
 * ve bittiğinde onun yazımı da başka bir satırı ezer. Kimse hata görmez.
 *
 * ESKİ KOD BUNU İKİ AYRI YOLDAN MÜMKÜN KILIYORDU:
 *
 *   1. `basarili()` token'ı OPSİYONELDİ (`$token = ''`). Boş geçilince WHERE
 *      yalnız `id`ye bakıyordu — sahiplik denetimi hiç yapılmıyordu. Üstelik
 *      `durum` da denetlenmiyordu: ölü rafındaki bir iş "bitti"ye çevrilebilirdi.
 *   2. `basarisiz()` ÖNCE OKUYUP SONRA YAZIYORDU (SELECT → PHP karşılaştırması
 *      → UPDATE). İki ifade arasında kira devralınabilir; okuma anında geçerli
 *      olan token, yazma anında geçersizdir. Klasik TOCTOU. Yazan UPDATE ise
 *      token'ı WHERE'ine hiç almıyordu.
 *   3. `oldur()` hiçbir sahiplik denetimi yapmıyordu ve `basarisiz()` ölüm
 *      yoluna oradan giriyordu — yani ölüm yolu tamamen denetimsizdi.
 *
 * YENİ SÖZLEŞME: sonuç yazımı TEK ifadedir ve
 * `WHERE id = ? AND durum = 'calisiyor' AND kilit_token = ?` taşır.
 * `rowCount() !== 1` KAYIP SAHİPLİKTİR ve `KiraKaybedildi` ile bildirilir —
 * sessizce yutulmaz, çünkü yutulursa işleyici kendini başarılı sanır.
 *
 * `oldur()` yönetici eylemidir: token istemez, ADI AÇIKÇA yönetici yolunu
 * söyler (`yoneticiOldur()`) ve iş akışından çağrılmaz.
 */
final class KuyrukKiraSahipligiTest extends TestCase
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

    /** @return array<string, mixed> */
    private function satir(int $id): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->pdo->query('SELECT * FROM jobs WHERE id = ' . $id)->fetch();

        return $row;
    }

    /**
     * Kirası devralınmış bir iş kurar.
     *
     * @return array{id: int, eski: string, yeni: string} eski/yeni kira token'ı
     */
    private function kirasiDevralinmisIs(): array
    {
        $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 1], $this->simdi);

        $ilk = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($ilk);
        $eski = (string) $ilk['kilit_token'];

        // Kira süresi dolar; ikinci işleyici işi devralır.
        $sonra = $this->simdi->modify('+' . (JobQueue::KILIT_OMRU_SANIYE + 60) . ' seconds');
        $ikinci = $this->kuyruk->sahiplen('isci-2', $sonra);
        self::assertNotNull($ikinci, 'Kira dolunca iş yeniden alınabilir olmalı.');

        $yeni = (string) $ikinci['kilit_token'];
        self::assertNotSame($eski, $yeni, 'Devralma yeni token üretmeli.');

        return ['id' => (int) $ilk['id'], 'eski' => $eski, 'yeni' => $yeni];
    }

    // ─────────────── BAŞARI YOLU ───────────────

    public function testESKISAHIPBASARIYIYAZAMAZ(): void
    {
        ['id' => $id, 'eski' => $eski, 'yeni' => $yeni] = $this->kirasiDevralinmisIs();

        $this->expectException(KiraKaybedildi::class);

        try {
            $this->kuyruk->basarili($id, $this->simdi, $eski);
        } finally {
            $satir = $this->satir($id);
            self::assertSame(JobQueue::CALISIYOR, $satir['durum'], 'İş ikinci işleyicide ÇALIŞIYOR kalmalı.');
            self::assertSame($yeni, $satir['kilit_token'], 'Kira token\'ı ezilmemeli.');
        }
    }

    public function testTOKENSIZBASARIYAZIMIREDDEDILIR(): void
    {
        // Eski imza token'ı opsiyonel yapıyordu; boş token "sahiplik denetimi
        // yok" demekti ve HER çağrı geçerdi.
        $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 1], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $this->expectException(KiraKaybedildi::class);
        $this->kuyruk->basarili((int) $is['id'], $this->simdi, '');
    }

    public function testGERCEKSAHIPBASARIYIYAZAR(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 1], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $this->kuyruk->basarili((int) $is['id'], $this->simdi, (string) $is['kilit_token']);

        $satir = $this->satir((int) $is['id']);
        self::assertSame(JobQueue::BITTI, $satir['durum']);
        self::assertNull($satir['kilit_token'], 'Biten işin kirası bırakılmalı.');
    }

    public function testBITMISISYENIDENBITIRILEMEZ(): void
    {
        // `durum = calisiyor` koşulu olmasaydı, elinde eski token tutan bir
        // işleyici BİTMİŞ ya da ÖLÜ bir işi yeniden yazabilirdi.
        $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 1], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);
        $token = (string) $is['kilit_token'];
        $this->kuyruk->basarili((int) $is['id'], $this->simdi, $token);

        $this->expectException(KiraKaybedildi::class);
        $this->kuyruk->basarili((int) $is['id'], $this->simdi, $token);
    }

    // ─────────────── BAŞARISIZLIK YOLU ───────────────

    public function testESKISAHIPBASARISIZLIKYAZAMAZ(): void
    {
        ['id' => $id, 'eski' => $eski, 'yeni' => $yeni] = $this->kirasiDevralinmisIs();

        $this->expectException(KiraKaybedildi::class);

        try {
            $this->kuyruk->basarisiz($id, 'ağ hatası', $this->simdi, HataSinifi::GECICI, null, $eski);
        } finally {
            $satir = $this->satir($id);
            self::assertSame(JobQueue::CALISIYOR, $satir['durum']);
            self::assertSame($yeni, $satir['kilit_token']);
            self::assertNull($satir['hata'], 'Eski sahibin hatası yazılmamalı.');
        }
    }

    public function testESKISAHIPKALICIHATAYLAOLDUREMEZ(): void
    {
        // ÖLÜM YOLU EN TEHLİKELİSİYDİ: `basarisiz()` kalıcı hatada doğrudan
        // `oldur()`a giriyordu ve `oldur()` hiçbir sahiplik denetimi yapmıyordu.
        // Yani eski sahip, ikinci işleyicinin ÇALIŞAN işini ölü rafına atabiliyordu.
        ['id' => $id, 'eski' => $eski, 'yeni' => $yeni] = $this->kirasiDevralinmisIs();

        $this->expectException(KiraKaybedildi::class);

        try {
            $this->kuyruk->basarisiz($id, 'ürün silinmiş', $this->simdi, HataSinifi::KALICI, null, $eski);
        } finally {
            $satir = $this->satir($id);
            self::assertSame(JobQueue::CALISIYOR, $satir['durum'], 'İş ölü rafına DÜŞMEMELİ.');
            self::assertSame($yeni, $satir['kilit_token']);
        }
    }

    public function testGERCEKSAHIPBASARISIZLIKYAZAR(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 1], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $this->kuyruk->basarisiz(
            (int) $is['id'],
            'ağ hatası',
            $this->simdi,
            HataSinifi::GECICI,
            null,
            (string) $is['kilit_token'],
        );

        $satir = $this->satir((int) $is['id']);
        self::assertSame(JobQueue::BEKLIYOR, $satir['durum'], 'Deneme hakkı varken geri bırakılmalı.');
        self::assertSame('ağ hatası', $satir['hata']);
    }

    public function testDENEMEHAKKIBITINCEOLURAFINADUSER(): void
    {
        $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 1], $this->simdi, 0, 1);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $this->kuyruk->basarisiz(
            (int) $is['id'],
            'ağ hatası',
            $this->simdi,
            HataSinifi::GECICI,
            null,
            (string) $is['kilit_token'],
        );

        self::assertSame(JobQueue::OLU, $this->satir((int) $is['id'])['durum']);
    }

    // ─────────────── YÖNETİCİ YOLU ───────────────

    public function testYONETICIOLDURMETOKENISTEMEZ(): void
    {
        // Yönetici "bu iş bir daha denenmesin" diyebilmelidir; onun elinde
        // kira token'ı YOKTUR. Bu yol AYRI ve ADI AÇIK olmalıdır ki iş akışı
        // yanlışlıkla denetimsiz yolu kullanmasın.
        $this->kuyruk->ekle('ceviri', 'a', ['urun_id' => 1], $this->simdi);
        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($is);

        $this->kuyruk->yoneticiOldur((int) $is['id'], 'yönetici durdurdu', $this->simdi);

        $satir = $this->satir((int) $is['id']);
        self::assertSame(JobQueue::OLU, $satir['durum']);
        self::assertNull($satir['kilit_token']);
    }

    public function testKIRAKAYBIISTISNASIISKIMLIGINITASIR(): void
    {
        // İşleyicinin hatayı loglayabilmesi için hangi işi kaybettiğini
        // bilmesi gerekir; mesaj alt-string'i ayrıştırmak zorunda kalmamalı.
        ['id' => $id, 'eski' => $eski] = $this->kirasiDevralinmisIs();

        try {
            $this->kuyruk->basarili($id, $this->simdi, $eski);
            self::fail('KiraKaybedildi bekleniyordu.');
        } catch (KiraKaybedildi $hata) {
            self::assertSame($id, $hata->isId);
        }
    }
}
