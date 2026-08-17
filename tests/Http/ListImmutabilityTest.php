<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * K37 §B4 KRİTİK: terminal (completed/cancelled) liste DOKUNULMAZDIR.
 *
 * Ürün ekleme/taşıma/silme, durum ve alan düzenleme, yeniden sıralama, geri alma —
 * hepsi 422 `LIST_IMMUTABLE`. Reopen ucu yoktur; çözüm kopyalamadır.
 * Bilinçli istisna: arşivleme (visibility) ve çöp kutusu yaşam döngüsü.
 */
final class ListImmutabilityTest extends AuthTestCase
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

    /** @return array{list_id: int, product_id: int} Tamamlanmış liste + içindeki (received) ürün. */
    private function completedListWithProduct(): array
    {
        $list = $this->json($this->write('POST', '/api/lists', ['name' => 'Kapanmış Liste']))['data'];
        $listId = (int) $list['id'];
        $product = $this->json($this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Termos', 'qty' => 5, 'price_yuan' => '9.00',
        ]))['data'];
        $productId = (int) $product['id'];

        foreach (['ordered', 'in_transit', 'received'] as $status) {
            $response = $this->write('PATCH', '/api/products/' . $productId . '/status', ['status' => $status]);
            self::assertSame(200, $response->getStatusCode(), 'Hazırlık: ürün ' . $status . ' olmalı.');
        }
        foreach (['sent', 'ordered', 'completed'] as $status) {
            $response = $this->write('PATCH', '/api/lists/' . $listId, ['status' => $status]);
            self::assertSame(200, $response->getStatusCode(), 'Hazırlık: liste ' . $status . ' olmalı.');
        }

        return ['list_id' => $listId, 'product_id' => $productId];
    }

    private function assertImmutable(ResponseInterface $response, string $context): void
    {
        self::assertSame(422, $response->getStatusCode(), $context . ' 422 dönmeli.');
        self::assertSame('LIST_IMMUTABLE', $this->json($response)['error']['code'], $context);
    }

    public function testTamamlanmisListeyeHerMutasyonTuru422Doner(): void
    {
        ['list_id' => $listId, 'product_id' => $productId] = $this->completedListWithProduct();

        $this->assertImmutable(
            $this->write('POST', '/api/lists/' . $listId . '/products', ['name' => 'Yeni', 'qty' => 1, 'price_yuan' => '1.00']),
            'Ürün ekleme',
        );
        $this->assertImmutable(
            $this->write('PATCH', '/api/products/' . $productId, ['note' => 'değişiklik']),
            'Ürün alan düzenleme',
        );
        $this->assertImmutable(
            $this->write('PATCH', '/api/products/' . $productId . '/status', ['status' => 'in_transit']),
            'Ürün durum değişikliği',
        );
        $this->assertImmutable(
            $this->write('DELETE', '/api/products/' . $productId),
            'Ürün silme',
        );
        $this->assertImmutable(
            $this->write('PATCH', '/api/lists/' . $listId . '/products/reorder', ['ordered_ids' => [$productId]]),
            'Yeniden sıralama',
        );
        $this->assertImmutable(
            $this->write('PATCH', '/api/lists/' . $listId, ['name' => 'Yeni Ad']),
            'Liste alan düzenleme',
        );
        $this->assertImmutable(
            $this->write('PATCH', '/api/lists/' . $listId, ['status' => 'ordered']),
            'Reopen denemesi',
        );
    }

    public function testIptalEdilmisListeDeDokunulmazdir(): void
    {
        $list = $this->json($this->write('POST', '/api/lists', ['name' => 'İptal Edilen']))['data'];
        $listId = (int) $list['id'];
        $this->write('PATCH', '/api/lists/' . $listId, ['status' => 'cancelled']);

        $this->assertImmutable(
            $this->write('POST', '/api/lists/' . $listId . '/products', ['name' => 'X', 'qty' => 1, 'price_yuan' => '1.00']),
            'İptal listeye ürün ekleme',
        );
        $this->assertImmutable(
            $this->write('PATCH', '/api/lists/' . $listId, ['status' => 'draft']),
            'İptalden geri dönüş',
        );
    }

    public function testTopluIslemTerminalListeninUrununuAtlar(): void
    {
        ['product_id' => $productId] = $this->completedListWithProduct();

        $response = $this->write('PATCH', '/api/products/bulk', [
            'ids' => [$productId],
            'action' => 'status',
            'status' => 'in_transit',
        ]);

        self::assertSame(200, $response->getStatusCode());
        $data = $this->json($response)['data'];
        self::assertSame(0, $data['updated']);
        self::assertStringContainsString('LIST_IMMUTABLE', (string) $data['failed'][0]['error']);
    }

    public function testTerminalListeyeTopluTasima422Doner(): void
    {
        ['list_id' => $completedId] = $this->completedListWithProduct();

        $source = $this->json($this->write('POST', '/api/lists', ['name' => 'Kaynak']))['data'];
        $product = $this->json($this->write('POST', '/api/lists/' . $source['id'] . '/products', [
            'name' => 'Taşınacak', 'qty' => 1, 'price_yuan' => '2.00',
        ]))['data'];

        $this->assertImmutable(
            $this->write('PATCH', '/api/products/bulk', [
                'ids' => [(int) $product['id']],
                'action' => 'move',
                'target_list_id' => $completedId,
            ]),
            'Terminal listeye taşıma',
        );
    }

    public function testCopKutusundanTerminalListeyeGeriAlma422Doner(): void
    {
        // Hazırlık: A ürünü received, B ürünü silinmiş → liste tamamlanabilir (K15:
        // silinen ürün durum sayımına girmez). Sonra B'yi geri almak denenir.
        $list = $this->json($this->write('POST', '/api/lists', ['name' => 'Kapanacak']))['data'];
        $listId = (int) $list['id'];

        $a = $this->json($this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'A', 'qty' => 1, 'price_yuan' => '1.00',
        ]))['data'];
        $b = $this->json($this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'B', 'qty' => 1, 'price_yuan' => '1.00',
        ]))['data'];

        foreach (['ordered', 'in_transit', 'received'] as $status) {
            $this->write('PATCH', '/api/products/' . $a['id'] . '/status', ['status' => $status]);
        }
        self::assertSame(204, $this->write('DELETE', '/api/products/' . $b['id'])->getStatusCode());

        foreach (['sent', 'ordered', 'completed'] as $status) {
            $response = $this->write('PATCH', '/api/lists/' . $listId, ['status' => $status]);
            self::assertSame(200, $response->getStatusCode(), 'Hazırlık: liste ' . $status . ' olmalı.');
        }

        $this->assertImmutable(
            $this->write('POST', '/api/trash/products/' . $b['id'] . '/restore'),
            'Terminal listeye geri alma',
        );
    }

    public function testArsivlemeVeKopyalamaSerbestKalir(): void
    {
        ['list_id' => $listId] = $this->completedListWithProduct();

        $archive = $this->write('PATCH', '/api/lists/' . $listId, ['visibility' => 'archived']);
        self::assertSame(200, $archive->getStatusCode(), 'Arşivleme yaşam döngüsüdür, içerik mutasyonu değil.');

        $duplicate = $this->write('POST', '/api/lists/' . $listId . '/duplicate', ['name' => 'Devam Kopyası']);
        self::assertSame(201, $duplicate->getStatusCode(), 'Kopyalama terminal listenin ÇÖZÜM yoludur.');
        $copy = $this->json($duplicate)['data'];
        self::assertSame('draft', $copy['status']);
    }

    public function testTerminalListeCopKutusunaTasinabilir(): void
    {
        ['list_id' => $listId] = $this->completedListWithProduct();

        self::assertSame(204, $this->write('DELETE', '/api/lists/' . $listId)->getStatusCode(), 'K15 kaza koruması işler.');
    }
}
