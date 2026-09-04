<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;

/**
 * İE#22 A4 — BELGE HATTI KUR SNAPSHOT'INA BAĞLANMAZ (K50 bekçisi).
 *
 * K50: çıktı, üretildiği ANDAKİ liste hâlini temsil eder ve geçmişten indirme
 * her zaman AYNI içeriği verir. Bu garanti tek bir şeye dayanıyor: belge kuru
 * `lists.yuan_rate` KOPYASINDAN okunuyor, yaşayan bir kur kaynağından değil.
 *
 * Kur snapshot omurgası (Blok A) tam da böyle yaşayan bir kaynaktır. Biri
 * "tutarlı olsun" diye export hattını ona bağlarsa, snapshot satırı sonradan
 * düzeltildiğinde GEÇMİŞ BELGELER DEĞİŞİR — firmaya gönderilmiş bir teklifin
 * TL tutarı kendiliğinden başkalaşır. Bu test o bağlantıyı yasaklar.
 *
 * Neden statik denetim: çalışma zamanı testi ancak "bu senaryoda çağrılmadı"
 * diyebilir; kaynak taraması "hiçbir senaryoda çağrılamaz" der.
 */
final class ExportSnapshotKurBekcisiTest extends TestCase
{
    /** Belge üretim hattındaki dosyalar — hiçbiri kur snapshot'ını görmemeli. */
    private const BELGE_HATTI = [
        'app/Services/Export/ExportSnapshot.php',
        'app/Services/Export/CsvRenderer.php',
        'app/Services/Export/XlsxRenderer.php',
        'app/Services/Export/PdfRenderer.php',
    ];

    /** @return list<array{0: string}> */
    public static function belgeDosyalari(): array
    {
        return array_map(static fn (string $yol): array => [$yol], self::BELGE_HATTI);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('belgeDosyalari')]
    public function testBELGEHATTIKURSNAPSHOTINIGORMEZ(string $goreli): void
    {
        $yol = dirname(__DIR__, 2) . '/' . $goreli;
        self::assertFileExists($yol, 'Belge hattı dosyası taşınmış olabilir; bekçi güncellenmeli.');

        $kaynak = (string) file_get_contents($yol);

        self::assertStringNotContainsString(
            'RateSnapshotRepository',
            $kaynak,
            $goreli . ' kur snapshot deposuna bağlanmış. K50 kırılır: snapshot satırı '
            . 'değişince GEÇMİŞ belgeler de değişir. Belge kuru daima lists.yuan_rate kopyasından okunur.',
        );
        self::assertStringNotContainsString(
            'rate_snapshots',
            $kaynak,
            $goreli . ' rate_snapshots tablosuna doğrudan sorgu atıyor (aynı gerekçe).',
        );
    }

    public function testLISTSSEMASINARATESNAPSHOTIDEKLENMEMIS(): void
    {
        // YASAK YALNIZ `lists` İÇİNDİR (V3-C daraltması).
        //
        // İE#22'de bu bekçi TÜM migration'larda `rate_snapshot_id` arıyordu ve
        // o gün doğruydu: kavram henüz yoktu. V3-C ile `supplier_rounds`
        // rate_snapshot referansı ALDI (#15 §1) — koruması gereken şey ise
        // hep `lists` idi: oraya eklenen bir snapshot referansı belge kuruna
        // karışma riski doğurur (K50). Geniş bırakılsaydı bekçi, korumadığı
        // bir şey için doğru işi engellerdi.
        $migrations = glob(dirname(__DIR__, 2) . '/migrations/*.php') ?: [];
        foreach ($migrations as $dosya) {
            $kaynak = (string) file_get_contents($dosya);
            // Yalnız `lists` tablosuna yapılan eklemeler denetlenir.
            if (preg_match('/ALTER\s+TABLE\s+lists\s+ADD\s+COLUMN\s+rate_snapshot_id/i', $kaynak) === 1) {
                self::fail(basename($dosya) . ' lists tablosuna rate_snapshot_id ekliyor — bu karar HÂLÂ AÇIK.');
            }
            if (preg_match('/CREATE\s+TABLE\s+lists[^;]*rate_snapshot_id/is', $kaynak) === 1) {
                self::fail(basename($dosya) . ' lists şemasında rate_snapshot_id tanımlıyor.');
            }
        }

        self::assertTrue(true, 'lists tablosuna rate_snapshot_id eklenmemiş.');
    }

    public function testTURKENDIKURDORTLUSUNUKOPYALAR(): void
    {
        // PROVENANCE: turun hesapta kullandığı kur, tur açılış anında
        // `supplier_rounds`a KOPYALANIR. Yalnız `rate_snapshot_id` tutulsaydı,
        // snapshot satırı sonradan değişince ya da silinince turun hangi kurla
        // konuşulduğu KAYBOLURDU — oysa #28 kanıt seti bunu şart koşuyor.
        $goc = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/0037_firmalar_ve_turlar.php');

        foreach (['kur_para_birimi', 'kur_degeri', 'kur_kaynagi', 'kur_kilit_at'] as $kolon) {
            self::assertStringContainsString(
                $kolon,
                $goc,
                'supplier_rounds kur dörtlüsünü KOPYA olarak taşımalı: ' . $kolon,
            );
        }
    }

    public function testHICBIRYOLKURSNAPSHOTIYLEJOINYAPMAZ(): void
    {
        // Fiyat ve toplam TEK yoldan türetilir: `lists.yuan_rate` kopyası (K50).
        // Bir sorgu `rate_snapshots` ile JOIN yapıp oradan kur çekerse, aynı
        // sayının İKİ hesabı olur — biri belgede, biri ekranda — ve ayrıştıkları
        // gün hangisinin doğru olduğu bilinemez. `rate_snapshots` okunabilir
        // (ayarlar ekranı, eşik süpürmesi); JOIN ile HESABA KARIŞAMAZ.
        $ihlaller = [];
        $taranan = 0;
        $gezgin = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app', \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $dosya */
        foreach ($gezgin as $dosya) {
            if ($dosya->getExtension() !== 'php') {
                continue;
            }
            $taranan++;
            $kaynak = (string) file_get_contents($dosya->getPathname());
            if (preg_match('/JOIN\s+rate_snapshots/i', $kaynak) === 1) {
                $ihlaller[] = $dosya->getBasename();
            }
        }

        self::assertSame(
            [],
            $ihlaller,
            "K50 İHLALİ — kur snapshot'ı JOIN ile hesaba karışmış: " . implode(', ', $ihlaller),
        );
        // Tarama boşa düşerse bekçi sessizce "temiz" derdi — sayıyı doğrula.
        self::assertGreaterThan(100, $taranan, 'Kaynak taraması boşa düştü; bekçi hiçbir şey denetlemiyor.');
    }

    public function testBELGEHATTITURTABLOLARINIGORMEZ(): void
    {
        // A2 bekçisinin kapsamı İKİ tabloyu birden sayar: ExportSnapshot ne
        // `rate_snapshots`a ne `supplier_rounds`a bağlanır. Tur kuru turun iç
        // kıyası içindir; belgeye giren kur listenin kilitli kurudur.
        $kok = dirname(__DIR__, 2);
        $ihlaller = [];

        foreach (self::BELGE_HATTI as $goreli) {
            $kaynak = (string) file_get_contents($kok . '/' . $goreli);
            foreach (['supplier_rounds', 'rate_snapshots', 'quote_lines'] as $tablo) {
                if (str_contains($kaynak, $tablo)) {
                    $ihlaller[] = $goreli . ' -> ' . $tablo;
                }
            }
        }

        self::assertSame([], $ihlaller, 'Belge hattı tur/kur tablolarına bağlanmış: ' . implode(' | ', $ihlaller));
    }
}
