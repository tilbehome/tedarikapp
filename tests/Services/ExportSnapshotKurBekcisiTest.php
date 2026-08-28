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
        // İE#22 sınırı: `lists.rate_snapshot_id` İE#23'e bırakıldı. Eklenirse
        // hesaba karışma riski doğar; bekçi erken uyarsın.
        $migrations = glob(dirname(__DIR__, 2) . '/migrations/*.php') ?: [];
        foreach ($migrations as $dosya) {
            self::assertStringNotContainsString(
                'rate_snapshot_id',
                (string) file_get_contents($dosya),
                basename($dosya) . ' lists tablosuna rate_snapshot_id ekliyor — bu İE#22 kapsamı dışıdır.',
            );
        }
    }
}
