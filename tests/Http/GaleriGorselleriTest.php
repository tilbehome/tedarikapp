<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * D11a — GALERİ GÖRSELLERİ (saha bulgusu, 25 Ağu 2026).
 *
 * BULGU: ürün çekmecesi "5 görsel · video var" diyordu ama beş küçük resmin
 * yalnız biri doluydu; dördü BOŞ KARE çıkıyordu.
 *
 * İKİ AYRI KUSUR VARDI:
 *
 *  1. **Adres bozuluyordu.** Galeri satırları arşive taşınana kadar `path`
 *     alanında TAM ADRES tutar (https://cbu01.alicdn.com/...). `images()`
 *     her yola '/' ekliyordu → "/https://cbu01.alicdn.com/..." → tarayıcı
 *     kendi alanında arıyor, 404 alıyor, kare boş kalıyordu. Sayaç doğruydu,
 *     adresler bozuktu.
 *  2. **Galeri hiç indirilmiyordu.** Yakalamada yalnız ANA GÖRSEL
 *     MediaService'ten geçiyor; kalanlar `remote` kalıyordu. Taşıma hattı
 *     (`MediaMigrator`) vardı ama yalnız elle koşulan bir CLI'dan
 *     çağrılıyordu — yani pratikte hiç koşmuyordu.
 *
 * Bu test ikisini de kilitler.
 */
final class GaleriGorselleriTest extends AuthTestCase
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
            $this->call('POST', '/api/lists', ['name' => 'Galeri listesi'], [Csrf::HEADER => $this->csrf]),
        )['data']['id'];
    }

    private function yakala(): int
    {
        $yuk = [
            'capture_id' => '11a00000-3333-4444-8555-000000000011',
            'schema_version' => 2,
            'extension_version' => '2.0.0',
            'parser_version' => '1688-2026.08',
            'qty' => 10,
            'target_list_id' => $this->listeId,
            'source' => [
                'platform' => '1688',
                'external_id' => '895133432293',
                'url' => 'https://detail.1688.com/offer/895133432293.html',
                'captured_at' => '2026-08-25T20:20:00+03:00',
            ],
            'raw' => ['title' => '洞洞鞋'],
            'normalized' => [
                'name' => 'Terlik',
                'price_yuan' => '15.90',
                'price_tiers' => [['min_qty' => 1, 'price_yuan' => '15.90']],
                // Beş görsel: ilki ANA, kalan dördü galeri.
                'images' => [
                    'https://cbu01.alicdn.com/img/ibank/ana.jpg',
                    'https://cbu01.alicdn.com/img/ibank/bir.jpg',
                    'https://cbu01.alicdn.com/img/ibank/iki.jpg',
                    'https://cbu01.alicdn.com/img/ibank/uc.jpg',
                    'https://cbu01.alicdn.com/img/ibank/dort.jpg',
                ],
                'video_url' => 'https://cloud.video.taobao.com/play/u/1/p/1/e/6/t/1/v.mp4',
            ],
        ];

        $yanit = $this->call('POST', '/api/capture', $yuk, [
            'Authorization' => 'Bearer ' . $this->eklentiTokeni,
        ]);
        self::assertContains($yanit->getStatusCode(), [200, 201], (string) $yanit->getBody());

        return (int) $this->json($yanit)['data']['product_id'];
    }

    public function testUZAKGORSELADRESIBOZULMAZ(): void
    {
        $urunId = $this->yakala();

        $urun = $this->json($this->call('GET', '/api/products/' . $urunId . '/cekmece'))['data']['urun'];
        $gorseller = $urun['images'];

        self::assertNotSame([], $gorseller, 'Galeri satırları kaydedilmeli.');
        foreach ($gorseller as $gorsel) {
            // Eski hata: "/https://cbu01.alicdn.com/..." — tarayıcı 404 alırdı.
            self::assertStringStartsNotWith('/http', (string) $gorsel['url']);
            self::assertTrue($gorsel['uzak'], 'Henüz arşive alınmamış görsel İŞARETLENMELİ.');
        }
    }

    public function testYAKALAMAMEDYAISIYAZAR(): void
    {
        $urunId = $this->yakala();

        $satir = $this->pdo->query(
            "SELECT tur, anahtar, durum FROM jobs WHERE tur = 'medya' ORDER BY id DESC LIMIT 1",
        )->fetch(\PDO::FETCH_ASSOC);

        self::assertIsArray($satir, 'Yakalama bir MEDYA işi yazmalı — galeri kendiliğinden inmeli.');
        self::assertSame('urun:' . $urunId, $satir['anahtar']);
        self::assertSame('bekliyor', $satir['durum']);
    }

    public function testYERELGORSELUZAKISARETIALMAZ(): void
    {
        $urunId = $this->yakala();
        $this->pdo->exec(
            "UPDATE product_images SET path = 'public/media/ornek.jpg', storage_mode = 'local'
             WHERE product_id = " . $urunId,
        );

        $urun = $this->json($this->call('GET', '/api/products/' . $urunId . '/cekmece'))['data']['urun'];
        foreach ($urun['images'] as $gorsel) {
            self::assertFalse($gorsel['uzak']);
            self::assertStringStartsWith('/public/media/', (string) $gorsel['url']);
        }
    }
}
