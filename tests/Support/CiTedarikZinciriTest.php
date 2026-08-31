<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * SERTLEŞTİRME v1.2.1 BLOK E — CI TEDARİK ZİNCİRİ (TDR-026, TDR-027).
 *
 * NEDEN BEKÇİ: `uses: actions/checkout@v7` gibi bir etiket HAREKETLİ bir
 * işaretçidir. Etiketin arkasındaki commit değiştirilebilir (yeniden
 * etiketleme) ya da action deposu ele geçirilebilir; o an CI'mız, sırlarımıza
 * erişimi olan bir ortamda YABANCI kod çalıştırır. SHA sabittir ve değişmez.
 *
 * Bir bölümü zaten pinliydi ama YENİ eklenen satırlar etiketle geliyordu —
 * tam olarak bu yüzden bekçi gerekli: disiplin insan hafızasına bırakılamaz.
 */
final class CiTedarikZinciriTest extends TestCase
{
    /** @return list<string> */
    private function isAkislari(): array
    {
        $bulunan = glob(dirname(__DIR__, 2) . '/.github/workflows/*.yml') ?: [];

        return array_values($bulunan);
    }

    public function testISAKISIVAR(): void
    {
        // Tarama boşa düşerse bekçi sessizce "temiz" derdi.
        self::assertNotSame([], $this->isAkislari(), 'İş akışı dosyası bulunamadı; bekçi hiçbir şey denetlemiyor.');
    }

    public function testHERUCUNCUPARTIACTIONSHAILEPINLI(): void
    {
        $ihlaller = [];

        foreach ($this->isAkislari() as $dosya) {
            foreach (explode("\n", (string) file_get_contents($dosya)) as $no => $satir) {
                if (preg_match('/^\s*(-\s+)?uses:\s*(\S+)/', $satir, $eslesme) !== 1) {
                    continue;
                }
                $referans = $eslesme[2];

                // Yerel action (`./.github/...`) pinlenmez — depo içindedir.
                if (str_starts_with($referans, './')) {
                    continue;
                }

                // `sahip/ad@<40 hane sha>` — etiket ya da dal KABUL EDİLMEZ.
                if (preg_match('/@[0-9a-f]{40}$/', $referans) !== 1) {
                    $ihlaller[] = basename($dosya) . ':' . ($no + 1) . '  ' . $referans;
                }
            }
        }

        self::assertSame(
            [],
            $ihlaller,
            "Üçüncü parti action SHA ile pinli DEĞİL — etiket hareketli bir işaretçidir ve\n"
            . "arkasındaki kod değiştirilebilir. CI, sırlara erişimi olan bir ortamdır:\n  "
            . implode("\n  ", $ihlaller),
        );
    }

    public function testPINLIREFERANSSURUMYORUMUTASIR(): void
    {
        // Çıplak SHA okunamaz: hangi sürüm olduğunu kimse bilmez ve güncelleme
        // kararı verilemez. Yorum, pinlemeyi sürdürülebilir kılan şeydir.
        $ihlaller = [];

        foreach ($this->isAkislari() as $dosya) {
            foreach (explode("\n", (string) file_get_contents($dosya)) as $no => $satir) {
                if (preg_match('/uses:\s*\S+@[0-9a-f]{40}/', $satir) !== 1) {
                    continue;
                }
                if (!str_contains($satir, '#')) {
                    $ihlaller[] = basename($dosya) . ':' . ($no + 1);
                }
            }
        }

        self::assertSame([], $ihlaller, 'Pinli referans sürüm yorumu taşımıyor: ' . implode(', ', $ihlaller));
    }

    public function testIZINLERENAZYETKIYLEBASLAR(): void
    {
        $icerik = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/ci.yml');

        self::assertMatchesRegularExpression(
            '/^permissions:\s*\n\s+contents:\s*read/m',
            $icerik,
            'İş akışı varsayılan izni EN AZ yetki olmalı (contents: read).',
        );
    }

    public function testDEPENDABOTBUTUNPAKETDIZINLERINIGOZLUYOR(): void
    {
        // Gözetimsiz bir bağımlılık dizini, `npm audit` kapısının bir gün
        // kırmızıya dönmesi ve kimsenin sebebini bilmemesi demektir.
        $dependabot = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/dependabot.yml');

        foreach (['/frontend', '/extension', '/e2e'] as $dizin) {
            self::assertStringContainsString(
                'directory: ' . $dizin,
                $dependabot,
                $dizin . ' dependabot gözetiminde DEĞİL.',
            );
        }
    }

    public function testPAKETDIZINIVARSADEPENDABOTTADAVAR(): void
    {
        // Yeni bir paket dizini eklenirse bekçi onu da ister — liste elle
        // güncellenmek zorunda kalmasın.
        $kok = dirname(__DIR__, 2);
        $dependabot = (string) file_get_contents($kok . '/.github/dependabot.yml');
        $eksik = [];

        foreach (['frontend', 'extension', 'e2e'] as $ad) {
            if (is_file($kok . '/' . $ad . '/package.json')
                && !str_contains($dependabot, 'directory: /' . $ad)) {
                $eksik[] = $ad;
            }
        }

        self::assertSame([], $eksik, 'package.json var ama dependabot gözetmiyor: ' . implode(', ', $eksik));
    }
}
