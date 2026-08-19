<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * İE#11 Görev D — Gelen Kutusu: kuyruk → listeye taşıma → silme (K22 pending/assigned).
 */
final class InboxEndpointsTest extends AuthTestCase
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
        $this->token = (string) $this->json(
            $this->call('POST', '/api/settings/extension-token', [], [Csrf::HEADER => $this->csrf]),
        )['data']['token'];
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function seedCapture(string $captureId, string $name = 'Test ürünü', string $platform = '1688'): int
    {
        $payload = [
            'capture_id' => $captureId,
            'schema_version' => 2,
            'extension_version' => '1.0.0',
            'parser_version' => '1688-2026.08',
            'qty' => 5,
            'source' => [
                'platform' => $platform,
                'external_id' => 'e-' . $captureId,
                'url' => 'https://detail.1688.com/offer/1.html',
                'seller_name' => 'Test Satıcı',
                'captured_at' => '2026-08-19T10:00:00+03:00',
            ],
            'raw' => [
                'title' => $name . ' 原文',
                'normalized_attributes' => ['品牌' => 'Test', '容量' => '350ml'],
            ],
            'normalized' => [
                'name' => $name,
                'price_yuan' => '3.50',
                'images' => ['https://cbu01.alicdn.com/img/a.jpg'],
                'price_tiers' => [['min_qty' => 1, 'price_yuan' => '3.50'], ['min_qty' => 100, 'price_yuan' => '3.10']],
                'sku_matrix' => [['props' => ['renk' => 'Gri'], 'price_yuan' => '3.50']],
            ],
        ];

        return (int) $this->json(
            $this->call('POST', '/api/capture', $payload, ['Authorization' => 'Bearer ' . $this->token]),
        )['data']['inbox_id'];
    }

    public function testKuyrukOturumIster(): void
    {
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(401, $this->call('GET', '/api/inbox')->getStatusCode());
    }

    public function testTopluTasimaUrunAcarVeKuyruktanDusurur(): void
    {
        $a = $this->seedCapture('aaaaaaaa-1111-4222-8333-444444444444', 'Ürün A');
        $b = $this->seedCapture('bbbbbbbb-1111-4222-8333-444444444444', 'Ürün B');
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Hedef']))['data']['id'];

        $result = $this->json($this->write('POST', '/api/inbox/assign', ['ids' => [$a, $b], 'list_id' => $listId]))['data'];

        self::assertSame(2, $result['moved']);
        self::assertSame([], $result['failed']);

        $products = $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'];
        self::assertCount(2, $products);
        self::assertSame([], $this->json($this->call('GET', '/api/inbox'))['data'], 'Taşınanlar kuyruktan düşmeli.');
        // Excel/link zinciri: ürün url'si 1688 adresi (kabul kriteri).
        self::assertStringContainsString('detail.1688.com', (string) $products[0]['url']);
    }

    /** İE#11 EK-3 (2): kuyruktan taşıma da CaptureService'ten geçer — RAW ürüne yazılır. */
    public function testKuyruktanTasimaRawBlogunuUruneYazar(): void
    {
        $id = $this->seedCapture('eeeeeeee-1111-4222-8333-444444444444', 'RAW ürünü');
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'RAW hedefi']))['data']['id'];

        $this->write('POST', '/api/inbox/assign', ['ids' => [$id], 'list_id' => $listId]);

        $raw = $this->pdo->query('SELECT raw_attributes FROM products ORDER BY id DESC LIMIT 1')->fetchColumn();
        self::assertNotEmpty($raw, 'Taşımada da RAW blok ürüne yazılmalı (tek yol: CaptureService).');
        self::assertStringContainsString('原文', (string) $raw, 'Orijinal başlık RAW içinde durmalı.');
    }

    public function testTasinmisKayitTekrarTasinamaz(): void
    {
        $id = $this->seedCapture('cccccccc-1111-4222-8333-444444444444');
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Hedef']))['data']['id'];
        $this->write('POST', '/api/inbox/assign', ['ids' => [$id], 'list_id' => $listId]);

        $result = $this->json($this->write('POST', '/api/inbox/assign', ['ids' => [$id], 'list_id' => $listId]))['data'];

        self::assertSame(0, $result['moved']);
        self::assertCount(1, $result['failed']);
    }

    public function testSilmeKuyruktanKaldirir(): void
    {
        $id = $this->seedCapture('dddddddd-1111-4222-8333-444444444444');

        self::assertSame(204, $this->write('DELETE', '/api/inbox/' . $id)->getStatusCode());
        self::assertSame([], $this->json($this->call('GET', '/api/inbox'))['data']);
    }

    /** İE#13 B5 — arama/platform/tarih filtreleri + sayfalama meta'sı. */
    public function testFiltrelerVeSayfalamaMetasi(): void
    {
        $this->seedCapture('11111111-1111-4222-8333-444444444444', 'Mavi çanta');
        $this->seedCapture('22222222-1111-4222-8333-444444444444', 'Kırmızı bardak');

        $hepsi = $this->json($this->call('GET', '/api/inbox'));
        self::assertCount(2, $hepsi['data']);
        self::assertSame(2, $hepsi['meta']['total']);
        self::assertSame(20, $hepsi['meta']['per_page']);
        self::assertSame(['1688'], $hepsi['meta']['platforms']);

        $arama = $this->json($this->call('GET', '/api/inbox?q=çanta'));
        self::assertCount(1, $arama['data']);
        self::assertSame('Mavi çanta', $arama['data'][0]['name']);

        self::assertCount(0, $this->json($this->call('GET', '/api/inbox?platform=yok'))['data']);
        self::assertCount(2, $this->json($this->call('GET', '/api/inbox?from=2000-01-01'))['data']);
        self::assertCount(0, $this->json($this->call('GET', '/api/inbox?to=2000-01-01'))['data']);
    }

    /** Arama metnindeki LIKE jokerleri kaçırılır: "%" tüm kuyruğu döndürmemeli. */
    public function testAramaJokerKarakteriDuzMetinSayilir(): void
    {
        $this->seedCapture('33333333-1111-4222-8333-444444444444', 'Mavi çanta');

        self::assertCount(0, $this->json($this->call('GET', '/api/inbox?q=%25'))['data']);
    }

    /** İE#13 B3 — detay çekmecesi payload'dan zengin veriyi çıkarır. */
    public function testDetayUcuGorselKademeVaryasyonVeOzellikDoner(): void
    {
        $id = $this->seedCapture('44444444-1111-4222-8333-444444444444', 'Detaylı ürün');

        $data = $this->json($this->call('GET', '/api/inbox/' . $id))['data'];

        self::assertSame(['https://cbu01.alicdn.com/img/a.jpg'], $data['images']);
        self::assertSame([['min_qty' => 1, 'price_yuan' => '3.50'], ['min_qty' => 100, 'price_yuan' => '3.10']], $data['price_tiers']);
        self::assertSame([['label' => 'Gri', 'price_yuan' => '3.50']], $data['sku_matrix']);
        self::assertSame(['品牌' => 'Test', '容量' => '350ml'], $data['attributes']);
        self::assertSame('Test Satıcı', $data['seller_name']);
        self::assertStringContainsString('原文', (string) $data['raw_title']);
    }

    public function testOlmayanDetay404(): void
    {
        self::assertSame(404, $this->call('GET', '/api/inbox/99999')->getStatusCode());
    }

    /** İE#13 B1 — toplu silme; taşınmış kayda dokunmaz. */
    public function testTopluSilmeKuyruguBosaltir(): void
    {
        $a = $this->seedCapture('55555555-1111-4222-8333-444444444444');
        $b = $this->seedCapture('66666666-1111-4222-8333-444444444444');
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Hedef']))['data']['id'];
        $c = $this->seedCapture('77777777-1111-4222-8333-444444444444');
        $this->write('POST', '/api/inbox/assign', ['ids' => [$c], 'list_id' => $listId]);

        $sonuc = $this->json($this->write('POST', '/api/inbox/delete', ['ids' => [$a, $b, $c]]))['data'];

        self::assertSame(2, $sonuc['deleted'], 'Taşınmış (assigned) kayıt toplu silmeyle SİLİNMEZ — o artık üründür.');
        self::assertSame([], $this->json($this->call('GET', '/api/inbox'))['data']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
    }

    public function testTopluSilmeBosListeReddeder(): void
    {
        self::assertSame(422, $this->write('POST', '/api/inbox/delete', ['ids' => []])->getStatusCode());
    }

    /**
     * İE#13 B6 (K54) — kullanıcı çeviri önerisini kabul ettiyse ürün adı ÇEVİRİ olur,
     * orijinal başlık RAW içinde AYNEN kalır.
     */
    public function testKullaniciOnayliCeviriAdiUruneGecer_orijinalKorunur(): void
    {
        $id = $this->seedCapture('88888888-1111-4222-8333-444444444444', '便携式榨汁机');
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Çeviri hedefi']))['data']['id'];

        $this->write('POST', '/api/inbox/assign', [
            'ids' => [$id],
            'list_id' => $listId,
            'names' => [(string) $id => 'Taşınabilir meyve sıkacağı'],
        ]);

        $product = $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'][0];
        self::assertSame('Taşınabilir meyve sıkacağı', $product['name']);

        $raw = (string) $this->pdo->query('SELECT raw_attributes FROM products ORDER BY id DESC LIMIT 1')->fetchColumn();
        self::assertStringContainsString('便携式榨汁机', $raw, 'K54: orijinal başlık RAW içinde korunmalı.');
    }

    /** Ad geçersiz kılma yalnız GÖNDERİLEN kayıtlara uygulanır; boş ad yok sayılır. */
    public function testBosAdGecersizKilmaYokSayilir(): void
    {
        $id = $this->seedCapture('99999999-1111-4222-8333-444444444444', 'Orijinal ad');
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Hedef 2']))['data']['id'];

        $this->write('POST', '/api/inbox/assign', ['ids' => [$id], 'list_id' => $listId, 'names' => [(string) $id => '   ']]);

        $product = $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'][0];
        self::assertSame('Orijinal ad', $product['name']);
    }
}
