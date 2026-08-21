<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Share\ShareDownload;
use Tests\Support\AuthTestCase;

/**
 * İE#15 A1/A2/A3/A4 — OTURUMSUZ İMZALI İNDİRME.
 *
 * Sınananlar: imza olmadan indirilemez · süresi dolan bağlantı çalışmaz ·
 * kapsam (biçim/dil) kurcalanamaz · iptal edilen paylaşımda sabit 404 ·
 * hız sınırı · ve en önemlisi: İÇ KOPYA VERİSİ ÜÇ BİÇİMDE DE YOK.
 */
final class ShareDownloadTest extends AuthTestCase
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
            'name' => 'İndirme listesi',
            'period' => 'Eylül 2026',
        ]))['data']['id'];

        $this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'Termos Yemek Kabı',
            'name_original' => '双层不锈钢保温饭盒500ml',
            'qty' => 100,
            'price_yuan' => '12.00',
            // İÇ KOPYA VERİSİ: hedef satış ve kâr — firmaya giden çıktıda ASLA görünmemeli.
            'price_target_try' => '1499.00',
        ]);

        $url = (string) $this->json($this->write('POST', '/api/lists/' . $this->listId . '/share'))['data']['share_url'];
        $this->token = substr($url, strrpos($url, '/') + 1);
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function imzali(string $format, string $dil = 'tr', int $kaymaSaniye = 0): string
    {
        // Süre hesabı UYGULAMA SAATİNDEN yapılır (testte donuk saat): imza kapsamı
        // sunucunun "şimdi"sine göre denetlenir, gerçek duvar saatine göre değil.
        $downloads = new ShareDownload($this->appKey());
        $sonKullanma = $this->clock->now()->getTimestamp() + ShareDownload::OMUR_SANIYE + $kaymaSaniye;

        return '/p/' . $this->token . '/export?format=' . $format . '&lang=' . $dil
            . '&exp=' . $sonKullanma
            . '&sig=' . $downloads->imza($this->token, $format, $dil, $sonKullanma);
    }

    private function appKey(): string
    {
        return (string) $this->config()->get('APP_KEY', '');
    }

    public function testImzaliBaglantiylaOTURUMSUZ_indirilebilir(): void
    {
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        foreach (['xlsx', 'pdf', 'csv'] as $format) {
            $response = $this->call('GET', $this->imzali($format));

            self::assertSame(200, $response->getStatusCode(), $format . ' oturumsuz indirilebilmeli.');
            self::assertNotSame('', (string) $response->getBody());
            self::assertStringContainsString(
                'attachment;',
                $response->getHeaderLine('Content-Disposition'),
                'Dosya indirme olarak sunulmalı.',
            );
        }
    }

    public function testIMZASIZ_istek_404(): void
    {
        $response = $this->call('GET', '/p/' . $this->token . '/export?format=xlsx');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testKURCALANMIS_kapsam_404(): void
    {
        // xlsx için üretilmiş imzayla pdf istenirse imza tutmaz (kapsam imzanın içinde).
        $adres = str_replace('format=xlsx', 'format=pdf', $this->imzali('xlsx'));

        self::assertSame(404, $this->call('GET', $adres)->getStatusCode());
    }

    public function testSURESI_DOLMUS_baglanti_404(): void
    {
        $downloads = new ShareDownload($this->appKey());
        $gecmis = $this->clock->now()->getTimestamp() - 10;
        $adres = '/p/' . $this->token . '/export?format=xlsx&lang=tr&exp=' . $gecmis
            . '&sig=' . $downloads->imza($this->token, 'xlsx', 'tr', $gecmis);

        self::assertSame(404, $this->call('GET', $adres)->getStatusCode());
    }

    public function testCOK_UZUN_OMURLU_imza_reddedilir(): void
    {
        // Saatler sonrasına imza üretilmiş olsa bile kabul edilmez (üst sınır denetimi).
        self::assertSame(404, $this->call('GET', $this->imzali('xlsx', 'tr', 7200))->getStatusCode());
    }

    public function testPAYLASIM_IPTAL_EDILINCE_indirme_de_biter(): void
    {
        $adres = $this->imzali('xlsx');
        $this->write('DELETE', '/api/lists/' . $this->listId . '/share');

        self::assertSame(404, $this->call('GET', $adres)->getStatusCode(), 'İptal sonrası sabit 404.');
    }

    /** A4 — İÇ KOPYA ÜÇ BİÇİMDE DE YOK. */
    public function testIC_KOPYA_VERISI_UC_BICIMDE_DE_YOK(): void
    {
        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $govde = (string) $this->call('GET', $this->imzali($format))->getBody();

            self::assertStringNotContainsString('1499', $govde, $format . ': hedef satış fiyatı sızmamalı.');
            self::assertStringNotContainsString('HEDEF SATIŞ', mb_strtoupper($govde), $format . ': kâr sütunu yok.');
            self::assertStringNotContainsString('IC KOPYA', mb_strtoupper($govde), $format . ': iç kopya ibaresi yok.');
        }
    }

    /** A3 — ZH çıktıda ürün adı ORİJİNAL başlıktır. */
    public function testZH_CIKTIDA_URUN_ADI_ORIJINALDIR(): void
    {
        $tr = (string) $this->call('GET', $this->imzali('csv', 'tr'))->getBody();
        $zh = (string) $this->call('GET', $this->imzali('csv', 'zh'))->getBody();

        self::assertStringContainsString('Termos Yemek Kabı', $tr);
        self::assertStringContainsString('双层不锈钢保温饭盒500ml', $zh, 'ZH çıktı orijinal başlığı basar.');
    }

    /** A1 — hız sınırı: token başına saatte 20 indirme. */
    public function testHIZ_SINIRI_ASILINCA_429(): void
    {
        for ($i = 0; $i < 20; $i++) {
            self::assertSame(200, $this->call('GET', $this->imzali('csv'))->getStatusCode());
        }

        $response = $this->call('GET', $this->imzali('csv'));
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('3600', $response->getHeaderLine('Retry-After'));
    }

    /** A1 — erişim kaydı: token ÖNEKİ ve KIRPILMIŞ IP; tam token asla loglanmaz. */
    public function testERISIM_KAYDI_tam_token_ve_tam_IP_ICERMEZ(): void
    {
        $this->call('GET', $this->imzali('csv'));

        $satir = $this->pdo
            ->query("SELECT detail, ip FROM activity_log WHERE action = 'share_download' ORDER BY id DESC LIMIT 1")
            ->fetch();

        self::assertIsArray($satir);
        self::assertStringContainsString('csv', (string) $satir['detail']);
        self::assertStringNotContainsString($this->token, (string) $satir['detail'], 'Tam token loglanmaz (K51).');
        self::assertStringContainsString(substr($this->token, 0, 8), (string) $satir['detail']);
        self::assertStringEndsWith('.0', (string) $satir['ip'], 'IP son sekizlisi kırpılır.');
    }

    /** C3 — QR sunucuda üretilir; dış servis yok, içeriği yalnız paylaşım adresi. */
    public function testQR_SUNUCUDA_URETILIR(): void
    {
        $response = $this->call('GET', '/p/' . $this->token . '/qr.png');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith("\x89PNG", (string) $response->getBody());
    }

    public function testQR_GECERSIZ_TOKENDE_404(): void
    {
        self::assertSame(404, $this->call('GET', '/p/' . str_repeat('a', 64) . '/qr.png')->getStatusCode());
    }
}
