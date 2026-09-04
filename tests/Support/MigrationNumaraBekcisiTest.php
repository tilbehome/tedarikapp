<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * MIGRATION NUMARA BEKÇİSİ (v1.2.2 Blok 0.2 — 0036 çakışması dersi).
 *
 * NE OLDU: iki dal aynı anda `0036_` önekiyle migration açtı
 * (`0036_paylasim_anahtari_sifreli_alan` sertleştirmede,
 * `0036_firmalar_ve_turlar` V3-C'de). Çakışma ancak MERGE ZİNCİRİ kurulurken
 * fark edildi ve elle yeniden numaralama gerekti.
 *
 * NEDEN TEHLİKELİ: `Migrator` dosyaları ADA göre sıralar ve deftere ADI yazar.
 * İki dosya aynı önekle gelirse sıra belirsizleşir; daha kötüsü, biri deftere
 * işlenip diğeri "zaten uygulanmış" gibi görünebilir ve SESSİZCE ATLANIR.
 * Atlanan bir DDL, üretimde yalnız o kolona/tabloya dokunulduğunda patlar —
 * yani en geç ve en pahalı anda.
 *
 * Bu bekçi çakışmayı DAL BİRLEŞMEDEN, ilk koşumda kırmızıya çevirir.
 */
final class MigrationNumaraBekcisiTest extends TestCase
{
    /** @return list<string> */
    private function migrationDosyalari(): array
    {
        return array_values(glob(dirname(__DIR__, 2) . '/migrations/*.php') ?: []);
    }

    public function testMIGRATIONDIZINIBOSDEGIL(): void
    {
        // Tarama boşa düşerse bekçi sessizce "temiz" derdi.
        self::assertGreaterThan(20, count($this->migrationDosyalari()));
    }

    public function testAYNIONEKIKIDOSYADAKULLANILMAZ(): void
    {
        $onekler = [];
        foreach ($this->migrationDosyalari() as $yol) {
            $ad = basename($yol, '.php');
            if (preg_match('/^(\d{4})_/', $ad, $eslesme) !== 1) {
                continue; // biçim denetimi ayrı testte
            }
            $onekler[$eslesme[1]][] = $ad;
        }

        $cakisanlar = [];
        foreach ($onekler as $onek => $adlar) {
            if (count($adlar) > 1) {
                $cakisanlar[] = $onek . ' → ' . implode(' + ', $adlar);
            }
        }

        self::assertSame(
            [],
            $cakisanlar,
            "AYNI MIGRATION NUMARASI İKİ DOSYADA:\n  " . implode("\n  ", $cakisanlar)
            . "\nMigrator dosyaları ada göre sıralar ve deftere ADI yazar; çakışmada sıra\n"
            . "belirsizleşir ve biri SESSİZCE atlanabilir. Dal birleştirmeden ÖNCE yeniden\n"
            . 'numaralayın (dosya adı + BASELINE haritası + testler + docs).',
        );
    }

    public function testHERDOSYADORTHANELIONEKTASIR(): void
    {
        // Biçim serbest bırakılırsa (`36_`, `0036b_`) yukarıdaki çakışma
        // denetimi onları hiç görmez ve bekçi kendi kör noktasını üretir.
        $bozuk = [];
        foreach ($this->migrationDosyalari() as $yol) {
            $ad = basename($yol, '.php');
            if (preg_match('/^\d{4}_[a-z0-9_]+$/', $ad) !== 1) {
                $bozuk[] = $ad;
            }
        }

        self::assertSame(
            [],
            $bozuk,
            'Migration adı `NNNN_kucuk_harf_adi` biçiminde olmalı: ' . implode(', ', $bozuk),
        );
    }

    public function testNUMARALARBOSLUKSUZDEGILAMAARTAN(): void
    {
        // Boşluk OLABİLİR (bir migration iptal edilip silinmiş olabilir);
        // sınanan şey numaraların TEKİL ve artan sıralanabilir olması.
        $numaralar = [];
        foreach ($this->migrationDosyalari() as $yol) {
            if (preg_match('/^(\d{4})_/', basename($yol, '.php'), $eslesme) === 1) {
                $numaralar[] = (int) $eslesme[1];
            }
        }

        self::assertSame(
            count($numaralar),
            count(array_unique($numaralar)),
            'Numaralar tekil olmalı.',
        );
    }

    public function testBASELINEHARITASINDAKIADLARDOSYAYLAESLESIR(): void
    {
        // 0032 dersi: haritada OLMAYAN migration baseline akışında sessizce
        // atlanır. Tersi de doğrudur — haritada olup DOSYASI OLMAYAN bir ad,
        // yeniden numaralamanın yarım kaldığının işaretidir.
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Migrator.php');
        $blok = substr(
            $kaynak,
            (int) strpos($kaynak, 'BASELINE_OBJECTS = ['),
            (int) strpos($kaynak, 'KABUL_EDILEN_ESKI_CHECKSUMLAR') - (int) strpos($kaynak, 'BASELINE_OBJECTS = ['),
        );

        preg_match_all("/'(\d{4}_[a-z0-9_]+)' =>/", $blok, $eslesmeler);
        $haritadakiler = $eslesmeler[1];
        self::assertNotSame([], $haritadakiler, 'BASELINE haritası okunamadı; bekçi kör.');

        $diskteki = array_map(
            static fn (string $y): string => basename($y, '.php'),
            $this->migrationDosyalari(),
        );

        $dosyasiz = array_values(array_diff($haritadakiler, $diskteki));

        self::assertSame(
            [],
            $dosyasiz,
            'BASELINE haritasında var ama DOSYASI YOK (yeniden numaralama yarım kalmış olabilir): '
            . implode(', ', $dosyasiz),
        );
    }
}
