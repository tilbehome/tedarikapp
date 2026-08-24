<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Export\BelgeMarkasi;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * BELGE MARKASI (İE#21 B13 — antet/amblem/filigran).
 *
 * Sınıfın tek sözü: varlık VARSA yolunu ver, YOKSA null ver ve belge üretimini
 * ENGELLEME. Bu testler o sözü iki yönden tutar; ayrıca varlıkların sürüm
 * paketine giren yerde (public/) durduğunu kanıtlar — `docs/` pakete girmez,
 * yani orada bırakılan bir görsel canlıda hiç var olmazdı.
 */
final class BelgeMarkasiTest extends TestCase
{
    use TempDirectory;

    public function testGERCEKKURULUMDAVARLIKLARBULUNUR(): void
    {
        $marka = new BelgeMarkasi(dirname(__DIR__, 2));

        self::assertNotNull($marka->amblem(), 'Amblem public/marka/belge altında olmalı.');
        self::assertNotNull($marka->filigran(), 'Filigran public/marka/belge altında olmalı.');
        self::assertNotNull($marka->antet(), 'Antet bandı public/marka/belge altında olmalı.');
    }

    public function testVARLIKLARPAKETEGIRENDIZINDEDIR(): void
    {
        $kok = dirname(__DIR__, 2);

        // bin/release.php yalnız app, bin, bootstrap, config, migrations, public,
        // setup, vendor dizinlerini taşır. Varlık `docs/` altında kalsaydı canlı
        // kurulumda hiç bulunmaz, filigran sessizce hiç basılmazdı.
        foreach (['amblem.png', 'filigran.png', 'antet.png'] as $ad) {
            self::assertFileExists($kok . '/public/marka/belge/' . $ad);
        }
        self::assertFileExists($kok . '/config/belge-tema.json');
    }

    public function testVARLIKYOKSANULLDONERVEPATLAMAZ(): void
    {
        $bos = $this->tempPath('marka-bos');

        $marka = new BelgeMarkasi($bos);

        self::assertNull($marka->amblem());
        self::assertNull($marka->filigran());
        self::assertNull($marka->antet());
    }

    public function testSAYIBICIMITEMADANOKUNUR(): void
    {
        $marka = new BelgeMarkasi(dirname(__DIR__, 2));

        self::assertSame('#,##0.00', $marka->sayiBicimi('currency', 'YEDEK'));
        self::assertSame('#,##0', $marka->sayiBicimi('quantity', 'YEDEK'));
    }

    public function testBILINMEYENBICIMVARSAYILANADUSER(): void
    {
        $marka = new BelgeMarkasi(dirname(__DIR__, 2));

        self::assertSame('YEDEK', $marka->sayiBicimi('boyle_bir_bicim_yok', 'YEDEK'));
    }

    public function testTEMAYOKSAVARSAYILANKULLANILIR(): void
    {
        $bos = $this->tempPath('marka-temasiz');

        // Tema dosyası olmayan kurulumda Excel üretimi düşmemeli: biçim
        // varsayılanı döner.
        self::assertSame('#,##0.00', (new BelgeMarkasi($bos))->sayiBicimi('currency', '#,##0.00'));
    }

    public function testBOZUKTEMAJSONUURETIMIDUSURMEZ(): void
    {
        $dizin = $this->tempPath('marka-bozuk');
        mkdir($dizin . '/config', 0o775, true);
        file_put_contents($dizin . '/config/belge-tema.json', '{ bu json değil');

        self::assertSame('YEDEK', (new BelgeMarkasi($dizin))->sayiBicimi('currency', 'YEDEK'));
    }
}
