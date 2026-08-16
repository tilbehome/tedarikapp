<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * İE#8 — panelin (E9 Aktivite, durum menüsü) dayandığı uçlar.
 *
 * K35 gereği smoke seviyesi: uç ayakta mı, kimlik doğrulaması istiyor mu, sözleşme
 * biçimi doğru mu. Durum makinesi kurallarının kendisi StateMachineTest'te sınanır.
 */
final class PanelSupportEndpointsTest extends AuthTestCase
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

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    public function testAktiviteUcuOturumIster(): void
    {
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        $response = $this->call('GET', '/api/activity');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('UNAUTHENTICATED', $this->json($response)['error']['code']);
    }

    public function testAktiviteKayitlariSayfaliDoner(): void
    {
        // Giriş akışının kendisi zaten aktivite kaydı üretir.
        $payload = $this->json($this->call('GET', '/api/activity'));

        self::assertTrue($payload['success']);
        self::assertNotEmpty($payload['data'], 'Giriş kayıtları aktivite günlüğüne yazılmalı.');
        self::assertSame(1, $payload['meta']['page']);
        self::assertSame(25, $payload['meta']['per_page']);
        self::assertGreaterThan(0, $payload['meta']['total']);

        $first = $payload['data'][0];
        foreach (['id', 'entity_type', 'entity_id', 'action', 'detail', 'ip', 'actor_type', 'created_at'] as $key) {
            self::assertArrayHasKey($key, $first);
        }
    }

    public function testAktiviteVarlikTuruneGoreSuzulur(): void
    {
        $data = $this->json($this->call('GET', '/api/activity?entity_type=auth'))['data'];

        self::assertNotEmpty($data);
        self::assertSame(['auth'], array_values(array_unique(array_column($data, 'entity_type'))));
    }

    public function testGecersizVarlikKimligi422Doner(): void
    {
        $response = $this->call('GET', '/api/activity?entity_id=abc');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('VALIDATION', $this->json($response)['error']['code']);
    }

    /**
     * PM eki (İE#8): `main_image` diğer URL alanlarıyla aynı doğrulamadan geçer.
     * Panelin görsel akışı gerçek güvenlik zincirine bağlıdır — http reddedilir.
     */
    public function testGorselAdresiHttpIseReddedilir(): void
    {
        $listId = $this->json($this->write('POST', '/api/lists', ['name' => 'Görsel denemesi']))['data']['id'];

        $response = $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Http görselli ürün',
            'qty' => 1,
            'price_yuan' => '1.00',
            'main_image' => 'http://cbu01.alicdn.com/resim.jpg',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('https://', $this->json($response)['error']['fields']['main_image']);
    }

    /** Beyaz liste dışı alan adı MediaService kapısında durdurulur (SSRF — docs/04 §2d). */
    public function testGorselAdresiBeyazListeDisiIseReddedilir(): void
    {
        $listId = $this->json($this->write('POST', '/api/lists', ['name' => 'Görsel denemesi 2']))['data']['id'];

        $response = $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Yabancı görselli ürün',
            'qty' => 1,
            'price_yuan' => '1.00',
            'main_image' => 'https://evil.example.com/resim.jpg',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('main_image', $this->json($response)['error']['fields']);
    }

    /** Panel ürünü yeniden kaydederken yerel medya yolu geri gelir; tekrar indirilmez. */
    public function testYerelMedyaYoluYenidenIndirilmez(): void
    {
        $listId = $this->json($this->write('POST', '/api/lists', ['name' => 'Görsel denemesi 3']))['data']['id'];
        $created = $this->json($this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Görselsiz ürün',
            'qty' => 1,
            'price_yuan' => '1.00',
        ]))['data'];

        $response = $this->write('PATCH', '/api/products/' . $created['id'], ['main_image' => '/media/abc123.jpg']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/media/abc123.jpg', $this->json($response)['data']['main_image']);
    }

    /** Panel durum menüsünü bu haritadan kurar; kaynak backend'dir (docs/04 §2b). */
    public function testDurumGecisHaritasiDoner(): void
    {
        $data = $this->json($this->call('GET', '/api/system/state-machine'))['data'];

        self::assertSame(['ordered', 'cancelled'], $data['product']['to_order']);
        // Geldi'den yalnızca BİR adım geri alınabilir; iptale geçilemez (docs/04 §2b).
        self::assertSame(['in_transit'], $data['product']['received']);
        self::assertSame([], $data['product']['cancelled'], 'İptal uç durumdur.');
        self::assertArrayHasKey('draft', $data['list']);
        self::assertContains('sent', $data['list']['draft']);
    }
}
