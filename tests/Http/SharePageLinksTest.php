<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * İE#15 C1/C2/C4 · D1/D2 · E3 · F1/F2 — paylaşım sayfasının dış yüzü.
 *
 * En kritik sözleşme D1'dir: sayfadan dış siteye çıkan TEK öğe "Ürüne git"
 * düğmesidir. Ürün adı ve görsel artık kaynak siteye götürmez — firma yanlışlıkla
 * 1688'e düşmesin, çıkış bilinçli ve tek noktadan olsun.
 */
final class SharePageLinksTest extends AuthTestCase
{
    private string $csrf = '';
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $listId = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Paylaşım kanalları',
            'period' => 'Eylül 2026',
        ]))['data']['id'];

        $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Termos Yemek Kabı',
            'name_original' => '双层不锈钢保温饭盒500ml',
            'qty' => 240,
            'price_yuan' => '12.00',
            'price_target_try' => '1499.00',
            'url' => 'https://detail.1688.com/offer/833438962156.html',
        ]);

        $url = (string) $this->json($this->write('POST', '/api/lists/' . $listId . '/share'))['data']['share_url'];
        $this->token = substr($url, strrpos($url, '/') + 1);
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function sayfa(string $sorgu = ''): string
    {
        $response = $this->call('GET', '/p/' . $this->token . $sorgu);
        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    /**
     * D1 — SAYFADAKİ TEK DIŞ BAĞLANTI "Ürüne git"tir.
     *
     * Bu test, sayfadaki TÜM `href` değerlerini toplar; http(s) ile başlayan ve
     * sayfanın kendi kaynağı dışına giden her bağlantı `op-git` sınıfını taşımak
     * ZORUNDADIR. Yeni bir dış bağlantı eklenirse bu test kırılır — kural
     * belgede değil, testte yaşar.
     */
    public function testSayfadaURUNE_GIT_disinda_DIS_BAGLANTI_YOK(): void
    {
        $html = $this->sayfa();

        preg_match_all('/<a\s([^>]*)>/i', $html, $eslesmeler);
        $disBaglantilar = [];
        foreach ($eslesmeler[1] as $nitelikler) {
            if (preg_match('/href="(https?:\/\/[^"]+)"/i', $nitelikler, $href) !== 1) {
                continue; // göreli bağlantı: sayfanın kendi içinde
            }
            if (str_contains($nitelikler, 'op-git')) {
                continue; // izin verilen TEK çıkış
            }
            $disBaglantilar[] = $href[1];
        }

        self::assertSame([], $disBaglantilar, 'Sayfada "Ürüne git" dışında dış bağlantı olmamalı.');
    }

    public function testURUN_ADI_ARTIK_KOPRU_DEGIL(): void
    {
        $html = $this->sayfa();

        self::assertStringContainsString('<span class="pn">Termos Yemek Kabı</span>', $html);
        self::assertStringNotContainsString('<a class="pn"', $html, 'Ürün adı düz metin olmalı.');
    }

    /** D2 — tek çıkış noktası: yeni sekme + noopener/noreferrer/nofollow. */
    public function testURUNE_GIT_GUVENLI_NITELIKLERLE_ACILIR(): void
    {
        $html = $this->sayfa();

        self::assertMatchesRegularExpression(
            '/<a class="op-git" href="https:\/\/detail\.1688\.com[^"]*" target="_blank" rel="noopener noreferrer nofollow">/',
            $html,
        );
    }

    /** F1 — çıktı grubu firma tarafında da görünür ve İMZALI bağlantı taşır. */
    public function testCIKTI_GRUBU_IMZALI_BAGLANTILARLA_BASILIR(): void
    {
        $html = $this->sayfa();

        foreach (['xlsx', 'pdf', 'csv'] as $bicim) {
            self::assertMatchesRegularExpression(
                '/href="\/p\/[0-9a-f]{64}\/export\?format=' . $bicim . '&amp;lang=tr&amp;exp=\d+&amp;sig=[A-Za-z0-9_-]{32}"/',
                $html,
                $bicim . ' bağlantısı imzalı olmalı.',
            );
        }
        self::assertStringContainsString('data-yazdir', $html);
    }

    /** C1 — paylaşım kanalları (Çin tarafı dahil). */
    public function testPAYLAS_MENUSU_TUM_KANALLARI_ICERIR(): void
    {
        $html = $this->sayfa();

        foreach (['whatsapp', 'wechat', 'qq', 'dingtalk', 'telegram', 'eposta'] as $kanal) {
            self::assertStringContainsString('data-kanal="' . $kanal . '"', $html, $kanal . ' kanalı olmalı.');
        }
        // WeChat ve DingTalk link şemasıyla açılmaz → QR modalı ile paylaşılır.
        self::assertMatchesRegularExpression('/data-kanal="wechat" data-qr="1"/', $html);
        self::assertMatchesRegularExpression('/data-kanal="dingtalk" data-qr="1"/', $html);
    }

    /** C2/C4 — metinler dil dosyasından; dil seçici bağlantıya ?lang= ekler. */
    public function testPAYLASIM_METNI_DILE_GORE_DEGISIR(): void
    {
        $tr = $this->sayfa();
        self::assertStringContainsString('DDP fiyat teklifinizi', $tr);

        $zh = $this->sayfa('?lang=zh');
        self::assertStringContainsString('采购清单', $zh, 'Çince özet metni.');
        self::assertStringContainsString('请点击链接填写DDP报价', $zh);
        self::assertStringContainsString('lang=zh', $zh, 'Dil seçici bağlantıya ?lang= ekler.');

        $en = $this->sayfa('?lang=en');
        self::assertStringContainsString('DDP quotation', $en);
    }

    /** F2 — link önizlemesinde liste adı + ürün sayısı + dönem; FİYAT YOK. */
    public function testONIZLEME_METASINDA_FIYAT_YOK(): void
    {
        $html = $this->sayfa();

        preg_match_all('/<meta (?:property|name)="(?:og|twitter):description" content="([^"]*)"/', $html, $eslesmeler);
        self::assertNotSame([], $eslesmeler[1], 'Önizleme açıklaması basılmalı.');

        foreach ($eslesmeler[1] as $aciklama) {
            self::assertStringContainsString('1 ürün', $aciklama);
            self::assertStringContainsString('Eylül 2026', $aciklama);
            self::assertDoesNotMatchRegularExpression('/\d+[.,]\d{2}/', $aciklama, 'Önizlemede tutar olmamalı.');
            foreach (['₺', '¥', '$', '2 880', '1499'] as $para) {
                self::assertStringNotContainsString($para, $aciklama, 'Önizlemede para birimi/tutar olmamalı.');
            }
        }
    }

    /** E3 — video rozeti yalnız gerçek veriden doğar; sahte rozet basılmaz. */
    public function testVIDEOSU_OLMAYAN_URUNDE_ROZET_BASILMAZ(): void
    {
        $html = $this->sayfa();

        self::assertStringNotContainsString('data-video=', $html);
        self::assertStringNotContainsString('data-video-yok=', $html);
    }

    /** K51 — yeni bileşenler CSP sözleşmesini bozmadı. */
    public function testYENI_BILESENLER_SATIRICI_SCRIPT_GETIRMEDI(): void
    {
        $html = $this->sayfa();

        self::assertStringNotContainsString('onclick=', $html);
        self::assertStringNotContainsString('<style', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('<script src="/p-share.js" defer></script>', $html);
    }
}
