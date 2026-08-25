<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * OTURUM GRUBUNDA TOPLU İŞLEM ATOMİKLİĞİ (E2E-PNL-21).
 *
 * Aynı yakalama oturumundaki üç kayıt TEK istekle listeye alınır. Söz üç parçalı:
 *  1. üçü de listeye TEKİL satır olarak girer (mükerrer yok),
 *  2. üçü de Gelen Kutusu'ndan düşer (kısmi ara durum kalmaz),
 *  3. sonuç sayacı gerçeği söyler: `3 başarılı / 0 başarısız`.
 *
 * TERMİNAL LİSTE denemesi ayrıca sınanır: kapalı listeye toplu taşıma kabul
 * EDİLMEZ ve hiçbir kayıt yarım taşınmaz — "bazıları geçti" hâli, kullanıcının
 * neyi göndereceğini bilememesi demektir.
 */
final class TopluTasimaAtomikTest extends AuthTestCase
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
            'name' => 'LST-E2E-001',
        ]))['data']['id'];
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    /** Gerçek yakalama ucundan Gelen Kutusu'na bir kayıt düşürür. */
    private function yakala(string $disKimlik): int
    {
        $yuk = [
            'capture_id' => sprintf('%08x-7777-4888-8999-%012x', crc32($disKimlik), crc32($disKimlik)),
            'schema_version' => 2,
            'extension_version' => '2.0.0',
            'parser_version' => '1688-2026.08.2',
            'qty' => 1,
            'source' => [
                'platform' => '1688',
                'external_id' => $disKimlik,
                'url' => 'https://detail.1688.com/offer/' . crc32($disKimlik) . '.html',
                'seller_name' => 'Grup Satıcı',
                'captured_at' => '2026-08-25T10:00:00+03:00',
            ],
            'raw' => ['title' => $disKimlik . ' 原文'],
            'normalized' => [
                'name' => 'Yağlık ' . $disKimlik,
                'price_yuan' => '12.50',
                'images' => ['https://cbu01.alicdn.com/img/a.jpg'],
                'price_tiers' => [['min_qty' => 1, 'price_yuan' => '12.50']],
            ],
        ];

        return (int) $this->json(
            $this->call('POST', '/api/capture', $yuk, ['Authorization' => 'Bearer ' . $this->eklentiTokeni]),
        )['data']['inbox_id'];
    }

    private function listedekiUrunSayisi(): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM products WHERE list_id = :liste AND deleted_at IS NULL',
        );
        $statement->execute(['liste' => $this->listeId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function inboxDurumlari(int ...$idler): array
    {
        $durumlar = [];
        foreach ($idler as $id) {
            $statement = $this->pdo->prepare('SELECT status FROM inbox_items WHERE id = :id');
            $statement->execute(['id' => $id]);
            $durumlar[] = (string) $statement->fetchColumn();
        }

        return $durumlar;
    }

    public function testE2E_PNL_21_UCLU_GRUP_TEK_ISTEKTE_TASINIR(): void
    {
        $grup = [$this->yakala('DM-016'), $this->yakala('DM-023'), $this->yakala('DM-025')];

        $yanit = $this->write('POST', '/api/inbox/assign', [
            'list_id' => $this->listeId,
            'ids' => $grup,
        ]);

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        $veri = $this->json($yanit)['data'];

        // Sonuç sayacı gerçeği söyler: 3 başarılı / 0 başarısız.
        self::assertSame(3, $veri['moved'] ?? $veri['updated'] ?? -1, (string) $yanit->getBody());
        self::assertSame([], $veri['failed'] ?? []);

        // Listede ÜÇ TEKİL satır; mükerrer yok.
        self::assertSame(3, $this->listedekiUrunSayisi());

        // Üçü de desteden düştü: kısmi ara durum kalmadı.
        self::assertSame(['assigned', 'assigned', 'assigned'], $this->inboxDurumlari(...$grup));
    }

    public function testAYNIGRUBUNIKINCITASIMASIMUKERRERURETMEZ(): void
    {
        $grup = [$this->yakala('DM-031'), $this->yakala('DM-032')];

        $this->write('POST', '/api/inbox/assign', ['list_id' => $this->listeId, 'ids' => $grup]);
        $ikinci = $this->write('POST', '/api/inbox/assign', ['list_id' => $this->listeId, 'ids' => $grup]);

        // İkinci istek hata vermese de ürün SAYISI artmamalı: taşınmış kayıt
        // yeniden taşınmaz (idempotens · K25).
        self::assertContains($ikinci->getStatusCode(), [200, 422]);
        self::assertSame(2, $this->listedekiUrunSayisi());
    }

    public function testTERMINALLISTEYETOPLUTASIMAREDDEDILIR(): void
    {
        $grup = [$this->yakala('DM-041'), $this->yakala('DM-042')];

        // Listeyi terminal duruma taşı (K37 §B4: donmuş listeye ürün girmez).
        $this->write('PATCH', '/api/lists/' . $this->listeId, ['status' => 'cancelled']);

        $yanit = $this->write('POST', '/api/inbox/assign', [
            'list_id' => $this->listeId,
            'ids' => $grup,
        ]);

        // Kısmi taşıma OLMAMALI: ya hepsi ya hiçbiri.
        self::assertSame(0, $this->listedekiUrunSayisi(), 'Donmuş listeye tek satır bile girmemeli.');
        self::assertSame(['pending', 'pending'], $this->inboxDurumlari(...$grup));
        self::assertContains($yanit->getStatusCode(), [200, 422]);
    }
}
