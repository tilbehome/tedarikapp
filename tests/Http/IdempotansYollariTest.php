<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Models\InboxRepository;
use Tests\Support\AuthTestCase;

/**
 * rc8-03 / DIŞ DENETİM F-07 — İDEMPOTANS ÜÇ YOLDA DA ATOMİK.
 *
 * SÖZLEŞME (K25): aynı `capture_id` ikinci kez gelirse İLK sonucun aynısı döner.
 *
 * BULGU: bu söz yalnız hedef-listeli yolda tutuluyordu (orada UNIQUE satırı
 * transaction'ın ilk yazımıdır ve kurtarma vardır). Varsayılan Gelen Kutusu ve
 * doğrulama-hatası yolları doğrudan `create()` çağırıyordu; controller'daki ön
 * kontrol ise bir OKUMADIR (TOCTOU). İki eşzamanlı tekrar isteği ön kontrolü
 * geçebilir, yarışı kaybeden ham `PDOException` alır — eklentiye 500 döner.
 * Eklenti kuyruğu (A5) tekrar denediği için bu yarış teoriden ibaret değildir.
 *
 * YARIŞIN TAKLİDİ: gerçek eşzamanlılık tek bağlantılı SQLite'ta üretilemez.
 * Bu yüzden yarışın SONUCU üretilir: satır, çağrı yapılmadan HEMEN ÖNCE başka
 * bir "istek" tarafından yazılır. Kurtarma yolu böylece GERÇEKTEN koşar —
 * yalnız ön kontrolün çalıştığını görmek yeterli olmazdı.
 */
final class IdempotansYollariTest extends AuthTestCase
{
    private string $csrf = '';
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
        $this->token = (string) $this->json(
            $this->call('POST', '/api/settings/extension-token', [], [Csrf::HEADER => $this->csrf]),
        )['data']['token'];
    }

    /** @return array<string, mixed> */
    private function yuk(string $captureId, bool $gecerli = true): array
    {
        $yuk = [
            'capture_id' => $captureId,
            'schema_version' => 2,
            'extension_version' => '2.0.1',
            'parser_version' => '1688-2026.08',
            'qty' => 3,
            'source' => [
                'platform' => '1688',
                'external_id' => '777',
                'url' => 'https://detail.1688.com/offer/777.html',
                'captured_at' => '2026-08-26T13:00:00+03:00',
            ],
            'raw' => ['title' => '洞洞鞋'],
            'normalized' => [
                'name' => 'Terlik',
                'price_yuan' => '15.90',
                'price_tiers' => [['min_qty' => 1, 'price_yuan' => '15.90']],
                'images' => [],
            ],
        ];

        if (!$gecerli) {
            // Doğrulama hatası yolu: fiyat alanı bozuk.
            $yuk['normalized']['price_yuan'] = 'ÜCRETSİZ';
        }

        return $yuk;
    }

    private function gonder(array $yuk): \Psr\Http\Message\ResponseInterface
    {
        return $this->call('POST', '/api/capture', $yuk, ['Authorization' => 'Bearer ' . $this->token]);
    }

    private function satirSayisi(string $captureId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM inbox_items WHERE capture_id = :c');
        $statement->execute(['c' => $captureId]);

        return (int) $statement->fetchColumn();
    }

    public function testVARSAYILANGELENKUTUSU_IKITEKRAR_TEKSATIR(): void
    {
        $captureId = 'f0700000-1111-4222-8333-000000000001';

        $ilk = $this->gonder($this->yuk($captureId));
        $ikinci = $this->gonder($this->yuk($captureId));

        self::assertSame(201, $ilk->getStatusCode());
        self::assertSame(201, $ikinci->getStatusCode());
        self::assertSame(
            $this->json($ilk)['data']['inbox_id'],
            $this->json($ikinci)['data']['inbox_id'],
            'Aynı capture_id İLK kaydın kimliğini döndürmeli.',
        );
        self::assertSame(1, $this->satirSayisi($captureId));
        self::assertTrue($this->json($ikinci)['data']['idempotent_replay']);
    }

    public function testDOGRULAMAHATASI_IKITEKRAR_TEKSATIR(): void
    {
        $captureId = 'f0700000-1111-4222-8333-000000000002';

        $ilk = $this->gonder($this->yuk($captureId, false));
        $ikinci = $this->gonder($this->yuk($captureId, false));

        self::assertSame(201, $ilk->getStatusCode(), (string) $ilk->getBody());
        self::assertSame('error', $this->json($ilk)['data']['status']);
        self::assertSame(
            $this->json($ilk)['data']['inbox_id'],
            $this->json($ikinci)['data']['inbox_id'],
        );
        self::assertSame(1, $this->satirSayisi($captureId));
    }

    public function testYARISI_KAYBEDEN_ISTEK_500_ALMAZ_GELENKUTUSU(): void
    {
        $captureId = 'f0700000-1111-4222-8333-000000000003';

        // Ön kontrol ile yazma ARASINDA başka bir istek satırı yazdı:
        // kurtarma yolu gerçekten koşsun diye satır elle önceden açılır.
        $depo = new InboxRepository($this->connection);
        $onceki = $depo->create([
            'capture_id' => $captureId,
            'status' => 'pending',
            'platform' => '1688',
            'payload_json' => '{}',
        ], $this->clock->now());

        // Bu çağrı `create()` yapar, UNIQUE'e çarpar ve MEVCUDU döndürür.
        $sonuc = $depo->rezerveEtVeyaBul([
            'capture_id' => $captureId,
            'status' => 'pending',
            'platform' => '1688',
            'payload_json' => '{}',
        ], $this->clock->now());

        self::assertSame($onceki, $sonuc['id'], 'Yarışı kaybeden çağrı MEVCUT satırı döndürmeli.');
        self::assertFalse($sonuc['yeni']);
        self::assertSame(1, $this->satirSayisi($captureId));
    }

    public function testUNIQUEDISI_HATA_YUTULMAZ(): void
    {
        // Kurtarma yolu yalnız "zaten var" için çalışır; başka bir hata
        // sessizce yutulursa gerçek arıza gizlenir.
        $depo = new InboxRepository($this->connection);

        $this->expectException(\PDOException::class);
        $depo->rezerveEtVeyaBul([
            'capture_id' => 'f0700000-1111-4222-8333-000000000004',
            'status' => 'pending',
            'platform' => '1688',
            // `payload_json` NOT NULL'dur; null vermek UNIQUE ihlali DEĞİLDİR.
            'payload_json' => null,
        ], $this->clock->now());
    }
}
