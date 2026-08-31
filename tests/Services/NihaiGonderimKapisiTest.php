<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Tur\NihaiGonderimKapisi;
use PHPUnit\Framework\TestCase;

/**
 * V3-C BLOK B — NİHAİ GÖNDERİM KAPISI (#15 §4).
 *
 * KORUNAN FELAKET: firma "gönder"e basar, tur `RESPONDED` olur ve Ürün Sahibi
 * teklifi değerlendirmeye alır — ama satırların yarısında fiyat yok, birinde
 * MOQ boş, kademeler çakışıyor. Eksiklik ancak sipariş aşamasında görülür ve o
 * noktada firmaya geri dönmek bir tur daha demektir.
 *
 * Kapı SUNUCUDADIR (K37): portal isteği panelden gelmez ve arayüzde
 * gizlenmiş bir düğme, gönderilemeyen bir istek anlamına gelmez.
 *
 * SEKİZ KOŞULUN HEPSİ AYRI RAPORLANIR: "geçersiz" demek firmayı boş yere
 * dolaştırır. Firma hangi satırda neyin eksik olduğunu görmeli.
 */
final class NihaiGonderimKapisiTest extends TestCase
{
    private NihaiGonderimKapisi $kapi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kapi = new NihaiGonderimKapisi();
    }

    /** @return array<string, mixed> */
    private function bulunanSatir(array $ustyaz = []): array
    {
        return array_merge([
            'rfq_satir_id' => 'a1',
            'yanit_durumu' => 'found',
            'fiyat' => '12.50',
            'para_birimi' => 'CNY',
            'ddp_kdv_dahil' => true,
            'moq' => 100,
            'termin_baslangic' => '2026-09-15',
            'termin_gun' => 20,
            'birim' => 'adet',
            'kademeler' => [],
        ], $ustyaz);
    }

    /** @param list<array<string, mixed>> $satirlar */
    private function sonuc(array $satirlar, array $ustyaz = []): array
    {
        return $this->kapi->degerlendir(array_merge([
            'durum' => 'PRICING',
            'erisim_iptal' => false,
            'rfq_satir_idler' => array_column($satirlar, 'rfq_satir_id'),
            'satirlar' => $satirlar,
            'gecerlilik_onayi' => true,
            'ddp_kdv_onayi' => true,
            'istemci_surumu' => 3,
            'sunucu_surumu' => 3,
        ], $ustyaz));
    }

    public function testEKSIKSIZTEKLIFGECER(): void
    {
        $sonuc = $this->sonuc([$this->bulunanSatir()]);

        self::assertTrue($sonuc['gecerli'], json_encode($sonuc, JSON_UNESCAPED_UNICODE));
        self::assertSame([], $sonuc['eksikler']);
    }

    public function testYANLISDURUMDANGONDERILEMEZ(): void
    {
        // Koşul 1: tur SENT/VIEWED/PRICING olmalı.
        $sonuc = $this->sonuc([$this->bulunanSatir()], ['durum' => 'APPROVED']);

        self::assertFalse($sonuc['gecerli']);
        self::assertContains('durum', array_column($sonuc['eksikler'], 'alan'));
    }

    public function testERISIMIPTALEDILMISSEGONDERILEMEZ(): void
    {
        $sonuc = $this->sonuc([$this->bulunanSatir()], ['erisim_iptal' => true]);

        self::assertFalse($sonuc['gecerli']);
        self::assertContains('erisim', array_column($sonuc['eksikler'], 'alan'));
    }

    public function testYANITSIZSATIRENGELLER(): void
    {
        // Koşul 2: HER RFQ satırında nihai bir yanıt olmalı. "Yanıtlanmayan"
        // ile "bulunamadı" AYRI şeylerdir (K67: bilinmeyen ≠ sıfır) —
        // yanıtlanmayan satır teklifi eksik bırakır.
        $sonuc = $this->sonuc(
            [$this->bulunanSatir()],
            ['rfq_satir_idler' => ['a1', 'a2']],
        );

        self::assertFalse($sonuc['gecerli']);
        $eksik = $sonuc['eksikler'][0];
        self::assertSame('a2', $eksik['satir']);
        self::assertSame('yanit_durumu', $eksik['alan']);
    }

    public function testBULUNANSATIRDAFIYATZORUNLU(): void
    {
        $sonuc = $this->sonuc([$this->bulunanSatir(['fiyat' => null])]);

        self::assertFalse($sonuc['gecerli']);
        self::assertContains('fiyat', array_column($sonuc['eksikler'], 'alan'));
    }

    public function testBULUNANSATIRDASIFIRFIYATGECERSIZ(): void
    {
        // Sıfır fiyat "bedava" değil, DOLDURULMAMIŞ demektir. Bunu geçirmek,
        // toplamı sessizce yanlış hesaplatır.
        $sonuc = $this->sonuc([$this->bulunanSatir(['fiyat' => '0'])]);

        self::assertFalse($sonuc['gecerli']);
    }

    public function testBULUNANSATIRDAKALANALANLARZORUNLU(): void
    {
        foreach (['para_birimi', 'moq', 'termin_baslangic', 'termin_gun', 'birim'] as $alan) {
            $sonuc = $this->sonuc([$this->bulunanSatir([$alan => null])]);

            self::assertFalse($sonuc['gecerli'], $alan . ' boşken geçmemeli.');
            self::assertContains($alan, array_column($sonuc['eksikler'], 'alan'));
        }
    }

    public function testDDPKDVONAYISATIRDAZORUNLU(): void
    {
        $sonuc = $this->sonuc([$this->bulunanSatir(['ddp_kdv_dahil' => false])]);

        self::assertFalse($sonuc['gecerli']);
        self::assertContains('ddp_kdv_dahil', array_column($sonuc['eksikler'], 'alan'));
    }

    public function testBULUNAMADISATIRINDAACIKLAMAZORUNLU(): void
    {
        // Koşul 4. Açıklamasız "bulunamadı", Ürün Sahibi için bilgi taşımaz:
        // stok mu yok, firma mı bakmadı, ürün mü yanlış anlaşıldı?
        $sonuc = $this->sonuc([[
            'rfq_satir_id' => 'a1',
            'yanit_durumu' => 'not_found',
            'aciklama' => '',
        ]]);

        self::assertFalse($sonuc['gecerli']);
        self::assertContains('aciklama', array_column($sonuc['eksikler'], 'alan'));
    }

    public function testBULUNAMADISATIRIACIKLAMAYLAGECER(): void
    {
        $sonuc = $this->sonuc([[
            'rfq_satir_id' => 'a1',
            'yanit_durumu' => 'not_found',
            'aciklama' => 'Üretici bu modeli bıraktı.',
        ]]);

        self::assertTrue($sonuc['gecerli'], json_encode($sonuc, JSON_UNESCAPED_UNICODE));
    }

    public function testALTERNATIFSATIRIFIYATVEBAGLANTIISTER(): void
    {
        // Koşul 5: alternatif önerisi, önerildiği için değil FİYATLANDIĞI için
        // işe yarar. Fiyatsız alternatif bir sonraki turu doğurur.
        $sonuc = $this->sonuc([[
            'rfq_satir_id' => 'a1',
            'yanit_durumu' => 'alternative_available',
            'alternatif_baglanti' => '',
            'aciklama' => '',
            'fiyat' => '9.90',
            'moq' => 50,
            'termin_gun' => 15,
        ]]);

        self::assertFalse($sonuc['gecerli']);
        self::assertContains('alternatif_baglanti', array_column($sonuc['eksikler'], 'alan'));
    }

    public function testALTERNATIFSATIRITAMAMSAGECER(): void
    {
        $sonuc = $this->sonuc([[
            'rfq_satir_id' => 'a1',
            'yanit_durumu' => 'alternative_available',
            'alternatif_baglanti' => 'https://detail.1688.com/x.html',
            'fiyat' => '9.90',
            'moq' => 50,
            'termin_gun' => 15,
        ]]);

        self::assertTrue($sonuc['gecerli'], json_encode($sonuc, JSON_UNESCAPED_UNICODE));
    }

    public function testKADEMELERSIRALIVEPOZITIFOLMALI(): void
    {
        // Koşul 6. Çakışan kademe, aynı miktar için İKİ fiyat demektir ve
        // hangisinin geçerli olduğu bilinemez (K92 ruhu: belirsizlik hesaba
        // girmez).
        $sonuc = $this->sonuc([$this->bulunanSatir(['kademeler' => [
            ['adet' => 100, 'fiyat' => '12.00'],
            ['adet' => 50, 'fiyat' => '13.00'],
        ]])]);

        self::assertFalse($sonuc['gecerli']);
        self::assertContains('kademeler', array_column($sonuc['eksikler'], 'alan'));
    }

    public function testAYNIADETIKIKEZKADEMEOLAMAZ(): void
    {
        $sonuc = $this->sonuc([$this->bulunanSatir(['kademeler' => [
            ['adet' => 100, 'fiyat' => '12.00'],
            ['adet' => 100, 'fiyat' => '11.00'],
        ]])]);

        self::assertFalse($sonuc['gecerli']);
    }

    public function testARTANSIRALIKADEMEGECER(): void
    {
        $sonuc = $this->sonuc([$this->bulunanSatir(['kademeler' => [
            ['adet' => 100, 'fiyat' => '12.00'],
            ['adet' => 500, 'fiyat' => '11.00'],
        ]])]);

        self::assertTrue($sonuc['gecerli'], json_encode($sonuc, JSON_UNESCAPED_UNICODE));
    }

    public function testGECERLILIKVEKDVONAYLARIZORUNLU(): void
    {
        // Koşul 7.
        self::assertFalse($this->sonuc([$this->bulunanSatir()], ['gecerlilik_onayi' => false])['gecerli']);
        self::assertFalse($this->sonuc([$this->bulunanSatir()], ['ddp_kdv_onayi' => false])['gecerli']);
    }

    public function testSURUMCAKISMASIENGELLER(): void
    {
        // Koşul 8: istemci eski sürümü gönderiyorsa, gördüğü RFQ artık geçerli
        // değildir. Sessizce kabul etmek, firmanın FARKLI bir şeye fiyat
        // vermesi demektir.
        $sonuc = $this->sonuc([$this->bulunanSatir()], ['istemci_surumu' => 2, 'sunucu_surumu' => 3]);

        self::assertFalse($sonuc['gecerli']);
        self::assertContains('round_version', array_column($sonuc['eksikler'], 'alan'));
        self::assertTrue($sonuc['cakisma'], 'Sürüm çakışması ayrı işaretlenmeli — çakışma ekranı açılır.');
    }

    public function testEKSIKLERSATIRBAZINDARAPORLANIR(): void
    {
        // "Geçersiz" demek firmayı boş yere dolaştırır: hangi satırda ne eksik?
        $sonuc = $this->sonuc([
            $this->bulunanSatir(['rfq_satir_id' => 'a1', 'fiyat' => null]),
            $this->bulunanSatir(['rfq_satir_id' => 'a2', 'moq' => null]),
        ]);

        self::assertCount(2, $sonuc['eksikler']);
        self::assertSame(['a1', 'a2'], array_column($sonuc['eksikler'], 'satir'));
    }
}
