<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;

/**
 * K47 Görev 3/4/5 — HTTP katmanı: kırık görsel dayanıklılığı + arşive taşıma ucu.
 *
 * Sahte indirici kullanılır (CI'da canlı alicdn isteği YOK — İE#9.6 §5). İndirme
 * hatası 403/404/zaman aşımının test eşdeğeridir: FakeMediaFetcher tanımsız adreste
 * MediaException fırlatır.
 */
final class MediaArchiveEndpointsTest extends AuthTestCase
{
    private string $csrf = '';
    private FakeMediaFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetcher = new FakeMediaFetcher();
        $this->mediaFetcher = $this->fetcher;
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

    /**
     * K47 Görev 4 — KRİTİK: indirme hatası (403/404/zaman aşımı) ürün kaydını BOZMAZ.
     * URL uzak olarak saklanır; panel yer tutucu + "yeniden dene" gösterir.
     */
    public function testIndirmeHatasiUrunKaydiniBozmaz(): void
    {
        $listId = $this->json($this->write('POST', '/api/lists', ['name' => 'Kırık görsel listesi']))['data']['id'];
        $url = 'https://cbu01.alicdn.com/img/ibank/indirilemeyen.jpg';

        $response = $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Görseli inmeyen ürün',
            'qty' => 1,
            'price_yuan' => '1.00',
            'main_image' => $url,
        ]);

        self::assertSame(201, $response->getStatusCode(), 'İndirme hatası ürün kaydını REDDETMEMELİ.');
        self::assertSame($url, $this->json($response)['data']['main_image'], 'URL uzak (remote) olarak aynen saklanmalı.');
    }

    /** Güvenlik reddi (beyaz liste dışı) ise ürün kaydı REDDEDİLİR — dayanıklılık SSRF kapısını gevşetmez. */
    public function testGuvenlikReddiKaydiReddetmeyeDevamEder(): void
    {
        $listId = $this->json($this->write('POST', '/api/lists', ['name' => 'SSRF listesi']))['data']['id'];

        $response = $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Yabancı görselli ürün',
            'qty' => 1,
            'price_yuan' => '1.00',
            'main_image' => 'https://evil.example.com/resim.jpg',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    // ─────────────── POST /api/system/media-migrate (K47 Görev 2) ───────────────

    public function testTasimaUcuOturumIster(): void
    {
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(401, $this->call('POST', '/api/system/media-migrate')->getStatusCode());
    }

    public function testTasimaUcuCsrfIster(): void
    {
        self::assertSame(403, $this->call('POST', '/api/system/media-migrate')->getStatusCode());
    }

    public function testTasimaBasarisizKaydiRaporlarVeBozmaz(): void
    {
        $listId = $this->json($this->write('POST', '/api/lists', ['name' => 'Taşıma listesi']))['data']['id'];
        $url = 'https://cbu01.alicdn.com/img/ibank/tasinamayan.jpg';
        $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Uzak görselli ürün',
            'qty' => 1,
            'price_yuan' => '1.00',
            'main_image' => $url,
        ]);

        $payload = $this->json($this->write('POST', '/api/system/media-migrate'));

        self::assertTrue($payload['success']);
        self::assertSame(0, $payload['data']['migrated']);
        self::assertCount(1, $payload['data']['failed']);
        self::assertSame($url, $payload['data']['failed'][0]['url']);
        self::assertSame(1, $payload['data']['remaining']);

        $stored = (string) $this->pdo->query('SELECT main_image FROM products ORDER BY id DESC LIMIT 1')->fetchColumn();
        self::assertSame($url, $stored, 'Başarısız taşıma kaydı BOZMAMALI.');
    }
}
