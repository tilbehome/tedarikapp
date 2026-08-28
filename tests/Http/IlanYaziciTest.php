<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * YAKALAMA → İLAN KAYDI (İE#21 B3 saha bulgusu, 23 Ağu 2026).
 *
 * BULGU: `listings` tablosunu yalnız tek seferlik göç betiği dolduruyordu; canlı
 * sıfırlamadan (K73) sonra gelen hiçbir yakalama ilan kaydı açmıyordu. Ürün≠ilan
 * ayrımı (K67) şemada duruyor, veri akışında yaşamıyordu — Keşif'te skor "—",
 * çekmecede kaynak/satıcı boştu.
 *
 * Bu testler akışın kapandığını kanıtlar: her yakalama bir ilan satırı açar,
 * kademeleri yazar, tekrar edildiğinde ÇOĞALTMAZ.
 */
final class IlanYaziciTest extends AuthTestCase
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
            $this->call('POST', '/api/settings/extension-token', [], [Csrf::HEADER => $this->csrf]),
        )['data']['token'];

        $this->listeId = (int) $this->json(
            $this->call('POST', '/api/lists', ['name' => 'İlan listesi'], [Csrf::HEADER => $this->csrf]),
        )['data']['id'];
    }

    /**
     * @param array<string, mixed> $fark
     *
     * @param bool $kademesiz kademe bloğunu TAMAMEN kaldırır — `array_replace_recursive`
     *                        boş diziyle mevcut kademeleri silmediği için ayrı bayrak gerekir
     */
    private function yakala(string $disKimlik, array $fark = [], bool $kademesiz = false): \Psr\Http\Message\ResponseInterface
    {
        $yuk = [
            'capture_id' => sprintf('%08x-2222-4333-8444-%012x', crc32($disKimlik), crc32($disKimlik)),
            'schema_version' => 2,
            'extension_version' => '1.2.1',
            'parser_version' => '1688-2026.08',
            'qty' => 1,
            'target_list_id' => $this->listeId,
            'source' => [
                'platform' => '1688',
                'external_id' => $disKimlik,
                'url' => 'https://detail.1688.com/offer/' . crc32($disKimlik) . '.html',
                'seller_name' => 'İlan Satıcı',
                'seller_url' => 'https://sirket.1688.com/ilan-satici',
                'captured_at' => '2026-08-23T10:00:00+03:00',
            ],
            'raw' => ['title' => $disKimlik . ' 原文标题'],
            'normalized' => [
                'name' => 'İlan ürünü ' . $disKimlik,
                'price_yuan' => '26.90',
                'images' => ['https://cbu01.alicdn.com/img/a.jpg'],
                'price_tiers' => [
                    ['min_qty' => 1, 'price_yuan' => '26.90'],
                    ['min_qty' => 100, 'price_yuan' => '24.90'],
                ],
            ],
        ];

        $govde = array_replace_recursive($yuk, $fark);
        if ($kademesiz) {
            unset($govde['normalized']['price_tiers']);
        }

        return $this->call('POST', '/api/capture', $govde, [
            'Authorization' => 'Bearer ' . $this->eklentiTokeni,
        ]);
    }

    /** Hedef liste VERİLMEDEN yakalar: kayıt Gelen Kutusu'nda bekler. */
    private function yakalaHedefsiz(string $disKimlik): \Psr\Http\Message\ResponseInterface
    {
        return $this->yakala($disKimlik, ['target_list_id' => null]);
    }

    /** @return array<string, mixed>|null */
    private function ilan(int $urunId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM listings WHERE product_id = :id');
        $statement->execute(['id' => $urunId]);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    private function urunId(\Psr\Http\Message\ResponseInterface $yanit): int
    {
        return (int) $this->json($yanit)['data']['product_id'];
    }

    public function testYakalamaILANKAYDIACAR(): void
    {
        $urunId = $this->urunId($this->yakala('IY-001'));

        $ilan = $this->ilan($urunId);

        self::assertNotNull($ilan, 'Yakalama ilan kaydı açmalı — yoksa Keşif skorsuz kalır.');
        self::assertSame('1688', $ilan['platform_kod']);
        self::assertSame('IY-001', $ilan['external_id']);
        self::assertSame('İlan Satıcı', $ilan['satici_ad']);
        self::assertSame('https://sirket.1688.com/ilan-satici', $ilan['satici_url']);
        self::assertStringContainsString('原文标题', (string) $ilan['baslik_orijinal']);
        self::assertSame('CNY', $ilan['para_birimi']);
    }

    public function testFIYATKADEMELERIYAZILIR(): void
    {
        $urunId = $this->urunId($this->yakala('IY-002'));
        $ilan = $this->ilan($urunId);
        self::assertNotNull($ilan);

        $statement = $this->pdo->prepare(
            'SELECT min_adet, birim_fiyat FROM listing_price_tiers WHERE listing_id = :id ORDER BY min_adet',
        );
        $statement->execute(['id' => $ilan['id']]);
        $kademeler = $statement->fetchAll();

        self::assertCount(2, $kademeler);
        self::assertSame(1, (int) $kademeler[0]['min_adet']);
        self::assertSame(100, (int) $kademeler[1]['min_adet']);
        // Para değeri STRING taşınır; ondalık kaybı olmamalı (K14).
        self::assertSame('24.9000', number_format((float) $kademeler[1]['birim_fiyat'], 4, '.', ''));
    }

    public function testKADEMESIZYAKALAMAILANIYINEACAR(): void
    {
        $urunId = $this->urunId($this->yakala('IY-003', [], true));

        $ilan = $this->ilan($urunId);

        // Kademe yoksa ilan yine açılır: kademe bir zenginleştirmedir, koşul değil.
        self::assertNotNull($ilan);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM listing_price_tiers WHERE listing_id = :id');
        $statement->execute(['id' => $ilan['id']]);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    public function testAYNIYAKALAMATEKRARIILANICOGALTMAZ(): void
    {
        $ilk = $this->urunId($this->yakala('IY-004'));

        // Ağ hatası sonrası eklenti aynı isteği tekrarlar (idempotans).
        $this->yakala('IY-004');

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM listings WHERE product_id = :id');
        $statement->execute(['id' => $ilk]);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testBILINMEYENSINYALSIFIRDEGILNULLDUR(): void
    {
        $urunId = $this->urunId($this->yakala('IY-005'));

        $ilan = $this->ilan($urunId);
        self::assertNotNull($ilan);

        // "0 satmış" ile "bilmiyoruz" aynı şey değildir (K67 disiplini): bugünkü
        // yakalama sözleşmesi bu sinyalleri taşımıyor, o hâlde NULL kalmalı.
        self::assertNull($ilan['satis_adedi']);
        self::assertNull($ilan['degerlendirme_puani']);
        self::assertNull($ilan['satici_puan']);
    }

    public function testGELENKUTUSUNDANTASIMADAILANACILIR(): void
    {
        // Hedef listesi verilmeden gelen yakalama Gelen Kutusu'nda bekler…
        $yanit = $this->yakalaHedefsiz('IY-006');
        $inboxId = (int) $this->json($yanit)['data']['inbox_id'];

        // …panelden taşındığında da ilan kaydı açılmalı: iki yol, tek sonuç.
        $tasima = $this->call('POST', '/api/inbox/assign', [
            'list_id' => $this->listeId,
            'ids' => [$inboxId],
        ], [Csrf::HEADER => $this->csrf]);
        self::assertSame(200, $tasima->getStatusCode(), (string) $tasima->getBody());

        $statement = $this->pdo->prepare('SELECT assigned_product_id FROM inbox_items WHERE id = :id');
        $statement->execute(['id' => $inboxId]);
        $urunId = (int) $statement->fetchColumn();

        self::assertNotNull($this->ilan($urunId), 'Gelen Kutusu yolu da ilan açmalı.');
    }
}
