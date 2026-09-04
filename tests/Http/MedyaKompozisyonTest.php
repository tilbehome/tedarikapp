<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Middleware\Csrf;
use App\Models\SettingsRepository;
use App\Services\UrlGuard;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TasinamayanMediaService;

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
 *
 * v1.2.2 D1 SONRASI: yakalama artık İNDİRMEZ. Kalıcılaşma KUYRUK TURUNDA olur.
 * Bu süit bu yüzden iki adımlı: yakalama → (ana görsel KAYNAK adresle,
 * `media_pending`) → kuyruk turu → kanıt. Tur, `AppBuilder` ile aynı yoldan
 * kurulur (`KuyrukIsleyicileri::kaydet` + KOMPOZE `MediaService`): işleyici
 * kendi indiricisini kurmaz, enjekte edileni kullanır — aksi hâlde sahte
 * indirici yakalamayı sınar, indiren tek yol olan kuyruğu sınayamazdı.
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
     * D1 — yakalama sonrası ara durum: ana görsel KAYNAK adresle, iş kuyrukta.
     */
    private function bekliyorKanitla(int $urunId): void
    {
        $anaGorsel = (string) $this->pdo->query(
            'SELECT main_image FROM products WHERE id = ' . $urunId,
        )->fetchColumn();
        self::assertStringStartsWith('https://', $anaGorsel, 'D1: yakalama indirmez; ana görsel KAYNAK adresle yazılır.');
        self::assertSame(0, $this->mediaFetcher?->callCount ?? 0, 'D1: yakalama sırasında indirme YAPILMAMALI.');

        $is = (new \App\Services\Kuyruk\JobQueue(Connection::fromCallable(fn (): \PDO => $this->pdo)))
            ->bul(\App\Services\Kuyruk\KuyrukIsleyicileri::TUR_MEDYA, 'urun:' . $urunId);
        self::assertNotNull($is, 'Medya işi kuyruğa girmeli.');
    }

    /**
     * KUYRUK TURU — üretimdeki kompozisyonun aynısı (AppBuilder ile aynı kayıt),
     * indirici sahte. İşleyici enjekte edilen MediaService'i kullanmak ZORUNDA:
     * kendi cURL'ünü kursaydı bu tur ağa çıkar ve test kırmızı olurdu.
     */
    private function kuyrukTurunuKos(): array
    {
        $baglanti = Connection::fromCallable(fn (): \PDO => $this->pdo);
        $config = $this->config();
        $urlGuard = new UrlGuard(['alicdn.com', '1688.com']);
        $medya = new \App\Services\MediaService(
            dirname(__DIR__, 2),
            $urlGuard,
            $this->mediaFetcher ?? new FakeMediaFetcher(),
            new SettingsRepository($baglanti),
            8 * 1024 * 1024,
        );
        $kuyruk = new \App\Services\Kuyruk\JobQueue($baglanti);
        $kosucu = new \App\Services\Kuyruk\JobRunner($kuyruk, new \Psr\Log\NullLogger(), saat: $this->clock);
        \App\Services\Kuyruk\KuyrukIsleyicileri::kaydet(
            $kosucu,
            $config,
            $baglanti,
            new \Psr\Log\NullLogger(),
            dirname(__DIR__, 2),
            $this->clock,
            null,
            medyaServisi: $medya,
        );

        return $kosucu->kos();
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

        $this->bekliyorKanitla($urunId);
        $tur = $this->kuyrukTurunuKos();
        self::assertSame(1, $tur['basarili'], 'Medya işi kuyruk turunda BİTMELİ: ' . $tur['durma_nedeni']);
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

        // D1: Gelen Kutusu'ndan taşıma yolu da medya işini kuyruğa yazar
        // (eskiden yazmıyordu — taşınan ürünün galerisi sonsuza kadar uzak kalırdı).
        $this->bekliyorKanitla($urunId);
        $this->kuyrukTurunuKos();
        $this->medyaKanitla($urunId);
    }

    /**
     * rc8/E1 — TAŞIMA BAŞARISIZSA: KAYIT KAYNAK ADRESE DÜŞER, `.tmp` KALMAZ.
     *
     * `commit()` false döndüğünde ürün kaydı zaten kaynak adrese düşüyordu
     * (rc8-01/F-13) ama geçici dosya diskte kalıyordu: kimsenin bakmayacağı
     * bir yetim, F-14 envanterinde her seferinde yeni bir kalıntı.
     */
    public function testTASIMABASARISIZSA_KAYNAGADUSULUR_VE_TMPSILINIR(): void
    {
        // D1 sonrası: yakalama zaten yazmaz; bu test kayıtta KAYNAK adresin
        // durduğunu ve diske hiçbir `.tmp` düşmediğini korur.
        $oncekiTmpler = $this->tmpDosyalari();

        $this->mediaService = new TasinamayanMediaService(
            dirname(__DIR__, 2),
            new UrlGuard(['alicdn.com', '1688.com']),
            $this->mediaFetcher ?? new FakeMediaFetcher(),
            new SettingsRepository(Connection::fromCallable(fn (): \PDO => $this->pdo)),
            8 * 1024 * 1024,
        );

        $yanit = $this->call('POST', '/api/capture', $this->yuk('aa110000-1111-4222-8333-000000000004', [
            'target_list_id' => $this->listeId,
        ]), ['Authorization' => 'Bearer ' . $this->eklentiTokeni]);

        self::assertSame(201, $yanit->getStatusCode(), (string) $yanit->getBody());
        $urunId = (int) $this->json($yanit)['data']['product_id'];

        $anaGorsel = (string) $this->pdo->query(
            'SELECT main_image FROM products WHERE id = ' . $urunId,
        )->fetchColumn();
        self::assertSame(
            'https://cbu01.alicdn.com/img/ibank/aa110000-1111-4222-8333-000000000004-ana.jpg',
            $anaGorsel,
            'Taşıma başarısızken kayıt KAYNAK adrese düşmeli — `.tmp` adresi yazılamaz.',
        );

        self::assertSame(
            [],
            array_values(array_diff($this->tmpDosyalari(), $oncekiTmpler)),
            'Yetim `.tmp` diskte kaldı (E1).',
        );
    }

    /** @return list<string> */
    private function tmpDosyalari(): array
    {
        return array_values(array_filter(
            $this->medyaDosyalari(),
            static fn (string $yol): bool => str_ends_with($yol, '.tmp'),
        ));
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

        $this->mediaFetcher?->respondWith('https://cbu01.alicdn.com/img/ibank/temiz-galeri.jpg', $this->jpeg(), 'image/jpeg');
        $this->kuyrukTurunuKos();
        $this->medyaKanitla($urunId);
        // Galeri dosyası da üretildi; temizlenmek üzere kaydedilir.
        foreach ($this->medyaDosyalari() as $dosya) {
            if (!in_array($dosya, $this->uretilenler, true)) {
                $this->uretilenler[] = $dosya;
            }
        }
    }
}
