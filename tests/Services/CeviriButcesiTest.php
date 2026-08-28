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
 * İE#22 B2 / K93 — TEKİL "ÇEVİR" ZAMAN BÜTÇESİ (V3-B kabul ölçütü 6).
 *
 * İE#22 bu davranışı UYGULADI ama DETERMİNİSTİK TESTİNİ BIRAKMADI: doğruluğu
 * yalnız gerçek LLM'li bir saha turunda görülebiliyordu. Saha teyidi hâlâ
 * gerekli (gerçek gecikmeyi ancak o gösterir) ama davranışın kendisi burada
 * ağsız ve saniyesiz kanıtlanıyor.
 *
 * YAVAŞ SAĞLAYICI TAKLİDİ: her dil için bütçeyi aşan bir çevirmen. Beklenen
 * sonuç, spinner'ın sonsuza dönmesi DEĞİL, ilk dilin bitip kalanın çağırana
 * BİLDİRİLMESİDİR.
 */
final class CeviriButcesiTest extends TestCase
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
        // Kolon kümesi `ProductRepository::COLUMNS` ile AYNI olmalı: depo tüm
        // alanları tek SELECT'te okur ve eksik bir kolon "no such column" ile
        // patlar. Şemayı elle yazmak yerine depodan türetmek daha temiz olurdu
        // ama depo bir DDL üretmiyor; en azından liste burada tam tutuluyor.
        $this->pdo->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT, list_id INTEGER NOT NULL,
                sort_no INTEGER NOT NULL DEFAULT 0, category_id INTEGER NULL,
                platform TEXT NULL, external_id TEXT NULL,
                name TEXT NULL, name_original TEXT NULL, name_elle INTEGER NOT NULL DEFAULT 0,
                source_lang TEXT NULL, detail TEXT NULL, url TEXT NULL,
                vendor_name TEXT NULL, vendor_url TEXT NULL,
                sku_selection TEXT NULL, sku_matrix TEXT NULL,
                main_image TEXT NULL, main_image_source TEXT NULL, video_url TEXT NULL,
                qty INTEGER NULL, price_yuan TEXT NULL, price_ddp_usd TEXT NULL, price_target_try TEXT NULL,
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
        $this->pdo->exec(
            "INSERT INTO products (list_id, name, name_original, source_lang, created_at, updated_at)
             VALUES (1, '无脚踏平衡车', '无脚踏平衡车', 'zh', '2026-08-29 10:00:00', '2026-08-29 10:00:00')",
        );

        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);
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

    public function testBUTCEDOLUNCAKALANDILLERBILDIRILIR(): void
    {
        // Bütçe 0 sn: ilk dil bitince kalan süre ZATEN tükenmiş sayılır.
        // Böylece "kısmen" yolu saniye beklemeden kanıtlanır.
        $cevirmen = new YavasCevirmen($this->pdo);
        $sonuc = $this->yurutucu($cevirmen)->urunuTamamla(1, 0);

        self::assertNotSame([], $sonuc['eksikti'], 'Ürünün eksik dilleri olmalı.');
        self::assertNotSame([], $sonuc['cevrilen'], 'En az bir dil bitmeli — hiçbiri bitmezse spinner yine takılırdı.');
        self::assertNotSame([], $sonuc['kalan'], 'Bütçeye sığmayan dil ÇAĞIRANA SÖYLENMELİ.');
        self::assertNull($sonuc['hata']);

        // Kalan diller çevrilmemiş olmalı: bütçe "yarım bırak" demektir,
        // "hızlıca ve kötü çevir" değil.
        self::assertSame(
            count($sonuc['eksikti']),
            count($sonuc['cevrilen']) + count($sonuc['kalan']),
            'Her eksik dil ya çevrildi ya kaldı; ikisi arasında kaybolan dil olamaz.',
        );
    }

    public function testBUTCESIZCAGRIHEPSINITEKTURDAISTER(): void
    {
        // Kuyruk işçisi bütçesiz çağırır: kullanıcı beklemiyor, hepsi istenir.
        $cevirmen = new YavasCevirmen($this->pdo);
        $sonuc = $this->yurutucu($cevirmen)->urunuTamamla(1, null);

        self::assertSame([], $sonuc['kalan'], 'Bütçesiz çağrıda kalan dil OLMAMALI.');
        self::assertSame(1, $cevirmen->cagriSayisi, 'Bütçesiz çağrı diller için TEK istek atmalı.');
    }

    public function testBUTCELICAGRIDILLERITEKTEKISTER(): void
    {
        // "Kısmen" ancak diller ayrı ayrı istenirse mümkündür: hepsini tek
        // istekte sormak bütçeyi ilk yanıtta aşar ve kısmi sonuç doğmaz.
        $cevirmen = new YavasCevirmen($this->pdo);
        $this->yurutucu($cevirmen)->urunuTamamla(1, 0);

        self::assertGreaterThanOrEqual(1, $cevirmen->cagriSayisi);
        foreach ($cevirmen->istenenDiller as $istek) {
            self::assertCount(1, $istek, 'Bütçeli çağrıda her istek TEK dil sormalı.');
        }
    }

    public function testCEVIRMENPATLARSAHATASOYLENIRVEDILLERKALIR(): void
    {
        $sonuc = $this->yurutucu(new PatlayanCevirmen())->urunuTamamla(1, 0);

        self::assertNotNull($sonuc['hata'], 'Sağlayıcı arızası SESSİZ geçilemez.');
        self::assertSame([], $sonuc['cevrilen']);
        self::assertNotSame([], $sonuc['kalan'], 'Patlayan çağrıda diller KALAN sayılmalı.');
    }
}

/**
 * Bütçeyi aşan sağlayıcı taklidi.
 *
 * Gerçekten UYUMAZ: test süresini uzatmak davranışı daha iyi kanıtlamaz ve
 * süite saniyeler ekler. Bütçe 0 verildiğinde ilk dilden sonra süre zaten
 * dolmuş sayılır; sınanan mantık aynıdır.
 */
final class YavasCevirmen implements TranslatorInterface
{
    public int $cagriSayisi = 0;

    /** @var list<list<string>> */
    public array $istenenDiller = [];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param  array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function translateProduct(array $product): array
    {
        $this->cagriSayisi++;
        /** @var list<string> $hedefler */
        $hedefler = is_array($product['target_langs'] ?? null) ? $product['target_langs'] : [];
        $this->istenenDiller[] = $hedefler;

        // GERÇEK ÇEVİRMEN GİBİ ÖNBELLEĞE YAZAR. Yürütücü "hangi dil bitti?"
        // sorusunu dönüş değerinden değil ÖNBELLEKTEN okur (K54: sonuç öneridir,
        // ürün alanına yazılmaz). Yazmayan bir taklit, hiçbir dili bitmemiş
        // gösterir ve test yanlış yerde kırmızı verirdi.
        $metin = (string) ($product['name'] ?? '');
        $kaynak = (string) ($product['source_lang'] ?? 'zh');
        $ceviriler = [];
        foreach ($hedefler as $dil) {
            $ceviriler[$dil] = ['name' => 'Çeviri ' . strtoupper($dil)];
            $ekle = $this->pdo->prepare(
                'INSERT INTO translation_cache
                    (source_hash, source_lang, target_lang, source_text, suggested_text, provider, surum, created_at)
                 VALUES (:h, :kaynak, :dil, :metin, :oneri, :saglayici, :surum, :simdi)',
            );
            $ekle->execute([
                'h' => hash('sha256', $metin . '|' . $dil),
                'kaynak' => $kaynak,
                'dil' => $dil,
                'metin' => $metin,
                'oneri' => 'Çeviri ' . strtoupper($dil),
                // `llm:` öneki ZORUNLU: kalıcılık ölçütü buna bakıyor (K91).
                'saglayici' => 'llm:sahte',
                'surum' => 'v1',
                'simdi' => '2026-08-29 10:00:00',
            ]);
        }

        return ['ceviriler' => $ceviriler];
    }

    /** @return array<string, string> */
    public function getGlossary(string $sourceLang = 'zh'): array
    {
        return [];
    }

    public function name(): string
    {
        return 'sahte';
    }
}

/** Sağlayıcı arızası. */
final class PatlayanCevirmen implements TranslatorInterface
{
    /**
     * @param  array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function translateProduct(array $product): array
    {
        throw new \RuntimeException('sağlayıcı 503');
    }

    /** @return array<string, string> */
    public function getGlossary(string $sourceLang = 'zh'): array
    {
        return [];
    }

    public function name(): string
    {
        return 'sahte';
    }
}
