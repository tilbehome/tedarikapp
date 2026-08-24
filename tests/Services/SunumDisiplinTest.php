<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Middleware\SecurityHeaders;
use App\Services\ProductDetails;
use App\Services\Share\ProductFacts;
use App\Services\Translation\Glossary;
use App\Services\Translation\ValueSet;
use PHPUnit\Framework\TestCase;

/**
 * İE#17 EK-2 — VERİ SUNUM DİSİPLİNİ (Görev 8 · 9 · 11).
 *
 * Canlı bulgular: 40 varyantlık "Renk" değeri satır hücresini duvara çeviriyor,
 * varyasyon sütunu yer kaplıyor, değerlerde "&gt;" entity artıkları görünüyor,
 * video oynatılmıyor.
 */
final class SunumDisiplinTest extends TestCase
{
    private function values(): ValueSet
    {
        return new ValueSet(new Glossary(dirname(__DIR__, 2) . '/config'));
    }

    /** @param array<string, mixed> $raw */
    private function urun(array $raw, ?string $detail = null): array
    {
        return [
            'category_id' => null,
            'detail' => $detail,
            'raw_attributes' => json_encode($raw, JSON_UNESCAPED_UNICODE),
            'units_per_carton' => null,
            'external_id' => null,
            'platform' => null,
            'video_url' => null,
        ];
    }

    // ── GÖREV 8: uzun değer satıra girmez ───────────────────────────────────

    public function testUZUN_OZNITELIK_SATIR_HUCRESINE_GIRMEZ(): void
    {
        // 40 varyantlık Renk değeri — canlıda satırı boğan gerçek desen.
        $renkler = implode('/', array_map(static fn (int $i): string => '颜色' . $i, range(1, 40)));
        self::assertGreaterThan(ProductDetails::SATIR_ESIGI, mb_strlen($renkler));

        $urun = $this->urun([
            'normalized_attributes' => ['颜色' => $renkler, '材质' => '不锈钢', '品牌' => 'Tilbe'],
        ]);

        $detay = ProductDetails::detay($urun, $this->values());

        self::assertIsString($detay);
        self::assertStringNotContainsString('颜色40', $detay, 'Uzun değer satıra girmemeli.');
        self::assertStringContainsString('Malzeme: Paslanmaz çelik', $detay, 'Kısa alanlar kalmalı.');
        self::assertLessThanOrEqual(
            ProductDetails::SATIR_ESIGI * 4,
            mb_strlen($detay),
            'Satır metni kısa alanların toplamını aşmamalı.',
        );
    }

    public function testUZUN_DEGER_DETAY_PANELINDE_TAM_DURUR(): void
    {
        $renkler = implode('/', array_map(static fn (int $i): string => '颜色' . $i, range(1, 40)));
        $urun = $this->urun(['normalized_attributes' => ['颜色' => $renkler]]);

        $gruplu = ProductFacts::grouped($urun, $this->values());
        $renkSatiri = array_values(array_filter(
            $gruplu['dolu'],
            static fn (array $satir): bool => $satir[0] === 'Renk',
        ));

        self::assertCount(1, $renkSatiri, 'Panelde alan durmalı.');
        self::assertStringContainsString('颜色40', $renkSatiri[0][1], 'Panelde TAM değer görünür.');
    }

    public function testKULLANICI_DETAYI_KIRPILMAZ(): void
    {
        $uzunNot = str_repeat('Kutu logolu olacak, koli etiketi Türkçe. ', 5);
        $detay = ProductDetails::detay($this->urun([], $uzunNot), $this->values());

        self::assertSame(trim($uzunNot), $detay, 'Kullanıcının kendi yazdığı detaya dokunulmaz.');
    }

    // ── GÖREV 8-b: varyasyon hücresi kompakt rozet ──────────────────────────

    public function testCOK_VARYASYONDA_HUCREDE_YALNIZ_SAYI(): void
    {
        $degerler = array_map(static fn (int $i): string => 'Seçenek ' . $i, range(1, 40));

        self::assertSame('40 seçenek', $this->values()->ozet($degerler));
    }

    public function testTEK_KISA_VARYASYONDA_DEGERIN_KENDISI(): void
    {
        self::assertSame('Gri', $this->values()->ozet(['灰色']));
    }

    public function testTEK_AMA_UZUN_VARYASYONDA_YINE_SAYI(): void
    {
        $uzun = str_repeat('çok uzun varyasyon adı ', 4);

        self::assertSame('1 seçenek', $this->values()->ozet([$uzun]));
    }

    // ── GÖREV 9: entity artıkları ───────────────────────────────────────────

    public function testENTITY_ARTIKLARI_TEK_SEFER_COZULUR(): void
    {
        // Canlı belirti: "英文版&gt;1" → görüntüde ">1" olmalı.
        self::assertSame('英文版>1', ValueSet::normalize('英文版&gt;1'));

        // ÇÖZÜM TEK SEFERDİR (şartname G9-a). Çift kodlanmış "&amp;gt;" bir kat
        // çözülür ve "&gt;" olarak kalır — BİLİNÇLİ: döngüyle çözmek, saldırganın
        // katmanlayarak kaçış atlatmasına kapı açan bilinen bir tuzaktır.
        self::assertSame('英文版&gt;1', ValueSet::normalize('英文版&amp;gt;1'));
        self::assertSame('A & B', ValueSet::normalize('A &amp; B'));
        // Görünmez boşluklar da temizlenir (1688 değerleri sık NBSP taşır).
        self::assertSame('Gri Mavi', ValueSet::normalize("Gri\u{00A0} Mavi"));
    }

    public function testDECODE_KACISIN_YERINE_GECMEZ_XSS_REGRESYONU(): void
    {
        // Çözüm sonrası ham metin script içerebilir; ÇIKIŞTA kaçış zorunludur.
        $cozulmus = ValueSet::normalize('&lt;script&gt;alert(1)&lt;/script&gt;');
        self::assertSame('<script>alert(1)</script>', $cozulmus, 'Decode gerçekten çözer…');

        $kacirilmis = htmlspecialchars($cozulmus, ENT_QUOTES, 'UTF-8');
        self::assertStringNotContainsString('<script>', $kacirilmis, '…ama çıkışta zararsızlaşır.');
        self::assertStringContainsString('&lt;script&gt;', $kacirilmis);
    }

    public function testDEGER_CEVIRISI_ENTITY_COZULDUKTEN_SONRA_ESLESIR(): void
    {
        // "&amp;" yüzünden sözlük eşleşmesi kaçmamalı.
        self::assertSame('Paslanmaz çelik', $this->values()->value('&#19981;&#38152;&#38050;'));
    }

    // ── GÖREV 11: CSP media-src ─────────────────────────────────────────────

    public function testVIDEO_HOSTU_YALNIZ_MEDIA_SRC_BESLER(): void
    {
        $csp = $this->cspBasligi(['alicdn.com'], ['cloud.video.taobao.com']);

        preg_match('/img-src ([^;]+);/', $csp, $img);
        preg_match('/media-src ([^;]+);/', $csp, $media);

        self::assertStringNotContainsString('cloud.video.taobao.com', $img[1], 'Görsel beyaz listesi GENİŞLEMEZ.');
        self::assertStringContainsString('https://cloud.video.taobao.com', $media[1]);
        self::assertStringContainsString('https://*.cloud.video.taobao.com', $media[1]);
        // Poster görselleri aynı yerden geldiği için görsel kaynakları media-src'de de durur.
        self::assertStringContainsString('https://alicdn.com', $media[1]);
    }

    public function testVIDEO_HOSTU_YOKSA_CSP_ESKISI_GIBI_DAR(): void
    {
        $csp = $this->cspBasligi(['alicdn.com'], []);

        preg_match('/media-src ([^;]+);/', $csp, $media);
        self::assertStringNotContainsString('taobao', $media[1]);
        self::assertStringContainsString("default-src 'self'", $csp);
    }

    /**
     * @param list<string> $medya
     * @param list<string> $video
     */
    private function cspBasligi(array $medya, array $video): string
    {
        $middleware = new SecurityHeaders($medya, $video);
        $istek = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/p/abc');
        $handler = new class () implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new \Slim\Psr7\Response();
            }
        };

        return $middleware->process($istek, $handler)->getHeaderLine('Content-Security-Policy');
    }
}
