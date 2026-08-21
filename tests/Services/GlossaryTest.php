<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\TranslationCacheRepository;
use App\Services\Translation\Glossary;
use App\Services\Translation\LayeredTranslator;
use App\Services\Translation\ProductNaming;
use App\Services\Translation\TranslationClient;
use App\Services\Translation\TranslationService;
use Psr\Log\NullLogger;
use Tests\Support\AuthTestCase;

/**
 * Yerel sözlük ve katmanlı çevirmen (İE#14 A1/A2 · K56).
 *
 * KRİTİK kurallar: sözlük belirlenimcidir ve AĞA ÇIKMAZ; marka/model/ölçü
 * ASLA çevrilmez; İngilizce kaynaklar (Alibaba/AliExpress/Amazon) aynı hattan
 * geçer; ad ile orijinal başlık aynıysa ikinci satır basılmaz (A1).
 */
final class GlossaryTest extends AuthTestCase
{
    private function glossary(): Glossary
    {
        return new Glossary(dirname(__DIR__, 2) . '/config');
    }

    public function testCinceTerimSozluktenCEVRILIR(): void
    {
        $g = $this->glossary();

        self::assertSame('Paslanmaz çelik', $g->lookup('不锈钢'));
        self::assertSame('Beyaz', $g->lookup('白色'));
        self::assertSame('Marka', $g->lookup('品牌'));
    }

    /** Alibaba/AliExpress/Amazon İngilizce gelir — aynı hat, büyük/küçük harf duyarsız. */
    public function testIngilizceTerimSozluktenCEVRILIR(): void
    {
        $g = $this->glossary();

        self::assertSame('Paslanmaz çelik', $g->lookup('stainless steel'));
        self::assertSame('Beyaz', $g->lookup('White'));
        self::assertSame('Asgari sipariş', $g->lookup('MOQ'));
        self::assertSame('en', Glossary::detect('stainless steel'));
        self::assertSame('zh', Glossary::detect('不锈钢'));
    }

    public function testMarkaModelOlcuCEVRILMEZ(): void
    {
        $g = $this->glossary();

        self::assertFalse($g->translatable('600×500×150 mm'), 'Ölçü korunur.');
        self::assertFalse($g->translatable('350ml'), 'Birimli değer korunur.');
        self::assertFalse($g->translatable('WH-1000XM4'), 'Model kodu korunur.');
        self::assertFalse($g->translatable('155'), 'Salt sayı çeviri adayı değildir.');
        self::assertTrue($g->translatable('不锈钢'));
        self::assertTrue($g->translatable('waterproof'));
    }

    public function testSozlukKAYDEDILIR_veKodEnjeksiyonuOLMAZ(): void
    {
        $gecici = sys_get_temp_dir() . '/tdk-sozluk-' . bin2hex(random_bytes(4));
        mkdir($gecici);
        $g = new Glossary($gecici);

        $g->save(['测试' => "Deneme'; echo 'kod", 'x' => 'Y'], 'zh');

        $yeniden = new Glossary($gecici);
        self::assertSame("Deneme'; echo 'kod", $yeniden->lookup('测试'));
        self::assertStringNotContainsString('echo \'kod\';', (string) file_get_contents($yeniden->path('zh')));

        @unlink($yeniden->path('zh'));
        @rmdir($gecici);
    }

    /** A2: sözlük katmanı ağa ÇIKMADAN yanıt verir — makine çevirmeni hiç çağrılmaz. */
    public function testSozlukKatmani_MAKINEYE_GITMEZ(): void
    {
        $client = new class () implements TranslationClient {
            public int $cagri = 0;

            public function translate(string $text, string $sourceLang, string $targetLang): ?string
            {
                $this->cagri++;

                return 'ASLA';
            }

            public function name(): string
            {
                return 'sahte';
            }
        };

        $service = new TranslationService(
            new TranslationCacheRepository($this->connection),
            $client,
            $this->clock,
            new NullLogger(),
            true,
            'zh',
            'tr',
            $this->glossary(),
        );

        $sonuc = $service->suggest('不锈钢');

        self::assertSame('Paslanmaz çelik', $sonuc['suggestion']);
        self::assertSame('sozluk', $sonuc['source']);
        self::assertSame(0, $client->cagri, 'Sözlükte bulunan terim için ağa çıkılmamalı.');
    }

    /** Katmanlı çevirmen ürünün TAMAMINI çevirir ve her alanın kaynağını bildirir. */
    public function testKatmanliCevirmen_urununTAMAMINI_cevirir(): void
    {
        $client = new class () implements TranslationClient {
            public function translate(string $text, string $sourceLang, string $targetLang): ?string
            {
                return $text === '便携式榨汁机' ? 'Taşınabilir meyve sıkacağı' : null;
            }

            public function name(): string
            {
                return 'sahte';
            }
        };

        $service = new TranslationService(
            new TranslationCacheRepository($this->connection),
            $client,
            $this->clock,
            new NullLogger(),
            true,
            'zh',
            'tr',
            $this->glossary(),
        );
        $translator = new LayeredTranslator($this->glossary(), $service);

        $sonuc = $translator->translateProduct([
            'name' => '便携式榨汁机',
            'attributes' => ['品牌' => 'Sony', '材质' => '不锈钢', '尺寸' => '600×500×150 mm'],
            'variants' => ['白色', '黑色'],
        ]);

        self::assertSame('Taşınabilir meyve sıkacağı', $sonuc['name']);
        self::assertSame('Marka', array_key_first($sonuc['attributes']), 'Öznitelik ADI da çevrilir.');
        self::assertSame('Sony', $sonuc['attributes']['Marka'], 'Marka DEĞERİ çevrilmez.');
        self::assertSame('Paslanmaz çelik', $sonuc['attributes']['Malzeme']);
        self::assertSame('600×500×150 mm', $sonuc['attributes']['Ölçü'], 'Ölçü korunur.');
        self::assertSame(['Beyaz', 'Siyah'], $sonuc['variants']);
        self::assertSame('makine', $sonuc['meta']['sources']['name']);
        self::assertSame('sozluk', $sonuc['meta']['sources']['attributes.材质']);
        self::assertSame('ham', $sonuc['meta']['sources']['attributes.品牌']);
    }

    /** A1: ad ile orijinal başlık aynıysa ikinci satır BASILMAZ. */
    public function testAyniAdIkinciSatirBASILMAZ(): void
    {
        self::assertNull(ProductNaming::originalOf(['name' => 'Termos', 'name_original' => 'Termos']));
        self::assertNull(ProductNaming::originalOf(['name' => 'Termos  Kabı', 'name_original' => 'termos kabı']));
        self::assertNull(ProductNaming::originalOf(['name' => 'Termos', 'name_original' => '']));
        self::assertSame('保温饭盒', ProductNaming::originalOf(['name' => 'Termos', 'name_original' => '保温饭盒']));
    }
}
