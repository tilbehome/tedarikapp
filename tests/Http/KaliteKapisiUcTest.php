<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * İE#20 C8 — "HAZIR" kapısının SUNUCU tarafı.
 *
 * Kapının arayüzde olması yetmez: arayüzü atlayan her istemci (betik, eski panel,
 * elle atılan istek) onu delip geçer. Bu süit kuralın uçta zorlandığını kanıtlar.
 */
final class KaliteKapisiUcTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // C8 kolonları test şemasında yok; migration'ı burada koşuyoruz.
        $migration = require dirname(__DIR__, 2) . '/migrations/0026_arama_ve_kalite.php';
        $migration->up($this->pdo);

        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', [
            'email' => 'admin@tedarikapp.test',
            'password' => 'cok-gizli-sifre',
            'remember' => false,
        ]);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $liste = $this->json($this->call('POST', '/api/lists', ['name' => 'Kalite listesi'], [Csrf::HEADER => $this->csrf]));
        $this->listId = (int) $liste['data']['id'];
    }

    /** @param array<string, mixed> $alanlar */
    private function urunEkle(array $alanlar = []): int
    {
        $govde = array_merge([
            'name' => 'Termos',
            'qty' => 10,
            'price_yuan' => '12.00',
        ], $alanlar);

        $yanit = $this->json($this->call(
            'POST',
            '/api/lists/' . $this->listId . '/products',
            $govde,
            [Csrf::HEADER => $this->csrf],
        ));

        return (int) $yanit['data']['id'];
    }

    public function testEKSIKURUNHAZIRISARETLENEMEZ(): void
    {
        $urunId = $this->urunEkle(); // link, görsel, varyant, kategori YOK

        $yanit = $this->call(
            'PATCH',
            '/api/products/' . $urunId . '/hazir',
            ['hazir' => true],
            [Csrf::HEADER => $this->csrf],
        );

        self::assertSame(422, $yanit->getStatusCode());
        $govde = $this->json($yanit);
        self::assertStringContainsString('eksik', (string) $govde['error']['message']);
        // Eksikler İSİM İSİM dönmeli: "3 alan eksik" kullanıcıyı tahmine iter.
        self::assertContains('Kaynak linki', $govde['meta']['eksikler']);
        self::assertContains('Kategori', $govde['meta']['eksikler']);
    }

    public function testTAMURUNHAZIRISARETLENEBILIR(): void
    {
        $kategori = $this->json($this->call('POST', '/api/categories', ['name' => 'Mutfak'], [Csrf::HEADER => $this->csrf]));
        $urunId = $this->urunEkle([
            'url' => 'https://detail.1688.com/offer/9001.html',
            'main_image' => 'https://cbu01.alicdn.com/img/a.jpg',
            'sku_selection' => ['renk' => 'Gri'],
            'category_id' => (int) $kategori['data']['id'],
        ]);

        $yanit = $this->call(
            'PATCH',
            '/api/products/' . $urunId . '/hazir',
            ['hazir' => true],
            [Csrf::HEADER => $this->csrf],
        );

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        self::assertTrue($this->json($yanit)['data']['hazir']);
        self::assertSame(1, (int) $this->pdo->query('SELECT hazir FROM products WHERE id = ' . $urunId)->fetchColumn());
    }

    public function testHAZIRISARETIKALDIRILABILIR(): void
    {
        // Eksik ürün bile "hazır DEĞİL" işaretlenebilmeli: kapı yalnız hazır
        // İŞARETLEMEYİ kısıtlar, geri almayı değil.
        $urunId = $this->urunEkle();

        $yanit = $this->call(
            'PATCH',
            '/api/products/' . $urunId . '/hazir',
            ['hazir' => false],
            [Csrf::HEADER => $this->csrf],
        );

        self::assertSame(200, $yanit->getStatusCode());
        self::assertFalse($this->json($yanit)['data']['hazir']);
    }

    public function testLISTEHAZIRLIKOZETIEKSIKDOKUMUVERIR(): void
    {
        $this->urunEkle();
        $this->urunEkle(['name' => 'İkinci ürün']);

        $ozet = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/hazirlik', null, [Csrf::HEADER => $this->csrf]))['data'];

        self::assertSame(2, $ozet['urun']);
        self::assertSame(2, $ozet['hazir_olmayan']);
        self::assertTrue($ozet['tamamlanabilir']);
        self::assertSame(2, $ozet['eksik_dokumu']['Kategori'], 'Eksik dökümü hangi alanın kaç üründe eksik olduğunu söylemeli.');
    }

    public function testBOSLISTETAMAMLANABILIRDEGIL(): void
    {
        $ozet = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/hazirlik', null, [Csrf::HEADER => $this->csrf]))['data'];

        self::assertSame(0, $ozet['urun']);
        self::assertFalse($ozet['tamamlanabilir']);
        self::assertStringContainsString('BOŞ', (string) $ozet['neden']);
    }

    public function testOTURUMSUZERISIMYOK(): void
    {
        // CSRF katmanı Auth'tan ÖNCE yanıt verdiği için durum kodu 403 olabilir;
        // sınanan şey kodun kendisi değil, isteğin GEÇMEMESİDİR.
        $durum = $this->call('PATCH', '/api/products/1/hazir', ['hazir' => true])->getStatusCode();

        self::assertContains($durum, [401, 403], 'Oturumsuz istek kapıdan geçti.');
    }
}
