<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * İE#11 — yakalama zinciri (docs/04 §2c v2): token → Bearer capture → kuyruk/ürün.
 *
 * KRİTİK kurallar: token'sız 401; idempotans (aynı capture_id ikinci kez yeni kayıt
 * AÇMAZ); doğrulanamayan gövde error statüsüyle SAKLANIR (veri kaybolmaz); K25
 * mükerrer uyarısı engel değil; hedef liste seçiliyse doğrudan ürün + galeri REMOTE.
 */
final class CaptureEndpointsTest extends AuthTestCase
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
        // Panelden token üret (İE#11): tam değer yalnız bu yanıtta.
        $this->token = (string) $this->json(
            $this->call('POST', '/api/settings/extension-token', [], [Csrf::HEADER => $this->csrf]),
        )['data']['token'];
    }

    /** @param array<string, mixed> $payload */
    private function capture(array $payload, ?string $token = null): ResponseInterface
    {
        return $this->call('POST', '/api/capture', $payload, [
            'Authorization' => 'Bearer ' . ($token ?? $this->token),
        ]);
    }

    /** @return array<string, mixed> */
    private function validPayload(string $captureId = '9f1c8d2e-4b7a-4c31-9f0e-2a6d5b3c8e11'): array
    {
        return [
            'capture_id' => $captureId,
            'schema_version' => 2,
            'extension_version' => '1.0.0',
            'parser_version' => '1688-2026.08',
            'target_list_id' => null,
            'qty' => 24,
            'source' => [
                'platform' => '1688',
                'external_id' => '895133432293',
                'url' => 'https://detail.1688.com/offer/895133432293.html',
                'seller_name' => '永康市测试',
                'captured_at' => '2026-08-19T15:00:00+03:00',
            ],
            'raw' => [
                'title' => '跨境榨汁机便携式小型水果机',
                'price_blocks' => ['currentPrices' => [['price' => '9.00', 'beginAmount' => 24]]],
                'images' => ['https://cbu01.alicdn.com/img/ibank/a.jpg'],
            ],
            'normalized' => [
                'name' => '跨境榨汁机便携式小型水果机',
                'price_yuan' => '9.00',
                'price_tiers' => [['min_qty' => 24, 'price_yuan' => '9.00']],
                'images' => ['https://cbu01.alicdn.com/img/ibank/a.jpg', 'https://cbu01.alicdn.com/img/ibank/b.jpg'],
                'sku_matrix' => [['props' => ['颜色' => '白色'], 'price_yuan' => '9.00', 'min_qty' => 24]],
                'video_url' => null,
            ],
        ];
    }

    public function testTokensizIstek401(): void
    {
        self::assertSame(401, $this->call('POST', '/api/capture', $this->validPayload())->getStatusCode());
        self::assertSame(401, $this->capture($this->validPayload(), 'tdk_yanlis-token')->getStatusCode());
    }

    public function testGecerliYakalamaKuyrugaDuser(): void
    {
        $response = $this->capture($this->validPayload());

        self::assertSame(201, $response->getStatusCode());
        $data = $this->json($response)['data'];
        self::assertSame('pending', $data['status']);
        self::assertNull($data['product_id']);
        self::assertNull($data['duplicate']);

        $queue = $this->json($this->call('GET', '/api/inbox'))['data'];
        self::assertCount(1, $queue);
        self::assertSame('跨境榨汁机便携式小型水果机', $queue[0]['name']);
    }

    public function testIdempotans_AyniCaptureIdYeniKayitAcmaz(): void
    {
        $payload = $this->validPayload();
        $first = $this->json($this->capture($payload))['data'];
        $second = $this->json($this->capture($payload))['data'];

        self::assertSame($first['inbox_id'], $second['inbox_id'], 'Aynı capture_id AYNI kaydı döndürmeli.');
        self::assertTrue($second['idempotent_replay']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM inbox_items')->fetchColumn());
    }

    public function testDogrulanamayanGovdeHamHaliyleSaklanir(): void
    {
        $broken = $this->validPayload('11111111-2222-4333-8444-555555555555');
        unset($broken['normalized']['name']); // zorunlu alan eksik

        $response = $this->capture($broken);

        self::assertSame(201, $response->getStatusCode(), 'Veri KAYBOLMAZ — 201 + error statüsü.');
        self::assertSame('error', $this->json($response)['data']['status']);
        $row = $this->pdo->query('SELECT status, payload_json FROM inbox_items')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('error', $row['status']);
        self::assertStringContainsString('currentPrices', (string) $row['payload_json'], 'RAW blok aynen saklanmalı.');
    }

    public function testHedefListeSecilirseDogrudanUrunAcilirGaleriRemote(): void
    {
        $listId = (int) $this->json($this->call('POST', '/api/lists', ['name' => 'Yakalama listesi'], [Csrf::HEADER => $this->csrf]))['data']['id'];
        $payload = $this->validPayload('22222222-3333-4444-8555-666666666666');
        $payload['target_list_id'] = $listId;

        $data = $this->json($this->capture($payload))['data'];

        self::assertSame('assigned', $data['status']);
        self::assertIsInt($data['product_id']);

        $product = $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'][0];
        self::assertSame('跨境榨汁机便携式小型水果机', $product['name']);
        self::assertSame('9.00', $product['price_yuan']);
        self::assertSame(24, $product['qty']);
        self::assertSame('1688', $product['platform']);
        self::assertSame('895133432293', $product['external_id']);
        // İkinci görsel REMOTE galeri satırı olmalı (K47 hattı sonra indirir).
        $gallery = $this->pdo->query('SELECT storage_mode, source_url FROM product_images')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertNotEmpty($gallery);
        self::assertSame('remote', $gallery[0]['storage_mode']);
    }

    /**
     * İE#11 EK-3 (2): RAW blok ürünün raw_attributes'ına OLDUĞU GİBİ yazılır;
     * menşe bilgisi varsa ülke kolonuna (ISO alpha-2), yoksa null (uydurma yok).
     */
    public function testYakalamaRawBlogunuVeMenseyiUruneYazar(): void
    {
        $listId = (int) $this->json($this->call('POST', '/api/lists', ['name' => 'RAW listesi'], [Csrf::HEADER => $this->csrf]))['data']['id'];
        $payload = $this->validPayload('77777777-8888-4999-8aaa-bbbbbbbbbbbb');
        $payload['target_list_id'] = $listId;
        $payload['raw']['normalized_attributes'] = ['品牌' => '总裁小姐', '产地' => '浙江'];
        $payload['raw']['origin_text'] = '浙江';
        $payload['normalized']['country_of_origin'] = 'CN';

        $productId = (int) $this->json($this->capture($payload))['data']['product_id'];

        $row = $this->pdo->query('SELECT raw_attributes, country_of_origin FROM products WHERE id = ' . $productId)
            ->fetch(\PDO::FETCH_ASSOC);
        self::assertNotNull($row['raw_attributes'], 'RAW blok ürüne yazılmalı.');
        $decoded = json_decode((string) $row['raw_attributes'], true);
        self::assertSame('总裁小姐', $decoded['normalized_attributes']['品牌'] ?? null);
        self::assertArrayHasKey('price_blocks', $decoded, 'Orijinal fiyat blokları da RAW içinde durmalı.');
        self::assertSame('CN', $row['country_of_origin']);
    }

    /** Menşe yoksa kolon NULL kalır — sistem ülke uydurmaz. */
    public function testMenseYoksaKolonBosKalir(): void
    {
        $listId = (int) $this->json($this->call('POST', '/api/lists', ['name' => 'Menşesiz'], [Csrf::HEADER => $this->csrf]))['data']['id'];
        $payload = $this->validPayload('88888888-9999-4aaa-8bbb-cccccccccccc');
        $payload['target_list_id'] = $listId;

        $productId = (int) $this->json($this->capture($payload))['data']['product_id'];

        self::assertNull(
            $this->pdo->query('SELECT country_of_origin FROM products WHERE id = ' . $productId)->fetchColumn() ?: null,
        );
    }

    public function testMukerrerYakalamaUyariTasirAmaEngellemez(): void
    {
        $listId = (int) $this->json($this->call('POST', '/api/lists', ['name' => 'İlk liste'], [Csrf::HEADER => $this->csrf]))['data']['id'];
        $first = $this->validPayload('33333333-4444-4555-8666-777777777777');
        $first['target_list_id'] = $listId;
        $this->capture($first);

        $second = $this->validPayload('44444444-5555-4666-8777-888888888888');
        $data = $this->json($this->capture($second))['data'];

        self::assertSame('pending', $data['status'], 'Mükerrer ENGEL DEĞİL — kayıt yine açılır.');
        self::assertNotNull($data['duplicate']);
        self::assertSame('İlk liste', $data['duplicate']['list_name']);
    }

    public function testSecicilerBearerIleDoner(): void
    {
        $response = $this->call('GET', '/api/extension/selectors?platform=1688', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(200, $response->getStatusCode());
        $data = $this->json($response)['data'];
        self::assertSame('1688', $data['platform']);
        self::assertArrayHasKey('paths', $data);
        self::assertArrayHasKey('offer_id', $data['paths']);
    }

    public function testTokenIptaliEklentiyiAnindaDusurur(): void
    {
        self::assertSame(201, $this->capture($this->validPayload('55555555-6666-4777-8888-999999999999'))->getStatusCode());

        $this->call('DELETE', '/api/settings/extension-token', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(401, $this->capture($this->validPayload('66666666-7777-4888-9999-aaaaaaaaaaaa'))->getStatusCode());
    }
}
