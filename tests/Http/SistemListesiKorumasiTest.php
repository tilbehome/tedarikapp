<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Inbox\DesteEylemi;
use Tests\Support\AuthTestCase;

/**
 * SİSTEM LİSTESİ KORUMASI (İE#21 B4 — PM'in iki şartı, 23 Ağu 2026).
 *
 * Keşif Havuzu kullanıcının kurduğu bir liste değildir; `products.list_id`
 * zorunlu olduğu için var olan bir uygulama detayıdır. PM iki şart koydu ve bu
 * dosya ikisini de sınar:
 *
 *  (a) HİÇBİR listelemede/sayımda görünmez — liste ekranı, liste seçicileri ve
 *      Panorama'nın "aktif liste" sayısı aynı ucu (`GET /api/lists`) kullanır.
 *  (b) SİLİNEMEZ · İLETİLEMEZ · PAYLAŞILAMAZ — kapı SUNUCUDADIR; arayüzü atlayıp
 *      doğrudan API'ye giden istemci de aynı reddi alır.
 */
final class SistemListesiKorumasiTest extends AuthTestCase
{
    private string $csrf = '';
    private string $eklentiTokeni = '';
    private int $normalListe = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->eklentiTokeni = (string) $this->json(
            $this->write('POST', '/api/settings/extension-token'),
        )['data']['token'];

        $this->normalListe = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Eylül Listesi',
        ]))['data']['id'];
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    /** Havuzu GERÇEK yoldan doğurur: bir yakalamayı deste modundan havuza gönderir. */
    private function havuzuOlustur(): int
    {
        $yuk = [
            'capture_id' => '9a7c1100-1111-4222-8333-000000000901',
            'schema_version' => 2,
            'extension_version' => '1.2.1',
            'parser_version' => '1688-2026.08',
            'qty' => 1,
            'source' => [
                'platform' => '1688',
                'external_id' => 'SYS-001',
                'url' => 'https://detail.1688.com/offer/901.html',
                'seller_name' => 'Havuz Satıcı',
                'captured_at' => '2026-08-23T10:00:00+03:00',
            ],
            'raw' => ['title' => 'SYS-001 原文'],
            'normalized' => [
                'name' => 'Havuz ürünü',
                'price_yuan' => '10.00',
                'images' => ['https://cbu01.alicdn.com/img/a.jpg'],
                'price_tiers' => [['min_qty' => 1, 'price_yuan' => '10.00']],
            ],
        ];

        $inboxId = (int) $this->json(
            $this->call('POST', '/api/capture', $yuk, ['Authorization' => 'Bearer ' . $this->eklentiTokeni]),
        )['data']['inbox_id'];

        $veri = $this->json($this->write('POST', '/api/inbox/deste', [
            'id' => $inboxId,
            'hedef' => DesteEylemi::HEDEF_HAVUZ,
        ]))['data'];

        return (int) $veri['liste_id'];
    }

    // ───────────────── ŞART (a): görünmezlik ─────────────────

    public function testHavuzListeListelemesindeGORUNMEZ(): void
    {
        $havuz = $this->havuzuOlustur();

        $liste = $this->json($this->call('GET', '/api/lists'))['data'];
        $kimlikler = array_map(static fn (array $satir): int => (int) $satir['id'], $liste);

        self::assertNotContains($havuz, $kimlikler, 'Sistem listesi liste ekranında görünmemeli.');
        self::assertContains($this->normalListe, $kimlikler, 'Normal liste kaybolmamalı.');
    }

    public function testPanoramaAktifListeSAYIMINAGIRMEZ(): void
    {
        $havuz = $this->havuzuOlustur();

        // Panorama "aktif liste" sayısını bu uçtan alır; havuz pasif açılsa bile
        // süzgeçsiz sayımda görünmediğini burada kanıtlıyoruz.
        $hepsi = $this->json($this->call('GET', '/api/lists'))['data'];
        $pasifler = $this->json($this->call('GET', '/api/lists?visibility=passive'))['data'];

        self::assertCount(1, $hepsi, 'Sayımda yalnız kullanıcının listesi olmalı.');
        self::assertSame([], $pasifler, 'Havuz, pasif süzgecinde de görünmemeli.');

        // Kayıt gerçekten DURUYOR — gizlenen şey satır değil, görünürlüğü.
        $sayim = $this->pdo->prepare('SELECT COUNT(*) FROM lists WHERE id = :id AND deleted_at IS NULL');
        $sayim->execute(['id' => $havuz]);
        self::assertSame(1, (int) $sayim->fetchColumn());
    }

    public function testAramaSonuclarindaDaGORUNMEZ(): void
    {
        $this->havuzuOlustur();

        $sonuc = $this->json($this->call('GET', '/api/lists?q=' . rawurlencode('Keşif')))['data'];

        self::assertSame([], $sonuc, 'Adıyla aransa bile sistem listesi bulunmamalı.');
    }

    // ───────────────── ŞART (b): silinemez/iletilemez/paylaşılamaz ─────────────────

    public function testHavuzSILINEMEZ(): void
    {
        $havuz = $this->havuzuOlustur();

        $yanit = $this->write('DELETE', '/api/lists/' . $havuz);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('SYSTEM_LIST', $this->json($yanit)['error']['code']);

        // Silinseydi havuzdaki ürünler yetim kalırdı: satır YERİNDE durmalı.
        $satir = $this->pdo->prepare('SELECT deleted_at FROM lists WHERE id = :id');
        $satir->execute(['id' => $havuz]);
        self::assertNull($satir->fetchColumn());
    }

    public function testHavuzILETILEMEZ(): void
    {
        $havuz = $this->havuzuOlustur();

        $yanit = $this->write('PATCH', '/api/lists/' . $havuz, ['status' => 'sent']);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('SYSTEM_LIST', $this->json($yanit)['error']['code']);

        // Durum da kur kilidi de dokunulmamış olmalı: iletilseydi kur kilitlenirdi.
        $satir = $this->pdo->prepare('SELECT status, rate_locked_at FROM lists WHERE id = :id');
        $satir->execute(['id' => $havuz]);
        $veri = $satir->fetch();
        self::assertSame('draft', $veri['status']);
        self::assertNull($veri['rate_locked_at']);
    }

    public function testHavuzPAYLASILAMAZ(): void
    {
        $havuz = $this->havuzuOlustur();

        $yanit = $this->write('POST', '/api/lists/' . $havuz . '/share');

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('SYSTEM_LIST', $this->json($yanit)['error']['code']);

        $satir = $this->pdo->prepare('SELECT share_token_hash FROM lists WHERE id = :id');
        $satir->execute(['id' => $havuz]);
        self::assertNull($satir->fetchColumn());
    }

    public function testNormalListeAYNIYOLLARLACALISMAYADEVAMEDER(): void
    {
        $this->havuzuOlustur();

        // Koruma yalnız sistem listesine bakar; kullanıcının listesi eskisi gibi
        // iletilir ve paylaşılır. Aksi hâlde kapı çok geniş kapanmış olurdu.
        $ilet = $this->write('PATCH', '/api/lists/' . $this->normalListe, ['status' => 'sent']);
        self::assertSame(200, $ilet->getStatusCode(), (string) $ilet->getBody());

        $paylas = $this->write('POST', '/api/lists/' . $this->normalListe . '/share');
        self::assertSame(200, $paylas->getStatusCode(), (string) $paylas->getBody());

        $sil = $this->write('DELETE', '/api/lists/' . $this->normalListe);
        self::assertSame(204, $sil->getStatusCode());
    }

    public function testHavuzYOKKENLISTELEMEBOZULMAZ(): void
    {
        // Havuz hiç doğmamışken `haric_id` süzgeci devreye girmemeli — aksi hâlde
        // temiz kurulumda liste ekranı boş kalırdı.
        $liste = $this->json($this->call('GET', '/api/lists'))['data'];

        self::assertCount(1, $liste);
        self::assertSame($this->normalListe, (int) $liste[0]['id']);
    }
}
