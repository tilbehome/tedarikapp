<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;

/**
 * rc8-01 / DIŞ DENETİM F-01 — MEDYA HATTI GERÇEK KOMPOZİSYONLA SINANIR.
 *
 * SAHA KANITI (26 Ağu 2026): `AppBuilder` `CaptureApplier`e `MediaService`
 * geçmiyordu; parametre `?MediaService = null` olduğu için yanlış wiring
 * SESSİZ kaldı. Arşiv modunda her yakalama diske `<ad>.jpg.tmp` bırakıyor,
 * veritabanına ise çözülemeyen `/media/<ad>.jpg` yazıyordu.
 *
 * CI BUNU NEDEN KAÇIRDI: mevcut testler `CaptureApplier`i ELLE kuruyor ve
 * `MediaService`i kendileri veriyordu. Yani sınanan şey sınıfın davranışıydı,
 * uygulamanın KOMPOZİSYONU değil. Bu süit tam o boşluğu kapatır: uygulama
 * `AppBuilder::build()` ile ayağa kalkar (üretimdeki yolun aynısı) ve dosya
 * sistemi GERÇEKTEN kontrol edilir.
 *
 * Üç şey birden doğrulanır — üçü de tek başına yanıltıcıdır:
 *   1. nihai dosya VAR,
 *   2. `.tmp` YOK,
 *   3. `main_image` alanı diskteki dosyayı gösterir (yol eşleşmesi).
 */
final class MedyaKompozisyonTest extends AuthTestCase
{
    private string $csrf = '';
    private string $eklentiTokeni = '';
    private int $listeId = 0;

    /** @var list<string> testin ürettiği dosyalar — sonunda temizlenir */
    private array $uretilenler = [];

    protected function setUp(): void
    {
        $this->mediaFetcher = new FakeMediaFetcher();
        parent::setUp();

        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->eklentiTokeni = (string) $this->json(
            $this->call('POST', '/api/settings/extension-token', [], [Csrf::HEADER => $this->csrf]),
        )['data']['token'];

        $this->listeId = (int) $this->json(
            $this->call('POST', '/api/lists', ['name' => 'Medya kompozisyonu'], [Csrf::HEADER => $this->csrf]),
        )['data']['id'];
    }

    protected function tearDown(): void
    {
        foreach ($this->uretilenler as $yol) {
            @unlink($yol);
        }
        $this->uretilenler = [];

        parent::tearDown();
    }

    private function medyaDizini(): string
    {
        return dirname(__DIR__, 2) . '/public/media';
    }

    /** @return list<string> dizindeki dosyaların tam yolları */
    private function medyaDosyalari(): array
    {
        return glob($this->medyaDizini() . '/*') ?: [];
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(12, 12);
        self::assertNotFalse($image);
        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $fark */
    private function yuk(string $captureId, array $fark = []): array
    {
        $ana = 'https://cbu01.alicdn.com/img/ibank/' . $captureId . '-ana.jpg';
        $this->mediaFetcher->respondWith($ana, $this->jpeg(), 'image/jpeg');

        return array_replace_recursive([
            'capture_id' => $captureId,
            'schema_version' => 2,
            'extension_version' => '2.0.1',
            'parser_version' => '1688-2026.08',
            'qty' => 5,
            'source' => [
                'platform' => '1688',
                'external_id' => '900' . substr($captureId, -3),
                'url' => 'https://detail.1688.com/offer/900.html',
                'captured_at' => '2026-08-26T12:00:00+03:00',
            ],
            'raw' => ['title' => '洞洞鞋'],
            'normalized' => [
                'name' => 'Terlik',
                'price_yuan' => '15.90',
                'price_tiers' => [['min_qty' => 1, 'price_yuan' => '15.90']],
                'images' => [$ana],
            ],
        ], $fark);
    }

    /**
     * Üçlü doğrulama: nihai dosya var · `.tmp` yok · adres diski gösteriyor.
     */
    private function medyaKanitla(int $urunId): void
    {
        $anaGorsel = (string) $this->pdo->query(
            'SELECT main_image FROM products WHERE id = ' . $urunId,
        )->fetchColumn();

        self::assertStringStartsWith('/media/', $anaGorsel, 'Arşiv modunda adres yerel olmalı.');

        $mutlak = dirname(__DIR__, 2) . '/public' . $anaGorsel;
        $this->uretilenler[] = $mutlak;

        self::assertFileExists($mutlak, 'NİHAİ DOSYA YOK: kayıt çözülemeyen bir adrese işaret ediyor (F-01).');

        $tmpler = array_filter($this->medyaDosyalari(), static fn (string $y): bool => str_ends_with($y, '.tmp'));
        foreach ($tmpler as $tmp) {
            $this->uretilenler[] = $tmp;
        }
        self::assertSame([], array_values(array_map('basename', $tmpler)), '.tmp dosyası KALDI (F-01).');
    }

    public function testEKLENTIDENHEDEFLISTEYE_MEDYAKALICILASIR(): void
    {
        $yanit = $this->call('POST', '/api/capture', $this->yuk('aa110000-1111-4222-8333-000000000001', [
            'target_list_id' => $this->listeId,
        ]), ['Authorization' => 'Bearer ' . $this->eklentiTokeni]);

        self::assertSame(201, $yanit->getStatusCode(), (string) $yanit->getBody());
        $urunId = (int) $this->json($yanit)['data']['product_id'];
        self::assertGreaterThan(0, $urunId);

        $this->medyaKanitla($urunId);
    }

    public function testGELENKUTUSUNDANUYGULAMADA_MEDYAKALICILASIR(): void
    {
        // Hedef liste YOK: kayıt Gelen Kutusu'na düşer.
        $yanit = $this->call(
            'POST',
            '/api/capture',
            $this->yuk('aa110000-1111-4222-8333-000000000002'),
            ['Authorization' => 'Bearer ' . $this->eklentiTokeni],
        );
        self::assertSame(201, $yanit->getStatusCode(), (string) $yanit->getBody());
        $inboxId = (int) $this->json($yanit)['data']['inbox_id'];

        // Panelden listeye uygula — ikinci medya yolu budur.
        $uygula = $this->call(
            'POST',
            '/api/inbox/assign',
            ['list_id' => $this->listeId, 'ids' => [$inboxId]],
            [Csrf::HEADER => $this->csrf],
        );
        self::assertContains($uygula->getStatusCode(), [200, 201], (string) $uygula->getBody());

        $urunId = (int) $this->pdo->query(
            'SELECT assigned_product_id FROM inbox_items WHERE id = ' . $inboxId,
        )->fetchColumn();
        self::assertGreaterThan(0, $urunId, 'Gelen Kutusu kaydı ürüne bağlanmalı.');

        $this->medyaKanitla($urunId);
    }

    public function testGALERIADRESLERISSRFBEYAZLISTESINDENGECER(): void
    {
        // F-16: iç ağ adresi galeri satırı olarak KAYDA GİRMEZ.
        $ana = 'https://cbu01.alicdn.com/img/ibank/aa110000-1111-4222-8333-000000000003-ana.jpg';
        $yuk = $this->yuk('aa110000-1111-4222-8333-000000000003', [
            'target_list_id' => $this->listeId,
        ]);
        $yuk['normalized']['images'] = [
            $ana,
            'https://cbu01.alicdn.com/img/ibank/temiz-galeri.jpg',
            'https://127.0.0.1/gizli.jpg',
            'https://192.168.1.10/ic-ag.jpg',
        ];

        $yanit = $this->call('POST', '/api/capture', $yuk, ['Authorization' => 'Bearer ' . $this->eklentiTokeni]);
        self::assertSame(201, $yanit->getStatusCode(), (string) $yanit->getBody());
        $urunId = (int) $this->json($yanit)['data']['product_id'];

        $satirlar = $this->pdo->query(
            'SELECT path FROM product_images WHERE product_id = ' . $urunId,
        )->fetchAll(\PDO::FETCH_COLUMN);

        self::assertSame(['https://cbu01.alicdn.com/img/ibank/temiz-galeri.jpg'], $satirlar);
        $this->medyaKanitla($urunId);
    }
}
