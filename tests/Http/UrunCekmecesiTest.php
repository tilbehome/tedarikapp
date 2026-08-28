<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * ÜRÜN ÇEKMECESİ UCU (İE#21 B3 · `GET /api/products/{id}/cekmece`).
 *
 * Çekmece bir TIKLA açılır; bu yüzden ürünün tüm hikâyesi TEK istekte gelmelidir.
 * Testler iki şeyi tutar:
 *   · dolu üründe ürün + ilan + kademe + skor + yorum birlikte dönüyor,
 *   · veri yoksa NULL dönüyor — sıfır ya da boş dizi ile "veri var" izlenimi
 *     verilmiyor (K67).
 */
final class UrunCekmecesiTest extends AuthTestCase
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
            $this->call('POST', '/api/lists', ['name' => 'Çekmece listesi'], [Csrf::HEADER => $this->csrf]),
        )['data']['id'];
    }

    private function yakala(string $disKimlik): int
    {
        $yuk = [
            'capture_id' => sprintf('%08x-3333-4444-8555-%012x', crc32($disKimlik), crc32($disKimlik)),
            'schema_version' => 2,
            'extension_version' => '1.2.1',
            'parser_version' => '1688-2026.08',
            'qty' => 240,
            'target_list_id' => $this->listeId,
            'source' => [
                'platform' => '1688',
                'external_id' => $disKimlik,
                'url' => 'https://detail.1688.com/offer/' . crc32($disKimlik) . '.html',
                'seller_name' => '义乌市世博塑料制品厂',
                'captured_at' => '2026-08-23T10:00:00+03:00',
            ],
            'raw' => ['title' => '跨境新款多功能切菜器'],
            'normalized' => [
                'name' => 'Sebze doğrayıcı ' . $disKimlik,
                'price_yuan' => '26.90',
                'images' => ['https://cbu01.alicdn.com/img/a.jpg'],
                'price_tiers' => [
                    ['min_qty' => 2, 'price_yuan' => '26.90'],
                    ['min_qty' => 100, 'price_yuan' => '24.90'],
                ],
            ],
        ];

        return (int) $this->json(
            $this->call('POST', '/api/capture', $yuk, ['Authorization' => 'Bearer ' . $this->eklentiTokeni]),
        )['data']['product_id'];
    }

    /** @return array<string, mixed> */
    private function cekmece(int $urunId): array
    {
        $yanit = $this->call('GET', '/api/products/' . $urunId . '/cekmece');
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());

        /** @var array<string, mixed> $veri */
        $veri = $this->json($yanit)['data'];

        return $veri;
    }

    public function testTEKISTEKTEURUNILANKADEMEGELIR(): void
    {
        $urunId = $this->yakala('CK-001');

        $veri = $this->cekmece($urunId);

        self::assertSame($urunId, $veri['urun']['id']);
        self::assertSame(240, $veri['urun']['qty']);
        self::assertSame('1688', $veri['ilan']['platform']);
        self::assertSame('CK-001', $veri['ilan']['external_id']);
        self::assertSame('义乌市世博塑料制品厂', $veri['ilan']['satici_ad']);
        self::assertStringContainsString('多功能', (string) $veri['ilan']['baslik_orijinal']);

        self::assertCount(2, $veri['kademeler']);
        self::assertSame(2, $veri['kademeler'][0]['min_adet']);
        self::assertSame(100, $veri['kademeler'][1]['min_adet']);
    }

    public function testSKORYOKKENSIFIRDEGILNULLDONER(): void
    {
        $urunId = $this->yakala('CK-002');

        $veri = $this->cekmece($urunId);

        // Sinyal yok → skor hesaplanamaz. "0 puan" demek, kötü bir ürün demektir;
        // "bilmiyoruz" demek doğru olandır (K67).
        self::assertNull($veri['ilan']['skor']);
        self::assertNull($veri['ilan']['skor_bilesenleri']);
        self::assertNull($veri['ilan']['satis_adedi']);
        self::assertNull($veri['yorum_ozeti']);
    }

    public function testYORUMOZETIVARSADONER(): void
    {
        $urunId = $this->yakala('CK-003');

        // Sinyaller eklenti v2 ile gelecek; bugünkü davranış "gelirse gösterilir".
        $guncelle = $this->pdo->prepare(
            'UPDATE listings SET degerlendirme_adedi = 312, degerlendirme_puani = 4.70, skor = 62,
                 skor_bilesenleri = :bilesen WHERE product_id = :id',
        );
        $guncelle->execute(['bilesen' => json_encode(['satis' => 28]), 'id' => $urunId]);

        $veri = $this->cekmece($urunId);

        self::assertSame(312, $veri['yorum_ozeti']['adet']);
        self::assertSame(62, $veri['ilan']['skor']);
        self::assertSame(['satis' => 28], $veri['ilan']['skor_bilesenleri']);
        // Bant, skor motorunun eşiklerinden gelir — panel kendi eşiğini uydurmaz.
        self::assertSame(\App\Services\Ilan\SkorHesaplayici::bant(62), $veri['ilan']['bant']);
    }

    public function testELLEEKLENENURUNDEILANKAYDIVARDIRAMASINYALIYOKTUR(): void
    {
        // Panelden elle eklenen ürün: ilan kaydı "manuel" platformla açılmaz —
        // ilan kaydı yalnız yakalama yolunda doğar; çekmece bunu `null` der.
        $urunId = (int) $this->json($this->call('POST', '/api/lists/' . $this->listeId . '/products', [
            'name' => 'Elle eklenen ürün',
            'qty' => 5,
            'price_yuan' => '10.00',
        ], [Csrf::HEADER => $this->csrf]))['data']['id'];

        $veri = $this->cekmece($urunId);

        self::assertNull($veri['ilan']);
        self::assertSame([], $veri['kademeler']);
        self::assertNull($veri['yorum_ozeti']);
    }

    public function testYURTICIKIYASACIKCANULLDUR(): void
    {
        $urunId = $this->yakala('CK-004');

        $veri = $this->cekmece($urunId);

        // Boş dizi "kıyas yapıldı, sonuç yok" derdi; null "kaynak yok" der.
        self::assertArrayHasKey('yurtici_kiyas', $veri);
        self::assertNull($veri['yurtici_kiyas']);
    }

    public function testOLMAYANURUN404(): void
    {
        $yanit = $this->call('GET', '/api/products/999999/cekmece');

        self::assertSame(404, $yanit->getStatusCode());
    }

    public function testCEKMECEOTURUMISTER(): void
    {
        $urunId = $this->yakala('CK-005');
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        $yanit = $this->call('GET', '/api/products/' . $urunId . '/cekmece');

        self::assertSame(401, $yanit->getStatusCode());
    }
}
