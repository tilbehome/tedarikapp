<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * İE#6 sözleşme testleri — docs/10 §3, §4, §6.
 *
 * Akış testleri gerçek uçlardan geçer: liste oluştur → ürün ekle → durum ilerlet →
 * topla → kopyala → sil → geri al.
 */
final class DataLayerEndpointsTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    private function loginAsAdmin(): void
    {
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

    /** @return array<string, mixed> */
    private function createList(string $name = 'Eylül 2026 DDP Sipariş'): array
    {
        $response = $this->write('POST', '/api/lists', ['name' => $name, 'period' => 'EYLÜL 2026']);
        self::assertSame(201, $response->getStatusCode());

        /** @var array<string, mixed> $data */
        $data = $this->json($response)['data'];

        return $data;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function addProduct(int $listId, array $overrides = []): array
    {
        $response = $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Termos Yemek Kabı',
            'qty' => 24,
            'price_yuan' => '9.00',
            ...$overrides,
        ]);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        /** @var array<string, mixed> $data */
        $data = $this->json($response)['data'];

        return $data;
    }

    // ─────────────── Listeler ───────────────

    public function testListeOlusturulurVeVarsayilanlariAlir(): void
    {
        $list = $this->createList();

        self::assertSame('draft', $list['status']);
        self::assertSame('active', $list['visibility']);
        self::assertSame('7.0400', $list['yuan_rate']);
        self::assertNull($list['rate_locked_at']);
        self::assertSame(0, $list['revision']);
        self::assertSame(0, $list['product_count']);
        self::assertSame(
            ['to_order' => 0, 'ordered' => 0, 'in_transit' => 0, 'received' => 0, 'cancelled' => 0],
            $list['progress'],
        );
    }

    public function testListeAdiZorunlu(): void
    {
        $response = $this->write('POST', '/api/lists', ['period' => 'EYLÜL 2026']);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('name', $this->json($response)['error']['fields']);
    }

    public function testCokUzunListeAdiReddedilir(): void
    {
        $response = $this->write('POST', '/api/lists', ['name' => str_repeat('a', 201)]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testListeleriFiltrelemeCalisir(): void
    {
        $first = $this->createList('Aktif liste');
        $second = $this->createList('Arşivlenecek');
        $this->write('PATCH', '/api/lists/' . $second['id'], ['visibility' => 'archived']);

        $active = $this->json($this->call('GET', '/api/lists?visibility=active'))['data'];
        self::assertCount(1, $active);
        self::assertSame($first['id'], $active[0]['id']);

        $archived = $this->json($this->call('GET', '/api/lists?visibility=archived'))['data'];
        self::assertCount(1, $archived);
        self::assertNotNull($archived[0]['archived_at']);
    }

    public function testGecersizGorunurlukReddedilir(): void
    {
        self::assertSame(422, $this->call('GET', '/api/lists?visibility=arsiv')->getStatusCode());
    }

    // ─────────────── Kur kilidi (K4) ───────────────

    public function testTaslakListedeKurDegistirilebilir(): void
    {
        $list = $this->createList();

        $response = $this->write('PATCH', '/api/lists/' . $list['id'], ['yuan_rate' => '7.5000']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('7.5000', $this->json($response)['data']['yuan_rate']);
    }

    public function testSentDurumunaGecisteKurKilitlenir(): void
    {
        $list = $this->createList();

        $response = $this->write('PATCH', '/api/lists/' . $list['id'], ['status' => 'sent']);
        $data = $this->json($response)['data'];

        self::assertSame('sent', $data['status']);
        self::assertNotNull($data['rate_locked_at'], 'sent geçişinde rate_locked_at yazılmalı (K4).');
    }

    public function testKilitliListedeKurDegisikligi422Doner(): void
    {
        $list = $this->createList();
        $this->write('PATCH', '/api/lists/' . $list['id'], ['status' => 'sent']);

        $response = $this->write('PATCH', '/api/lists/' . $list['id'], ['yuan_rate' => '8.0000']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('STATE_TRANSITION', $this->json($response)['error']['code']);
    }

    public function testGecersizListeDurumGecisi422VeIzinliListeDoner(): void
    {
        $list = $this->createList();

        $response = $this->write('PATCH', '/api/lists/' . $list['id'], ['status' => 'completed']);
        $body = $this->json($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('STATE_TRANSITION', $body['error']['code']);
        self::assertSame(['sent', 'cancelled'], $body['meta']['allowed']);
    }

    public function testAcikUrunVarkenListeTamamlanamaz(): void
    {
        $list = $this->createList();
        $this->addProduct((int) $list['id']);
        $this->write('PATCH', '/api/lists/' . $list['id'], ['status' => 'sent']);
        $this->write('PATCH', '/api/lists/' . $list['id'], ['status' => 'ordered']);

        $response = $this->write('PATCH', '/api/lists/' . $list['id'], ['status' => 'completed']);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('1 ürün', $this->json($response)['error']['message']);
    }

    // ─────────────── Ürünler ve para ───────────────

    public function testUrunEklenirVeParaAlanlariDogruHesaplanir(): void
    {
        $list = $this->createList();
        $product = $this->addProduct((int) $list['id']);

        self::assertSame('9.00', $product['price_yuan']);
        // Altın test: ¥9,00 × 7,04 = ₺63,36
        self::assertSame('63.36', $product['price_yuan_tl']);
        self::assertSame('216.00', $product['line_total_yuan']);
        self::assertSame('1520.64', $product['line_total_yuan_tl']);
        self::assertSame('to_order', $product['status']);
        self::assertSame(1, $product['sort_no']);
    }

    public function testListeToplamlariUcKalemdeDogru(): void
    {
        $list = $this->createList();
        $listId = (int) $list['id'];
        $this->addProduct($listId, ['name' => 'A', 'qty' => 24, 'price_yuan' => '9.00']);
        $this->addProduct($listId, ['name' => 'B', 'qty' => 10, 'price_yuan' => '12.50']);
        $this->addProduct($listId, ['name' => 'C', 'qty' => 3, 'price_yuan' => '7.25']);

        $totals = $this->json($this->call('GET', '/api/lists/' . $listId))['data']['totals'];

        self::assertSame(37, $totals['qty']);
        self::assertSame('362.75', $totals['yuan']);
        self::assertSame('2553.76', $totals['yuan_tl']);
    }

    public function testIptalEdilenUrunToplamaGirmez(): void
    {
        $list = $this->createList();
        $listId = (int) $list['id'];
        $this->addProduct($listId, ['name' => 'A', 'qty' => 10, 'price_yuan' => '10.00']);
        $cancelled = $this->addProduct($listId, ['name' => 'B', 'qty' => 5, 'price_yuan' => '10.00']);

        $this->write('PATCH', '/api/products/' . $cancelled['id'] . '/status', ['status' => 'cancelled']);

        $totals = $this->json($this->call('GET', '/api/lists/' . $listId))['data']['totals'];

        self::assertSame(10, $totals['qty'], 'İptal edilen ürün toplama girmemeli.');
        self::assertSame('100.00', $totals['yuan']);
    }

    public function testGecersizFiyatReddedilir(): void
    {
        $list = $this->createList();

        foreach (['-1.00', '9.999', 'abc', '10000000.00'] as $price) {
            $response = $this->write('POST', '/api/lists/' . $list['id'] . '/products', [
                'name' => 'Test', 'qty' => 1, 'price_yuan' => $price,
            ]);
            self::assertSame(422, $response->getStatusCode(), $price . ' reddedilmeliydi.');
        }
    }

    public function testGecersizMiktarReddedilir(): void
    {
        $list = $this->createList();

        foreach ([0, 1000001, -5] as $qty) {
            $response = $this->write('POST', '/api/lists/' . $list['id'] . '/products', [
                'name' => 'Test', 'qty' => $qty, 'price_yuan' => '9.00',
            ]);
            self::assertSame(422, $response->getStatusCode(), (string) $qty . ' reddedilmeliydi.');
        }
    }

    public function testHttpsOlmayanUrlReddedilir(): void
    {
        $list = $this->createList();

        $response = $this->write('POST', '/api/lists/' . $list['id'] . '/products', [
            'name' => 'Test', 'qty' => 1, 'price_yuan' => '9.00',
            'url' => 'http://detail.1688.com/offer/1.html',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('url', $this->json($response)['error']['fields']);
    }

    // ─────────────── Durum makinesi (API üzerinden) ───────────────

    public function testUrunDurumuIlerletilirVeTarihceyeYazilir(): void
    {
        $list = $this->createList();
        $product = $this->addProduct((int) $list['id']);

        foreach (['ordered', 'in_transit', 'received'] as $status) {
            $response = $this->write('PATCH', '/api/products/' . $product['id'] . '/status', ['status' => $status]);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame($status, $this->json($response)['data']['status']);
        }

        $statement = $this->pdo->query('SELECT from_status, to_status FROM product_status_history ORDER BY id');
        $rows = $statement === false ? [] : $statement->fetchAll();

        self::assertCount(4, $rows, 'Oluşturma + 3 geçiş kaydedilmeli.');
        self::assertNull($rows[0]['from_status']);
        self::assertSame('to_order', $rows[0]['to_status']);
        self::assertSame('in_transit', $rows[3]['from_status']);
        self::assertSame('received', $rows[3]['to_status']);
    }

    public function testDurumAtlama422VeIzinliListeDoner(): void
    {
        $list = $this->createList();
        $product = $this->addProduct((int) $list['id']);

        $response = $this->write('PATCH', '/api/products/' . $product['id'] . '/status', ['status' => 'received']);
        $body = $this->json($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('STATE_TRANSITION', $body['error']['code']);
        self::assertSame(['ordered', 'cancelled'], $body['meta']['allowed']);
    }

    public function testGeldiDurumundanIptalEdilemez(): void
    {
        $list = $this->createList();
        $product = $this->addProduct((int) $list['id']);
        foreach (['ordered', 'in_transit', 'received'] as $status) {
            $this->write('PATCH', '/api/products/' . $product['id'] . '/status', ['status' => $status]);
        }

        $response = $this->write('PATCH', '/api/products/' . $product['id'] . '/status', ['status' => 'cancelled']);

        self::assertSame(422, $response->getStatusCode());
    }

    // ─────────────── revision / çıktı güncelliği ───────────────

    public function testUrunEklemeRevisionArtirir(): void
    {
        $list = $this->createList();
        self::assertSame(0, $list['revision']);

        $this->addProduct((int) $list['id']);
        $after = $this->json($this->call('GET', '/api/lists/' . $list['id']))['data'];

        self::assertSame(1, $after['revision']);
    }

    public function testNotDuzenlemeRevisionArtirmaz(): void
    {
        $list = $this->createList();
        $product = $this->addProduct((int) $list['id']);
        $before = $this->json($this->call('GET', '/api/lists/' . $list['id']))['data']['revision'];

        $this->write('PATCH', '/api/products/' . $product['id'], ['note' => 'Renk teyit edilecek']);
        $after = $this->json($this->call('GET', '/api/lists/' . $list['id']))['data']['revision'];

        self::assertSame($before, $after, 'Not düzenlemek çıktıyı eskitmez (K25).');
    }

    public function testFiyatDegisikligiRevisionArtirir(): void
    {
        $list = $this->createList();
        $product = $this->addProduct((int) $list['id']);
        $before = $this->json($this->call('GET', '/api/lists/' . $list['id']))['data']['revision'];

        $this->write('PATCH', '/api/products/' . $product['id'], ['price_yuan' => '9.50']);
        $after = $this->json($this->call('GET', '/api/lists/' . $list['id']))['data']['revision'];

        self::assertSame($before + 1, $after);
    }

    // ─────────────── Kopyalama ───────────────

    public function testListeKopyalanirVeGuncelKuruAlir(): void
    {
        $list = $this->createList();
        $listId = (int) $list['id'];
        $this->addProduct($listId, ['name' => 'A', 'qty' => 5, 'price_yuan' => '9.00']);
        $product = $this->addProduct($listId, ['name' => 'B', 'qty' => 2, 'price_yuan' => '3.00']);
        $this->write('PATCH', '/api/products/' . $product['id'] . '/status', ['status' => 'ordered']);
        $this->write('PATCH', '/api/lists/' . $listId, ['status' => 'sent']);

        $response = $this->write('POST', '/api/lists/' . $listId . '/duplicate');
        $copy = $this->json($response)['data'];

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('draft', $copy['status'], 'Kopya taslak olarak açılır.');
        self::assertNull($copy['rate_locked_at'], 'Kopya kuru yeniden kilitlenir, eski kilit taşınmaz.');
        self::assertSame(2, $copy['product_count']);

        $copiedProducts = $this->json($this->call('GET', '/api/lists/' . $copy['id'] . '/products'))['data'];
        foreach ($copiedProducts as $copied) {
            self::assertSame('to_order', $copied['status'], 'Kopyada ürünler başa döner.');
            self::assertNull($copied['tracking_no']);
        }
    }

    // ─────────────── Toplu işlem ve sıralama ───────────────

    public function testTopluDurumDegisikligiKismiBasariDoner(): void
    {
        $list = $this->createList();
        $listId = (int) $list['id'];
        $a = $this->addProduct($listId, ['name' => 'A']);
        $b = $this->addProduct($listId, ['name' => 'B']);
        // B'yi received'a çıkar: artık ordered'a geçemez.
        foreach (['ordered', 'in_transit', 'received'] as $status) {
            $this->write('PATCH', '/api/products/' . $b['id'] . '/status', ['status' => $status]);
        }

        $response = $this->write('PATCH', '/api/products/bulk', [
            'ids' => [(int) $a['id'], (int) $b['id'], 99999],
            'action' => 'status',
            'status' => 'ordered',
        ]);
        $data = $this->json($response)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $data['updated']);
        self::assertCount(2, $data['failed']);
    }

    public function testTopluTasimaUrunuBaskaListeyeGecirir(): void
    {
        $source = $this->createList('Kaynak');
        $target = $this->createList('Hedef');
        $product = $this->addProduct((int) $source['id']);

        $response = $this->write('PATCH', '/api/products/bulk', [
            'ids' => [(int) $product['id']],
            'action' => 'move',
            'target_list_id' => (int) $target['id'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->json($response)['data']['updated']);
        self::assertCount(1, $this->json($this->call('GET', '/api/lists/' . $target['id'] . '/products'))['data']);
        self::assertCount(0, $this->json($this->call('GET', '/api/lists/' . $source['id'] . '/products'))['data']);
    }

    public function testSiralamaYenidenYazilir(): void
    {
        $list = $this->createList();
        $listId = (int) $list['id'];
        $a = $this->addProduct($listId, ['name' => 'A']);
        $b = $this->addProduct($listId, ['name' => 'B']);
        $c = $this->addProduct($listId, ['name' => 'C']);

        $response = $this->write('PATCH', '/api/lists/' . $listId . '/products/reorder', [
            'ordered_ids' => [(int) $c['id'], (int) $a['id'], (int) $b['id']],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $this->json($response)['data']['updated']);

        $names = array_column($this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'], 'name');
        self::assertSame(['C', 'A', 'B'], $names);
    }

    // ─────────────── Tekrar uyarısı ───────────────

    public function testAyniUrunTekrarEklenirse409Doner(): void
    {
        $list = $this->createList();
        $listId = (int) $list['id'];
        $this->addProduct($listId, ['platform' => '1688', 'external_id' => '864237880687']);

        $response = $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Aynı ürün', 'qty' => 1, 'price_yuan' => '9.00',
            'platform' => '1688', 'external_id' => '864237880687',
        ]);
        $body = $this->json($response);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('DUPLICATE_WARNING', $body['error']['code']);
        self::assertSame($listId, $body['meta']['existing']['list_id']);
    }

    public function testForceIleTekrarEklenebilir(): void
    {
        $list = $this->createList();
        $listId = (int) $list['id'];
        $this->addProduct($listId, ['platform' => '1688', 'external_id' => '864237880687']);

        $response = $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Aynı ürün', 'qty' => 1, 'price_yuan' => '9.00',
            'platform' => '1688', 'external_id' => '864237880687', 'force' => true,
        ]);

        self::assertSame(201, $response->getStatusCode());
    }

    // ─────────────── Çöp kutusu ───────────────

    public function testSilinenListeNormalUctaGorunmezCopKutusundaGorunur(): void
    {
        $list = $this->createList();

        self::assertSame(204, $this->write('DELETE', '/api/lists/' . $list['id'])->getStatusCode());
        self::assertCount(0, $this->json($this->call('GET', '/api/lists'))['data']);
        self::assertSame(404, $this->call('GET', '/api/lists/' . $list['id'])->getStatusCode());

        $trash = $this->json($this->call('GET', '/api/trash'))['data'];
        self::assertCount(1, $trash['lists']);
        self::assertSame(30, $trash['retention_days']);
        self::assertSame(30, $trash['lists'][0]['days_left']);
    }

    public function testSilinenListeGeriAlinir(): void
    {
        $list = $this->createList();
        $this->write('DELETE', '/api/lists/' . $list['id']);

        $response = $this->write('POST', '/api/trash/lists/' . $list['id'] . '/restore');

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $this->json($this->call('GET', '/api/lists'))['data']);
    }

    public function testListesiSilinmisUrunTekBasinaGeriAlinamaz(): void
    {
        $list = $this->createList();
        $product = $this->addProduct((int) $list['id']);
        $this->write('DELETE', '/api/products/' . $product['id']);
        $this->write('DELETE', '/api/lists/' . $list['id']);

        $response = $this->write('POST', '/api/trash/products/' . $product['id'] . '/restore');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('STATE_TRANSITION', $this->json($response)['error']['code']);
    }

    public function testCopKutusundanKaliciSilme(): void
    {
        $list = $this->createList();
        $this->addProduct((int) $list['id']);
        $this->write('DELETE', '/api/lists/' . $list['id']);

        self::assertSame(204, $this->write('DELETE', '/api/trash/lists/' . $list['id'])->getStatusCode());
        self::assertCount(0, $this->json($this->call('GET', '/api/trash'))['data']['lists']);
    }

    // ─────────────── Koruma ───────────────

    public function testOturumsuzErisim401Doner(): void
    {
        $this->session = new \Tests\Support\ArraySession();

        self::assertSame(401, $this->call('GET', '/api/lists')->getStatusCode());
    }

    public function testCsrfsizYazma403Doner(): void
    {
        $response = $this->call('POST', '/api/lists', ['name' => 'Deneme']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('CSRF', $this->json($response)['error']['code']);
    }
}
