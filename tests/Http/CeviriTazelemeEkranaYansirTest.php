<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Models\TranslationCacheRepository;
use Tests\Support\AuthTestCase;

/**
 * D11b — TAZELEME SONRASI LİSTE, ÇEKMECE VE PAYLAŞIM SAYFASI YENİ ÇEVİRİYİ GÖSTERİR.
 *
 * Saha vakası: LLM turu ürünün TR karşılığını "Pedalsız Denge Bisikleti" yaptı;
 * beş dakika sonra panel hâlâ "Bisiklet Yok" basıyordu. Sınav (`ceviri-sinavi`)
 * çeviri belleğini, ekran ise `products.name`i okuyordu.
 *
 * Bu test üç yüzeyi birden bağlar: liste satırı, ürün çekmecesi ve dışa açık
 * paylaşım sayfası. Üçü aynı adı göstermezse kullanıcı hangisinin doğru
 * olduğunu bilemez.
 */
final class CeviriTazelemeEkranaYansirTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listeId = 0;
    private int $urunId = 0;

    private const ORIJINAL = '无脚踏平衡车';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listeId = (int) $this->json(
            $this->call('POST', '/api/lists', ['name' => 'Çeviri listesi'], [Csrf::HEADER => $this->csrf]),
        )['data']['id'];

        // Yakalamadan gelmiş gibi: ad MAKİNE çevirisi, orijinal Çince.
        $statement = $this->pdo->prepare(
            'INSERT INTO products (list_id, name, name_original, price_yuan, price_ddp_usd, qty, status, created_at, updated_at)
             VALUES (:liste, :ad, :orijinal, :fiyat, :ddp, 10, :durum, :simdi, :simdi)',
        );
        $statement->execute([
            'liste' => $this->listeId,
            'ad' => 'Bisiklet Yok',
            'orijinal' => self::ORIJINAL,
            'fiyat' => '15.90',
            'ddp' => '3.20',
            'durum' => 'to_order',
            'simdi' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
        $this->urunId = (int) $this->pdo->lastInsertId();
    }

    /**
     * LLM turunun yazdığı satırların AYNISI: sürümlü + sürümsüz anahtar (D6).
     *
     * Sürüm anahtarı uygulamanın kendi fabrikasından alınır; testin uydurduğu
     * bir sürüm, okuma tarafıyla eşleşmez ve testi yalancı yeşil yapardı.
     */
    private function llmCevirisiYaz(string $metin = 'Pedalsız Denge Bisikleti'): void
    {
        $sozluk = new \App\Services\Translation\Glossary(
            dirname(__DIR__, 2) . '/config',
            dirname(__DIR__, 2) . '/storage',
        );
        $ayarlar = new \App\Services\Translation\CeviriAyarlari(
            new \App\Models\SettingsRepository($this->connection),
            new \App\Core\Encrypter($this->config()),
        );
        $surumAnahtari = \App\Services\Translation\CeviriSurumu::kur($ayarlar, $sozluk)->anahtar();

        $onbellek = new TranslationCacheRepository($this->connection);
        foreach (['', $surumAnahtari] as $surum) {
            $onbellek->tazele(
                TranslationCacheRepository::hash(self::ORIJINAL, 'zh', 'tr', $surum),
                self::ORIJINAL,
                $metin,
                'llm:deepseek',
                'zh',
                'tr',
                $this->clock->now(),
                $surum,
            );
        }
    }

    public function testLISTEVECEKMECEYENICEVIRIYIGOSTERIR(): void
    {
        // Tazeleme ÖNCESİ: ekranda yakalama adı var.
        $once = $this->json($this->call('GET', '/api/lists/' . $this->listeId . '/products'))['data'];
        self::assertSame('Bisiklet Yok', $once[0]['ad_gosterim']);
        self::assertSame('yakalama', $once[0]['ad_kaynak']);

        $this->llmCevirisiYaz();

        // Tazeleme SONRASI: aynı uç YENİ çeviriyi basar.
        $sonra = $this->json($this->call('GET', '/api/lists/' . $this->listeId . '/products'))['data'];
        self::assertSame('Pedalsız Denge Bisikleti', $sonra[0]['ad_gosterim']);
        self::assertSame('ceviri', $sonra[0]['ad_kaynak']);
        self::assertSame('llm:deepseek', $sonra[0]['ad_saglayici']);
        // SAKLANAN ad değişmedi: çeviri bir öneridir, alana yazılmaz (K54).
        self::assertSame('Bisiklet Yok', $sonra[0]['name']);

        // Çekmece de aynı adı gösterir — üç yüzey tek kaynaktan okur.
        $cekmece = $this->json($this->call('GET', '/api/products/' . $this->urunId . '/cekmece'))['data'];
        self::assertSame('Pedalsız Denge Bisikleti', $cekmece['urun']['ad_gosterim']);
    }

    public function testELLEDUZELTILENADTAZELEMEYLEDEGISMEZ(): void
    {
        $this->call(
            'PATCH',
            '/api/products/' . $this->urunId,
            ['name' => 'Denge bisikleti — ithalat'],
            [Csrf::HEADER => $this->csrf],
        );

        $this->llmCevirisiYaz();

        $satirlar = $this->json($this->call('GET', '/api/lists/' . $this->listeId . '/products'))['data'];
        self::assertSame('Denge bisikleti — ithalat', $satirlar[0]['ad_gosterim']);
        self::assertSame('elle', $satirlar[0]['ad_kaynak']);
    }

    public function testPAYLASIMSAYFASIYENICEVIRIYIGOSTERIR(): void
    {
        $this->llmCevirisiYaz();

        $url = (string) $this->json(
            $this->call('POST', '/api/lists/' . $this->listeId . '/share', [], [Csrf::HEADER => $this->csrf]),
        )['data']['share_url'];
        $token = substr($url, (int) strrpos($url, '/') + 1);

        $cerez = $this->paylasimCerezi($token, $this->listeId, $this->csrf);
        $sayfa = (string) $this->call('GET', '/p/' . $token, null, [], $cerez)->getBody();

        self::assertStringContainsString('Pedalsız Denge Bisikleti', $sayfa);
        self::assertStringNotContainsString('Bisiklet Yok', $sayfa);
    }
}
