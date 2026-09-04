<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Yanit\YapistirAyristirici;
use PHPUnit\Framework\TestCase;

/**
 * YAPIŞTIR-AYRIŞTIR ALTIN SETİ (V3-C Aşama 2.2 · docs/v3/hazirlik/v3-c/yapistir-ayristir-altin-seti.json).
 *
 * Kabul kapısı SETİN KENDİSİNDE yazılıdır (`kabul_kapisi`):
 *   · alan doğruluğu ≥ %90 (doğru pozitif + doğru null/belirsiz kararı birlikte),
 *   · yanlış ürün eşleşmesi %0 — TEK OLAYDA dahi otomatik ret,
 *   · belirsiz ürün / para birimi OTOMATİK BAĞLANMAZ.
 *
 * Bu süit seti olduğu gibi sürer; eşik ve örnekler koddan değil dosyadan
 * okunur ki set büyüdüğünde test kendiliğinden sertleşsin. Serbest metin
 * alanları (not, alternatif açıklaması, özel termin açıklaması) ÇEVİRİ olduğu
 * için birebir değil VARLIK olarak sayılır; para/sayı alanları bcmath ile
 * karşılaştırılır (K14: float yok).
 */
final class YapistirAyristirAltinSetiTest extends TestCase
{
    private const SET = __DIR__ . '/../../docs/v3/hazirlik/v3-c/yapistir-ayristir-altin-seti.json';

    /** @var array<string, mixed> */
    private array $set;

    protected function setUp(): void
    {
        $ham = file_get_contents(self::SET);
        self::assertNotFalse($ham, 'Altın set dosyası okunamadı.');
        $this->set = json_decode($ham, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(30, $this->set['ornekler'], 'Set 30 örnektir (#28-EK).');
    }

    public function testALTINSETKABULKAPISINDANGECER(): void
    {
        $ayristirici = new YapistirAyristirici();
        $toplam = 0;
        $dogru = 0;
        $yanlisUrun = 0;
        $rapor = [];

        foreach ($this->set['ornekler'] as $ornek) {
            $sonuc = $ayristirici->ayristir((string) $ornek['ham_metin'], $this->baglam($ornek));
            $skor = $this->skorla($ornek, $sonuc);
            $toplam += $skor['toplam'];
            $dogru += $skor['dogru'];
            $yanlisUrun += $skor['yanlis_urun'];
            if ($skor['hatalar'] !== []) {
                $rapor[] = $ornek['id'] . ': ' . implode(' · ', $skor['hatalar']);
            }
        }

        $yuzde = $toplam === 0 ? 0 : (int) floor($dogru * 100 / $toplam);
        $esik = (int) $this->set['kabul_kapisi']['alan_dogrulugu_min_yuzde'];
        $ozet = sprintf("alan doğruluğu %%%d (%d/%d) · yanlış ürün %d\n%s", $yuzde, $dogru, $toplam, $yanlisUrun, implode("\n", $rapor));

        self::assertSame(0, $yanlisUrun, "Yanlış ürüne fiyat yazıldı — tek olayda otomatik ret.\n" . $ozet);
        self::assertGreaterThanOrEqual($esik, $yuzde, "Alan doğruluğu eşiğin altında.\n" . $ozet);
    }

    /** Aynı ad iki satırda ve kod yoksa: HİÇBİRİNE yazılmaz, ikisi de aday olarak belirsiz listesine düşer. */
    public function testAYNIADLIIKISATIRKODSUZGELIRSEOTOMATIKBAGLANMAZ(): void
    {
        $ornek = $this->ornek('YA-016');
        $sonuc = (new YapistirAyristirici())->ayristir((string) $ornek['ham_metin'], $this->baglam($ornek));

        self::assertSame([], $sonuc['eslesmeler']);
        self::assertCount(1, $sonuc['belirsiz']);
        self::assertEqualsCanonicalizing(['DM-001', 'DM-004'], $sonuc['belirsiz'][0]['aday_satir_idleri']);
    }

    /** Para birimi yazılmamış fiyat: sayı fiyat alanına GİRMEZ; eksik listesinde ddp + para birimi. */
    public function testPARABIRIMSIZFIYATBAGLANMAZ(): void
    {
        $ornek = $this->ornek('YA-010');
        $sonuc = (new YapistirAyristirici())->ayristir((string) $ornek['ham_metin'], $this->baglam($ornek));

        self::assertCount(1, $sonuc['eslesmeler']);
        self::assertNull($sonuc['eslesmeler'][0]['ddp']);
        self::assertContains('ddp_birim_fiyat_kdv_dahil', $sonuc['eslesmeler'][0]['eksik_zorunlu']);
        self::assertContains('para_birimi', $sonuc['eslesmeler'][0]['eksik_zorunlu']);
        self::assertCount(1, $sonuc['belirsiz']);
        self::assertSame(['DM-021'], $sonuc['belirsiz'][0]['aday_satir_idleri']);
    }

    /** Karışık sıradaki kademeler artan sıraya dizilir; çakışan kademe hata olarak işaretlenir, sessiz düzeltme yok. */
    public function testKADEMELERSIRALANIRCAKISMAHATAVERIR(): void
    {
        $a = new YapistirAyristirici();

        $sirali = $a->ayristir((string) $this->ornek('YA-023')['ham_metin'], $this->baglam($this->ornek('YA-023')));
        self::assertSame(['100', '500', '1000'], array_map(static fn (array $k): string => bcadd((string) $k['min_adet'], '0', 0), $sirali['eslesmeler'][0]['kademeler']));
        self::assertSame(0, bccomp('2.60', (string) $sirali['eslesmeler'][0]['ddp']['deger'], 2), 'Ana fiyat en düşük kademedir.');

        $cakisan = $a->ayristir((string) $this->ornek('YA-024')['ham_metin'], $this->baglam($this->ornek('YA-024')));
        self::assertSame('kademeli_fiyatlar', $cakisan['dogrulama_hatalari'][0]['alan'] ?? null);
        self::assertSame(0, bccomp('500', (string) $cakisan['eslesmeler'][0]['kademeler'][0]['max_adet'], 0), 'Kaynak sınır korunur, düzeltilmez.');
    }

    /** Bu turda olmayan kod yabancıdır: hiçbir satıra yazılmaz, belirsiz listesinde adaysız görünür. */
    public function testYABANCIKODHICBIRSATIRAYAZILMAZ(): void
    {
        $sonuc = (new YapistirAyristirici())->ayristir(
            "ZZ-999 有货 USD 3.00 MOQ 100 订单后10天\nDM-001 有货，含土耳其税DDP USD 4.20，MOQ 300，定金后20天",
            [['satir_id' => 'DM-001', 'kod' => 'DM-001', 'adlar' => ['竹纤维浴巾 70×140cm 400g']]],
        );

        self::assertCount(1, $sonuc['eslesmeler']);
        self::assertSame('DM-001', $sonuc['eslesmeler'][0]['satir_id']);
        self::assertCount(1, $sonuc['belirsiz']);
        self::assertSame([], $sonuc['belirsiz'][0]['aday_satir_idleri']);
    }

    // ── yardımcılar ────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function ornek(string $id): array
    {
        foreach ($this->set['ornekler'] as $o) {
            if ($o['id'] === $id) {
                return $o;
            }
        }
        self::fail('Örnek yok: ' . $id);
    }

    /**
     * @param  array<string, mixed> $ornek
     * @return list<array{satir_id: string, kod: string, adlar: list<string>}>
     */
    private function baglam(array $ornek): array
    {
        return array_map(static fn (array $b): array => [
            'satir_id' => (string) $b['demo_id'],
            'kod' => (string) $b['demo_id'],
            'adlar' => array_values(array_filter([$b['ad_zh'] ?? null, $b['ad_tr'] ?? null, $b['ad_en'] ?? null], static fn ($a): bool => is_string($a) && $a !== '')),
        ], $ornek['baglam_satirlari']);
    }

    /**
     * Bir örneği puanlar. Alan = tek karşılaştırılabilir karar.
     *
     * @param  array<string, mixed> $ornek
     * @param  array<string, mixed> $sonuc
     * @return array{toplam: int, dogru: int, yanlis_urun: int, hatalar: list<string>}
     */
    private function skorla(array $ornek, array $sonuc): array
    {
        $beklenen = $ornek['beklenen'];
        $hatalar = [];
        $toplam = 0;
        $dogru = 0;
        $yanlisUrun = 0;

        $beklenenIdler = array_map(static fn (array $e): string => (string) $e['demo_id'], $beklenen['eslesmeler']);
        $uretilen = [];
        foreach ($sonuc['eslesmeler'] as $e) {
            $uretilen[(string) $e['satir_id']] = $e;
            if (!in_array((string) $e['satir_id'], $beklenenIdler, true)) {
                $yanlisUrun++;
                $hatalar[] = 'YANLIŞ ÜRÜN ' . $e['satir_id'];
            }
        }
        // Belirsiz bırakılması gereken çok adaylı parça bir adaya yazılmışsa: yanlış ürün.
        foreach ($beklenen['belirsiz'] as $b) {
            if (count($b['aday_demo_idleri']) > 1) {
                foreach ($b['aday_demo_idleri'] as $aday) {
                    if (isset($uretilen[$aday])) {
                        $yanlisUrun++;
                        $hatalar[] = 'BELİRSİZ AMA BAĞLANDI ' . $aday;
                    }
                }
            }
        }

        $kontrol = function (string $ad, bool $esit) use (&$toplam, &$dogru, &$hatalar): void {
            $toplam++;
            if ($esit) {
                $dogru++;
            } else {
                $hatalar[] = $ad;
            }
        };

        foreach ($beklenen['eslesmeler'] as $b) {
            $id = (string) $b['demo_id'];
            $u = $uretilen[$id] ?? null;
            $on = $id . '.';
            $kontrol($on . 'durum', $u !== null && $u['durum'] === $b['durum']);
            $kontrol($on . 'ddp', $u !== null && $this->ddpEsit($b['ddp'], $u['ddp']));
            $kontrol($on . 'moq', $u !== null && $this->miktarEsit($b['moq'], $u['moq']));
            $kontrol($on . 'termin', $u !== null && $this->terminEsit($b['termin'], $u['termin']));
            $kontrol($on . 'kademeler', $u !== null && $this->kademelerEsit($b['kademeler'], $u['kademeler']));
            $kontrol($on . 'koli', $u !== null && $this->koliEsit($b['koli'], $u['koli']));
            $kontrol($on . 'alternatif', $u !== null && $this->alternatifEsit($b['alternatif'], $u['alternatif']));
            $kontrol($on . 'not', $u !== null && (($b['not'] ?? null) === null) === (($u['not'] ?? null) === null));
            $kontrol($on . 'eksik', $u !== null && $this->kumeEsit($b['eksik_zorunlu'], $u['eksik_zorunlu']));
        }

        // Belirsiz kararları: aday kümesi eşleşen bir kayıt var mı?
        foreach ($beklenen['belirsiz'] as $b) {
            $var = false;
            foreach ($sonuc['belirsiz'] as $u) {
                if ($this->kumeEsit($b['aday_demo_idleri'], $u['aday_satir_idleri'])) {
                    $var = true;
                }
            }
            $kontrol('belirsiz[' . implode(',', $b['aday_demo_idleri']) . ']', $var);
        }
        $kontrol('belirsiz.sayi', count($beklenen['belirsiz']) === count($sonuc['belirsiz']));

        // Doğrulama hataları: (satır, alan) kümesi birebir.
        $bekHata = array_map(static fn (array $h): string => $h['demo_id'] . ':' . $h['alan'], $beklenen['dogrulama_hatalari']);
        $urHata = array_map(static fn (array $h): string => $h['satir_id'] . ':' . $h['alan'], $sonuc['dogrulama_hatalari']);
        $kontrol('dogrulama_hatalari', $this->kumeEsit($bekHata, $urHata));

        return ['toplam' => $toplam, 'dogru' => $dogru, 'yanlis_urun' => $yanlisUrun, 'hatalar' => $hatalar];
    }

    private function sayiEsit(mixed $beklenen, mixed $uretilen): bool
    {
        if ($beklenen === null || $uretilen === null) {
            return $beklenen === null && $uretilen === null;
        }

        return bccomp(sprintf('%.6F', (float) $beklenen), bcadd((string) $uretilen, '0', 6), 6) === 0;
    }

    private function ddpEsit(mixed $b, mixed $u): bool
    {
        if ($b === null || $u === null) {
            return $b === null && $u === null;
        }

        return $this->sayiEsit($b['deger'], $u['deger'])
            && $b['para_birimi'] === $u['para_birimi']
            && $b['turkiye_kdv_dahil_beyani'] === $u['turkiye_kdv_dahil_beyani']
            && $b['nitelik'] === $u['nitelik'];
    }

    private function miktarEsit(mixed $b, mixed $u): bool
    {
        if ($b === null || $u === null) {
            return $b === null && $u === null;
        }

        return $this->sayiEsit($b['deger'], $u['deger']) && $b['birim'] === $u['birim'];
    }

    private function terminEsit(mixed $b, mixed $u): bool
    {
        if ($b === null || $u === null) {
            return $b === null && $u === null;
        }
        if ($b['baslangic'] === 'custom' && !is_string($u['baslangic_aciklamasi'] ?? null)) {
            return false;
        }

        return $b['baslangic'] === $u['baslangic'] && (int) $b['sure'] === (int) $u['sure'] && $b['birim'] === $u['birim'];
    }

    /** @param list<array<string, mixed>> $b @param list<array<string, mixed>> $u */
    private function kademelerEsit(array $b, array $u): bool
    {
        if (count($b) !== count($u)) {
            return false;
        }
        foreach ($b as $i => $k) {
            if (!$this->sayiEsit($k['min_adet'], $u[$i]['min_adet'] ?? null)
                || !$this->sayiEsit($k['max_adet'], $u[$i]['max_adet'] ?? null)
                || !$this->sayiEsit($k['birim_fiyat'], $u[$i]['birim_fiyat'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function koliEsit(mixed $b, mixed $u): bool
    {
        if ($b === null || $u === null) {
            return $b === null && $u === null;
        }
        foreach (['koli_ici_adet', 'uzunluk_cm', 'genislik_cm', 'yukseklik_cm', 'cbm', 'brut_kg', 'net_kg'] as $alan) {
            if (!$this->sayiEsit($b[$alan], $u[$alan] ?? null)) {
                return false;
            }
        }

        return ($b['ambalaj'] === null) === (($u['ambalaj'] ?? null) === null);
    }

    private function alternatifEsit(mixed $b, mixed $u): bool
    {
        if ($b === null || $u === null) {
            return $b === null && $u === null;
        }

        return ($b['url'] ?? null) === ($u['url'] ?? null) && is_string($u['aciklama'] ?? null);
    }

    /** @param list<mixed> $a @param list<mixed> $b */
    private function kumeEsit(array $a, array $b): bool
    {
        $a = array_map('strval', $a);
        $b = array_map('strval', $b);
        sort($a);
        sort($b);

        return array_values(array_unique($a)) === array_values(array_unique($b));
    }
}
