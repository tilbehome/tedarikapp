<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * Paylaşım sayfası v4 (İE#13 F4) — şartname: docs/sablon/paylasim-v4-premium.html.
 *
 * KRİTİK kurallar: dış istek YOK (Google Fonts dahil), satır içi script/stil YOK
 * (K51 CSP), iç kopya verisi (hedef satış/kâr) SAYFADA GÖRÜNMEZ, iptal edilen ürün
 * basılmaz, her değer escape edilir.
 */
final class SharePageV4Test extends AuthTestCase
{
    private string $csrf = '';
    private string $shareUrl = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $listId = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Paylaşım <script>alert(1)</script>',
            'period' => 'Eylül 2026',
        ]))['data']['id'];

        $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Termos Yemek Kabı',
            'name_original' => '双层不锈钢保温饭盒500ml',
            'qty' => 240,
            'price_yuan' => '12.00',
            'price_target_try' => '999.00',
            'note' => 'Kutu logolu olacak',
            'url' => 'https://detail.1688.com/offer/833438962156.html',
            // İE#14 A6: iki alan DOLU, kalanı boş — katlama davranışı böyle sınanır.
            'platform' => '1688',
            'external_id' => '833438962156',
            'units_per_carton' => 20,
        ]);
        $iptal = (int) $this->json($this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'İptal edilen ürün',
            'qty' => 5,
            'price_yuan' => '1.00',
        ]))['data']['id'];
        $this->write('PATCH', '/api/products/' . $iptal . '/status', ['status' => 'cancelled']);

        $this->shareUrl = (string) $this->json(
            $this->write('POST', '/api/lists/' . $listId . '/share'),
        )['data']['share_url'];
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function sayfa(): string
    {
        $token = substr($this->shareUrl, strrpos($this->shareUrl, '/') + 1);
        $response = $this->call('GET', '/p/' . $token);
        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    public function testPremiumIskeletVeAracCubugu(): void
    {
        $html = $this->sayfa();

        foreach (['class="hero"', 'class="tools"', 'class="kpis"', 'class="twrap"', 'class="tot"', 'class="legal"'] as $parca) {
            self::assertStringContainsString($parca, $html, $parca . ' bölümü şartnamede var.');
        }
        self::assertStringContainsString('data-yazdir', $html);
        // İE#15 C1: tek WhatsApp düğmesi yerine ÇOK KANALLI paylaş menüsü.
        self::assertStringContainsString('data-paylas-menu', $html);
        self::assertStringContainsString('data-kanal="whatsapp"', $html);
        self::assertStringContainsString('data-kopyala', $html);
    }

    public function testUcDilliBasliklarVeSutunSeti(): void
    {
        $html = $this->sayfa();

        foreach (['ÜRÜN ADI', '产品名称', 'Product name', 'VARYASYON', '规格', 'DDP ₺', '含税'] as $baslik) {
            self::assertStringContainsString($baslik, $html);
        }
    }

    public function testDISARIYA_ISTEK_YOK_ve_SATIRICI_SCRIPT_YOK(): void
    {
        $html = $this->sayfa();

        self::assertStringNotContainsString('fonts.googleapis.com', $html, 'Google Fonts DIŞ İSTEĞİ olmamalı.');
        self::assertStringNotContainsString('fonts.gstatic.com', $html);
        self::assertStringNotContainsString('onclick=', $html, 'K51: satır içi olay işleyicisi yok.');
        self::assertStringNotContainsString('<style', $html, 'K51: satır içi stil yok.');
        self::assertStringContainsString('<script src="/p-share.js" defer></script>', $html);
        self::assertStringContainsString('href="/p-style.css"', $html);
    }

    public function testIcKopyaVerisiSAYFADA_GORUNMEZ(): void
    {
        $html = $this->sayfa();

        self::assertStringNotContainsString('999.00', $html, 'F5: hedef satış paylaşım sayfasına GİRMEZ.');
        self::assertStringNotContainsString('Hedef Satış', $html);
        self::assertStringNotContainsString('Kâr', $html);
    }

    public function testIptalEdilenUrunBasilmaz_ve_XSS_kacirilir(): void
    {
        $html = $this->sayfa();

        self::assertStringNotContainsString('İptal edilen ürün', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testDetayPaneliBilgiIzgarasiVeCinceBaslik(): void
    {
        $html = $this->sayfa();

        self::assertStringContainsString('ÜRÜN BİLGİLERİ', $html);
        self::assertStringContainsString('双层不锈钢保温饭盒500ml', $html, 'Orijinal Çince başlık görünür.');
        self::assertStringContainsString('Not: Kutu logolu olacak', $html);
        // TEDARİK PUANI verisi yok → bölüm hiç basılmaz (V3-A'ya kadar).
        self::assertStringNotContainsString('TEDARİK PUANI', $html);
    }

    /**
     * İE#14 A6 — DOLU ALANLAR ÜSTTE, boşlar katlamanın içinde.
     *
     * Eski davranış: 17 alan sırayla basılıyor, yarısı "—" oluyordu; göz dolu
     * bilgiyi bulamıyordu. Yeni kural: dolu alanlar ızgarada, boşlar
     * "Eksik bilgileri göster (N)" katlamasında — ve katlama SATIR İÇİ SCRIPT
     * kullanmadan (<details>) açılır, CSP korunur (K51).
     */
    public function testEksikAlanlarKatlamaninIcinde(): void
    {
        $html = $this->sayfa();

        self::assertMatchesRegularExpression(
            '/Eksik bilgileri göster \((\d+)\)/u',
            $html,
            'Boş alanlar sayıyla katlanmalı.',
        );
        self::assertStringContainsString('<details class="eks">', $html);
        self::assertStringNotContainsString('onclick', $html, 'Katlama satır içi script kullanmaz (K51).');

        // Dolu alan ızgarada, boş alan katlamanın İÇİNDE olmalı.
        $katlamaBasi = strpos($html, 'Eksik bilgileri göster');
        $koliIci = strpos($html, 'Koli içi');
        self::assertIsInt($katlamaBasi);
        self::assertIsInt($koliIci);
        self::assertLessThan($katlamaBasi, $koliIci, 'Dolu alan katlamadan ÖNCE basılmalı.');
        self::assertGreaterThan($katlamaBasi, (int) strpos($html, 'Garanti'), 'Boş alan katlamanın içinde.');
    }

    /**
     * İE#14 A4 — veri yoksa alan HİÇ BASILMAZ: "Kategorisiz" damgası kalktı.
     */
    public function testKategorisizYazisiBasilmaz(): void
    {
        self::assertStringNotContainsString('Kategorisiz', $this->sayfa());
    }

    /**
     * İE#14 B2 — YAZDIRMA REGRESYONU: canlıda sağdaki DDP sütunları kâğıt dışında
     * kalıyordu. Baskı bloğunda sabit yerleşim ve yüzde genişlikler DURMALI.
     */
    public function testYazdirmaBloguTasmayiOnleyenKurallariIcerir(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/p-style.css');

        self::assertStringContainsString('@page { size: A4 landscape', $css, 'Yatay A4 korunmalı.');
        self::assertStringContainsString('table-layout: fixed !important', $css);
        self::assertStringContainsString('min-width: 0 !important', $css, 'min-width baskıda iptal edilmeli.');
        self::assertStringContainsString('overflow-wrap: anywhere', $css);
        self::assertStringContainsString('zoom: 1 !important', $css, 'Baskıda ölçekleme uygulanmamalı.');

        // Sütun yüzdeleri: toplam 100 (şartnamedeki sütun sırası).
        $baskiBlogu = substr($css, (int) strrpos($css, '@media print'));
        preg_match_all('/nth-child\((\d+)\)\s*{\s*width:\s*(\d+)%/', $baskiBlogu, $eslesmeler);
        $yuzdeler = array_map('intval', $eslesmeler[2]);
        self::assertCount(13, $yuzdeler, '13 veri sütununun her biri genişlik almalı.');
        self::assertSame(100, array_sum($yuzdeler), 'Yüzdeler toplamı 100 olmalı.');

        // Ekran düzeni baskıda TETİKLENMEZ: mobil sorgular "screen and" kilitli.
        self::assertStringNotContainsString('@media (max-width:940px)', $css);
        self::assertStringContainsString('@media screen and (max-width:940px)', $css);
    }

    public function testGirissizGoruntuleyendeExcelPdfDugmesiYOK(): void
    {
        $html = $this->sayfa();

        self::assertStringNotContainsString('data-export="xlsx"', $html, 'Export uçları oturum ister — düğme basılmaz.');
    }
}
