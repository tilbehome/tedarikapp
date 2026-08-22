<?php

declare(strict_types=1);

namespace Tests\Models;

use PHPUnit\Framework\TestCase;

/**
 * TEKRAR EDEN YER TUTUCU BEKÇİSİ — canlı hata dersi (22 Ağu 2026).
 *
 * BELİRTİ: Listeler ekranında arama kutusuna bir şey yazınca 500 dönüyordu.
 *
 * KÖK NEDEN: `... name LIKE :q OR supplier_name LIKE :q` — aynı isimli yer tutucu
 * SQL'de iki kez geçiyor ama bir kez bağlanıyordu. Üretimde PDO **native prepare**
 * kullanır (`ATTR_EMULATE_PREPARES => false`) ve MySQL bunu kabul etmez: sürücü
 * `HY093 Invalid parameter number` fırlatır. Test ortamı SQLite'ta EMÜLASYON açık
 * olduğu için aynı sorgu orada sorunsuz koşuyor — hata testlerden GİZLENİYORDU.
 *
 * Bu test kaynağı tarar: bir SQL metninde aynı `:ad` yer tutucusu birden çok kez
 * geçiyorsa kırılır. Böylece kusur ortama değil KODA bakılarak yakalanır.
 */
final class SorguYerTutucuTest extends TestCase
{
    public function testAyniYerTutucuBIR_SQL_ICINDE_TEKRAR_ETMEZ(): void
    {
        $ihlaller = [];

        foreach ($this->phpDosyalari() as $dosya) {
            foreach ($this->sqlMetinleri($dosya) as $sql) {
                preg_match_all('/:([a-z_][a-z0-9_]*)/i', $sql, $tutucular);
                foreach (array_count_values($tutucular[1]) as $ad => $adet) {
                    if ($adet > 1) {
                        $ihlaller[] = sprintf(
                            '%s → ":%s" %d kez · %.70s…',
                            str_replace(DIRECTORY_SEPARATOR, '/', substr($dosya, strlen($this->kok()) + 1)),
                            $ad,
                            $adet,
                            trim(preg_replace('/\s+/', ' ', $sql) ?? $sql),
                        );
                    }
                }
            }
        }

        self::assertSame(
            [],
            $ihlaller,
            "Aynı yer tutucu bir SQL parçasında birden çok kez geçiyor. Üretimde MySQL
"
            . "native prepare bunu HY093 ile reddeder (SQLite emülasyonu gizler). Her
"
            . "sütuna AYRI ad verin ve değeri her birine bağlayın:
"
            . implode("
", $ihlaller),
        );
    }

    /**
     * Dosyadaki GERÇEK string sabitleri (yorumlar hariç) — PHP'nin kendi
     * çözümleyicisiyle alınır; içlerinden SQL görünenler süzülür.
     *
     * Yorum metinlerini düzenli ifadeyle ayıklamak Türkçe KESME İŞARETİ yüzünden
     * yanılıyordu: iki yorum arasındaki kesme işaretleri eşleşip araya giren kodu
     * "string" sanıyordu. Tokenizer bu tuzağa düşmez.
     *
     * @return list<string>
     */
    private function sqlMetinleri(string $dosya): array
    {
        $out = [];
        foreach (token_get_all((string) file_get_contents($dosya)) as $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] !== T_CONSTANT_ENCAPSED_STRING && $token[0] !== T_ENCAPSED_AND_WHITESPACE) {
                continue;
            }
            $metin = trim($token[1], '\'\"');
            // Yalnız SQL fiiliyle ya da eklenen koşul parçasıyla başlayanlar.
            if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|AND|OR|SET|VALUES|WHERE)\b/i', $metin) === 1) {
                $out[] = $metin;
            }
        }

        return $out;
    }

    private function kok(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> */
    private function phpDosyalari(): array
    {
        $out = [];
        foreach (['app', 'bin'] as $dizin) {
            $yol = $this->kok() . '/' . $dizin;
            if (!is_dir($yol)) {
                continue;
            }
            $gezgin = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($yol, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($gezgin as $dosya) {
                if ($dosya instanceof \SplFileInfo && $dosya->getExtension() === 'php') {
                    $out[] = $dosya->getPathname();
                }
            }
        }
        sort($out);

        return $out;
    }
}
