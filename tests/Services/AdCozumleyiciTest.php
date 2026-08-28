<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\TranslationCacheRepository;
use App\Services\Translation\AdCozumleyici;
use Tests\Support\AuthTestCase;

/**
 * D11b — TAZELENEN ÇEVİRİ EKRANA YANSIR (saha bulgusu, 25 Ağu 2026).
 *
 * BULGU: 20:15 sınavında ürün 1 ve 4'ün TR çevirisi `llm:deepseek` ile
 * "Pedalsız Denge Bisikleti" olmuştu; 20:20'de liste ve çekmece hâlâ eski
 * "Bisiklet Yok…" başlığını basıyordu.
 *
 * KÖK NEDEN: `products.name` YAKALAMA ANINDA donar (CaptureService); çeviri
 * turu ise yalnız `translation_cache`i tazeler (D6). Sınav ile ekran farklı
 * kaynak okuyordu — D5 (popup/panel) ve D9 (sayaç/işçi) ayrışmasının üçüncü
 * tekrarı.
 *
 * SINIR: `products.name` sessizce EZİLMEZ (K54). Sunum katmanı çözer ve
 * kullanıcının elle yazdığı ad hiçbir turla değişmez.
 */
final class AdCozumleyiciTest extends AuthTestCase
{
    private function urun(string $ad, ?string $orijinal, int $elle = 0): array
    {
        return ['name' => $ad, 'name_original' => $orijinal, 'name_elle' => $elle];
    }

    private function cozumleyici(): AdCozumleyici
    {
        return new AdCozumleyici(new TranslationCacheRepository($this->connection));
    }

    private function ceviriYaz(string $orijinal, string $ceviri, string $saglayici): void
    {
        (new TranslationCacheRepository($this->connection))->store(
            TranslationCacheRepository::hash($orijinal, 'zh', 'tr'),
            $orijinal,
            $ceviri,
            $saglayici,
            'zh',
            'tr',
            $this->clock->now(),
        );
    }

    public function testTAZELENENCEVIRIGOSTERILIR(): void
    {
        // Yakalama anındaki (makine) ad ürüne donmuş durumda.
        $urun = $this->urun('Bisiklet Yok Denge', '无脚踏平衡车');
        $this->ceviriYaz('无脚踏平衡车', 'Pedalsız Denge Bisikleti', 'llm:deepseek');

        $sonuc = $this->cozumleyici()->coz($urun);

        self::assertSame('Pedalsız Denge Bisikleti', $sonuc['ad']);
        self::assertSame(AdCozumleyici::KAYNAK_CEVIRI, $sonuc['kaynak']);
        self::assertSame('llm:deepseek', $sonuc['saglayici']);
    }

    public function testELLEYAZILANADCEVIRIYLEDEGISMEZ(): void
    {
        $urun = $this->urun('Denge bisikleti (kırmızı) — ithalat', '无脚踏平衡车', 1);
        $this->ceviriYaz('无脚踏平衡车', 'Pedalsız Denge Bisikleti', 'llm:deepseek');

        $sonuc = $this->cozumleyici()->coz($urun);

        // K54: son söz insanındır.
        self::assertSame('Denge bisikleti (kırmızı) — ithalat', $sonuc['ad']);
        self::assertSame(AdCozumleyici::KAYNAK_ELLE, $sonuc['kaynak']);
    }

    public function testMAKINECEVIRISI_ONERIDIYEIKINCIKEZGOSTERILMEZ(): void
    {
        // `products.name` zaten makine çevirisidir; aynı metni "çeviri önerisi"
        // rozetiyle bir daha göstermek kullanıcıyı yanıltır.
        $urun = $this->urun('Bisiklet Yok', '无脚踏平衡车');
        $this->ceviriYaz('无脚踏平衡车', 'Bisiklet Yok', 'mymemory');

        $sonuc = $this->cozumleyici()->coz($urun);

        self::assertSame('Bisiklet Yok', $sonuc['ad']);
        self::assertSame(AdCozumleyici::KAYNAK_YAKALAMA, $sonuc['kaynak']);
    }

    public function testORIJINALIOLMAYANURUNDEADDEGISMEZ(): void
    {
        $sonuc = $this->cozumleyici()->coz($this->urun('Elle girilen ürün', null));

        self::assertSame('Elle girilen ürün', $sonuc['ad']);
        self::assertSame(AdCozumleyici::KAYNAK_YAKALAMA, $sonuc['kaynak']);
    }

    public function testONAYLIELLECEVIRIDEKULLANILIR(): void
    {
        $urun = $this->urun('Bisiklet Yok', '无脚踏平衡车');
        $this->ceviriYaz('无脚踏平衡车', 'Denge Bisikleti', TranslationCacheRepository::ELLE_SAGLAYICI);

        self::assertSame('Denge Bisikleti', $this->cozumleyici()->coz($urun)['ad']);
    }
}
