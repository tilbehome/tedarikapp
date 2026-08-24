<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\ProductDetails;
use App\Services\Share\ProductFacts;
use App\Services\Translation\Glossary;
use App\Services\Translation\ValueSet;
use PHPUnit\Framework\TestCase;

/**
 * İE#14 A3/A4 — değer çevirisi, kırıntı yolundan kategori, türetilmiş detay.
 *
 * Kural: veri YOKSA null döner ve çağıran hücreyi BOŞ bırakır — "Kategorisiz"
 * damgası ve boş "Detaylar" satırı basılmaz.
 */
final class ProductDetailsTest extends TestCase
{
    private function values(): ValueSet
    {
        return new ValueSet(new Glossary(dirname(__DIR__, 2) . '/config'));
    }

    /** @param array<string, mixed> $raw */
    private function urun(array $raw, ?int $categoryId = null, ?string $detail = null): array
    {
        return [
            'category_id' => $categoryId,
            'detail' => $detail,
            'raw_attributes' => json_encode($raw, JSON_UNESCAPED_UNICODE),
            'units_per_carton' => null,
            'external_id' => null,
            'platform' => null,
            'video_url' => null,
        ];
    }

    public function testKategoriPaneldekiAtamadanGelir(): void
    {
        $kategori = ProductDetails::kategori($this->urun([], 7), [7 => 'Mutfak'], $this->values());

        self::assertSame('Mutfak', $kategori);
    }

    public function testKategoriYokSaKirintiYolundanTuretilir(): void
    {
        $urun = $this->urun(['breadcrumb' => ['首页', '家居', '厨房用品', '保温杯']]);

        self::assertSame('保温杯', ProductDetails::kategori($urun, [], null), 'Son adım kategoridir.');
        self::assertSame(
            ['家居', '厨房用品', '保温杯'],
            ProductDetails::kirintiYolu($urun),
            'Kök adım (首页) atılır.',
        );
    }

    public function testKirintiYoluMetinOlarakDaGelebilir(): void
    {
        $urun = $this->urun(['category_path' => '家居 > 厨房用品 > 收纳']);

        self::assertSame(['家居', '厨房用品', '收纳'], ProductDetails::kirintiYolu($urun));
    }

    public function testVeriYoksaKategoriNULL_kategorisizYAZILMAZ(): void
    {
        self::assertNull(ProductDetails::kategori($this->urun([]), [], $this->values()));
    }

    public function testDetayYoksaOzniteliklerdenTuretilir(): void
    {
        $urun = $this->urun([
            'normalized_attributes' => ['材质' => '不锈钢', '颜色' => '灰色', '产地' => '中国'],
        ]);

        $detay = ProductDetails::detay($urun, $this->values());

        self::assertIsString($detay);
        self::assertStringContainsString('Malzeme: Paslanmaz çelik', $detay, 'Değer sözlükten geçer (A3).');
        self::assertStringContainsString('Renk: Gri', $detay);
    }

    public function testMevcutDetayKORUNUR_uzerineYAZILMAZ(): void
    {
        $urun = $this->urun(['normalized_attributes' => ['材质' => '不锈钢']], null, 'Kapak contası çift olacak');

        self::assertSame('Kapak contası çift olacak', ProductDetails::detay($urun, $this->values()));
    }

    public function testHicVeriYoksaDetayNULL(): void
    {
        self::assertNull(ProductDetails::detay($this->urun([]), $this->values()));
    }

    /**
     * SÖZLEŞME DEĞİŞTİ (İE#17 G8-b): satır hücresinde artık "ilk 3 + … (N seçenek)"
     * YOKTUR, yalnız KOMPAKT ROZET vardır. Gerekçe: 40 varyantlı üründe üç değer
     * bile satırı şişiriyordu; tam liste detay panelindedir.
     */
    public function testUzunListeKOMPAKT_ROZETLE_OZETLENIR(): void
    {
        $ozet = $this->values()->ozet(['灰色', '蓝色', '黑色', '红色', '白色']);

        self::assertSame('5 seçenek', $ozet);
    }

    public function testIKI_DEGERDE_DE_SAYI_BASILIR(): void
    {
        self::assertSame('2 seçenek', $this->values()->ozet(['灰色', '蓝色']));
    }

    /** Tek ve kısa değer varsa sayı yerine DEĞERİN KENDİSİ daha bilgilendiricidir. */
    public function testTEK_KISA_DEGERDE_DEGERIN_KENDISI(): void
    {
        self::assertSame('Gri', $this->values()->ozet(['灰色']));
    }

    public function testEtiketliVeBirlesikDegerlerParcaParcaCevrilir(): void
    {
        $values = $this->values();

        self::assertSame('Renk: Gri', $values->value('颜色: 灰色'));
        self::assertSame('Gri / Paslanmaz çelik', $values->value('灰色 / 不锈钢'));
        // Sözlükte olmayan değer HAM kalır — veri kaybolmaz.
        self::assertSame('特殊定制款', $values->value('特殊定制款'));
    }

    /** A6 — dolu alanlar ayrı, boşlar ayrı; hepsi boşsa dolu küme BOŞTUR. */
    public function testGroupedDoluVeBosAlanlariAyirir(): void
    {
        $urun = $this->urun(['normalized_attributes' => ['材质' => '不锈钢']]);
        $gruplu = ProductFacts::grouped($urun, $this->values());

        self::assertNotSame([], $gruplu['dolu']);
        // İE#21 EK-5 (K81): etiket artık TEK DİLDE döner — [etiket, değer].
        self::assertSame('Malzeme', $gruplu['dolu'][0][0]);
        self::assertSame('Paslanmaz çelik', $gruplu['dolu'][0][1]);
        self::assertGreaterThan(5, count($gruplu['bos']), 'Doldurulmayan alanlar boş kümede.');

        $bosUrun = ProductFacts::grouped($this->urun([]), $this->values());
        self::assertSame([], $bosUrun['dolu'], 'Hiç veri yoksa bölüm basılmaz.');
    }

    /** A5 ek düzeltme: koli içi SAYIDIR, "anlamsız kısa sayı" elemesine takılmaz. */
    public function testKoliIciSayisiGizlenmez(): void
    {
        $urun = $this->urun([]);
        $urun['units_per_carton'] = 20;

        $degerler = [];
        foreach (ProductFacts::build($urun) as [$etiket, $deger]) {
            $degerler[$etiket] = $deger;
        }

        self::assertSame('20', $degerler['Koli içi']);
    }
}
