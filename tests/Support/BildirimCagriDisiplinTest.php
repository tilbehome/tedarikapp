<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * K102 BEKÇİSİ — `yayimla()` DOĞRUDAN ÇAĞRILMAZ (madde 13).
 *
 * K102 tek bir kural koydu: bildirim yayını transaction İÇİNDEYSE istisna
 * atar (birincil kayıt geri alınır), DIŞINDAYSA sayar (birincil eylem
 * düşmez). Kuralı uygulayan yer `BildirimYayinci::guvenliYayimla()`.
 *
 * Biri ileride kolaylık olsun diye `yayimla()`yı doğrudan çağırırsa kural o
 * noktada DELİNİR ve sonucu şudur: transaction dışında yazılamayan bir
 * bildirim, başarıyla kaydedilmiş bir listeyi kullanıcıya 500 olarak
 * gösterir. Çalışma zamanında ancak sağlayıcı çöktüğünde görülür — yani
 * en kötü anda.
 *
 * Bu bekçi `CalismaZamaniVerisiTest` ile aynı kalıptadır: KAYNAK TARAR.
 * Tek istisna `BildirimYayinci`nin kendi içidir — kuralı orada uyguluyor.
 */
final class BildirimCagriDisiplinTest extends TestCase
{
    /** Kuralı UYGULAYAN dosya: `yayimla()`yı çağırması gereken tek yer. */
    private const MUAF = 'app/Services/Bildirim/BildirimYayinci.php';

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

    public function testYAYIMLADOGRUDANCAGRILMAZ(): void
    {
        $kok = dirname(__DIR__, 2);
        $ihlaller = [];

        foreach (['app', 'bin', 'bootstrap'] as $dizin) {
            foreach ($this->phpDosyalari($dizin) as $goreli) {
                if ($goreli === self::MUAF) {
                    continue;
                }

                foreach (explode("\n", (string) file_get_contents($kok . '/' . $goreli)) as $no => $satir) {
                    $kirpik = trim($satir);
                    // Yorumlar hariç: K102'nin gerekçesi bu dosyalarda yazılı
                    // ve yöntem adını anmak zorunda.
                    if ($kirpik === '' || str_starts_with($kirpik, '*') || str_starts_with($kirpik, '//')
                        || str_starts_with($kirpik, '/*')) {
                        continue;
                    }
                    // `guvenliYayimla(` da `yayimla(` içerir — önce onu eleriz.
                    $temiz = str_replace('guvenliYayimla(', '', $kirpik);
                    if (!str_contains($temiz, '->yayimla(')) {
                        continue;
                    }
                    $ihlaller[] = sprintf('%s:%d  %s', $goreli, $no + 1, mb_substr($kirpik, 0, 80));
                }
            }
        }

        self::assertSame(
            [],
            $ihlaller,
            "K102 İHLALİ — `yayimla()` DOĞRUDAN çağrılmış. Kural yalnız\n"
            . "`guvenliYayimla()` içinde uygulanır: transaction içindeyse at,\n"
            . "dışındaysa say. Doğrudan çağrı, commit sonrası bir bildirim\n"
            . "hatasının başarılı bir işlemi 500'e çevirmesine yol açar:\n  "
            . implode("\n  ", $ihlaller),
        );
    }

    public function testMUAFDOSYAKURALIGERCEKTENUYGULUYOR(): void
    {
        // Muafiyet, o dosyanın kuralı UYGULADIĞI varsayımına dayanıyor.
        // Varsayım bir gün bozulursa muafiyet sessiz bir delik olurdu.
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::MUAF);

        self::assertStringContainsString('public function guvenliYayimla(', $kaynak);
        self::assertStringContainsString('islemIcindeMi()', $kaynak, 'Kural transaction algısına dayanmalı.');
        self::assertStringContainsString('KEY_HATA_SAYISI', $kaynak, 'Kayıt sonrası hata SAYILMALI.');
    }

    public function testTARAMAGERCEKTENDOSYABULUYOR(): void
    {
        // Glob/iterator bozulursa test "0 ihlal" der ve yeşil kalır.
        self::assertGreaterThan(50, count($this->phpDosyalari('app')));
    }
}
