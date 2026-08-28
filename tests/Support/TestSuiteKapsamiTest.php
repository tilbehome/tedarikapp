<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * İE#22 BLOK D — MATRİS DİLİMLERİ TAM SÜİTİ KAPSAR (K19'un ZORUNLU şartı).
 *
 * CI artık `tests/Http`u dört paralel job'da koşuyor. Bölme dosya adlarıyla
 * yapıldığı için sessiz bir kayıp mümkündür: yeni bir test dosyası eklenir,
 * `phpunit.xml`e yazılmaz ve o dosya CI'da HİÇ KOŞMAZ. Sonuç, testlerin
 * bulabileceği en kötü durumdur — YEŞİL AMA YALAN bir boru hattı.
 *
 * Bu bekçi tam olarak onu yasaklar: diskteki her `tests/Http` dosyası bir
 * dilimde olmalı, hiçbir dosya iki dilimde birden olmamalı ve dilimlerde
 * artık (silinmiş dosya) kalmamalı.
 *
 * K19 onayı bu testin varlığına BAĞLIYDI (ci-hizlandirma-plani.md:183).
 */
final class TestSuiteKapsamiTest extends TestCase
{
    /** @return array{dilimler: array<string, list<string>>, hepsi: list<string>} */
    private function dilimler(): array
    {
        $xml = new SimpleXMLElement((string) file_get_contents(dirname(__DIR__, 2) . '/phpunit.xml'));
        $dilimler = [];
        $hepsi = [];

        foreach ($xml->testsuites->testsuite as $suite) {
            $ad = (string) $suite['name'];
            if (!str_starts_with($ad, 'http-')) {
                continue;
            }
            $dosyalar = [];
            foreach ($suite->file as $dosya) {
                $yol = trim((string) $dosya);
                $dosyalar[] = $yol;
                $hepsi[] = $yol;
            }
            $dilimler[$ad] = $dosyalar;
        }

        return ['dilimler' => $dilimler, 'hepsi' => $hepsi];
    }

    /** @return list<string> */
    private function diskteki(): array
    {
        $kok = dirname(__DIR__, 2);
        $bulunan = glob($kok . '/tests/Http/*.php') ?: [];

        return array_values(array_map(
            static fn (string $yol): string => str_replace('\\', '/', substr($yol, strlen($kok) + 1)),
            $bulunan,
        ));
    }

    public function testHERHTTPDOSYASIBIRDILIMDE(): void
    {
        $kayitli = $this->dilimler()['hepsi'];
        $eksik = array_values(array_diff($this->diskteki(), $kayitli));

        self::assertSame(
            [],
            $eksik,
            "Bu dosyalar hiçbir CI diliminde YOK — yani CI'da hiç koşmuyorlar:\n  "
            . implode("\n  ", $eksik)
            . "\nphpunit.xml içindeki http-* suite'lerinden birine ekleyin.",
        );
    }

    public function testDILIMLERDEHAYALETDOSYAYOK(): void
    {
        $fazla = array_values(array_diff($this->dilimler()['hepsi'], $this->diskteki()));

        self::assertSame(
            [],
            $fazla,
            "phpunit.xml silinmiş dosyalara işaret ediyor:\n  " . implode("\n  ", $fazla),
        );
    }

    public function testAYNIDOSYAIKIDILIMDEOLAMAZ(): void
    {
        $hepsi = $this->dilimler()['hepsi'];
        $tekrar = array_values(array_diff_assoc($hepsi, array_unique($hepsi)));

        self::assertSame([], $tekrar, 'İki dilimde birden koşan dosya, CI süresini boşa harcar: ' . implode(', ', $tekrar));
    }

    public function testDILIMLERDENGELIDIR(): void
    {
        // Dengesiz dilim, matrisin kazancını yok eder: en yavaş job neyse
        // toplam süre odur. Kaba bir eşik yeter — dosya sayısı iki katı aşmasın.
        $sayilar = array_map('count', $this->dilimler()['dilimler']);
        self::assertNotEmpty($sayilar, 'En az bir http-* dilimi tanımlı olmalı.');

        $enAz = min($sayilar);
        $enCok = max($sayilar);
        self::assertLessThanOrEqual(
            2 * max(1, $enAz),
            $enCok,
            'Dilimler dengesiz: en dolu dilim en boşun iki katından fazla dosya taşıyor.',
        );
    }
}
