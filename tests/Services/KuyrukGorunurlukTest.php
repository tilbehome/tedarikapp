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

/**
 * D9 — PANELİN GÖRDÜĞÜ İŞ, İŞÇİNİN DE GÖRDÜĞÜ İŞTİR (saha bulgusu, 25 Ağu 2026).
 *
 * BULGU (canlı, rc5, 20:06): Ayarlar > Kuyruk durumu "Bekleyen 5 · Çalışan 0 ·
 * Ölü 0 · en eski bekleyen 8 dk · kuyruk sağlıklı" derken, aynı dakikalarda
 * her 5 dakikada bir koşan cron günlüğü "KUYRUK TURU: 0 iş · kuyruk boş"
 * yazıyordu. Ölü 0 ve hata 0 olması teşhisi daraltır: işler denenip
 * DÜŞMÜYOR, hiç ALINMIYOR.
 *
 * KÖK NEDEN: iki yüzey aynı tabloya AYRI koşullarla bakıyordu —
 *   · sayaç (`saglik`)   → `durum = 'bekliyor'`
 *   · işçi  (`sahiplen`) → `durum = 'bekliyor' AND calisacak_at <= now`
 * Tek fark zaman koşuludur. `calisacak_at` ileri tarihliyse ya da işçinin saati
 * sayacınkinden geriyse (paylaşımlı hostingde cron farklı ortam değişkenleriyle
 * koşar) panel "5 bekliyor" der, işçi hiçbir şey görmez ve çelişkiyi kimse fark
 * etmez. D5'te popup ile sayfa içi panel arasında yaşanan ayrışmanın kuyruk hâli.
 *
 * BU SÜİTİN SÖZLEŞMESİ: "panel sayacının bekleyen dediği her iş, işçinin bir
 * sonraki turda claim ettiği kümededir" — ya da fark VARSA panel bunu sayı
 * olarak söyler ve işçi sebebini yazar. Sessiz ayrışma yasaktır.
 */
final class KuyrukGorunurlukTest extends TestCase
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
        $this->simdi = new DateTimeImmutable('2026-08-25 20:06:00');
    }

    /** Bir turda claim edilen iş kimlikleri. */
    private function birTurdaAlinanlar(int $adet = 20): array
    {
        $alinan = [];
        $kosucu = new JobRunner($this->kuyruk, new NullLogger(), sureSiniri: 50, isSiniri: $adet);
        $kosucu->kaydet('ceviri', static function (array $yuk, array $is) use (&$alinan): void {
            $alinan[] = (int) $is['id'];
        });
        $kosucu->kaydet('skor', static function (array $yuk, array $is) use (&$alinan): void {
            $alinan[] = (int) $is['id'];
        });
        $kosucu->kos($this->simdi, 'test:1');

        sort($alinan);

        return $alinan;
    }

    /** @return list<int> panelin "bekleyen" dediği işlerin kimlikleri */
    private function panelinBekleyenleri(): array
    {
        $satirlar = $this->pdo->query("SELECT id FROM jobs WHERE durum = 'bekliyor'")->fetchAll();
        $idler = array_map(static fn (array $r): int => (int) $r['id'], $satirlar ?: []);
        sort($idler);

        return $idler;
    }

    // ── ASIL SÖZLEŞME ────────────────────────────────────────────────────────

    public function testPANELINBEKLEYENDEDIGIHERISBIRSONRAKITURDAALINIR(): void
    {
        // Toplu çeviri düğmesinin yazdığı beş iş (saha vakasının birebir kopyası).
        for ($i = 1; $i <= 5; $i++) {
            $this->kuyruk->ekle('ceviri', 'urun:' . $i, ['urun_id' => $i], $this->simdi->modify('-9 minutes'));
        }

        $bekleyenler = $this->panelinBekleyenleri();
        self::assertCount(5, $bekleyenler);
        self::assertSame(5, $this->kuyruk->saglik($this->simdi)['bekleyen']);
        // Panelin gördüğü = işçinin alabileceği.
        self::assertSame(5, $this->kuyruk->saglik($this->simdi)['alinabilir']);

        // KRİTİK: kümeler BİREBİR aynı olmalı — "sayılar tutuyor" yetmez.
        self::assertSame($bekleyenler, $this->birTurdaAlinanlar());
    }

    public function testSAYACLARAYRISIRSA_PANELBUNUSAYIOLARAKSOYLER(): void
    {
        // Saha senaryosu: işler ileri tarihli yazılmış (ya da işçinin saati geri).
        $this->kuyruk->ekle('ceviri', 'urun:1', ['urun_id' => 1], $this->simdi->modify('+3 hours'));
        $this->kuyruk->ekle('ceviri', 'urun:2', ['urun_id' => 2], $this->simdi->modify('+3 hours'));

        $saglik = $this->kuyruk->saglik($this->simdi);

        // Eski davranış: yalnız "bekleyen 2" görünürdü ve panel "sağlıklı" derdi.
        self::assertSame(2, $saglik['bekleyen']);
        self::assertSame(0, $saglik['alinabilir'], 'İşçi bu işleri ALAMAZ; panel bunu gizlememeli.');
        self::assertSame(2, $saglik['ileri_tarihli']);
        self::assertSame(180, $saglik['en_yakin_calisacak_dakika']);
        // İşçi gerçekten de alamaz — sayaç ile işçi aynı gerçeği söylüyor.
        self::assertSame([], $this->birTurdaAlinanlar());
    }

    public function testISCIKUYRUKBOSDEMEZ_SEBEBINIYAZAR(): void
    {
        $this->kuyruk->ekle('ceviri', 'urun:1', ['urun_id' => 1], $this->simdi->modify('+45 minutes'));

        $kosucu = new JobRunner($this->kuyruk, new NullLogger(), sureSiniri: 50, isSiniri: 5);
        $kosucu->kaydet('ceviri', static function (): void {
        });
        $sonuc = $kosucu->kos($this->simdi, 'test:1');

        self::assertSame(0, $sonuc['islenen']);
        // Sahada günlüğe düşen cümle buydu ve YANILTICIYDI.
        self::assertStringNotContainsString('kuyruk boş', $sonuc['durma_nedeni']);
        self::assertStringContainsString('1 iş bekliyor', $sonuc['durma_nedeni']);
        self::assertStringContainsString('45 dk sonra', $sonuc['durma_nedeni']);
        // Saat kaymasını teşhis edebilmek için işçinin saati de yazılır.
        self::assertStringContainsString('2026-08-25 20:06:00', $sonuc['durma_nedeni']);
    }

    public function testGERCEKTENBOSKUYRUK_HALAKUYRUKBOSDER(): void
    {
        $kosucu = new JobRunner($this->kuyruk, new NullLogger(), sureSiniri: 50, isSiniri: 5);
        $sonuc = $kosucu->kos($this->simdi, 'test:1');

        self::assertSame('kuyruk boş', $sonuc['durma_nedeni']);
    }

    public function testZAMANIGELENISSONRAKITURDAALINIR(): void
    {
        $this->kuyruk->ekle('ceviri', 'urun:1', ['urun_id' => 1], $this->simdi->modify('+10 minutes'));

        self::assertSame([], $this->birTurdaAlinanlar(), 'Zamanı gelmeden alınmamalı.');

        // On bir dakika sonra aynı iş alınabilir olur — gecikme kalıcı değildir.
        $this->simdi = $this->simdi->modify('+11 minutes');
        self::assertSame($this->panelinBekleyenleri(), $this->birTurdaAlinanlar());
    }

    public function testKIRASIDOLMUSISDEIKIYUZEYDEDEALINABILIRSAYILIR(): void
    {
        $id = $this->kuyruk->ekle('ceviri', 'urun:1', ['urun_id' => 1], $this->simdi);
        $this->kuyruk->sahiplen('olen-isci', $this->simdi);

        // Kira dolar: iş yeniden alınabilir hâle gelir.
        $sonrasi = $this->simdi->modify('+' . (JobQueue::KILIT_OMRU_SANIYE + 60) . ' seconds');
        self::assertSame(1, $this->kuyruk->alinabilirSayisi($sonrasi));

        $this->simdi = $sonrasi;
        self::assertSame([$id], $this->birTurdaAlinanlar());
    }
}
