<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Middleware\Csrf;
use App\Models\SettingsRepository;
use App\Services\Kuyruk\JobQueue;
use App\Services\MediaMigrator;
use App\Services\MediaService;
use App\Services\UrlGuard;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * v1.2.2 BLOK D1/D2 — YAKALAMA SIRASINDA İNDİRME YOK.
 *
 * ESKİ HÂL: eklenti yakalama gönderir, sunucu ANA GÖRSELİ o istek içinde
 * indirir (alicdn ~7,5 sn ölçüldü), sonra ürünü yazar. On görselli sayfada
 * eklenti "kaydediliyor…" der ve kullanıcı bekler; indirme zaman aşımına
 * uğrarsa istek 500'e yakın süreler harcar. Galeri zaten kuyruğa gidiyordu —
 * ana görselin gitmemesi tutarsızdı.
 *
 * YENİ HÂL:
 *   · Yakalama HİÇBİR görsel indirmez; ana görsel KAYNAK ADRESİYLE yazılır
 *     (`main_image` = kaynak URL) ve ürün `media_pending` ile işaretlenir.
 *   · Medya işi kuyruğa girer; tur ana görseli + galeriyi indirir ve mevcut
 *     satır CAS'ıyla (`WHERE main_image = :eski`) SONLANDIRIR.
 *   · Bekleme süresince panel görseli K47 vekiliyle gösterir ve "uzak" der.
 *
 * SONLANDIRMA YARIŞI DETERMİNİSTİKTİR: FakeMediaFetcher'ın indirmeden-önce
 * kancası, "indirme sürerken satır değişti" anını kesin üretir. CAS tutmaz,
 * indirilen dosya silinir, kullanıcının elle koyduğu görsel EZİLMEZ.
 */
final class YakalamaMedyaKuyruguTest extends AuthTestCase
{
    use TempDirectory;

    private string $csrf = '';
    private string $token = '';
    private FakeMediaFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetcher = new FakeMediaFetcher();
        $this->mediaFetcher = $this->fetcher;
        mkdir($this->tempPath('public/media'), 0775, true);

        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
        $this->token = (string) $this->json(
            $this->call('POST', '/api/settings/extension-token', [], [Csrf::HEADER => $this->csrf]),
        )['data']['token'];
    }

    /** @param array<string, mixed> $payload */
    private function capture(array $payload): ResponseInterface
    {
        return $this->call('POST', '/api/capture', $payload, ['Authorization' => 'Bearer ' . $this->token]);
    }

    /** @return list<string> */
    private function onGorsel(): array
    {
        $adresler = [];
        for ($i = 1; $i <= 10; $i++) {
            $adresler[] = 'https://cbu01.alicdn.com/img/ibank/kapak-' . $i . '.jpg';
        }

        return $adresler;
    }

    /**
     * @param list<string> $images
     * @return array<string, mixed>
     */
    private function payload(array $images, string $captureId = 'aaaa1111-2222-4333-8444-555566667777'): array
    {
        return [
            'capture_id' => $captureId,
            'schema_version' => 2,
            'extension_version' => '1.0.0',
            'parser_version' => '1688-2026.08',
            'target_list_id' => null,
            'qty' => 24,
            'source' => [
                'platform' => '1688',
                'external_id' => '895133432293',
                'url' => 'https://detail.1688.com/offer/895133432293.html',
                'seller_name' => '永康市测试',
                'captured_at' => '2026-09-04T15:00:00+03:00',
            ],
            'raw' => ['title' => '测试', 'price_blocks' => [], 'images' => $images],
            'normalized' => [
                'name' => '测试',
                'price_yuan' => '9.00',
                'price_tiers' => [['min_qty' => 24, 'price_yuan' => '9.00']],
                'images' => $images,
                'sku_matrix' => [],
                'video_url' => null,
            ],
        ];
    }

    private function listeAc(): int
    {
        return (int) $this->json(
            $this->call('POST', '/api/lists', ['name' => 'Medya listesi'], [Csrf::HEADER => $this->csrf]),
        )['data']['id'];
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(20, 20);
        self::assertNotFalse($image);
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    private function migrator(): MediaMigrator
    {
        $media = new MediaService(
            $this->tempRoot(),
            new UrlGuard(['alicdn.com', '1688.com']),
            $this->fetcher,
            new SettingsRepository($this->connection),
            8 * 1024 * 1024,
            'public/media',
        );

        return new MediaMigrator($this->connection, $media);
    }

    // ── D1: yakalama indirmez ───────────────────────────────────────────

    public function testONGORSELLIYAKALAMAINDIRMEZVEIKISANIYEALTINDABITER(): void
    {
        // Sahte indirici HİÇBİR adrese yanıt tanımlamıyor: indirme denenirse
        // istisna çıkar ve (eski yolda) ana görsel kaynağa düşerdi. Biz daha
        // güçlüsünü istiyoruz — indirme HİÇ DENENMEMELİ.
        $payload = $this->payload($this->onGorsel());
        $payload['target_list_id'] = $this->listeAc();

        $baslangic = microtime(true);
        $yanit = $this->capture($payload);
        $sure = microtime(true) - $baslangic;

        self::assertSame(201, $yanit->getStatusCode(), (string) $yanit->getBody());
        self::assertSame(0, $this->fetcher->callCount, 'Yakalama sırasında TEK indirme bile yapılmamalı.');
        self::assertLessThan(2.0, $sure, 'On görselli yakalama 2 saniyenin altında bitmeli (ölçülen: ' . round($sure, 2) . ' sn).');
    }

    public function testANAGORSELKAYNAKADRESIYLEYAZILIRVEBEKLIYORISARETLENIR(): void
    {
        $payload = $this->payload($this->onGorsel());
        $listId = $this->listeAc();
        $payload['target_list_id'] = $listId;

        $this->capture($payload);

        $urun = $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'][0];
        self::assertSame('https://cbu01.alicdn.com/img/ibank/kapak-1.jpg', $urun['main_image'], 'Ana görsel KAYNAK adresle yazılır.');
        self::assertTrue($urun['media_pending'], 'Kuyrukta bekleyen medya işi varken ürün "bekliyor" işaretlenir.');
        self::assertCount(9, $urun['images'], 'Galeri 9 uzak satır olarak yazılır.');

        $is = (new JobQueue(Connection::fromCallable(fn (): \PDO => $this->pdo)))->bul('medya', 'urun:' . $urun['id']);
        self::assertNotNull($is, 'Medya işi kuyruğa girmeli.');
    }

    // ── D1: kuyruk sonlandırır ──────────────────────────────────────────

    public function testKUYRUKANAGORSELIINDIRIRVEBEKLEMEKALKAR(): void
    {
        $payload = $this->payload($this->onGorsel());
        $listId = $this->listeAc();
        $payload['target_list_id'] = $listId;
        $this->capture($payload);
        foreach ($this->onGorsel() as $adres) {
            $this->fetcher->respondWith($adres, $this->jpeg(), 'image/jpeg');
        }
        $urunId = (int) $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'][0]['id'];

        $sonuc = $this->migrator()->urununMedyasi($urunId);
        self::assertSame(10, $sonuc['indirilen'], 'Ana görsel + 9 galeri = 10 indirme.');

        // İş bitti sayılsın diye kuyruk satırını kapatıyoruz (tur simülasyonu).
        $this->pdo->exec("UPDATE jobs SET durum = 'bitti' WHERE tur = 'medya'");

        $urun = $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'][0];
        self::assertStringStartsWith('/media/', $urun['main_image'], 'Ana görsel artık YEREL.');
        self::assertFalse($urun['media_pending']);
    }

    public function testSONLANDIRMAYARISIELLEKONANGORSELIEZMEZ(): void
    {
        // İndirme sürerken kullanıcı ana görseli değiştirdi: CAS tutmaz,
        // indirilen dosya silinir, kullanıcının seçimi kalır.
        $payload = $this->payload($this->onGorsel());
        $listId = $this->listeAc();
        $payload['target_list_id'] = $listId;
        $this->capture($payload);
        $urunId = (int) $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'][0]['id'];

        foreach ($this->onGorsel() as $adres) {
            $this->fetcher->respondWith($adres, $this->jpeg(), 'image/jpeg');
        }
        $this->fetcher->indirmedenOnce(function (string $url) use ($urunId): void {
            if (str_ends_with($url, 'kapak-1.jpg')) {
                $this->pdo->exec("UPDATE products SET main_image = '/media/elle-secilen.jpg' WHERE id = {$urunId}");
            }
        });

        $this->migrator()->urununMedyasi($urunId);

        self::assertSame(
            '/media/elle-secilen.jpg',
            $this->pdo->query("SELECT main_image FROM products WHERE id = {$urunId}")->fetchColumn(),
            'Kullanıcının seçimi EZİLMEMELİ.',
        );
        // İndirilen ama yazılamayan ana görsel dosyası diskte YETİM kalmamalı.
        $dosyalar = glob($this->tempPath('public/media') . '/*.jpg') ?: [];
        self::assertCount(9, $dosyalar, 'Yalnız 9 galeri dosyası kalmalı; CAS kaybeden ana görsel silinmeli.');
    }

    // ── D2: vekil + rozet verisi ────────────────────────────────────────

    public function testUZAKGORSELVEKILADRESIYLESUNULUR(): void
    {
        // Panel uzak görseli doğrudan alicdn'den çizemez (Referer ACL). Ürün
        // nesnesi vekil adresini de taşır; arayüz bekleme süresince onu çizer.
        $payload = $this->payload($this->onGorsel());
        $listId = $this->listeAc();
        $payload['target_list_id'] = $listId;
        $this->capture($payload);

        $urun = $this->json($this->call('GET', '/api/lists/' . $listId . '/products'))['data'][0];

        self::assertSame(
            '/api/media/proxy?url=' . rawurlencode('https://cbu01.alicdn.com/img/ibank/kapak-1.jpg'),
            $urun['main_image_gosterim'],
        );
        self::assertTrue($urun['main_image_uzak']);
    }
}
