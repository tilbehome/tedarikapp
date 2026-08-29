<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * K99 BEKÇİSİ — ÇALIŞMA ZAMANI VERİSİ `docs/` ALTINDAN OKUNMAZ.
 *
 * OLAY (29 Ağu 2026): v1.2.0'ın ilk paketi doğrulamadan geçti — 2225 dosya
 * sayıldı, hepsinin SHA'sı tuttu, manifest eşleşti — ve içindeki bildirim
 * sistemi ÖLÜYDÜ. Sebep: `BildirimKatalogu` ve `PanoramaServisi` katalogları
 * `docs/` altından okuyordu, `docs/` pakete girmiyor. Testler yeşildi çünkü
 * repo kökünden koşuyorlar; orada `docs/` var.
 *
 * Bu bekçi `ExportSnapshotKurBekcisiTest` kalıbındadır: KAYNAK TARAR. Bir
 * gün biri kolaylık olsun diye yeni bir katalogu `docs/` altına koyarsa, o
 * gün CI kırmızı yanar — canlıda sessizce ölmez.
 *
 * TEK İSTİSNA `docs/surum-notlari/`: kullanıcıya dönük içeriktir, `docs/`ta
 * kalması doğrudur ve `bin/release.php` onu AÇIKÇA pakete alır.
 */
final class CalismaZamaniVerisiTest extends TestCase
{
    /** Uygulama kodu — çalışma zamanında koşan her şey. */
    private const TARANAN_DIZINLER = ['app', 'bootstrap'];

    /** İzinli tek `docs/` yolu ve gerekçesi. */
    private const IZINLI = 'docs/surum-notlari/';

    /** @return list<string> */
    private function phpDosyalari(string $goreliDizin): array
    {
        $kok = dirname(__DIR__, 2);
        $bulunan = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($kok . '/' . $goreliDizin, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $dosya) {
            if ($dosya instanceof \SplFileInfo && $dosya->isFile() && $dosya->getExtension() === 'php') {
                $bulunan[] = str_replace('\\', '/', substr($dosya->getPathname(), strlen($kok) + 1));
            }
        }

        return $bulunan;
    }

    public function testUYGULAMAKODUDOCSALTINDANOKUMAZ(): void
    {
        $kok = dirname(__DIR__, 2);
        $ihlaller = [];

        foreach (self::TARANAN_DIZINLER as $dizin) {
            foreach ($this->phpDosyalari($dizin) as $goreli) {
                $icerik = (string) file_get_contents($kok . '/' . $goreli);

                foreach (explode("\n", $icerik) as $no => $satir) {
                    $kirpik = trim($satir);
                    // Yorum satırları hariç: K99'un GEREKÇESİ bu dosyalarda
                    // yazılı ve "docs/" kelimesini anmak zorunda.
                    if ($kirpik === '' || str_starts_with($kirpik, '*') || str_starts_with($kirpik, '//')
                        || str_starts_with($kirpik, '/*')) {
                        continue;
                    }
                    if (!str_contains($kirpik, "'/docs/") && !str_contains($kirpik, "'docs/")) {
                        continue;
                    }
                    if (str_contains($kirpik, self::IZINLI)) {
                        continue;
                    }
                    $ihlaller[] = sprintf('%s:%d  %s', $goreli, $no + 1, mb_substr($kirpik, 0, 90));
                }
            }
        }

        self::assertSame(
            [],
            $ihlaller,
            "K99 İHLALİ — uygulama kodu `docs/` altından okuyor. `docs/` pakete GİRMEZ;\n"
            . "bu yol canlıda dosya bulunamadı hatası verir ve özellik SESSİZCE ölür.\n"
            . "Çalışma zamanı verisi `config/` altına taşınmalı:\n  "
            . implode("\n  ", $ihlaller),
        );
    }

    public function testKATALOGLARCONFIGALTINDA(): void
    {
        $kok = dirname(__DIR__, 2);

        foreach ([
            'config/bildirim-olay-katalogu.json',
            'config/panorama-brifing-katalogu.json',
        ] as $katalog) {
            self::assertFileExists($kok . '/' . $katalog, $katalog . ' K99 gereği config/ altında olmalı.');
        }
    }

    public function testKATALOGLARDOCSALTINDAKALMAMIS(): void
    {
        // Taşıma KOPYA DEĞİL: eski yolda bir dosya kalırsa iki gerçek kaynak
        // doğar ve hangisinin okunduğu belirsizleşir.
        $kok = dirname(__DIR__, 2);

        foreach ([
            'docs/v3/hazirlik/v3-b/bildirim-olay-katalogu.json',
            'docs/v3/hazirlik/v3-b/panorama-brifing-katalogu.json',
        ] as $eski) {
            self::assertFileDoesNotExist(
                $kok . '/' . $eski,
                $eski . ' hâlâ duruyor — taşıma kopya olmuş, iki gerçek kaynak var.',
            );
        }
    }

    public function testRELEASESURUMNOTLARINIPAKETEALIR(): void
    {
        $release = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/release.php');

        self::assertStringContainsString(
            "docs/surum-notlari/",
            $release,
            'Sürüm notları pakete alınmazsa "Yenilikler" balonu sessizce boş çıkar.',
        );
    }

    public function testRELEASECALISTIRMADENETIMIVAR(): void
    {
        $release = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/release.php');

        // Dosya saymak yetmedi: paket ÇALIŞTIRILARAK denetlenmeli.
        self::assertStringContainsString('paketCalistirmaDenetimi', $release);
        self::assertStringContainsString('mevcutKurulumUstuneDenetimi', $release);
    }
}
