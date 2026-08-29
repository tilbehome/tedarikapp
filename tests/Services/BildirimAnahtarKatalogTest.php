<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Bildirim\GrupAnahtariCozucu;
use PHPUnit\Framework\TestCase;

/**
 * V3-B A2 BEKÇİSİ — KATALOGDAKİ HER BİRLEŞTİRME ANAHTARININ ÇÖZÜCÜSÜ VAR.
 *
 * Nöbet Raporu 5'in 1 numaralı riski buydu: katalogdaki `grup_anahtari` alanı
 * bir METİNDİR (`"kullanici_id+platform"`). Çözücü tanımadığı bir atomla
 * karşılaşırsa ya sessizce boş anahtar üretir ya da her çağrı noktası kendi
 * yorumunu yazar. İkisi de aynı sonuca çıkar: birleştirme ÇALIŞMAZ ve bildirim
 * merkezi aynı satırı on kez gösterir. Böyle bir bozukluk çalışma zamanında
 * fark edilmez — "çok bildirim geliyor" diye şikâyet edilir, sebebi aranmaz.
 *
 * Bekçi TestSuiteKapsamiTest kalıbındadır: kayıt dışı kalan şey, çalışmayan
 * şeydir. Katalogda olup `GrupAnahtariCozucu::ATOMLAR` listesinde olmayan bir
 * atom bu testi KIRMIZI yapar.
 */
final class BildirimAnahtarKatalogTest extends TestCase
{
    private const KATALOG = '/config/bildirim-olay-katalogu.json';

    /** @return list<array<string, mixed>> */
    private function olaylar(): array
    {
        $ham = (string) file_get_contents(dirname(__DIR__, 2) . self::KATALOG);
        /** @var array{olaylar: list<array<string, mixed>>} $veri */
        $veri = json_decode($ham, true, 512, JSON_THROW_ON_ERROR);

        return $veri['olaylar'];
    }

    /** @return list<string> birleştirmesi açık olayların anahtar ifadeleri */
    private function ifadeler(): array
    {
        $ifadeler = [];
        foreach ($this->olaylar() as $olay) {
            /** @var array{izinli?: bool, grup_anahtari?: string} $birlestirme */
            $birlestirme = $olay['birlestirme'] ?? [];
            if (($birlestirme['izinli'] ?? false) === true) {
                $ifadeler[] = (string) ($birlestirme['grup_anahtari'] ?? '');
            }
        }

        return array_values(array_unique($ifadeler));
    }

    public function testKATALOGDAKIHERIFADECOZULEBILIR(): void
    {
        $cozucu = new GrupAnahtariCozucu();
        $cozulemeyen = [];

        foreach ($this->ifadeler() as $ifade) {
            if (!$cozucu->cozulebilirMi($ifade)) {
                $cozulemeyen[] = $ifade;
            }
        }

        self::assertSame(
            [],
            $cozulemeyen,
            "Bu birleştirme anahtarlarının çözücüsü YOK — birleştirme sessizce çalışmaz:\n  "
            . implode("\n  ", $cozulemeyen)
            . "\nGrupAnahtariCozucu::ATOMLAR listesine eksik atomları ekleyin.",
        );
    }

    public function testCOZUCUDEHAYALETATOMYOK(): void
    {
        // Ters yön: listede olup katalogda hiç kullanılmayan atom, ya silinmiş
        // bir olaydan kalmıştır ya da yanlış yazılmıştır. İkisi de temizlenmeli.
        $kullanilan = [];
        $cozucu = new GrupAnahtariCozucu();
        foreach ($this->ifadeler() as $ifade) {
            foreach ($cozucu->atomlari($ifade) as $atom) {
                $kullanilan[$atom] = true;
            }
        }

        $fazla = array_values(array_diff(GrupAnahtariCozucu::ATOMLAR, array_keys($kullanilan)));

        self::assertSame(
            [],
            $fazla,
            'Katalogda hiç kullanılmayan atom: ' . implode(', ', $fazla),
        );
    }

    public function testCOZUMSIRALIVEBELIRLENIMCIDIR(): void
    {
        $cozucu = new GrupAnahtariCozucu();
        $baglam = ['kullanici_id' => 7, 'platform' => '1688'];

        self::assertSame('7|1688', $cozucu->coz('kullanici_id+platform', $baglam));
        // Aynı bağlam her zaman aynı anahtarı vermeli; sıra ifadeden gelir.
        self::assertSame('1688|7', $cozucu->coz('platform+kullanici_id', $baglam));
    }

    public function testEKSIKDEGERPATLAMAZISARETLENIR(): void
    {
        // Bilinmeyen platform bildirimi KAYBETTİRMEZ — anahtarda "-" olur.
        $cozucu = new GrupAnahtariCozucu();

        self::assertSame('7|-', $cozucu->coz('kullanici_id+platform', ['kullanici_id' => 7]));
    }

    public function testBILINMEYENATOMPATLAR(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new GrupAnahtariCozucu())->coz('kullanici_id+uydurma_alan', []);
    }

    public function testUZUNANAHTARKISALTILIRAMABELIRLENIMCIKALIR(): void
    {
        $cozucu = new GrupAnahtariCozucu();
        $uzun = str_repeat('a', 300);

        $bir = $cozucu->coz('ip_hash', ['ip_hash' => $uzun]);
        $iki = $cozucu->coz('ip_hash', ['ip_hash' => $uzun]);

        self::assertSame($bir, $iki, 'Özet deterministik olmalı, yoksa birleştirme çalışmaz.');
        self::assertLessThanOrEqual(190, mb_strlen($bir), 'Anahtar VARCHAR(190) sınırını aşmamalı.');
    }
}
