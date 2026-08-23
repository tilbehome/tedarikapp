<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Inbox\DesteEylemi;
use Tests\Support\AuthTestCase;

/**
 * GELEN KUTUSU — DESTE MODU (İE#21 B4 · E2E-PNL-18/19).
 *
 * Deste modu Gelen Kutusu'nu 40 üründe 2 dakikada elemek içindir: tek tuş, tek
 * karar, tek veritabanı geçişi. Bu testler o sözün iki yarısını tutar —
 * her tuş DOĞRU hedefe gider ve son eylem GERİ ALINABİLİR.
 */
final class DesteModuTest extends AuthTestCase
{
    private string $csrf = '';
    private string $eklentiTokeni = '';
    private int $listeId = 0;

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

        $this->listeId = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Deste hedef listesi',
            'period' => 'Eylül 2026',
        ]))['data']['id'];
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    /**
     * Gelen Kutusu'na GERÇEK yakalama ucundan bir kayıt düşürür.
     *
     * Yükü elle tabloya yazmak cazipti ama yanlış olurdu: yakalama sözleşmesi
     * (v2) `capture_id`, `schema_version`, `extension_version` ve `parser_version`
     * ister; elle yazılan kayıt taşıma anında doğrulamadan geçemez ve test,
     * ürünün değil kendi kurgusunun hatasını gösterirdi.
     */
    private function yakalama(string $disKimlik): int
    {
        $yuk = [
            'capture_id' => sprintf('%08x-1111-4222-8333-%012x', crc32($disKimlik), crc32($disKimlik)),
            'schema_version' => 2,
            'extension_version' => '1.2.1',
            'parser_version' => '1688-2026.08',
            'qty' => 1,
            'source' => [
                'platform' => '1688',
                'external_id' => $disKimlik,
                'url' => 'https://detail.1688.com/offer/' . crc32($disKimlik) . '.html',
                'seller_name' => 'Deste Satıcı',
                'captured_at' => '2026-08-23T10:00:00+03:00',
            ],
            'raw' => ['title' => $disKimlik . ' 原文'],
            'normalized' => [
                'name' => 'Deste ürünü ' . $disKimlik,
                'price_yuan' => '12.50',
                'images' => ['https://cbu01.alicdn.com/img/a.jpg'],
                'price_tiers' => [['min_qty' => 1, 'price_yuan' => '12.50']],
            ],
        ];

        return (int) $this->json(
            $this->call('POST', '/api/capture', $yuk, ['Authorization' => 'Bearer ' . $this->eklentiTokeni]),
        )['data']['inbox_id'];
    }

    private function inboxDurumu(int $id): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM inbox_items WHERE id = :id');
        $statement->execute(['id' => $id]);

        return (string) $statement->fetchColumn();
    }

    // ─────────────────── E2E-PNL-18: üç hedef, üç tuş ───────────────────

    public function testE2E_PNL_18_SolOkCOPEGONDERIR(): void
    {
        $id = $this->yakalama('DM-001');

        $yanit = $this->write('POST', '/api/inbox/deste', ['id' => $id, 'hedef' => DesteEylemi::HEDEF_COP]);

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        $veri = $this->json($yanit)['data'];
        self::assertSame('cop', $veri['hedef']);
        // Çöpe atılan yakalama kayıttan düşer — desteye geri gelmez.
        self::assertSame('', $this->inboxDurumu($id));
        // Sahte bir "geri al" vaadi verilmez.
        self::assertFalse($veri['geri_alinabilir']);
    }

    public function testE2E_PNL_18_AsagiOkHAVUZAGONDERIR(): void
    {
        $id = $this->yakalama('DM-002');

        $yanit = $this->write('POST', '/api/inbox/deste', ['id' => $id, 'hedef' => DesteEylemi::HEDEF_HAVUZ]);

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        $veri = $this->json($yanit)['data'];
        self::assertNotNull($veri['urun_id'], (string) $yanit->getBody());
        self::assertSame('assigned', $this->inboxDurumu($id));

        // Havuz SİSTEM listesidir: sipariş listeleri arasında görünmez (pasif).
        $liste = $this->pdo->prepare('SELECT name, visibility FROM lists WHERE id = :id');
        $liste->execute(['id' => $veri['liste_id']]);
        $satir = $liste->fetch();
        self::assertSame(DesteEylemi::HAVUZ_ADI, $satir['name']);
        self::assertSame('passive', $satir['visibility']);
    }

    public function testE2E_PNL_18_SagOkLISTEYEGONDERIR(): void
    {
        $id = $this->yakalama('DM-003');

        $yanit = $this->write('POST', '/api/inbox/deste', [
            'id' => $id,
            'hedef' => DesteEylemi::HEDEF_LISTE,
            'list_id' => $this->listeId,
        ]);

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        $veri = $this->json($yanit)['data'];
        self::assertSame($this->listeId, $veri['liste_id']);

        // Ürün listede TEK satırdır — çapraz hedef ya da kopya oluşmaz.
        $sayim = $this->pdo->prepare(
            'SELECT COUNT(*) FROM products WHERE list_id = :liste AND deleted_at IS NULL',
        );
        $sayim->execute(['liste' => $this->listeId]);
        self::assertSame(1, (int) $sayim->fetchColumn());
    }

    public function testE2E_PNL_18_HEDEFSIZISTEKREDDEDILIR(): void
    {
        $id = $this->yakalama('DM-004');

        $yanit = $this->write('POST', '/api/inbox/deste', ['id' => $id, 'hedef' => 'baska']);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('pending', $this->inboxDurumu($id), 'Geçersiz istek kaydı DEĞİŞTİRMEMELİ.');
    }

    public function testE2E_PNL_18_LISTEHEDEFISIZLISTEIDISTER(): void
    {
        $id = $this->yakalama('DM-005');

        $yanit = $this->write('POST', '/api/inbox/deste', ['id' => $id, 'hedef' => DesteEylemi::HEDEF_LISTE]);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('pending', $this->inboxDurumu($id));
    }

    // ─────────────────── E2E-PNL-19: geri alma ───────────────────

    public function testE2E_PNL_19_SonEylemGERIALINIR(): void
    {
        $id = $this->yakalama('DM-006');
        $veri = $this->json($this->write('POST', '/api/inbox/deste', [
            'id' => $id,
            'hedef' => DesteEylemi::HEDEF_LISTE,
            'list_id' => $this->listeId,
        ]))['data'];

        $geri = $this->write('POST', '/api/inbox/deste/geri-al', [
            'urun_id' => $veri['urun_id'],
            'inbox_id' => $id,
        ]);

        self::assertSame(200, $geri->getStatusCode(), (string) $geri->getBody());
        self::assertTrue($this->json($geri)['data']['geri_alindi']);

        // Yakalama desteye DÖNER…
        self::assertSame('pending', $this->inboxDurumu($id));

        // …ve listede satır KALMAZ.
        $sayim = $this->pdo->prepare(
            'SELECT COUNT(*) FROM products WHERE list_id = :liste AND deleted_at IS NULL',
        );
        $sayim->execute(['liste' => $this->listeId]);
        self::assertSame(0, (int) $sayim->fetchColumn());
    }

    public function testE2E_PNL_19_IKINCIGERIALMAETKISIZDIR(): void
    {
        $id = $this->yakalama('DM-007');
        $veri = $this->json($this->write('POST', '/api/inbox/deste', [
            'id' => $id,
            'hedef' => DesteEylemi::HEDEF_LISTE,
            'list_id' => $this->listeId,
        ]))['data'];

        $this->write('POST', '/api/inbox/deste/geri-al', ['urun_id' => $veri['urun_id'], 'inbox_id' => $id]);
        $ikinci = $this->write('POST', '/api/inbox/deste/geri-al', ['urun_id' => $veri['urun_id'], 'inbox_id' => $id]);

        self::assertSame(200, $ikinci->getStatusCode());
        // Etkisiz ve AÇIKÇA söylenir — "geri alındı" demek sayaçları bozardı.
        self::assertFalse($this->json($ikinci)['data']['geri_alindi']);
        self::assertSame('pending', $this->inboxDurumu($id));
    }

    public function testHAVUZLISTESIBIRKEZOLUSUR(): void
    {
        $ilk = $this->json($this->write('POST', '/api/inbox/deste', [
            'id' => $this->yakalama('DM-008'),
            'hedef' => DesteEylemi::HEDEF_HAVUZ,
        ]))['data'];
        $ikinci = $this->json($this->write('POST', '/api/inbox/deste', [
            'id' => $this->yakalama('DM-009'),
            'hedef' => DesteEylemi::HEDEF_HAVUZ,
        ]))['data'];

        self::assertSame($ilk['liste_id'], $ikinci['liste_id'], 'Her yakalama için yeni havuz açılmamalı.');

        $sayim = $this->pdo->query(
            "SELECT COUNT(*) FROM lists WHERE name = '" . DesteEylemi::HAVUZ_ADI . "'",
        );
        self::assertSame(1, (int) $sayim->fetchColumn());
    }

    public function testOTURUMSUZDESTEYOK(): void
    {
        $id = $this->yakalama('DM-010');
        $this->write('POST', '/api/auth/logout');

        self::assertSame(
            401,
            $this->call('POST', '/api/inbox/deste', ['id' => $id, 'hedef' => 'havuz'])->getStatusCode(),
        );
    }
}
