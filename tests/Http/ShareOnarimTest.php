<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\AppVersion;
use App\Middleware\Csrf;
use App\Services\Share\ShareDownload;
use Psr\Log\AbstractLogger;
use Tests\Support\AuthTestCase;

/**
 * İE#17 — PAYLAŞIM SAYFASI ONARIM TURU (canlı kusurlar).
 *
 * Görevler: G1 varlık sürümleme · G2 baskı kuralları · G3 girilmemiş fiyat ·
 * G4 taze imza ucu · G6 başarısızlık logu.
 */
final class ShareOnarimTest extends AuthTestCase
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
            'name' => 'Onarım listesi',
            'period' => 'Eylül 2026',
        ]))['data']['id'];

        // DDP'si GİRİLMİŞ ürün.
        $this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'DDP girilmiş ürün',
            'qty' => 10,
            'price_yuan' => '12.00',
            'price_ddp_usd' => '3.50',
        ]);
        // DDP'si GİRİLMEMİŞ ürün — canlıda "$ 0.00" basılan durum.
        $this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'DDP girilmemiş ürün',
            'qty' => 5,
            'price_yuan' => '8.00',
        ]);

        $url = (string) $this->json($this->write('POST', '/api/lists/' . $this->listId . '/share'))['data']['share_url'];
        $this->token = substr($url, strrpos($url, '/') + 1);
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function sayfa(): string
    {
        $response = $this->call('GET', '/p/' . $this->token);
        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    // ── G1: VARLIK SÜRÜMLEME ────────────────────────────────────────────────

    public function testVarlikBaglantilariSURUM_TASIR(): void
    {
        $html = $this->sayfa();
        $surum = AppVersion::VALUE;

        self::assertStringContainsString('/p-style.css?v=' . $surum, $html);
        self::assertStringContainsString('/p-share.js?v=' . $surum, $html);
        // Sürümsüz bağlantı KALMAMALI — biri unutulursa bayat önbellek geri gelir.
        self::assertStringNotContainsString('"/p-style.css"', $html);
        self::assertStringNotContainsString('src="/p-share.js"', $html);
    }

    public function testGecersizLinkSayfasiDA_SURUMLU(): void
    {
        $govde = (string) $this->call('GET', '/p/' . str_repeat('a', 64))->getBody();

        self::assertStringContainsString('/p-style.css?v=' . AppVersion::VALUE, $govde);
    }

    // ── G2: BASKI KURALLARI GERÇEKTEN @media print İÇİNDE ───────────────────

    public function testBaskiKurallariPRINT_BLOGUNUN_ICINDE(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/p-style.css');

        // Süslü parantezler dengeli (kapatılmamış kural bırakılmamış).
        self::assertSame(
            substr_count($css, '{'),
            substr_count($css, '}'),
            'Kapatılmamış kural bloğu var — kurallar yanlış yuvalanır.',
        );

        $gizleme = strrpos($css, '.pmenu, .qrm, .ynot, .lbx, .tools');
        self::assertIsInt($gizleme, 'Baskıda gizleme kuralı bulunmalı.');

        $sonPrint = strrpos($css, '@media print');
        self::assertIsInt($sonPrint);
        self::assertLessThan($gizleme, $sonPrint, 'Gizleme kuralı bir @media print bloğunun İÇİNDE olmalı.');

        // Katlama kuralları da baskı bloğunda olmalı (kâğıtta açık basılır).
        $katlama = strrpos($css, '.eks > summary { display: none !important; }');
        self::assertIsInt($katlama);
        self::assertLessThan($katlama, $sonPrint);

        // Ekran kuralı KAPATILMIŞ olmalı: içine kural yuvalanmamış.
        self::assertStringContainsString('.eks > .sg, .eks > .vr { margin-top:10px; }', $css);
    }

    // ── G3: GİRİLMEMİŞ FİYAT BASILMAZ ───────────────────────────────────────

    public function testGirilmemisDDP_SAYFADA_BASILMAZ(): void
    {
        $html = $this->sayfa();

        // Girilmiş olan basılır…
        self::assertStringContainsString('3.50', $html);
        // …girilmemiş olan "0.00" olarak BASILMAZ.
        self::assertStringNotContainsString('$ 0.00', $html);
        self::assertStringNotContainsString('₺ 0.00', $html);
    }

    public function testHIC_DDP_YOKSA_KPI_TIRE(): void
    {
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'DDP yok']))['data']['id'];
        $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Yalnız vitrin fiyatı',
            'qty' => 3,
            'price_yuan' => '9.00',
        ]);
        $url = (string) $this->json($this->write('POST', '/api/lists/' . $listId . '/share'))['data']['share_url'];
        $token = substr($url, strrpos($url, '/') + 1);

        $html = (string) $this->call('GET', '/p/' . $token)->getBody();

        self::assertMatchesRegularExpression(
            '/DDP · KDV DAHİL<\/b><span>—<\/span>/u',
            $html,
            'Hiç DDP girilmemişse KPI "—" olmalı, "₺ 0.00" değil.',
        );
    }

    public function testGirilmemisDDP_CSV_ciktisinda_BOS(): void
    {
        $downloads = new ShareDownload((string) $this->config()->get('APP_KEY', ''));
        $exp = $this->clock->now()->getTimestamp() + ShareDownload::OMUR_SANIYE;
        $adres = '/p/' . $this->token . '/export?format=csv&lang=tr&exp=' . $exp
            . '&sig=' . $downloads->imza($this->token, 'csv', 'tr', $exp);

        $csv = (string) $this->call('GET', $adres)->getBody();
        $satirlar = array_values(array_filter(explode("
", $csv)));

        // Girilmiş DDP çıktıda kalır…
        self::assertStringContainsString('3.50', $csv);

        // …girilmemiş olan BOŞ HÜCRE olur. (Toplam satırındaki "160.00" gibi meşru
        // değerler "0.00" içerdiği için metin araması yanıltıcıdır — ÜRÜN SATIRI okunur.)
        $girilmemis = array_values(array_filter(
            $satirlar,
            static fn (string $satir): bool => str_contains($satir, 'DDP girilmemiş ürün'),
        ));
        self::assertCount(1, $girilmemis);
        self::assertStringEndsWith(';;', trim($girilmemis[0]), 'DDP sütunları boş bırakılmalı.');
    }

    // ── G4: TAZE İMZA UCU ───────────────────────────────────────────────────

    public function testTazeImzaUcuKULLANILABILIR_BAGLANTI_DONER(): void
    {
        $response = $this->call('GET', '/p/' . $this->token . '/export-link?format=xlsx&lang=tr');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));

        /** @var array{ok: bool, data: array{url: string}} $govde */
        $govde = json_decode((string) $response->getBody(), true);
        self::assertTrue($govde['ok']);
        self::assertMatchesRegularExpression('#^/p/[0-9a-f]{64}/export\?format=xlsx&lang=tr&exp=\d+&sig=\S{32}$#', $govde['data']['url']);

        // Dönen bağlantı GERÇEKTEN indirilebilir olmalı (uçtan uca).
        $indirme = $this->call('GET', $govde['data']['url']);
        self::assertSame(200, $indirme->getStatusCode());
        self::assertStringContainsString('attachment;', $indirme->getHeaderLine('Content-Disposition'));
    }

    /**
     * K51 SABİT YANIT: dört ayrı ret nedeni de AYNI 404 gövdesini döndürür.
     *
     * Sınama "şu kelime geçmesin" biçiminde YAPILMAZ — sabit sayfanın kendi
     * metni ("süresi dolmuş olabilir") böyle bir aramayı yanıltır. Doğru
     * sözleşme şudur: gövdeler BİRBİRİNİN AYNISI olmalı; o zaman yanıt hiçbir
     * ayrım taşımıyor demektir.
     */
    public function testTazeImzaUcuGECERSIZ_GIRDIDE_SABIT_404(): void
    {
        $govdeler = [];
        foreach ([
            'token-bicimsiz' => '/p/kisa/export-link?format=xlsx&lang=tr',
            'token-yok' => '/p/' . str_repeat('b', 64) . '/export-link?format=xlsx&lang=tr',
            'bicim-gecersiz' => '/p/' . $this->token . '/export-link?format=docx&lang=tr',
            'dil-gecersiz' => '/p/' . $this->token . '/export-link?format=xlsx&lang=de',
        ] as $ad => $adres) {
            $response = $this->call('GET', $adres);

            self::assertSame(404, $response->getStatusCode(), $ad);
            $govdeler[$ad] = (string) $response->getBody();
        }

        self::assertCount(1, array_unique($govdeler), 'Dört ret dalı da AYNI gövdeyi döndürmeli (K51).');
        // Gövde ayrıca teşhis alanı taşımamalı: sebep kodları yalnız sunucu logunda.
        self::assertStringNotContainsString('sebep', reset($govdeler));
    }

    public function testTazeImzaUcuSAATLIK_INDIRME_SAYACINI_TUKETMEZ(): void
    {
        // 12 bağlantı üretmek (dakikalık sınırın altında) indirme hakkını yememeli.
        for ($i = 0; $i < 11; $i++) {
            self::assertSame(200, $this->call('GET', '/p/' . $this->token . '/export-link?format=csv&lang=tr')->getStatusCode());
        }

        $sayac = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM activity_log WHERE action = 'share_download'")
            ->fetchColumn();
        self::assertSame(0, $sayac, 'İmza üretimi indirme sayacına yazılmamalı.');
    }

    public function testTazeImzaUcuDAKIKALIK_SINIRDA_404(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->call('GET', '/p/' . $this->token . '/export-link?format=csv&lang=tr');
        }

        self::assertSame(
            404,
            $this->call('GET', '/p/' . $this->token . '/export-link?format=csv&lang=tr')->getStatusCode(),
            'Dakikalık üst sınır aşılınca sabit 404.',
        );
    }

    public function testImzaUretimSatirlariPANEL_AKISINDA_GORUNMEZ(): void
    {
        $this->call('GET', '/p/' . $this->token . '/export-link?format=csv&lang=tr');

        $kayitlar = $this->json($this->call('GET', '/api/activity'))['data'];
        foreach ($kayitlar as $kayit) {
            self::assertNotSame('share_link', $kayit['action'], 'Sayaç satırı panel akışını kirletmemeli.');
        }
    }

    // ── G6: BAŞARISIZLIK LOGU (istemciye sızmadan) ──────────────────────────

    public function testGECERSIZ_IMZADA_SABIT_404_ve_SUNUCU_LOGU(): void
    {
        $kayitci = new class () extends AbstractLogger {
            /** @var list<array{seviye: string, mesaj: string, baglam: array<string, mixed>}> */
            public array $satirlar = [];

            /**
             * @param mixed             $level
             * @param string|\Stringable $message
             * @param array<string, mixed> $context
             */
            public function log($level, $message, array $context = []): void
            {
                $this->satirlar[] = ['seviye' => (string) $level, 'mesaj' => (string) $message, 'baglam' => $context];
            }
        };

        $app = $this->app($kayitci);
        $istek = $this->rawRequest('GET', '/p/' . $this->token . '/export?format=xlsx&lang=tr&exp=9999999999&sig=' . str_repeat('x', 32));
        $response = $app->handle($istek);

        self::assertSame(404, $response->getStatusCode());

        $ilgili = array_values(array_filter(
            $kayitci->satirlar,
            static fn (array $satir): bool => str_contains($satir['mesaj'], 'Oturumsuz indirme reddedildi'),
        ));
        self::assertNotSame([], $ilgili, 'Ret sunucuda loglanmalı (canlı teşhis).');

        $baglam = $ilgili[0]['baglam'];
        self::assertSame('warning', $ilgili[0]['seviye']);
        self::assertSame('imza', $baglam['sebep']);
        self::assertSame(substr($this->token, 0, 8), $baglam['token_onek'], 'Yalnız ilk 8 hane.');
        self::assertSame('xlsx', $baglam['format']);
        self::assertStringEndsWith('.0', (string) $baglam['ip'], 'IP kırpılmış olmalı.');

        // Yanıt gövdesi sebebi AÇIKLAMAZ.
        self::assertStringNotContainsStringIgnoringCase('imza', (string) $response->getBody());
    }

    // ── G10: DETAY PANELİ GALERİ ŞERİDİ KALDIRILDI ──────────────────────────

    public function testDETAY_PANELINDE_GALERI_SERIDI_YOK(): void
    {
        $html = $this->sayfa();

        self::assertStringNotContainsString('GALERİ', $html, 'Paneldeki galeri şeridi kaldırıldı (G10).');
        self::assertStringNotContainsString('class="gl"', $html);
        // Lightbox AYNEN çalışır: veri ve tetikleyici korunur.
        self::assertStringContainsString('data-galeriler=', $html);
        self::assertStringContainsString('id="lbx"', $html);
    }

    public function testOLU_GALERI_CSS_KURALLARI_TEMIZLENDI(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/p-style.css');

        self::assertStringNotContainsString('.gl ', $css);
        self::assertStringNotContainsString('.gl{', $css);
    }

    // ── G8: satır hücresi disiplini (uçtan uca) ─────────────────────────────

    public function testUZUN_VARYASYON_SATIR_HUCRESINI_BOGMAZ(): void
    {
        $html = $this->sayfa();

        // Bu listedeki ürünlerde varyasyon yok; hücre boş kalmalı, "…" ya da
        // uzun liste basılmamalı. (Dolu senaryo SunumDisiplinTest'te birim testli.)
        self::assertStringNotContainsString(' … (', $html, 'Satırda uzun varyasyon özeti kalmamalı.');
    }

}
