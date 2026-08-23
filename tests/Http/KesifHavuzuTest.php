<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * KEŞİF HAVUZU (İE#21 B1) — panel E2E kataloğundan koda dönen senaryolar.
 *
 * Kapsanan kimlikler: E2E-PNL-01 · 02 · 03 · 09 · 10 · 13 · 15.
 * Her testin başında hangi senaryoyu karşıladığı yazar; kapsam defteri
 * (`docs/v3/hazirlik/e2e-kapsam-defteri.json`) bu adlara bakar.
 *
 * B SINIFI: gerçek API + gerçek şema. Dış istek YOKTUR.
 */
final class KesifHavuzuTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->kesifSemasi();
    }

    /** Keşif havuzu ilan tablolarını GERÇEK migration'lardan kurar. */
    private function kesifSemasi(): void
    {
        // 0026 arama alanlarını (`arama_metni`) da ekler; taban test şeması
        // C7 öncesinden kalma olduğu için burada birlikte koşulur.
        foreach ([
            '0022_create_platforms',
            '0023_create_listings',
            '0025_add_listings_skor',
            '0026_arama_ve_kalite',
            '0029_ilan_satis_toplam',
            '0030_kesif_havuzu',
        ] as $ad) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $ad . '.php';
            $migration->up($this->pdo);
        }
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    /**
     * Havuza ürün + ilan yazar.
     *
     * @param array<string, mixed> $veri
     */
    private function urun(array $veri): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (list_id, sort_no, category_id, name, name_original, arama_metni,
                arama_normal, main_image, video_url, qty, price_yuan, price_ddp_usd, status, created_at, updated_at)
             VALUES (:liste, 0, :kategori, :ad, :orijinal, :arama, :normal, NULL, :video, 1, :fiyat, 0,
                :durum, :simdi, :simdi)',
        );

        $arama = trim(($veri['ad'] ?? '') . ' ' . ($veri['orijinal'] ?? '') . ' ' . ($veri['arama_ek'] ?? ''));

        $statement->execute([
            'liste' => $this->listeId(),
            'kategori' => $veri['kategori_id'] ?? null,
            'ad' => (string) ($veri['ad'] ?? 'Ürün'),
            'orijinal' => $veri['orijinal'] ?? null,
            'arama' => $arama,
            'normal' => \App\Services\Kesif\AramaNormalizasyonu::normalize($arama),
            'video' => $veri['video'] ?? null,
            'fiyat' => (string) ($veri['fiyat'] ?? '10.0000'),
            'durum' => 'to_order',
            'simdi' => '2026-08-23 10:00:00',
        ]);
        $urunId = (int) $this->pdo->lastInsertId();

        $ilan = $this->pdo->prepare(
            'INSERT INTO listings (product_id, platform_kod, external_id, url, baslik_orijinal,
                satici_ad, satis_adedi, satis_toplam, degerlendirme_puani, degerlendirme_adedi,
                moq, birim_fiyat, skor, kume_anahtari, yakalandi_at, created_at, updated_at)
             VALUES (:urun, :platform, :dis, :url, :orijinal, :satici, :satis, :toplam, :puan, :yorum,
                :moq, :fiyat, :skor, :kume, :yakalandi, :simdi, :simdi)',
        );
        $ilan->execute([
            'urun' => $urunId,
            'platform' => (string) ($veri['platform'] ?? '1688'),
            'dis' => (string) ($veri['ilan_no'] ?? ('X' . $urunId)),
            'url' => 'https://ornek.test/u/' . $urunId,
            'orijinal' => $veri['orijinal'] ?? null,
            'satici' => (string) ($veri['satici'] ?? 'Demo Satıcı'),
            'satis' => $veri['satis'] ?? 100,
            'toplam' => $veri['satis_toplam'] ?? 1000,
            'puan' => $veri['puan'] ?? 4.5,
            'yorum' => $veri['yorum'] ?? 50,
            'moq' => $veri['moq'] ?? 10,
            'fiyat' => (string) ($veri['fiyat'] ?? '10.0000'),
            'skor' => $veri['skor'] ?? null,
            'kume' => $veri['kume'] ?? null,
            'yakalandi' => '2026-08-20 10:00:00',
            'simdi' => '2026-08-23 10:00:00',
        ]);

        return $urunId;
    }

    private ?int $havuzListesi = null;

    /**
     * Havuz testleri tek bir taşıyıcı liste kullanır.
     *
     * `products.list_id` şemada ZORUNLUDUR: bugün her ürün bir listeye bağlıdır ve
     * "listeye hiç girmemiş ürün" Gelen Kutusu'nda (`inbox_items`) yaşar. Havuzun
     * değeri süzme/kümeleme/karşılaştırmadadır, ürünün nerede durduğunda değil.
     */
    private function listeId(): int
    {
        if ($this->havuzListesi !== null) {
            return $this->havuzListesi;
        }

        $statement = $this->pdo->prepare(
            "INSERT INTO lists (name, status, visibility, yuan_rate, usd_rate, revision, created_at, updated_at)
             VALUES ('Havuz taşıyıcı', 'draft', 'active', '7.1500', '48.0500', 0, :simdi, :simdi)",
        );
        $statement->execute(['simdi' => '2026-08-23 10:00:00']);

        return $this->havuzListesi = (int) $this->pdo->lastInsertId();
    }

    private function kategori(string $ad): int
    {
        $statement = $this->pdo->prepare('INSERT INTO categories (name, sort) VALUES (:ad, 0)');
        $statement->execute(['ad' => $ad]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<string, mixed>
     */
    private function kesif(string $sorgu = ''): array
    {
        $response = $this->call('GET', '/api/kesif' . ($sorgu === '' ? '' : '?' . $sorgu));
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        /** @var array<string, mixed> $veri */
        $veri = $this->json($response)['data'];

        return $veri;
    }

    /**
     * @param array<string, mixed> $veri
     *
     * @return list<string>
     */
    private function ilanNolari(array $veri): array
    {
        return array_map(static fn (array $s): string => (string) $s['ilan_no'], $veri['satirlar']);
    }

    // ─────────────────── E2E-PNL-01: filtreler VE ile birleşir ───────────────────

    public function testE2E_PNL_01_UcFiltreVEileBirlesir(): void
    {
        $evTekstili = $this->kategori('Ev Tekstili');
        $mutfak = $this->kategori('Mutfak Gereçleri');

        $this->urun(['ad' => 'DM-003', 'ilan_no' => 'DM-003', 'kategori_id' => $evTekstili, 'platform' => '1688', 'skor' => 80]);
        $this->urun(['ad' => 'DM-007', 'ilan_no' => 'DM-007', 'kategori_id' => $evTekstili, 'platform' => '1688', 'skor' => 10]);
        $this->urun(['ad' => 'DM-016', 'ilan_no' => 'DM-016', 'kategori_id' => $mutfak, 'platform' => '1688', 'skor' => 80]);

        $veri = $this->kesif('kategori=Ev+Tekstili&skor_bandi=yuksek&platform=1688');

        // Gevşek OR olsaydı üçü de dönerdi; AND yalnız kesişimi getirir.
        self::assertSame(['DM-003'], $this->ilanNolari($veri));
        self::assertSame(1, $veri['toplam']);
    }

    public function testE2E_PNL_01_AyniSorguAynıSonucuVerir(): void
    {
        // "URL ile yenileme" sunucu tarafında: aynı sorgu dizesi aynı sonucu verir.
        $kategori = $this->kategori('Ev Tekstili');
        $this->urun(['ad' => 'A', 'ilan_no' => 'A', 'kategori_id' => $kategori, 'skor' => 90]);

        $ilk = $this->kesif('kategori=Ev+Tekstili&skor_bandi=yuksek&platform=1688');
        $ikinci = $this->kesif('kategori=Ev+Tekstili&skor_bandi=yuksek&platform=1688');

        self::assertSame($this->ilanNolari($ilk), $this->ilanNolari($ikinci));
    }

    // ─────────────────── E2E-PNL-02/03: çift dilli, normalize arama ───────────────────

    public function testE2E_PNL_02_TurkceSorguCinceKaydiBulur(): void
    {
        foreach (['DM-016', 'DM-023', 'DM-025'] as $no) {
            $this->urun([
                'ad' => 'Yüksek borosilikat cam yağlık 550ml',
                'orijinal' => '高硼硅玻璃油壶 550ml',
                'ilan_no' => $no,
                'kume' => 'CK-CAM-YAGLIK-550ML',
            ]);
        }
        $this->urun(['ad' => 'DM-034 alakasız ürün', 'ilan_no' => 'DM-034']);

        $veri = $this->kesif('q=' . rawurlencode('yüksek borosilikat cam yağlık 550ml'));

        $bulunan = $this->ilanNolari($veri);
        sort($bulunan);
        self::assertSame(['DM-016', 'DM-023', 'DM-025'], $bulunan);
        self::assertNotContains('DM-034', $bulunan);
    }

    public function testE2E_PNL_02_CinceBaslikKORUNUR(): void
    {
        // Türkçe sorgu Çince başlığın ÜZERİNE YAZMAZ: kaynak metin dokunulmazdır.
        $this->urun([
            'ad' => 'Yüksek borosilikat cam yağlık 550ml',
            'orijinal' => '高硼硅玻璃油壶 550ml',
            'ilan_no' => 'DM-016',
        ]);

        $veri = $this->kesif('q=' . rawurlencode('cam yağlık'));

        self::assertSame('高硼硅玻璃油壶 550ml', $veri['satirlar'][0]['ad_orijinal']);
    }

    public function testE2E_PNL_03_OlcuVeTurkceKarakterNormalize(): void
    {
        foreach (['DM-060', 'DM-064', 'DM-068'] as $no) {
            $this->urun([
                'ad' => 'Şeffaf çekmeceli ayakkabı kutusu 33×23×14cm',
                'orijinal' => '抽屉式透明鞋盒 33×23×14cm',
                'ilan_no' => $no,
            ]);
        }

        // Kullanıcı ASCII "x", boşluklu "cm" ve küçük harfle yazıyor.
        $veri = $this->kesif('q=' . rawurlencode('seffaf cekmeceli ayakkabi kutusu 33x23x14 cm'));

        $bulunan = $this->ilanNolari($veri);
        sort($bulunan);
        self::assertSame(['DM-060', 'DM-064', 'DM-068'], $bulunan);
    }

    public function testE2E_PNL_03_EksikVeriliUyeKAYBOLMAZ(): void
    {
        // DM-064'ün fiyat kademesi eksik; arama sonucundan DÜŞMEMELİ.
        $this->urun(['ad' => 'Şeffaf ayakkabı kutusu', 'ilan_no' => 'DM-064', 'fiyat' => '0.0000', 'skor' => null]);

        self::assertContains('DM-064', $this->ilanNolari($this->kesif('q=' . rawurlencode('ayakkabi kutusu'))));
    }

    // ─────────────────── E2E-PNL-09/10: karşılaştırma matrisi ───────────────────

    public function testE2E_PNL_09_IkiIleAltiUrunKarsilastirilir(): void
    {
        $kimlikler = [];
        for ($i = 0; $i < 6; $i++) {
            $kimlikler[] = $this->urun(['ad' => 'Ürün ' . $i, 'ilan_no' => 'K-' . $i, 'skor' => 50 + $i]);
        }

        $response = $this->write('POST', '/api/kesif/karsilastir', ['ids' => $kimlikler]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $veri = $this->json($response)['data'];
        self::assertCount(6, $veri['urunler']);
        // Satır başına "en iyi" işareti: en yüksek skorlu ürün işaretlenir.
        self::assertSame($kimlikler[5], $veri['en_iyiler']['skor']);
    }

    public function testE2E_PNL_10_YedinciUrunREDDEDILIR(): void
    {
        $kimlikler = [];
        for ($i = 0; $i < 7; $i++) {
            $kimlikler[] = $this->urun(['ad' => 'Ürün ' . $i, 'ilan_no' => 'Y-' . $i]);
        }

        $response = $this->write('POST', '/api/kesif/karsilastir', ['ids' => $kimlikler]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('6', (string) $this->json($response)['error']['message']);
    }

    public function testE2E_PNL_10_TekUrunKarsilastirilamaz(): void
    {
        $id = $this->urun(['ad' => 'Tek', 'ilan_no' => 'T-1']);

        self::assertSame(422, $this->write('POST', '/api/kesif/karsilastir', ['ids' => [$id]])->getStatusCode());
    }

    // ─────────────────── E2E-PNL-13: boş sonuç ───────────────────

    public function testE2E_PNL_13_BosSonucVeFiltreTemizleme(): void
    {
        $this->urun(['ad' => 'Tek ürün', 'ilan_no' => 'B-1', 'platform' => '1688']);

        $bos = $this->kesif('platform=taobao');
        self::assertSame(0, $bos['toplam']);
        self::assertSame([], $bos['satirlar']);

        // Filtre temizlenince aynı kayıt geri gelir — sonuç boş kalmaz.
        self::assertSame(1, $this->kesif()['toplam']);
    }

    // ─────────────────── E2E-PNL-15: eksik metrikte skor gizli ───────────────────

    public function testE2E_PNL_15_EksikMetriktekiSkorGIZLI(): void
    {
        $this->urun(['ad' => 'Metriksiz', 'ilan_no' => 'G-1', 'skor' => null]);
        $this->urun(['ad' => 'Metrikli', 'ilan_no' => 'G-2', 'skor' => 75]);

        $veri = $this->kesif('siralama=skor&yon=desc');

        // Gizli skorlu ürün listenin BAŞINDA durmaz: skor sıralaması "en iyi
        // hangisi" sorusunu yanıtlar, "hangisinin verisi eksik" sorusunu değil.
        self::assertSame('G-2', $veri['satirlar'][0]['ilan_no']);
        $gizli = array_values(array_filter(
            $veri['satirlar'],
            static fn (array $s): bool => $s['ilan_no'] === 'G-1',
        ))[0];
        self::assertNull($gizli['skor']);
        self::assertSame('gizli', $gizli['bant']);
    }

    // ─────────────────── E2E-PNL-07/08: 同款 kümeleme ───────────────────

    public function testE2E_PNL_07_AyniUrunKumesiTekKartta(): void
    {
        $this->urun(['ad' => 'Yağlık A', 'ilan_no' => 'C-1', 'kume' => 'CK-YAGLIK', 'fiyat' => '12.0000', 'skor' => 60]);
        $this->urun(['ad' => 'Yağlık B', 'ilan_no' => 'C-2', 'kume' => 'CK-YAGLIK', 'fiyat' => '9.5000', 'skor' => 75]);
        $this->urun(['ad' => 'Başka ürün', 'ilan_no' => 'C-3']);

        $veri = $this->kesif('kumele=1');

        self::assertNotNull($veri['kumeler']);
        $kume = array_values(array_filter(
            $veri['kumeler'],
            static fn (array $k): bool => $k['kume_anahtari'] === 'CK-YAGLIK',
        ))[0];

        self::assertSame(2, $kume['kaynak_sayisi']);
        // Sürücüye göre ondalık biçimi değişir (MySQL '9.5000', SQLite '9.5');
        // sınanan DEĞER, biçim değil.
        self::assertSame(0, bccomp('9.5', (string) $kume['en_ucuz'], 4));
        self::assertSame(75, $kume['en_yuksek_skor']);
        // Temsilci EN İYİ üyedir — kullanıcı kümeye bakarken en iyi teklifi görür.
        self::assertSame('C-2', $kume['temsilci']['ilan_no']);
    }

    public function testE2E_PNL_08_KumesizUrunKENDIBASINAKUMEDIR(): void
    {
        // Kümesizleri tek torbaya atmak, ilgisiz ürünleri aynı kartta gösterirdi.
        $this->urun(['ad' => 'Tekil 1', 'ilan_no' => 'D-1']);
        $this->urun(['ad' => 'Tekil 2', 'ilan_no' => 'D-2']);

        $kumeler = $this->kesif('kumele=1')['kumeler'];

        self::assertCount(2, $kumeler);
        foreach ($kumeler as $kume) {
            self::assertNull($kume['kume_anahtari']);
            self::assertSame(1, $kume['kaynak_sayisi']);
        }
    }

    // ─────────────────── Kaydedilmiş görünümler (§7.2) ───────────────────

    public function testGorunumKaydedilirVeGeriOkunur(): void
    {
        $this->write('POST', '/api/kesif/gorunumler', [
            'ad' => 'Aday ürünler',
            'sorgu' => ['skor_bandi' => 'yuksek', 'platform' => '1688'],
            'varsayilan' => true,
        ]);

        $gorunumler = $this->json($this->call('GET', '/api/kesif/gorunumler'))['data']['gorunumler'];

        self::assertCount(1, $gorunumler);
        self::assertSame('Aday ürünler', $gorunumler[0]['ad']);
        self::assertTrue($gorunumler[0]['varsayilan']);
    }

    public function testAYNIADLIGORUNUMUZERINEYAZAR(): void
    {
        // Kullanıcı görünümü güncellerken ikinci bir kopya beklemez.
        $this->write('POST', '/api/kesif/gorunumler', ['ad' => 'Aday', 'sorgu' => ['platform' => '1688']]);
        $this->write('POST', '/api/kesif/gorunumler', ['ad' => 'Aday', 'sorgu' => ['platform' => 'taobao']]);

        $gorunumler = $this->json($this->call('GET', '/api/kesif/gorunumler'))['data']['gorunumler'];

        self::assertCount(1, $gorunumler);
        self::assertSame('taobao', $gorunumler[0]['sorgu']['platform']);
    }

    public function testVARSAYILANTEKOLABILIR(): void
    {
        $this->write('POST', '/api/kesif/gorunumler', ['ad' => 'Bir', 'sorgu' => [], 'varsayilan' => true]);
        $this->write('POST', '/api/kesif/gorunumler', ['ad' => 'İki', 'sorgu' => [], 'varsayilan' => true]);

        $gorunumler = $this->json($this->call('GET', '/api/kesif/gorunumler'))['data']['gorunumler'];
        $varsayilanlar = array_filter($gorunumler, static fn (array $g): bool => $g['varsayilan'] === true);

        self::assertCount(1, $varsayilanlar, 'İki varsayılan, hangisinin açılacağını belirsiz bırakır.');
    }

    public function testOTURUMSUZKESIFYOK(): void
    {
        $this->write('POST', '/api/auth/logout');

        self::assertSame(401, $this->call('GET', '/api/kesif')->getStatusCode());
    }
}
