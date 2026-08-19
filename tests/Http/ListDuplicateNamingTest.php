<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * İE#10 5a — kopya adlandırma: "(kopya) (kopya)" yığılması biter.
 * Canlı vaka: 5 liste birden "test (kopya) (kopya)" olmuştu, ayırt edilemiyordu.
 */
final class ListDuplicateNamingTest extends AuthTestCase
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

    private function duplicate(int $listId): string
    {
        $response = $this->call('POST', '/api/lists/' . $listId . '/duplicate', [], [Csrf::HEADER => $this->csrf]);
        self::assertSame(201, $response->getStatusCode());

        return (string) $this->json($response)['data']['name'];
    }

    private function createList(string $name): int
    {
        return (int) $this->json($this->call('POST', '/api/lists', ['name' => $name], [Csrf::HEADER => $this->csrf]))['data']['id'];
    }

    public function testKopyaZinciriNumaralanir(): void
    {
        $original = $this->createList('test');

        self::assertSame('test (kopya)', $this->duplicate($original));
        // Kopyanın kopyası: "(kopya) (kopya)" DEĞİL, taban ad + sıradaki numara.
        $lists = $this->json($this->call('GET', '/api/lists'))['data'];
        $firstCopyId = (int) $lists[array_search('test (kopya)', array_column($lists, 'name'), true)]['id'];
        self::assertSame('test (kopya 2)', $this->duplicate($firstCopyId));
        self::assertSame('test (kopya 3)', $this->duplicate($original), 'Numara mevcut EN BÜYÜĞÜN devamı olmalı.');
    }

    public function testEskiYigilmisAdlarBozulmaz(): void
    {
        // Canlıdaki mevcut "(kopya) (kopya)" kayıtlarına dokunulmaz; onların kopyası
        // kendi tabanından numaralanır.
        $legacy = $this->createList('test (kopya) (kopya)');

        $name = $this->duplicate($legacy);

        self::assertSame('test (kopya) (kopya 2)', $name, 'Taban "test (kopya)" olarak arındırılıp numaralanmalı.');
    }

    public function testElleVerilenAdAynenKullanilir(): void
    {
        $listId = $this->createList('orijinal');
        $response = $this->call('POST', '/api/lists/' . $listId . '/duplicate', ['name' => 'Eylül tekrar siparişi'], [Csrf::HEADER => $this->csrf]);

        self::assertSame('Eylül tekrar siparişi', $this->json($response)['data']['name']);
    }
}
