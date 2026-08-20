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
        self::assertStringContainsString('data-whatsapp', $html);
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
        self::assertStringContainsString('class="yok">—<', $html, 'Veri olmayan alan — ile basılır.');
        self::assertStringContainsString('Not: Kutu logolu olacak', $html);
        // TEDARİK PUANI verisi yok → bölüm hiç basılmaz (V3-A'ya kadar).
        self::assertStringNotContainsString('TEDARİK PUANI', $html);
    }

    public function testGirissizGoruntuleyendeExcelPdfDugmesiYOK(): void
    {
        $html = $this->sayfa();

        self::assertStringNotContainsString('data-export="xlsx"', $html, 'Export uçları oturum ister — düğme basılmaz.');
    }
}
