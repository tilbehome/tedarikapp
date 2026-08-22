<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Share\ShareDownload;
use App\Services\Share\ShareKeyService;
use Tests\Support\AuthTestCase;

/**
 * İE#18 GÖREV 6 — ERİŞİM ANAHTARI KAPISI (K62).
 *
 * Ürün Sahibi kararı: paylaşım sayfası artık "linki bilen görür" DEĞİLDİR.
 *
 * En kritik sözleşme şudur ve ilk test onu sabitler: **anahtar doğrulanmadan
 * dönen yanıtta LİSTE VERİSİ BULUNMAZ.** Kapı görsel bir katman değil, veri
 * sınırıdır — buğulu arka plan yalnız bir imadır, korumanın kendisi sunucudadır.
 */
final class ErisimAnahtariTest extends AuthTestCase
{
    private string $csrf = '';
    private string $token = '';
    private int $listId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listId = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Anahtar Listesi',
            'period' => 'Eylül 2026',
            'supplier_name' => 'Ningbo Homeware',
        ]))['data']['id'];

        $this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'Termos Yemek Kabı',
            'name_original' => '保温饭盒',
            'qty' => 240,
            'price_yuan' => '12.00',
        ]);

        $url = (string) $this->json($this->write('POST', '/api/lists/' . $this->listId . '/share'))['data']['share_url'];
        $this->token = substr($url, strrpos($url, '/') + 1);
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function anahtar(): string
    {
        return (string) $this->json($this->call('GET', '/api/lists/' . $this->listId . '/share-key'))['data']['key'];
    }

    /** Doğru anahtarı gönderip çerezi alır. */
    private function cerezAl(): string
    {
        $yanit = $this->call('POST', '/liste/' . $this->token . '/anahtar', ['anahtar' => $this->anahtar()]);
        self::assertSame(303, $yanit->getStatusCode(), 'Doğru anahtar yönlendirmeli.');
        $setCookie = $yanit->getHeaderLine('Set-Cookie');
        self::assertStringContainsString(ShareKeyService::CEREZ_ADI, $setCookie);
        preg_match('/' . ShareKeyService::CEREZ_ADI . '=([^;]+)/', $setCookie, $m);

        return (string) ($m[1] ?? '');
    }

    // ── EN KRİTİK SÖZLEŞME ──────────────────────────────────────────────────

    public function testANAHTARSIZ_YANITTA_LISTE_VERISI_YOK(): void
    {
        $html = (string) $this->call('GET', '/liste/' . $this->token)->getBody();

        // Kilit ekranı geldi mi?
        self::assertStringContainsString('Erişim anahtarı', $html);
        self::assertStringContainsString('data-anahtar-haneler', $html);

        // VERİ SINIRI: ürün adı, orijinal başlık, fiyat, adet — HİÇBİRİ yok.
        self::assertStringNotContainsString('Termos Yemek Kabı', $html, 'Ürün adı sızmamalı.');
        self::assertStringNotContainsString('保温饭盒', $html, 'Orijinal başlık sızmamalı.');
        self::assertStringNotContainsString('12.00', $html, 'Fiyat sızmamalı.');
        self::assertStringNotContainsString('240', $html, 'Adet sızmamalı.');
        // Toplamlar ve KPI şeridi de yok.
        self::assertStringNotContainsString('GENEL TOPLAM', $html);
        self::assertStringNotContainsString('class="kpis"', $html);

        // Görünmesine izin verilen TEK bilgi: liste adı ve firma adı.
        self::assertStringContainsString('Anahtar Listesi', $html);
        self::assertStringContainsString('Ningbo Homeware', $html);
    }

    public function testDOGRU_ANAHTAR_SAYFAYI_ACAR(): void
    {
        $cerez = $this->cerezAl();

        $html = (string) $this->call(
            'GET',
            '/liste/' . $this->token,
            [],
            [],
            [ShareKeyService::CEREZ_ADI => $cerez],
        )->getBody();

        self::assertStringContainsString('Termos Yemek Kabı', $html, 'Çerezli istekte liste görünür.');
        self::assertStringContainsString('class="kpis"', $html);
        self::assertStringNotContainsString('data-anahtar-haneler', $html, 'Kilit ekranı bir daha gelmez.');
    }

    public function testYANLIS_ANAHTAR_401_ve_IPUCU_VERMEZ(): void
    {
        $yanit = $this->call('POST', '/liste/' . $this->token . '/anahtar', ['anahtar' => 'ZZZZZZ']);

        self::assertSame(401, $yanit->getStatusCode());
        $html = (string) $yanit->getBody();
        self::assertStringContainsString('Anahtar hatalı', $html);
        // Kaç deneme kaldığı SÖYLENMEZ; liste verisi de yok.
        self::assertStringNotContainsString('deneme', mb_strtolower($html));
        self::assertStringNotContainsString('Termos Yemek Kabı', $html);
        self::assertSame('', $yanit->getHeaderLine('Set-Cookie'), 'Yanlış anahtarda çerez yazılmaz.');
    }

    public function testANAHTAR_KUCUK_HARFLE_DE_KABUL_EDILIR(): void
    {
        $kucuk = mb_strtolower($this->anahtar(), 'UTF-8');
        $yanit = $this->call('POST', '/liste/' . $this->token . '/anahtar', ['anahtar' => $kucuk]);

        self::assertSame(303, $yanit->getStatusCode(), 'Küçük harf reddedilmemeli (gereksiz sürtünme).');
    }

    // ── ÇEREZ KAPSAMI VE ÖMRÜ ───────────────────────────────────────────────

    public function testCEREZ_BASKA_TOKENDE_GECERSIZ(): void
    {
        $cerez = $this->cerezAl();

        // İkinci liste + kendi paylaşım linki.
        $ikinciId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Başka Liste']))['data']['id'];
        $this->write('POST', '/api/lists/' . $ikinciId . '/products', [
            'name' => 'Gizli kalması gereken ürün',
            'qty' => 1,
            'price_yuan' => '1.00',
        ]);
        $url = (string) $this->json($this->write('POST', '/api/lists/' . $ikinciId . '/share'))['data']['share_url'];
        $ikinciToken = substr($url, strrpos($url, '/') + 1);

        $html = (string) $this->call(
            'GET',
            '/liste/' . $ikinciToken,
            [],
            [],
            [ShareKeyService::CEREZ_ADI => $cerez],
        )->getBody();

        self::assertStringNotContainsString('Gizli kalması gereken ürün', $html, 'Çerez YALNIZ kendi tokenında geçerli.');
        self::assertStringContainsString('data-anahtar-haneler', $html);
    }

    public function testANAHTAR_YENILENINCE_ESKI_CEREZ_OLUR(): void
    {
        $cerez = $this->cerezAl();

        // Panelden yenile — eski anahtar ve eski çerez ANINDA geçersiz.
        $this->write('POST', '/api/lists/' . $this->listId . '/share-key');

        $html = (string) $this->call(
            'GET',
            '/liste/' . $this->token,
            [],
            [],
            [ShareKeyService::CEREZ_ADI => $cerez],
        )->getBody();

        self::assertStringContainsString('data-anahtar-haneler', $html, 'Yenileme eski çerezi öldürmeli.');
        self::assertStringNotContainsString('Termos Yemek Kabı', $html);
    }

    public function testKURCALANMIS_CEREZ_KABUL_EDILMEZ(): void
    {
        $cerez = $this->cerezAl();
        $bozuk = preg_replace('/\.(.*)$/', '.' . str_repeat('x', 32), $cerez) ?? '';

        $html = (string) $this->call(
            'GET',
            '/liste/' . $this->token,
            [],
            [],
            [ShareKeyService::CEREZ_ADI => $bozuk],
        )->getBody();

        self::assertStringContainsString('data-anahtar-haneler', $html);
    }

    // ── KAPI TUTARLILIĞI: EXPORT'TAN DOLAŞILAMAZ (G6-e) ─────────────────────

    public function testEXPORT_UCLARI_CEREZSIZ_404(): void
    {
        $downloads = new ShareDownload((string) $this->config()->get('APP_KEY', ''));
        $exp = $this->clock->now()->getTimestamp() + ShareDownload::OMUR_SANIYE;
        $imzali = '/liste/' . $this->token . '/export?format=csv&lang=tr&exp=' . $exp
            . '&sig=' . $downloads->imza($this->token, 'csv', 'tr', $exp);

        // İmza GEÇERLİ ama anahtar çerezi YOK → kapı kapalı.
        self::assertSame(404, $this->call('GET', $imzali)->getStatusCode(), 'İndirme kapıdan dolaşamaz.');
        self::assertSame(
            404,
            $this->call('GET', '/liste/' . $this->token . '/export-link?format=csv&lang=tr')->getStatusCode(),
        );
        self::assertSame(404, $this->call('GET', '/liste/' . $this->token . '/qr.png')->getStatusCode());

        // Çerezle aynı istekler çalışır.
        $cerez = [ShareKeyService::CEREZ_ADI => $this->cerezAl()];
        self::assertSame(200, $this->call('GET', $imzali, [], [], $cerez)->getStatusCode());
        self::assertSame(
            200,
            $this->call('GET', '/liste/' . $this->token . '/export-link?format=csv&lang=tr', [], [], $cerez)->getStatusCode(),
        );
        self::assertSame(200, $this->call('GET', '/liste/' . $this->token . '/qr.png', [], [], $cerez)->getStatusCode());
    }

    // ── KAPALI MOD: eski davranış ───────────────────────────────────────────

    public function testKAPI_KAPALIYSA_TOKEN_YETER(): void
    {
        $this->write('PATCH', '/api/lists/' . $this->listId . '/share-key', ['enabled' => false]);

        $html = (string) $this->call('GET', '/liste/' . $this->token)->getBody();

        self::assertStringContainsString('Termos Yemek Kabı', $html, 'Kapalı modda eski davranış: token yeter.');
        self::assertStringNotContainsString('data-anahtar-haneler', $html);
    }

    // ── HIZ SINIRI ──────────────────────────────────────────────────────────

    public function testDAKIKADA_BES_DENEMEDEN_SONRA_SABIT_404(): void
    {
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(
                401,
                $this->call('POST', '/liste/' . $this->token . '/anahtar', ['anahtar' => 'AAAAAA'])->getStatusCode(),
            );
        }

        // Altıncı deneme: K51 dili — sabit 404, "kaç deneme kaldı" bilgisi YOK.
        $yanit = $this->call('POST', '/liste/' . $this->token . '/anahtar', ['anahtar' => 'AAAAAA']);
        self::assertSame(404, $yanit->getStatusCode());
        self::assertStringNotContainsString('deneme', mb_strtolower((string) $yanit->getBody()));
    }

    // ── ESKİ ÖN EK ALIAS'I (G5) ─────────────────────────────────────────────

    public function testESKI_P_ONEKI_AYNI_KAPIYI_UYGULAR(): void
    {
        $html = (string) $this->call('GET', '/p/' . $this->token)->getBody();

        self::assertStringContainsString('data-anahtar-haneler', $html, '/p/ alias da anahtar sorar.');
        self::assertStringNotContainsString('Termos Yemek Kabı', $html);

        // /p/ üzerinden doğrulama da çalışır (alias tam eşdeğer).
        self::assertSame(
            303,
            $this->call('POST', '/p/' . $this->token . '/anahtar', ['anahtar' => $this->anahtar()])->getStatusCode(),
        );
    }

    // ── PANEL UCU ───────────────────────────────────────────────────────────

    public function testPANEL_ANAHTARI_GOSTERIR_ve_YENILER(): void
    {
        $ilk = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/share-key'))['data'];
        self::assertMatchesRegularExpression('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/', $ilk['key']);
        self::assertTrue($ilk['enabled']);
        // Karışan karakterler alfabede YOK.
        self::assertDoesNotMatchRegularExpression('/[01OI]/', $ilk['key']);

        $yeni = $this->json($this->write('POST', '/api/lists/' . $this->listId . '/share-key'))['data'];
        self::assertNotSame($ilk['key'], $yeni['key'], 'Yenileme farklı anahtar üretmeli.');
    }
}
