<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * K37 §B5 KRİTİK: çok adımlı yazma akışları tek transaction'dır — ortadaki adım
 * patlarsa YARIM KAYIT KALMAZ. Testler ikinci adımın tablosunu düşürerek yapay
 * hata üretir ve ilk adımın geri alındığını doğrular.
 *
 * K37 §B6: reorder yalnızca listedeki ürünlerin TAM permütasyonunu kabul eder.
 */
final class TransactionIntegrityTest extends AuthTestCase
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

    /** @return array<string, mixed> */
    private function createList(string $name = 'Transaction Testi'): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->json($this->write('POST', '/api/lists', ['name' => $name]))['data'];

        return $data;
    }

    private function countRows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS c FROM ' . $table)->fetch()['c'];
    }

    // ─────────────── §B5: rollback ───────────────

    public function testUrunOlusturmaTarihceYazilamazsaGeriAlinir(): void
    {
        $list = $this->createList();

        // İkinci adımı (durum tarihçesi) patlat: yarım kayıt üretmeye çalış.
        $this->pdo->exec('DROP TABLE product_status_history');

        $response = $this->write('POST', '/api/lists/' . $list['id'] . '/products', [
            'name' => 'Yarım Kalacak', 'qty' => 1, 'price_yuan' => '1.00',
        ]);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(0, $this->countRows('products'), 'Tarihçesiz ürün kaydı KALMAMALI (rollback).');

        $fresh = $this->json($this->call('GET', '/api/lists/' . $list['id']))['data'];
        self::assertSame(0, $fresh['revision'], 'Revision da geri alınmalı.');
    }

    public function testKurGuncellemeTarihceYazilamazsaGeriAlinir(): void
    {
        $before = $this->json($this->call('GET', '/api/settings'))['data'];

        $this->pdo->exec('DROP TABLE rate_history');

        $response = $this->write('PUT', '/api/settings/rates', ['yuan_tl' => '9.9999']);

        self::assertSame(500, $response->getStatusCode());
        $after = $this->json($this->call('GET', '/api/settings'))['data'];
        self::assertSame($before['yuan_tl'], $after['yuan_tl'], 'rate_history yazılamadıysa kur da DEĞİŞMEMELİ.');
    }

    public function testTopluIslemOrtadaPatlarsaTamamiGeriAlinir(): void
    {
        $list = $this->createList();
        $listId = (int) $list['id'];
        $a = $this->json($this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'A', 'qty' => 1, 'price_yuan' => '1.00',
        ]))['data'];
        $b = $this->json($this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'B', 'qty' => 1, 'price_yuan' => '1.00',
        ]))['data'];

        // Durum tarihçesi tablosunu düşür: toplu durum işlemi ilk üründe SQL hatası alır.
        $this->pdo->exec('DROP TABLE product_status_history');

        $response = $this->write('PATCH', '/api/products/bulk', [
            'ids' => [(int) $a['id'], (int) $b['id']],
            'action' => 'status',
            'status' => 'ordered',
        ]);

        self::assertSame(500, $response->getStatusCode());
        $statuses = $this->pdo->query('SELECT status FROM products ORDER BY id')->fetchAll();
        foreach ($statuses as $row) {
            self::assertSame('to_order', $row['status'], 'Hiçbir ürün durumu DEĞİŞMEMELİ (rollback).');
        }
    }

    // ─────────────── §B6: reorder tam permütasyon ───────────────

    /** @return array{int, list<int>} liste kimliği + 3 ürünün kimlikleri */
    private function listWithThreeProducts(): array
    {
        $list = $this->createList('Sıralama');
        $ids = [];
        foreach (['A', 'B', 'C'] as $name) {
            $ids[] = (int) $this->json($this->write('POST', '/api/lists/' . $list['id'] . '/products', [
                'name' => $name, 'qty' => 1, 'price_yuan' => '1.00',
            ]))['data']['id'];
        }

        return [(int) $list['id'], $ids];
    }

    public function testEksikKimlikliReorder422Doner(): void
    {
        [$listId, $ids] = $this->listWithThreeProducts();

        $response = $this->write('PATCH', '/api/lists/' . $listId . '/products/reorder', [
            'ordered_ids' => [$ids[0], $ids[1]], // C eksik
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('eksik', $this->json($response)['error']['fields']['ordered_ids']);
    }

    public function testYinelenenKimlikliReorder422Doner(): void
    {
        [$listId, $ids] = $this->listWithThreeProducts();

        $response = $this->write('PATCH', '/api/lists/' . $listId . '/products/reorder', [
            'ordered_ids' => [$ids[0], $ids[1], $ids[1]],
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('yinelenemez', $this->json($response)['error']['fields']['ordered_ids']);
    }

    public function testListeDisiKimlikliReorder422Doner(): void
    {
        [$listId, $ids] = $this->listWithThreeProducts();

        $response = $this->write('PATCH', '/api/lists/' . $listId . '/products/reorder', [
            'ordered_ids' => [...$ids, 99999],
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('listede olmayan', $this->json($response)['error']['fields']['ordered_ids']);
    }

    public function testTamPermutasyonKabulEdilir(): void
    {
        [$listId, $ids] = $this->listWithThreeProducts();

        $response = $this->write('PATCH', '/api/lists/' . $listId . '/products/reorder', [
            'ordered_ids' => [$ids[2], $ids[0], $ids[1]],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $this->json($response)['data']['updated']);
    }
}
