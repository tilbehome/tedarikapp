<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Export\SafeCell;
use Tests\Support\AuthTestCase;

/**
 * İE#19 — HTTP yüzeyindeki sertleştirmelerin sözleşme testleri.
 *
 * Kapsam: G4 (bilgi sızıntısı), E7 (gövde sınırı), E11 (tekil ürün ucu),
 * E13 (maliyet etiketi/DDP'siz kâr), G8 (soft-delete listede paylaşım).
 */
final class IsEmri19SertlestirmeTest extends AuthTestCase
{
    /** @return array{csrf: string, listId: int} */
    private function girisVeListe(): array
    {
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', [
            'email' => 'admin@tedarikapp.test',
            'password' => 'cok-gizli-sifre',
            'remember' => false,
        ]);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $liste = $this->json($this->call('POST', '/api/lists', ['name' => 'Deneme listesi'], [Csrf::HEADER => $csrf]));

        return ['csrf' => $csrf, 'listId' => (int) $liste['data']['id']];
    }

    // ── G4: kimliksiz integrity ucu isim vermez ─────────────────────────────
    public function testKimliksizIntegrityUcuDOSYAADIVERMEZ(): void
    {
        $govde = $this->json($this->call('GET', '/api/system/integrity'));

        self::assertTrue($govde['success']);
        self::assertArrayNotHasKey('missing', $govde['data'], 'Kimliksiz uç eksik dosya ADLARINI döndürüyor.');
        self::assertArrayNotHasKey('modified', $govde['data']);
        self::assertArrayHasKey('missing_count', $govde['data'], 'Sinyal (sayı) kimliksiz kalmaya devam etmeli.');
        self::assertArrayHasKey('ok', $govde['data']);
    }

    public function testIntegrityDetayiOTURUMISTER(): void
    {
        self::assertSame(401, $this->call('GET', '/api/system/integrity/detay')->getStatusCode());
    }

    // ── E7: gövde sınırı ────────────────────────────────────────────────────
    public function testCokBuyukGovde413DONER(): void
    {
        ['csrf' => $csrf, 'listId' => $listId] = $this->girisVeListe();

        // Sınır varsayılan 512 KB; 1 MB'lık bir gövde reddedilmeli.
        $request = $this->rawRequest('POST', '/api/lists/' . $listId . '/products')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(Csrf::HEADER, $csrf)
            ->withHeader('Content-Length', (string) (1024 * 1024))
            ->withParsedBody(['name' => 'Deneme']);

        $yanit = $this->app()->handle($request);

        self::assertSame(413, $yanit->getStatusCode());
        self::assertStringContainsString('PAYLOAD_TOO_LARGE', (string) $yanit->getBody());
    }

    public function testNormalGovdeGECER(): void
    {
        ['csrf' => $csrf, 'listId' => $listId] = $this->girisVeListe();

        $yanit = $this->call('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Normal ürün',
            'qty' => 5,
            'price_yuan' => '12.00',
        ], [Csrf::HEADER => $csrf]);

        self::assertSame(201, $yanit->getStatusCode(), (string) $yanit->getBody());
    }

    // ── E11: tekil ürün ucu ─────────────────────────────────────────────────
    public function testTekilUrunUcuOTURUMISTER(): void
    {
        self::assertSame(401, $this->call('GET', '/api/products/1')->getStatusCode());
    }

    public function testTekilUrunUcuOLMAYANURUNDE404(): void
    {
        ['csrf' => $csrf] = $this->girisVeListe();

        self::assertSame(404, $this->call('GET', '/api/products/999999', null, [Csrf::HEADER => $csrf])->getStatusCode());
    }

    public function testTekilUrunUcuURUNUDONER(): void
    {
        ['csrf' => $csrf, 'listId' => $listId] = $this->girisVeListe();
        $urun = $this->json($this->call('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Tekil ürün',
            'qty' => 3,
            'price_yuan' => '9.00',
        ], [Csrf::HEADER => $csrf]));
        $urunId = (int) $urun['data']['id'];

        $yanit = $this->json($this->call('GET', '/api/products/' . $urunId, null, [Csrf::HEADER => $csrf]));

        self::assertTrue($yanit['success']);
        self::assertSame($urunId, $yanit['data']['id']);
        self::assertSame('Tekil ürün', $yanit['data']['name']);
    }

    // ── E13: DDP yoksa kâr hesaplanmaz ──────────────────────────────────────
    public function testDDPYokkenKARHESAPLANMAZ(): void
    {
        ['csrf' => $csrf, 'listId' => $listId] = $this->girisVeListe();

        $urun = $this->json($this->call('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Hedefi olan ürün',
            'qty' => 2,
            'price_yuan' => '10.00',
            'price_target_try' => '250.00',
        ], [Csrf::HEADER => $csrf]));

        self::assertNull(
            $urun['data']['unit_profit_try'],
            'DDP girilmemişken kâr üretiliyor — bu sayı Yuan etiket fiyatından türer ve gerçek kârdan yüksektir.',
        );
        self::assertNull($urun['data']['line_profit_try']);
        self::assertSame('250.00', $urun['data']['price_target_try'], 'Hedef fiyat korunmalı.');
    }

    public function testDDPVarkenKARHESAPLANIR(): void
    {
        ['csrf' => $csrf, 'listId' => $listId] = $this->girisVeListe();

        $urun = $this->json($this->call('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'DDP fiyatlı ürün',
            'qty' => 2,
            'price_yuan' => '10.00',
            'price_ddp_usd' => '3.00',
            'price_target_try' => '250.00',
        ], [Csrf::HEADER => $csrf]));

        self::assertNotNull($urun['data']['unit_profit_try']);
        self::assertNotNull($urun['data']['line_profit_try']);
    }

    // ── G8: çöpe atılan listenin paylaşımı askıya alınır ────────────────────
    public function testCOPEATILANLISTENINPAYLASIMSAYFASI404(): void
    {
        ['csrf' => $csrf, 'listId' => $listId] = $this->girisVeListe();

        $paylasim = $this->json($this->call('POST', '/api/lists/' . $listId . '/share', [], [Csrf::HEADER => $csrf]));
        $token = (string) preg_replace('#^.*/liste/#', '', (string) $paylasim['data']['share_url']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);

        // Kapıdan geçebildiğini önce doğrula (link canlı).
        $cerez = $this->paylasimCerezi($token, $listId, $csrf);
        self::assertSame(200, $this->call('GET', '/liste/' . $token, null, [], $cerez)->getStatusCode());

        // Liste çöp kutusuna alınır → paylaşım anında ölür.
        $this->call('DELETE', '/api/lists/' . $listId, null, [Csrf::HEADER => $csrf]);

        self::assertSame(
            404,
            $this->call('GET', '/liste/' . $token, null, [], $cerez)->getStatusCode(),
            'Çöpteki listenin paylaşım sayfası hâlâ açık.',
        );
    }

    // ── E13 etiket: çıktıda "₺ Karşılığı" kalmadı ───────────────────────────
    public function testCiktiSablonundaYAKLASIKURUNBEDELIETIKETI(): void
    {
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Export/TemplateV2.php');

        self::assertStringContainsString('Yaklaşık ürün bedeli (₺)', $kaynak);
        self::assertStringNotContainsString("'₺ Karşılığı'", $kaynak, 'Bayat etiket sütun tanımında duruyor.');
    }

    public function testSafeCellCsvdeGercektenKullaniliyor(): void
    {
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Export/CsvRenderer.php');

        self::assertStringContainsString('SafeCell::text', $kaynak);
        self::assertTrue(SafeCell::riskli('=1+1'));
    }
}
