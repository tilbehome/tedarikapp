<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * İE#7 Bölüm B — ayarlar, kur tarihçesi ve kategoriler (docs/10 §7).
 *
 * KRİTİK olan tek şey kur akışı: PUT ile değişen kur, ondan SONRA açılan listeye
 * kilitlenmeli; mevcut listeler kendi kurlarını korumalı (K4). Gerisi smoke (K35).
 */
final class SettingsEndpointsTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
    }

    /** @param array<string, mixed>|null $body */
    private function write(string $method, string $path, ?array $body = null): ResponseInterface
    {
        return $this->call($method, $path, $body ?? [], [Csrf::HEADER => $this->csrf]);
    }

    // ─────────────── Ayarlar ───────────────

    public function testAyarlarOkunur(): void
    {
        $data = $this->json($this->call('GET', '/api/settings'))['data'];

        self::assertSame('7.0400', $data['yuan_tl']);
        self::assertSame('41.5000', $data['usd_tl']);
        self::assertTrue($data['totp_enabled']);
        self::assertArrayHasKey('media_mode', $data, 'K33 çift modu panele açılmalı.');
    }

    public function testKurGuncellenirVeTarihceyeYazilir(): void
    {
        $response = $this->write('PUT', '/api/settings/rates', ['yuan_tl' => '7.5000', 'usd_tl' => '42.0000']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('7.5000', $this->json($response)['data']['yuan_tl']);

        $history = $this->json($this->call('GET', '/api/settings/rates/history'))['data'];
        self::assertCount(2, $history);
        // Tarihçe yeniden eskiye sıralanır; aynı saniyede yazılan iki kayıt id'ye göre gelir.
        self::assertSame(['USD', 'CNY'], array_column($history, 'currency'));
        self::assertSame('7.5000', $history[1]['rate']);
    }

    /** KRİTİK (K4, K45 düzeltmesi): kur İLETİLDİ'de kilitlenir; taslak güncel kuru izler. */
    public function testYeniKurYalnizcaSonrakiListeyeUygulanir(): void
    {
        $sent = $this->json($this->write('POST', '/api/lists', ['name' => 'Kilitlenecek liste']))['data'];
        $this->write('PATCH', '/api/lists/' . $sent['id'], ['status' => 'sent']);

        $draft = $this->json($this->write('POST', '/api/lists', ['name' => 'Taslak liste']))['data'];
        self::assertSame('7.0400', $draft['yuan_rate']);

        $this->write('PUT', '/api/settings/rates', ['yuan_tl' => '9.1234']);

        $locked = $this->json($this->call('GET', '/api/lists/' . $sent['id']))['data'];
        self::assertSame('7.0400', $locked['yuan_rate'], 'İletilmiş listenin kilitli kuru DEĞİŞMEMELİ.');

        $draftAfter = $this->json($this->call('GET', '/api/lists/' . $draft['id']))['data'];
        self::assertSame('9.1234', $draftAfter['yuan_rate'], 'Taslak liste güncel kuru izlemeli.');

        $after = $this->json($this->write('POST', '/api/lists', ['name' => 'Kur değiştikten sonra']))['data'];
        self::assertSame('9.1234', $after['yuan_rate'], 'Yeni liste güncel kuru almalı.');
    }

    public function testGecersizKurReddedilir(): void
    {
        foreach (['0', '1000.0001', '-7', 'abc', '7.00001'] as $rate) {
            $response = $this->write('PUT', '/api/settings/rates', ['yuan_tl' => $rate]);
            self::assertSame(422, $response->getStatusCode(), $rate . ' reddedilmeliydi.');
        }
    }

    public function testBosGovdeliKurGuncellemesiReddedilir(): void
    {
        self::assertSame(422, $this->write('PUT', '/api/settings/rates', [])->getStatusCode());
    }

    public function testTarihceParaBirimineGoreSuzulur(): void
    {
        $this->write('PUT', '/api/settings/rates', ['yuan_tl' => '7.5000', 'usd_tl' => '42.0000']);

        $onlyCny = $this->json($this->call('GET', '/api/settings/rates/history?currency=CNY'))['data'];

        self::assertCount(1, $onlyCny);
        self::assertSame('CNY', $onlyCny[0]['currency']);
    }

    public function testGecersizParaBirimiSuzgeci422Doner(): void
    {
        self::assertSame(422, $this->call('GET', '/api/settings/rates/history?currency=EUR')->getStatusCode());
    }

    // ─────────────── Kategoriler ───────────────

    public function testKategoriOlusturListeleGuncelleSil(): void
    {
        $created = $this->json($this->write('POST', '/api/categories', ['name' => 'MUTFAK']))['data'];
        self::assertSame('MUTFAK', $created['name']);
        self::assertSame(0, $created['product_count']);

        $list = $this->json($this->call('GET', '/api/categories'))['data'];
        self::assertCount(1, $list);

        $updated = $this->json($this->write('PATCH', '/api/categories/' . $created['id'], ['name' => 'MUTFAK GEREÇLERİ']))['data'];
        self::assertSame('MUTFAK GEREÇLERİ', $updated['name']);

        self::assertSame(204, $this->write('DELETE', '/api/categories/' . $created['id'])->getStatusCode());
        self::assertCount(0, $this->json($this->call('GET', '/api/categories'))['data']);
    }

    public function testAyniIsimdeIkinciKategoriReddedilir(): void
    {
        $this->write('POST', '/api/categories', ['name' => 'MUTFAK']);
        $response = $this->write('POST', '/api/categories', ['name' => 'MUTFAK']);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('name', $this->json($response)['error']['fields']);
    }

    public function testKullanimdakiKategoriSilinemez(): void
    {
        $category = $this->json($this->write('POST', '/api/categories', ['name' => 'MUTFAK']))['data'];
        $list = $this->json($this->write('POST', '/api/lists', ['name' => 'Liste']))['data'];
        $this->write('POST', '/api/lists/' . $list['id'] . '/products', [
            'name' => 'Ürün', 'qty' => 1, 'price_yuan' => '9.00', 'category_id' => $category['id'],
        ]);

        $response = $this->write('DELETE', '/api/categories/' . $category['id']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(1, $this->json($response)['meta']['product_count']);
    }

    public function testOlmayanKategori404Doner(): void
    {
        self::assertSame(404, $this->write('PATCH', '/api/categories/9999', ['name' => 'X'])->getStatusCode());
        self::assertSame(404, $this->write('DELETE', '/api/categories/9999')->getStatusCode());
    }

    public function testKategoriAdiZorunluVeSinirli(): void
    {
        self::assertSame(422, $this->write('POST', '/api/categories', ['name' => ''])->getStatusCode());
        self::assertSame(422, $this->write('POST', '/api/categories', ['name' => str_repeat('a', 101)])->getStatusCode());
    }

    // ─────────────── Koruma ───────────────

    public function testAyarlarCsrfsizDegistirilemez(): void
    {
        $response = $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '9.0000']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('CSRF', $this->json($response)['error']['code']);
    }

    public function testOturumsuzAyarErisimi401Doner(): void
    {
        $this->session = new \Tests\Support\ArraySession();

        self::assertSame(401, $this->call('GET', '/api/settings')->getStatusCode());
    }
}
