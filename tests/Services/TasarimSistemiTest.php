<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;

/**
 * TASARIM SİSTEMİ DİSİPLİNİ (İE#16 D1.1).
 *
 * İki kural burada BEKÇİYE bağlanır — yoksa zamanla sessizce çürürler:
 *
 *  1. `frontend/src/styles/tokens.css` şartnamenin (docs/v3/prototip) BİREBİR
 *     kopyası olmalı. Kopya sürüklenirse koyu tema ve marka renkleri panel ile
 *     prototip arasında ayrışır.
 *  2. Bileşenlerde SABİT RENK olmamalı: `bg-white`, `text-slate-500` gibi
 *     Tailwind hazır tonları koyu temada değişmez ve tema tek kaynaktan
 *     yönetilemez hale gelir.
 */
final class TasarimSistemiTest extends TestCase
{
    private function kok(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testTokenDosyasiSARTNAMEYLE_AYNI(): void
    {
        $sartname = $this->kok() . '/docs/v3/prototip/tasarim-tokenlari.css';
        $kod = $this->kok() . '/frontend/src/styles/tokens.css';

        self::assertFileExists($sartname);
        self::assertFileExists($kod);

        // Başlık yorumu koddaki kopyada genişletilmiştir (nereden geldiğini anlatır);
        // KARŞILAŞTIRMA ilk yorum bloğundan SONRASI üzerinden yapılır.
        $govde = static function (string $yol): string {
            $icerik = (string) file_get_contents($yol);
            $son = strpos($icerik, '*/');
            $govde = $son === false ? $icerik : substr($icerik, $son + 2);

            return trim(str_replace("\r\n", "\n", $govde));
        };

        self::assertSame(
            $govde($sartname),
            $govde($kod),
            'Token dosyası şartnameden AYRIŞMIŞ. Önce docs/v3/prototip güncellenir, sonra kod eşitlenir.',
        );
    }

    public function testBILESENLERDE_SABIT_RENK_YOK(): void
    {
        $ihlaller = [];
        // Tailwind'in hazır ton ölçekleri: bunlar koyu temada DEĞİŞMEZ.
        $desen = '/(?<![\w-])(?:bg|text|border|ring|divide|from|to|outline|fill|stroke)-'
            . '(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|indigo|violet|purple|fuchsia|pink|rose)'
            . '-\d{2,3}(?![\w-])/';

        foreach ($this->kaynakDosyalari() as $dosya) {
            $icerik = (string) file_get_contents($dosya);
            if (preg_match_all($desen, $icerik, $eslesmeler) === 0) {
                continue;
            }
            $ihlaller[str_replace($this->kok() . '/', '', str_replace('\\', '/', $dosya))] = array_values(
                array_unique($eslesmeler[0]),
            );
        }

        self::assertSame(
            [],
            $ihlaller,
            "Sabit renk sınıfı bulundu (D1.1). Token karşılıklarını kullanın:\n"
            . "  bg-white → bg-surface · bg-slate-50 → bg-g50 · text-slate-500 → text-ink-3\n"
            . "  border-slate-200 → border-line · amber → warn · rose → err · emerald → ok\n"
            . print_r($ihlaller, true),
        );
    }

    public function testKOYU_TEMA_TEK_KAYNAKTAN_CALISIR(): void
    {
        $tokenlar = (string) file_get_contents($this->kok() . '/frontend/src/styles/tokens.css');

        self::assertStringContainsString('[data-theme="dark"]', $tokenlar, 'Koyu tema token bloğu şart.');

        // Koyu blok yalnız DEĞİŞKEN atamalı olmalı: bileşen seçicisi (.sinif, #id)
        // içeriyorsa "ikinci bir CSS seti" yazılmaya başlanmış demektir.
        $bas = strpos($tokenlar, '[data-theme="dark"]');
        self::assertIsInt($bas);
        $blok = substr($tokenlar, $bas, (int) strpos($tokenlar, '}', $bas) - $bas);

        foreach (explode("\n", $blok) as $satir) {
            $satir = trim($satir);
            if ($satir === '' || str_starts_with($satir, '/*') || str_starts_with($satir, '*')
                || str_starts_with($satir, '[data-theme')) {
                continue;
            }
            self::assertMatchesRegularExpression(
                '/^--[\w-]+\s*:/',
                $satir,
                'Koyu tema bloğunda yalnız token ataması olur, bileşen kuralı yazılmaz: ' . $satir,
            );
        }
    }

    /** @return list<string> panelin kaynak dosyaları (bileşen + ekran) */
    private function kaynakDosyalari(): array
    {
        $kok = $this->kok() . '/frontend/src';
        if (!is_dir($kok)) {
            self::markTestSkipped('frontend/src yok.');
        }

        $out = [];
        $gezgin = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($kok, \FilesystemIterator::SKIP_DOTS));
        foreach ($gezgin as $dosya) {
            if (!$dosya instanceof \SplFileInfo || !in_array($dosya->getExtension(), ['ts', 'tsx'], true)) {
                continue;
            }
            $out[] = $dosya->getPathname();
        }
        sort($out);

        return $out;
    }
}
