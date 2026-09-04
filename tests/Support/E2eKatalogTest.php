<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * E2E KAPSAM DEFTERİ (İE#21 A7 · B15).
 *
 * İki katalog bağlayıcı test kitabıdır: eklenti 35, panel 52 senaryo. Bu dosya
 * onları KODA ÇEVİRMEZ — çevrilip çevrilmediğini ÖLÇER.
 *
 * NEDEN BÖYLE BİR ŞEY GEREKLİ: 81 senaryoluk bir kitabın hangi maddesinin
 * kodlandığı, hangisinin kodlanmadığı hafızada tutulamaz. Tutulmaya çalışılırsa
 * iki şey olur: ya kapsanmış sanılan bir senaryo hiç yazılmaz, ya da rapor
 * "hepsi yapıldı" der ve kimse doğrulayamaz. Defter üç şeyi zorlar:
 *
 *   1. Katalogdaki HER kimlik defterde yer alır (yeni senaryo eklenirse kırmızı).
 *   2. Defterde katalogda OLMAYAN kimlik bulunmaz (silinen senaryo iz bırakmaz).
 *   3. "Kapsandı" işaretli her senaryonun karşılığı GERÇEKTEN vardır — testin
 *      dosyası ve adı defterde yazar, dosya yoksa kırmızı yanar.
 *
 * Kapsanmayanlar bir başarısızlık DEĞİLDİR; ölçülen bir borçtur ve raporda
 * sayıyla görünür.
 */
final class E2eKatalogTest extends TestCase
{
    private const EKLENTI_KATALOG = '/docs/v3/hazirlik/eklenti-e2e-senaryo-katalogu.md';
    private const PANEL_KATALOG = '/docs/v3/hazirlik/panel-e2e-senaryo-katalogu.md';
    private const DEFTER = '/docs/v3/hazirlik/e2e-kapsam-defteri.json';

    private string $kok;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kok = dirname(__DIR__, 2);
    }

    /** @return list<string> */
    private function katalogKimlikleri(string $yol): array
    {
        $icerik = (string) file_get_contents($this->kok . $yol);
        preg_match_all('/^### (E2E-[A-Z]+-\d+)/m', $icerik, $eslesmeler);

        /** @var list<string> $kimlikler */
        $kimlikler = $eslesmeler[1];

        return $kimlikler;
    }

    /** @return array<string, array<string, mixed>> */
    private function defter(): array
    {
        $yol = $this->kok . self::DEFTER;
        self::assertFileExists($yol, 'Kapsam defteri yok.');

        /** @var array<string, mixed> $veri */
        $veri = json_decode((string) file_get_contents($yol), true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string, array<string, mixed>> $senaryolar */
        $senaryolar = is_array($veri['senaryolar'] ?? null) ? $veri['senaryolar'] : [];

        return $senaryolar;
    }

    public function testEKLENTIKATALOGU35SENARYO(): void
    {
        // 29 → 31 (rc7, 26 Ağu 2026): saha bulgularından iki senaryo doğdu —
        // EKL-30 (panel yatay taşmaz) ve EKL-31 (panel varsayılan kapalı).
        // 31 → 34 (v1.0, 27 Ağu 2026): 27 Ağustos saha turu üç senaryo daha
        // doğurdu — EKL-32 (çekmece dikey kaydırma), EKL-33 (metin disiplini:
        // rozet/entity/ipucu), EKL-34 (montaj konumu ve dil geçişi).
        // Hepsi GERÇEK TARAYICI ister; jsdom yerleşim hesaplamaz.
        // 34 → 35 (v1.0.1, 28 Ağu 2026): EKL-35 — varyant çiplerinin salt
        // görünüm Türkçeleşmesi ve verinin orijinal kalması (K90).
        self::assertCount(35, $this->katalogKimlikleri(self::EKLENTI_KATALOG));
    }

    public function testPANELKATALOGU73SENARYO(): void
    {
        // 62 → 67 (v1.2.0, Ayarlar yeniden tasarımı): sol dikey gezinme, arama,
        // KPI şeridi, dar ekran ve sürüm rozeti beş senaryo daha doğurdu.
        // 52 → 62 (v1.2.0, V3-B): bildirim merkezi, panorama, 16 sekmeli
        // ayarlar, tema ve PWA on senaryo doğurdu (PNL-53..62). Bunların
        // sekizinin sunucu/birim karşılığı VAR ve deftere dosya+test adıyla
        // yazıldı; ikisi (anlık kartın modal olmaması, tema kalıcılığı) GERÇEK
        // TARAYICI ister ve "bekliyor" olarak duruyor — jsdom odak tuzağını da
        // `prefers-color-scheme` emülasyonunu da doğru taklit etmez.
        // 67 → 73 (V3-C, 4 Eyl 2026): Listeler merkezi (Blok E: sekme çipleri, K105 menü/
        // seçim/klavye, şablonlar) dört; firma yanıtı (Aşama 2.2: yapıştır-ayrıştır,
        // Excel gel-git) iki senaryo doğurdu — PNL-68..73. Altısı Vitest ile kapsandı.
        self::assertCount(73, $this->katalogKimlikleri(self::PANEL_KATALOG));
    }

    public function testHERSENARYODEFTERDEVAR(): void
    {
        $defter = $this->defter();
        $eksik = [];

        foreach ([self::EKLENTI_KATALOG, self::PANEL_KATALOG] as $katalog) {
            foreach ($this->katalogKimlikleri($katalog) as $kimlik) {
                if (!array_key_exists($kimlik, $defter)) {
                    $eksik[] = $kimlik;
                }
            }
        }

        self::assertSame([], $eksik, 'Katalogda olup defterde olmayan senaryolar: ' . implode(', ', $eksik));
    }

    public function testDEFTERDEHAYALETSENARYOYOK(): void
    {
        $tumu = array_merge(
            $this->katalogKimlikleri(self::EKLENTI_KATALOG),
            $this->katalogKimlikleri(self::PANEL_KATALOG),
        );

        $hayalet = array_diff(array_keys($this->defter()), $tumu);

        self::assertSame([], array_values($hayalet), 'Defterde katalogda olmayan kimlik var.');
    }

    public function testKAPSANDIISARETLIHERSENARYONUNKARSILIGIVAR(): void
    {
        $eksikDosya = [];

        foreach ($this->defter() as $kimlik => $kayit) {
            if (($kayit['durum'] ?? '') !== 'kapsandi') {
                continue;
            }

            $dosya = (string) ($kayit['dosya'] ?? '');
            self::assertNotSame('', $dosya, $kimlik . ': "kapsandı" ama dosya yazılmamış.');

            if (!is_file($this->kok . '/' . $dosya)) {
                $eksikDosya[] = $kimlik . ' → ' . $dosya;

                continue;
            }

            // Test ADI da doğrulanır: dosya var ama o testi içermiyorsa kapsam yalandır.
            $testAdi = (string) ($kayit['test'] ?? '');
            if ($testAdi !== '') {
                $icerik = (string) file_get_contents($this->kok . '/' . $dosya);
                if (!str_contains($icerik, $testAdi)) {
                    $eksikDosya[] = $kimlik . ' → ' . $dosya . ' içinde "' . $testAdi . '" yok';
                }
            }
        }

        self::assertSame([], $eksikDosya, "Kapsandı denen ama karşılığı olmayan senaryolar:\n  "
            . implode("\n  ", $eksikDosya));
    }

    public function testKAPSAMORANIRAPORLANIR(): void
    {
        $defter = $this->defter();
        $kapsanan = count(array_filter($defter, static fn (array $k): bool => ($k['durum'] ?? '') === 'kapsandi'));
        $toplam = count($defter);

        // Bu bir eşik testi DEĞİLDİR: sayıyı görünür kılar. Eşik koymak, borcu
        // kapatmak yerine defteri manipüle etmeye teşvik ederdi.
        fwrite(STDERR, sprintf(
            "\nE2E KAPSAM: %d/%d senaryo kodlandı (%%%d)\n",
            $kapsanan,
            $toplam,
            $toplam > 0 ? (int) round($kapsanan / $toplam * 100) : 0,
        ));

        self::assertGreaterThan(0, $toplam);
    }
}
