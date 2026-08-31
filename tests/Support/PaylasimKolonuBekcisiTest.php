<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * K103 BEKÇİSİ — `lists` PAYLAŞIM KOLONLARI UYGULAMA KODUNDAN OKUNMAZ/YAZILMAZ.
 *
 * V3-C paylaşımı `lists`in altı kolonundan (`share_token_hash`,
 * `share_token_prefix`, `share_expires_at`, `share_key_hash`,
 * `share_key_plain`, `share_key_enabled`) ayrı bir `shares` tablosuna taşıdı.
 * Kolonlar SİLİNMEDİ (K103): canlıda firmalara gönderilmiş linkler var ve
 * geçiş süresince salt-okunur duruyorlar.
 *
 * TAM DA BU YÜZDEN BEKÇİ ŞART. İki kaynak yan yana durduğunda olan şey
 * bellidir: biri yazılır, öteki okunur ve ayrışma AYLARCA fark edilmez.
 * Burada iki şey zorlanır:
 *
 *   ŞART A — OKUMA: hiçbir uygulama dosyası bu kolonları dizi indeksiyle
 *            okumaz (`$row['share_token_hash']` gibi).
 *   ŞART B — YAZMA: hiçbir uygulama dosyası bu kolonlara SQL ile yazmaz
 *            (`UPDATE lists SET share_...`, `->update(..., ['share_...'])`).
 *
 * MUAF OLANLAR VE SEBEBİ:
 *   · göç migration'ları (0038) — taşımayı YAPAN kod, kolonları okumak
 *     zorundadır,
 *   · `Migrator::BASELINE_OBJECTS` — kolon adlarını harita olarak taşır,
 *   · göç doğrulama testi — göçün doğru çalıştığını kanıtlamak için okur.
 *
 * API ANAHTAR ADLARI MUAF DEĞİL AMA HEDEF DE DEĞİL: `ListPresenter` panele
 * hâlâ `share_token_prefix` adıyla veri döner (sözleşme kırılmasın diye) ama
 * DEĞERİ `shares`ten okur. Bekçi bu yüzden "anahtar adı geçiyor mu" değil,
 * "dizi indeksiyle okunuyor mu / SQL'e yazılıyor mu" diye bakar.
 */
final class PaylasimKolonuBekcisiTest extends TestCase
{
    /** @var list<string> */
    private const KOLONLAR = [
        'share_token_hash',
        'share_token_prefix',
        'share_expires_at',
        'share_key_hash',
        'share_key_plain',
        'share_key_enabled',
    ];

    /** @var list<string> */
    private const TARANAN = ['app', 'bin', 'bootstrap'];

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

    /** Yorum satırı mı? (K103'ün gerekçesi kodda yazılı ve kolon adlarını anıyor.) */
    private function yorumMu(string $satir): bool
    {
        $kirpik = ltrim($satir);

        return $kirpik === ''
            || str_starts_with($kirpik, '*')
            || str_starts_with($kirpik, '//')
            || str_starts_with($kirpik, '/*');
    }

    public function testSARTAHICBIRYERDEDIZIILEOKUNMAZ(): void
    {
        $kok = dirname(__DIR__, 2);
        $ihlaller = [];

        foreach (self::TARANAN as $dizin) {
            foreach ($this->phpDosyalari($dizin) as $goreli) {
                if ($goreli === 'app/Core/Migrator.php') {
                    continue; // BASELINE haritası kolon adlarını taşır
                }

                $icerik = (string) file_get_contents($kok . '/' . $goreli);
                foreach (explode("\n", $icerik) as $no => $satir) {
                    if ($this->yorumMu($satir)) {
                        continue;
                    }
                    foreach (self::KOLONLAR as $kolon) {
                        // DİZİ İNDEKSİYLE OKUMA: `$row['share_token_hash']`.
                        if (str_contains($satir, "['" . $kolon . "']")) {
                            $ihlaller[] = sprintf('%s:%d  %s', $goreli, $no + 1, trim($satir));
                        }
                    }
                }
            }
        }

        self::assertSame(
            [],
            $ihlaller,
            "K103 İHLALİ (ŞART A) — `lists` paylaşım kolonu DİZİ İLE OKUNUYOR.\n"
            . "Paylaşım artık `shares` tablosunda; okuma `ShareRepository`den geçmeli.\n"
            . "İki kaynak yan yana okunursa ayrışma aylarca fark edilmez:\n  "
            . implode("\n  ", $ihlaller),
        );
    }

    public function testSARTBHICBIRYERDEYAZILMAZ(): void
    {
        $kok = dirname(__DIR__, 2);
        $ihlaller = [];

        foreach (self::TARANAN as $dizin) {
            foreach ($this->phpDosyalari($dizin) as $goreli) {
                if ($goreli === 'app/Core/Migrator.php') {
                    continue;
                }

                $icerik = (string) file_get_contents($kok . '/' . $goreli);
                foreach (explode("\n", $icerik) as $no => $satir) {
                    if ($this->yorumMu($satir)) {
                        continue;
                    }
                    foreach (self::KOLONLAR as $kolon) {
                        // YAZMA: `'share_key_hash' => ...` biçiminde bir atama.
                        // `ListPresenter`ın API anahtarı da bu şekle benziyor;
                        // onu ayırmak için değerin `$paylasim[...]`den geldiği
                        // satırlar muaf tutulur — okuma zaten ŞART A'da sınandı.
                        if (!str_contains($satir, "'" . $kolon . "' =>")) {
                            continue;
                        }
                        if (str_contains($satir, '$paylasim[') || str_contains($satir, '$token')
                            || str_contains($satir, '$expiresAt')) {
                            continue; // API yanıtı / sunum katmanı — DB yazımı değil
                        }
                        $ihlaller[] = sprintf('%s:%d  %s', $goreli, $no + 1, trim($satir));
                    }
                }
            }
        }

        self::assertSame(
            [],
            $ihlaller,
            "K103 İHLALİ (ŞART B) — `lists` paylaşım kolonuna YAZILIYOR.\n"
            . "Yeni link/anahtar üretimi YALNIZ `shares`e yazar:\n  "
            . implode("\n  ", $ihlaller),
        );
    }

    public function testSQLICINDEDEGECMEZ(): void
    {
        // SELECT/UPDATE/INSERT metninde kolon adı geçmesi, dizi okumasından
        // daha sinsi bir kaçış yolu olurdu.
        $kok = dirname(__DIR__, 2);
        $ihlaller = [];

        foreach (self::TARANAN as $dizin) {
            foreach ($this->phpDosyalari($dizin) as $goreli) {
                if ($goreli === 'app/Core/Migrator.php') {
                    continue;
                }
                $icerik = (string) file_get_contents($kok . '/' . $goreli);
                foreach (explode("\n", $icerik) as $no => $satir) {
                    if ($this->yorumMu($satir)) {
                        continue;
                    }
                    $buyuk = strtoupper($satir);
                    $sqlMi = str_contains($buyuk, 'SELECT ') || str_contains($buyuk, 'UPDATE ')
                        || str_contains($buyuk, 'INSERT ') || str_contains($buyuk, 'WHERE ');
                    if (!$sqlMi) {
                        continue;
                    }
                    foreach (self::KOLONLAR as $kolon) {
                        if (str_contains($satir, $kolon)) {
                            $ihlaller[] = sprintf('%s:%d  %s', $goreli, $no + 1, trim($satir));
                        }
                    }
                }
            }
        }

        self::assertSame([], $ihlaller, "K103 İHLALİ — SQL'de `lists` paylaşım kolonu:\n  " . implode("\n  ", $ihlaller));
    }

    public function testGOCMIGRATIONIKOLONLARIHALAOKUYOR(): void
    {
        // Muafiyetin gerekçesi doğrulanır: göç dosyası GERÇEKTEN taşımayı
        // yapıyor. Yapmasaydı muafiyet sessiz bir delik olurdu.
        $goc = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/0038_paylasim_gocu.php');

        self::assertStringContainsString('share_token_hash', $goc);
        self::assertStringContainsString('INSERT INTO shares', $goc);
        self::assertStringContainsString('NOT EXISTS', $goc, 'Göç idempotent olmalı (K23).');
    }

    public function testTARAMAGERCEKTENDOSYABULUYOR(): void
    {
        self::assertGreaterThan(50, count($this->phpDosyalari('app')));
    }
}
