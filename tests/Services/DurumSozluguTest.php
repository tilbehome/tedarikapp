<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\DurumSozlugu;
use App\Services\Export\TemplateV2;
use App\Services\StateMachine;
use PHPUnit\Framework\TestCase;

/**
 * DURUM ETİKETLERİ TEK KAYNAK (İE#21 B13 · B8-2).
 *
 * Aynı durumun Türkçesi dört ayrı yerde yazılıydı: Excel/PDF şablonu, paylaşım
 * sayfası, panel sözlüğü ve marka kitindeki status-map. Dördü aynı anda
 * güncellenmediği sürece belge ile ekran birbirini yalanlar.
 *
 * Bu testler tek kaynağı ZORLAR: sözlük dosyası durum makinesiyle örtüşmeli,
 * panelin ve marka kitinin kopyaları ondan SAPMAMALIDIR. Sapma olursa kırmızı
 * yanar — yani iki liste var ama ayrı düşmeleri mümkün değil.
 */
final class DurumSozluguTest extends TestCase
{
    private string $kok;
    private DurumSozlugu $sozluk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kok = dirname(__DIR__, 2);
        DurumSozlugu::onbellegiTemizle();
        $this->sozluk = new DurumSozlugu($this->kok);
    }

    public function testURUNDURUMLARI5BILEBIREBIR(): void
    {
        // docs/04 §5B: Verilecek → Verildi → Yolda → Geldi (+İptal).
        self::assertSame(
            [
                StateMachine::PRODUCT_TO_ORDER,
                'ordered',
                'in_transit',
                'received',
                'cancelled',
            ],
            $this->sozluk->kodlar(DurumSozlugu::URUN),
        );
    }

    public function testLISTEDURUMLARI5BILEBIREBIR(): void
    {
        self::assertSame(
            ['draft', 'sent', 'ordered', 'completed', 'cancelled'],
            $this->sozluk->kodlar(DurumSozlugu::LISTE),
        );
    }

    public function testUCDILDEDEETIKETVAR(): void
    {
        foreach ([DurumSozlugu::URUN, DurumSozlugu::LISTE] as $kume) {
            foreach ($this->sozluk->kodlar($kume) as $kod) {
                foreach (['tr', 'en', 'zh'] as $dil) {
                    $etiket = $this->sozluk->etiket($kume, $kod, $dil);
                    self::assertNotSame($kod, $etiket, sprintf('%s/%s (%s) etiketsiz', $kume, $kod, $dil));
                }
            }
        }
    }

    public function testPANELSOZLUGUSAPMAZ(): void
    {
        $ts = (string) file_get_contents($this->kok . '/frontend/src/locales/tr.ts');

        foreach ($this->sozluk->kodlar(DurumSozlugu::URUN) as $kod) {
            $beklenen = $this->sozluk->etiket(DurumSozlugu::URUN, $kod);
            self::assertMatchesRegularExpression(
                '/' . preg_quote($kod, '/') . ":\s*'" . preg_quote($beklenen, '/') . "'/u",
                $ts,
                sprintf('Panel sözlüğünde ürün durumu "%s" → "%s" olmalı.', $kod, $beklenen),
            );
        }
    }

    public function testPANELLISTESOZLUGUSAPMAZ(): void
    {
        $ts = (string) file_get_contents($this->kok . '/frontend/src/locales/tr.ts');
        $listeBlogu = substr($ts, (int) strpos($ts, 'listStatusLabels'));

        foreach ($this->sozluk->kodlar(DurumSozlugu::LISTE) as $kod) {
            $beklenen = $this->sozluk->etiket(DurumSozlugu::LISTE, $kod);
            self::assertMatchesRegularExpression(
                '/' . preg_quote($kod, '/') . ":\s*'" . preg_quote($beklenen, '/') . "'/u",
                $listeBlogu,
                sprintf('Panel sözlüğünde liste durumu "%s" → "%s" olmalı.', $kod, $beklenen),
            );
        }
    }

    public function testMARKAKITISTATUSMAPESITLENMIS(): void
    {
        // B13: marka kitindeki harita 5B ile TEK KAYNAĞA eşitlenir (5B kazanır).
        $yol = $this->kok . '/docs/marka/design-system/status-map.json';
        self::assertFileExists($yol);

        /** @var array<string, mixed> $harita */
        $harita = json_decode((string) file_get_contents($yol), true, 512, JSON_THROW_ON_ERROR);

        foreach ($this->sozluk->kodlar(DurumSozlugu::URUN) as $kod) {
            self::assertArrayHasKey(
                $kod,
                $harita,
                sprintf('Marka kiti haritasında ürün durumu "%s" yok — 5B ile eşitlenmemiş.', $kod),
            );
            self::assertSame(
                $this->sozluk->etiket(DurumSozlugu::URUN, $kod),
                (string) ($harita[$kod]['tr'] ?? ''),
                sprintf('"%s" durumunun Türkçesi marka kitinde farklı.', $kod),
            );
        }
    }

    public function testBELGEROZETISOZLUKTENGELIR(): void
    {
        TemplateV2::sozlukBagla($this->kok);

        [$metin, $arka, $on] = TemplateV2::badge('to_order');

        // Canlı kusur: belgede "Bekleme Listesinde" yazıyordu, panelde "Verilecek".
        self::assertSame('● Verilecek', $metin);
        self::assertMatchesRegularExpression('/^[0-9A-Fa-f]{6}$/', $arka);
        self::assertMatchesRegularExpression('/^[0-9A-Fa-f]{6}$/', $on);
    }

    public function testBELGEROZETIBELGEDILINDEBASILIR(): void
    {
        TemplateV2::sozlukBagla($this->kok);

        self::assertSame('● Received', TemplateV2::badge('received', 'en')[0]);
        self::assertSame('● 已收货', TemplateV2::badge('received', 'zh')[0]);
    }

    public function testBILINMEYENKODUYDURULMAZ(): void
    {
        // Ham kod görünürse eksik eşleme fark edilir; uydurma Türkçe gizler.
        self::assertSame('uydurma_kod', $this->sozluk->etiket(DurumSozlugu::URUN, 'uydurma_kod'));
    }
}
