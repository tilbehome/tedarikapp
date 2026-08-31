<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Tur\TurDurumMakinesi;
use App\Services\Tur\TurGecisiReddedildi;
use PHPUnit\Framework\TestCase;

/**
 * V3-C BLOK B — TEKLİF TURU DURUM MAKİNESİ.
 *
 * Kaynak: `docs/v3/hazirlik/v3-c/teklif-turu-durum-makinesi.md` (#15). Bu test
 * o belgenin geçiş tablosunu KODA BAĞLAR: belge ile kod ayrışırsa kırmızı olur.
 *
 * NEDEN SUNUCUDA ZORLANIR (K37 deseni): geçişin bir kısmını FİRMA tetikler ve
 * firma tarafı dış dünyadadır — arayüzde gizlenen bir düğme, gönderilemeyen bir
 * istek anlamına gelmez. Kural sunucuda yoksa hiç yoktur.
 *
 * TUR NUMARASI DURUM ADINA GÖMÜLMEZ (#15 §2): `tur_no=2, state=SENT` arayüzde
 * "R2 gönderildi" olur. Aksi hâlde her yeni tur için yeni enum değeri gerekir
 * ve durum makinesi tur sayısı kadar çoğalır.
 */
final class TurDurumMakinesiTest extends TestCase
{
    private TurDurumMakinesi $makine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makine = new TurDurumMakinesi();
    }

    public function testONDURUMTANIMLI(): void
    {
        // Belge on durum sayıyor; eksik ya da fazla olması, belgeyle kodun
        // ayrıştığı anlamına gelir.
        self::assertCount(10, TurDurumMakinesi::DURUMLAR);
    }

    public function testTASLAKTANGONDERIME(): void
    {
        self::assertTrue($this->makine->gecebilirMi('DRAFT', 'SENT'));
    }

    public function testGONDERMEDENGORUNTULENEMEZ(): void
    {
        // Firma turu görmeden "görüntüledi" olamaz — atlama yok.
        self::assertFalse($this->makine->gecebilirMi('DRAFT', 'VIEWED'));
    }

    public function testFIYATLAMAYAUCYOLDANGIRILIR(): void
    {
        // #15 madde 4: firma ilk geçerli alan değişikliğini kaydettiği an.
        foreach (['SENT', 'VIEWED'] as $onceki) {
            self::assertTrue($this->makine->gecebilirMi($onceki, 'PRICING'), $onceki . ' → PRICING');
        }
    }

    public function testKISMIGONDERIMAYNIDURUMDAKALIR(): void
    {
        // #15 madde 5: kısmi teslim durumu DEĞİŞTİRMEZ. Kendine geçiş geçerli
        // olmalı, yoksa her kısmi gönderim reddedilirdi.
        self::assertTrue($this->makine->gecebilirMi('PRICING', 'PRICING'));
    }

    public function testNIHAIYANITUCDURUMDANGELIR(): void
    {
        foreach (['SENT', 'VIEWED', 'PRICING'] as $onceki) {
            self::assertTrue($this->makine->gecebilirMi($onceki, 'RESPONDED'), $onceki . ' → RESPONDED');
        }
    }

    public function testONAYYALNIZYANITSONRASI(): void
    {
        self::assertTrue($this->makine->gecebilirMi('RESPONDED', 'APPROVED'));
        self::assertFalse($this->makine->gecebilirMi('PRICING', 'APPROVED'), 'Yanıtlanmamış tur onaylanamaz.');
        self::assertFalse($this->makine->gecebilirMi('DRAFT', 'APPROVED'));
    }

    public function testREVIZYONYALNIZYANITSONRASI(): void
    {
        self::assertTrue($this->makine->gecebilirMi('RESPONDED', 'REVISION_REQUESTED'));
        self::assertFalse($this->makine->gecebilirMi('SENT', 'REVISION_REQUESTED'));
    }

    public function testTASLAKTANVAZGECILEBILIR(): void
    {
        self::assertTrue($this->makine->gecebilirMi('DRAFT', 'ABANDONED'));
    }

    public function testAKTIFTURLARDANVAZGECILEBILIR(): void
    {
        foreach (['SENT', 'VIEWED', 'PRICING', 'RESPONDED'] as $onceki) {
            self::assertTrue($this->makine->gecebilirMi($onceki, 'ABANDONED'), $onceki . ' → ABANDONED');
        }
    }

    public function testAKTIFTURLARSURESIDOLABILIR(): void
    {
        foreach (['SENT', 'VIEWED', 'PRICING', 'RESPONDED'] as $onceki) {
            self::assertTrue($this->makine->gecebilirMi($onceki, 'EXPIRED'), $onceki . ' → EXPIRED');
        }
        self::assertFalse($this->makine->gecebilirMi('DRAFT', 'EXPIRED'), 'Gönderilmemiş turun geçerliliği yok.');
    }

    public function testKAPALIDURUMLARDANCIKISYOK(): void
    {
        // APPROVED / ABANDONED / REVOKED nihaidir. Nihai durumdan çıkış,
        // kapanmış bir ticari kaydın sessizce yeniden açılması demektir.
        foreach (['APPROVED', 'ABANDONED', 'REVOKED'] as $kapali) {
            foreach (TurDurumMakinesi::DURUMLAR as $hedef) {
                self::assertFalse(
                    $this->makine->gecebilirMi($kapali, $hedef),
                    $kapali . ' nihaidir; ' . $hedef . ' hedefine geçemez.',
                );
            }
        }
    }

    public function testSURESIDOLANTURREVIZYONAACILABILIR(): void
    {
        // #15: "Tur salt okunur; revizyon açılabilir." EXPIRED tam kapalı
        // değildir — ticari gerçek: süresi geçmiş teklif yenilenebilir.
        self::assertTrue($this->makine->gecebilirMi('EXPIRED', 'REVISION_REQUESTED'));
        self::assertFalse($this->makine->gecebilirMi('EXPIRED', 'APPROVED'), 'Süresi dolmuş teklif onaylanamaz.');
    }

    public function testERISIMIPTALIHERAKTIFDURUMDANOLUR(): void
    {
        // #15 madde 15: güvenlik olayı her an gelebilir.
        foreach (['SENT', 'VIEWED', 'PRICING', 'RESPONDED'] as $onceki) {
            self::assertTrue($this->makine->gecebilirMi($onceki, 'REVOKED'), $onceki . ' → REVOKED');
        }
    }

    public function testTANIMSIZDURUMREDDEDILIR(): void
    {
        self::assertFalse($this->makine->gecebilirMi('DRAFT', 'UYDURMA'));
        self::assertFalse($this->makine->gecebilirMi('UYDURMA', 'SENT'));
    }

    public function testDOGRULAGECERSIZGECISTEISTISNAATAR(): void
    {
        // Sessiz `false` yeterli değil: çağıran kontrol etmeyi unutabilir ve
        // geçersiz geçiş sessizce yazılır. İstisna unutulamaz.
        $this->expectException(TurGecisiReddedildi::class);
        $this->makine->dogrula('DRAFT', 'APPROVED');
    }

    public function testISTISNAIKIDURUMUDATASIR(): void
    {
        try {
            $this->makine->dogrula('APPROVED', 'DRAFT');
            self::fail('TurGecisiReddedildi bekleniyordu.');
        } catch (TurGecisiReddedildi $hata) {
            self::assertSame('APPROVED', $hata->onceki);
            self::assertSame('DRAFT', $hata->hedef);
        }
    }

    public function testGECERLIGECISDOGRULAMADANGECER(): void
    {
        $this->makine->dogrula('DRAFT', 'SENT');

        self::assertTrue(true, 'Geçerli geçiş istisna atmamalı.');
    }

    public function testFIRMANINYAPABILECEGIGECISLERSINIRLI(): void
    {
        // FİRMA TARAFI DIŞ DÜNYADIR: portal isteği, panelin izin verdiğinden
        // fazlasını deneyebilir. Onay ve vazgeçme TİCARİ kararlardır ve yalnız
        // Ürün Sahibi'nindir.
        self::assertTrue($this->makine->firmaYapabilirMi('SENT', 'VIEWED'));
        self::assertTrue($this->makine->firmaYapabilirMi('PRICING', 'RESPONDED'));

        self::assertFalse($this->makine->firmaYapabilirMi('RESPONDED', 'APPROVED'), 'Firma kendi teklifini onaylayamaz.');
        self::assertFalse($this->makine->firmaYapabilirMi('SENT', 'ABANDONED'), 'Firma turu kapatamaz.');
        self::assertFalse($this->makine->firmaYapabilirMi('RESPONDED', 'REVISION_REQUESTED'));
    }

    public function testSAHIPTUMGECERLIGECISLERIYAPABILIR(): void
    {
        // Sahip firmanın yapabildiği her şeyi de yapabilir mi? HAYIR —
        // "firma görüntüledi" bir GÖZLEMDİR, sahibin ilan edeceği bir şey değil.
        self::assertTrue($this->makine->sahipYapabilirMi('RESPONDED', 'APPROVED'));
        self::assertTrue($this->makine->sahipYapabilirMi('DRAFT', 'ABANDONED'));
        self::assertFalse($this->makine->sahipYapabilirMi('SENT', 'VIEWED'), 'Görüntülemeyi sahip ilan edemez.');
    }

    public function testDURUMKARSILIKLARISOZLUKTEN(): void
    {
        // #15 §2: `status.*` karşılıkları TEK KAYNAKTAN gelir; ekranlar kendi
        // metnini uydurursa aynı durum iki yerde iki türlü yazılır.
        self::assertSame('status.preparing', $this->makine->ciktiTerimi('DRAFT'));
        self::assertSame('status.approved', $this->makine->ciktiTerimi('APPROVED'));
        self::assertSame('status.expired', $this->makine->ciktiTerimi('EXPIRED'));
        self::assertSame('status.cancelled', $this->makine->ciktiTerimi('REVOKED'));
    }
}
