<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Translation\Glossary;
use App\Services\Translation\SozlukFabrikasi;
use PHPUnit\Framework\TestCase;

/**
 * SERTLEŞTİRME v1.2.1 BLOK A6 — SÖZLÜK TEK FABRİKADAN KURULUR (TDR-015).
 *
 * SESSİZ BOZULMA SINIFI. Sözlük iki yerde AYRI AYRI kuruluyordu:
 *
 *   · senkron yol  — `ValueSetFabrikasi`: new Glossary($kok.'/config', $kok.'/storage')
 *   · kuyruk yolu  — `KuyrukIsleyicileri`: new Glossary($kok)
 *
 * İkincisi İKİ HATA birden taşıyordu: `config` eki yoktu (repo KÖKÜNDE
 * `sozluk-zh-tr.php` arıyordu ve öyle bir dosya YOK) ve `storage` hiç
 * verilmemişti (kullanıcının panelden girdiği terimler görünmüyordu).
 *
 * SONUÇ: kuyrukla çevrilen ürünler BOŞ SÖZLÜKLE çevriliyordu. Aynı ürün
 * senkron çevrildiğinde "paslanmaz çelik", toplu çeviriyle çevrildiğinde ham
 * Çince kalıyordu. Hiçbir hata düşmüyordu — sözlükte terim bulunamaması
 * normal bir durumdur. Üstelik `surum()` de farklı çıktığı için iki yol
 * FARKLI önbellek satırlarına yazıyordu; ayrışma kendi kendini besliyordu.
 *
 * Bu test iki şeyi zorlar: (1) tek fabrika var ve iki yol da onu kullanır,
 * (2) yalnız `storage/` üstyazımında olan bir terim iki yolda da aynı sonucu
 * ve aynı önbellek anahtarını verir.
 */
final class SozlukTekFabrikaTest extends TestCase
{
    private string $gecici = '';

    protected function tearDown(): void
    {
        if ($this->gecici !== '' && is_dir($this->gecici)) {
            foreach (glob($this->gecici . '/*/*.php') ?: [] as $dosya) {
                @unlink($dosya);
            }
            foreach (['config', 'storage'] as $alt) {
                @rmdir($this->gecici . '/' . $alt);
            }
            @rmdir($this->gecici);
        }
        parent::tearDown();
    }

    /**
     * Gerçek kurulumun ikizi: `config/` varsayılanı + `storage/` üstyazımı.
     *
     * @return string temel dizin
     */
    private function kurulumKur(): string
    {
        $this->gecici = sys_get_temp_dir() . '/sozluk-' . bin2hex(random_bytes(6));
        mkdir($this->gecici . '/config', 0o777, true);
        mkdir($this->gecici . '/storage', 0o777, true);

        file_put_contents(
            $this->gecici . '/config/sozluk-zh-tr.php',
            "<?php\n\nreturn ['不锈钢' => 'Paslanmaz çelik'];\n",
        );
        // YALNIZ storage'da olan terim: kullanıcının panelden girdiği karşılık.
        file_put_contents(
            $this->gecici . '/storage/sozluk-zh-tr.php',
            "<?php\n\nreturn ['折叠伞' => 'Katlanır şemsiye'];\n",
        );

        return $this->gecici;
    }

    public function testFABRIKAHEMCONFIGHEMSTORAGEOKUR(): void
    {
        $kok = $this->kurulumKur();

        $sozluk = SozlukFabrikasi::kur($kok);

        self::assertSame('Paslanmaz çelik', $sozluk->lookup('不锈钢', 'zh'), 'config varsayılanı okunmalı.');
        self::assertSame(
            'Katlanır şemsiye',
            $sozluk->lookup('折叠伞', 'zh'),
            'storage üstyazımı okunmalı — kuyruk yolu bunu HİÇ görmüyordu.',
        );
    }

    public function testIKIYOLAYNISOZLUGUVEAYNISURUMUALIR(): void
    {
        // Önbellek anahtarı `surum()`e dayanır. İki yol farklı sözlük görürse
        // sürümleri de ayrışır ve aynı ürün için İKİ önbellek satırı doğar.
        $kok = $this->kurulumKur();

        $senkron = SozlukFabrikasi::kur($kok);
        $kuyruk = SozlukFabrikasi::kur($kok);

        self::assertSame($senkron->surum(), $kuyruk->surum(), 'İki yolun sözlük sürümü AYNI olmalı.');
        self::assertSame($senkron->all('zh'), $kuyruk->all('zh'));
    }

    public function testKOKDIZINIDOGRUDANVERILINCESOZLUKBOSKALIR(): void
    {
        // Kusurun kendisi: `new Glossary($kok)` — `config` eki yok.
        // Bu test o çağrının NEDEN sessizce boş döndüğünü belgeler; fabrikaya
        // geçmenin gerekçesi kayıtta kalsın diye durur.
        $kok = $this->kurulumKur();

        $yanlis = new Glossary($kok);

        self::assertSame([], $yanlis->all('zh'), 'Kök dizinde sözlük dosyası yoktur — sessizce boş.');
        self::assertNull($yanlis->lookup('不锈钢', 'zh'), 'Terim bulunamayınca null döner — çağıran ham değeri kullanır.');
        self::assertNotSame(
            SozlukFabrikasi::kur($kok)->surum(),
            $yanlis->surum(),
            'Boş sözlük FARKLI sürüm üretir — iki yol ayrı önbellek satırlarına yazardı.',
        );
    }

    public function testKUYRUKISLEYICISIFABRIKAYIKULLANIR(): void
    {
        // Kaynak taraması: kuyruk yolu bir daha elle `new Glossary(...)`
        // kurmasın. Elle kurulum yeniden belirse, iki yol yeniden ayrışır ve
        // hata yine sessiz olur.
        $kaynak = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Kuyruk/KuyrukIsleyicileri.php',
        );

        self::assertStringNotContainsString(
            'new Glossary(',
            $kaynak,
            'Kuyruk işleyicisi sözlüğü ELLE kuruyor; SozlukFabrikasi::kur() kullanılmalı.',
        );
        self::assertStringContainsString('SozlukFabrikasi::kur(', $kaynak);
    }

    public function testDEGERKUMESIFABRIKASIDAAYNIFABRIKAYIKULLANIR(): void
    {
        $kaynak = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Translation/ValueSetFabrikasi.php',
        );

        self::assertStringNotContainsString('new Glossary(', $kaynak);
        self::assertStringContainsString('SozlukFabrikasi::kur(', $kaynak);
    }
}
