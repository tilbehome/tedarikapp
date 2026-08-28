<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Models\ProductRepository;
use App\Models\TranslationCacheRepository;
use App\Services\Translation\CeviriYurutucu;
use App\Services\Translation\TranslatorInterface;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * D12 — SENKRON ÇEVİRİ YÜRÜTÜCÜSÜ.
 *
 * En kritik vaka en sondadır: çevirmene GÖNDERİLEN metin. Bu, mock sağlayıcıyla
 * yapılan kanıt turunda ortaya çıkan gerçek kusurdur — yürütücü kalıcılığı
 * `name_original` üzerinden ölçerken çevirmene `name` (çoğu kayıtta makine
 * çevirisi Türkçe ad) gönderiyordu. Satırlar yazılıyor ama BAŞKA bir anahtara
 * yazılıyordu; aday listesi hiçbir zaman boşalmıyordu.
 */
final class CeviriYurutucuTest extends TestCase
{
    private PDO $pdo;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL DEFAULT 1,
                sort_no INTEGER NOT NULL DEFAULT 0,
                category_id INTEGER NULL, platform TEXT NULL, external_id TEXT NULL,
                name TEXT NOT NULL, name_original TEXT NULL, name_elle INTEGER NOT NULL DEFAULT 0,
                source_lang TEXT NULL, detail TEXT NULL, url TEXT NULL,
                vendor_name TEXT NULL, vendor_url TEXT NULL, sku_selection TEXT NULL, sku_matrix TEXT NULL,
                main_image TEXT NULL, main_image_source TEXT NULL, video_url TEXT NULL,
                qty INTEGER NOT NULL DEFAULT 1, price_yuan TEXT NOT NULL DEFAULT "0",
                price_ddp_usd TEXT NOT NULL DEFAULT "0", price_target_try TEXT NULL,
                units_per_carton INTEGER NULL, tracking_no TEXT NULL,
                raw_attributes TEXT NULL, country_of_origin TEXT NULL, country_of_dispatch TEXT NULL,
                status TEXT NOT NULL DEFAULT "to_order", note TEXT NULL,
                created_at TEXT NOT NULL, updated_at TEXT NOT NULL, deleted_at TEXT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE translation_cache (
                source_hash TEXT PRIMARY KEY, source_text TEXT NOT NULL, suggested_text TEXT NOT NULL,
                provider TEXT NOT NULL, source_lang TEXT NOT NULL, target_lang TEXT NOT NULL,
                surum TEXT NULL, guven TEXT NULL, created_at TEXT NOT NULL
            )',
        );
        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);
    }

    private function urunEkle(string $ad, ?string $orijinal, ?string $kaynakDil): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (name, name_original, source_lang, created_at, updated_at)
             VALUES (:ad, :orijinal, :dil, :simdi, :simdi)',
        );
        $statement->execute(['ad' => $ad, 'orijinal' => $orijinal, 'dil' => $kaynakDil, 'simdi' => '2026-08-28 10:00:00']);

        return (int) $this->pdo->lastInsertId();
    }

    private function yurutucu(TranslatorInterface $cevirmen): CeviriYurutucu
    {
        return new CeviriYurutucu(
            new ProductRepository($this->connection),
            new TranslationCacheRepository($this->connection),
            $cevirmen,
            new NullLogger(),
        );
    }

    public function testCEVIRMENEORIJINALMETINGIDER(): void
    {
        // KUSUR: ekrandaki ad zaten makine çevirisi Türkçedir. Onu göndermek
        // hem Türkçeyi Türkçeye çevirtir hem de üretilen satırı hiç aranmayan
        // bir anahtara yazar.
        $this->urunEkle('Bisiklet Yok', '无脚踏平衡车', 'zh');
        $casus = new CasusCevirmen();

        $this->yurutucu($casus)->urunuTamamla(1);

        self::assertSame('无脚踏平衡车', $casus->sonYuk['name'] ?? null, 'Çevrilecek metin ORİJİNALDİR.');
        self::assertSame('zh', $casus->sonYuk['source_lang'] ?? null);
        self::assertSame(['tr', 'en'], $casus->sonYuk['target_langs'] ?? null, 'Kaynak dil hedefe girmez.');
    }

    public function testTURKCEKAYNAKTATRISTENMEZ(): void
    {
        $this->urunEkle('Kalın Tabanlı Terlik', 'Kalın Tabanlı Terlik', 'tr');
        $casus = new CasusCevirmen();

        $this->yurutucu($casus)->urunuTamamla(1);

        self::assertSame(['en', 'zh'], $casus->sonYuk['target_langs'] ?? null, 'TR kaynakta motor TR ye dokunmaz.');
    }

    public function testKAYNAKDILIYOKSAMETINDENSAPTANIRVEUCUISTENIR(): void
    {
        $this->urunEkle('Bisiklet Yok', '无脚踏平衡车', null);
        $casus = new CasusCevirmen();

        $sonuc = $this->yurutucu($casus)->urunuTamamla(1);

        self::assertSame('zh', $sonuc['kaynak_dil'], 'Kayıt boşsa metinden saptanır.');
        self::assertSame(['tr', 'en'], $casus->sonYuk['target_langs'] ?? null);
    }

    public function testORIJINALYOKSACEVIRMENCAGRILMAZ(): void
    {
        // Elle eklenmiş ürün: çevrilecek kaynak metin yoktur; bu bir hata değildir.
        $this->urunEkle('Elle eklenen ürün', null, null);
        $casus = new CasusCevirmen();

        $sonuc = $this->yurutucu($casus)->urunuTamamla(1);

        self::assertNull($casus->sonYuk, 'Kaynak metin yokken çevirmen çağrılmaz.');
        self::assertSame([], $sonuc['eksikti']);
        self::assertNull($sonuc['hata']);
    }

    public function testCEVIRMENPATLARSAHATARAPORLANIRVEAKISDURMAZ(): void
    {
        $this->urunEkle('Bisiklet Yok', '无脚踏平衡车', 'zh');

        $sonuc = $this->yurutucu(new PatlayanCevirmen())->urunuTamamla(1);

        self::assertNotNull($sonuc['hata'], 'Hata YUTULMAZ.');
        self::assertSame(['tr', 'en'], $sonuc['kalan']);
    }

    public function testURUNYOKSAACIKHATA(): void
    {
        $sonuc = $this->yurutucu(new CasusCevirmen())->urunuTamamla(404);

        self::assertSame('Ürün bulunamadı.', $sonuc['hata']);
    }
}

/** Çevirmene NE gönderildiğini kaydeden casus. */
final class CasusCevirmen implements TranslatorInterface
{
    /** @var array<string, mixed>|null */
    public ?array $sonYuk = null;

    public function translateProduct(array $urun): array
    {
        $this->sonYuk = $urun;

        return [];
    }

    public function getGlossary(string $sourceLang = 'zh'): array
    {
        return [];
    }

    public function name(): string
    {
        return 'casus';
    }
}

/** Sağlayıcı arızasını taklit eder. */
final class PatlayanCevirmen implements TranslatorInterface
{
    public function translateProduct(array $urun): array
    {
        throw new \RuntimeException('sağlayıcı yanıt vermedi');
    }

    public function getGlossary(string $sourceLang = 'zh'): array
    {
        return [];
    }

    public function name(): string
    {
        return 'patlayan';
    }
}
