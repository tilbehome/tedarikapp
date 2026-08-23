<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Models\TranslationCacheRepository;
use App\Services\Translation\CeviriSurumu;
use App\Services\Translation\CevrilecekDegerler;
use App\Services\Translation\Glossary;
use App\Services\Translation\ValueSet;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * ÇEVİRİ TAM KAPSAM + SÜRÜMLÜ BELLEK (İE#21 B9 · B12).
 *
 * Canlı kanıt: TDK-2026-0001'de Renk/Marka/varyasyonlar ham Çince kaldı. İki ayrı
 * kusur vardı ve ikisi de burada sabitlenir:
 *   YAZMA — kuyruk işi LLM'e yalnız ürün ADINI soruyordu (`attributes: []`).
 *   OKUMA — sunum katmanı yalnız SÖZLÜĞE bakıyordu; kuyruğun ürettiği çeviriyi
 *           hiç okumuyordu, dolayısıyla çeviri üretilse bile görünmezdi.
 */
final class CeviriKapsamiTest extends TestCase
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
        $this->pdo->exec('CREATE TABLE translation_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_hash TEXT NOT NULL UNIQUE,
            source_lang TEXT NOT NULL,
            target_lang TEXT NOT NULL,
            source_text TEXT NOT NULL,
            suggested_text TEXT NOT NULL,
            provider TEXT NOT NULL,
            surum TEXT NULL,
            created_at TEXT NOT NULL
        )');
        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);
    }

    private function cache(): TranslationCacheRepository
    {
        return new TranslationCacheRepository($this->connection);
    }

    private function onbellegeYaz(string $ham, string $ceviri, string $dil, string $surum): void
    {
        $this->cache()->store(
            TranslationCacheRepository::hash($ham, 'zh', $dil, $surum),
            $ham,
            $ceviri,
            'deepseek',
            'zh',
            $dil,
            new DateTimeImmutable('2026-08-23 10:00:00'),
            $surum,
        );
    }

    private function degerler(string $dil = 'tr', string $surum = 'v1'): ValueSet
    {
        return new ValueSet(null, $this->cache(), $dil, $surum);
    }

    // ─────────────────────── B9: OKUMA ───────────────────────

    public function testOnbellektekiCeviriKULLANILIR(): void
    {
        $this->onbellegeYaz('灰色', 'Gri', 'tr', 'v1');

        self::assertSame('Gri', $this->degerler()->value('灰色'));
    }

    public function testCevrilemeyenDegerHAMKALIRAMAISARETLENIR(): void
    {
        $degerler = $this->degerler();

        // Değer KAYBOLMAZ: veri her zaman korunur.
        self::assertSame('不锈钢', $degerler->value('不锈钢'));
        // Ama sessizce melez bırakılmaz.
        self::assertTrue($degerler->bekliyorMu('不锈钢'));
        self::assertTrue($degerler->bekleyenVar());
        self::assertSame(['不锈钢'], $degerler->bekleyenler());
    }

    public function testLatinDegerCEVIRIBEKLEMEZ(): void
    {
        // "ABS", "058", "5 kg" çeviri gerektirmez; bunları işaretlemek göstergeyi
        // gürültüye boğar ve gerçek eksikleri gizler.
        $degerler = $this->degerler();

        foreach (['ABS', '058', '5 kg', 'PP'] as $deger) {
            self::assertSame($deger, $degerler->value($deger));
            self::assertFalse($degerler->bekliyorMu($deger), $deger . ' işaretlenmemeli');
        }
        self::assertFalse($degerler->bekleyenVar());
    }

    public function testEtiketliDegerinIKIPARCASIDACEVRILIR(): void
    {
        $this->onbellegeYaz('颜色', 'Renk', 'tr', 'v1');
        $this->onbellegeYaz('灰色', 'Gri', 'tr', 'v1');

        self::assertSame('Renk: Gri', $this->degerler()->value('颜色: 灰色'));
    }

    public function testBirlesikVaryasyonPARCAPARCACEVRILIR(): void
    {
        $this->onbellegeYaz('灰色', 'Gri', 'tr', 'v1');

        // İkinci parça çevrilemedi: metin korunur, işaret düşer.
        $degerler = $this->degerler();
        self::assertSame('Gri / 大号', $degerler->value('灰色 / 大号'));
        self::assertTrue($degerler->bekliyorMu('大号'));
    }

    public function testINGILIZCEGORUNUMKENDIONBELLEGINIOKUR(): void
    {
        $this->onbellegeYaz('灰色', 'Gri', 'tr', 'v1');
        $this->onbellegeYaz('灰色', 'Grey', 'en', 'v1');

        self::assertSame('Gri', $this->degerler('tr')->value('灰色'));
        self::assertSame('Grey', $this->degerler('en')->value('灰色'));
    }

    public function testCINCEGORUNUMDEDEGERHAMVEISARETSIZ(): void
    {
        // ZH bir çeviri değil KAYNAKTIR (K70): "çeviri bekliyor" demek saçma olurdu.
        $degerler = $this->degerler('zh');

        self::assertSame('不锈钢', $degerler->value('不锈钢'));
        self::assertFalse($degerler->bekliyorMu('不锈钢'));
    }

    public function testSOZLUKONBELLEGIYENER(): void
    {
        // Sözlük insan kararıdır ve makineyi BAĞLAR (K70).
        $sozlukDizini = sys_get_temp_dir() . '/tedarikapp-sozluk-' . bin2hex(random_bytes(4));
        mkdir($sozlukDizini, 0775, true);
        file_put_contents($sozlukDizini . '/sozluk-zh-tr.php', "<?php\n\nreturn ['其他' => 'Diğer'];\n");

        $this->onbellegeYaz('其他', 'Başkaları', 'tr', 'v1');

        $degerler = new ValueSet(new Glossary($sozlukDizini), $this->cache(), 'tr', 'v1');
        self::assertSame('Diğer', $degerler->value('其他'));

        unlink($sozlukDizini . '/sozluk-zh-tr.php');
        rmdir($sozlukDizini);
    }

    public function testDILEGORESAYACAYRIDIR(): void
    {
        $this->onbellegeYaz('灰色', 'Gri', 'tr', 'v1');

        $tr = $this->degerler('tr');
        $tr->value('灰色');
        self::assertFalse($tr->bekleyenVar(), 'TR tamam');

        $en = $tr->withDil('en');
        $en->value('灰色');
        self::assertTrue($en->bekleyenVar(), 'EN eksik — TR sayacı bunu gizlememeli');
    }

    // ─────────────────────── B12: SÜRÜMLÜ BELLEK ───────────────────────

    public function testSURUMDEGISINCEESKICEVIRIOKUNMAZ(): void
    {
        $this->onbellegeYaz('灰色', 'Gri', 'tr', 'v1');

        // Model/prompt/sözlük değişti → anahtar değişti → eski satır ölü.
        $yeni = $this->degerler('tr', 'v2');
        self::assertSame('灰色', $yeni->value('灰色'));
        self::assertTrue($yeni->bekliyorMu('灰色'));
    }

    public function testSURUMANAHTARIBILESENLERDENTURER(): void
    {
        $temel = new CeviriSurumu('deepseek', 'deepseek-v4-flash', 'g1');

        self::assertSame($temel->anahtar(), (new CeviriSurumu('deepseek', 'deepseek-v4-flash', 'g1'))->anahtar());
        self::assertNotSame($temel->anahtar(), (new CeviriSurumu('openai', 'deepseek-v4-flash', 'g1'))->anahtar());
        self::assertNotSame($temel->anahtar(), (new CeviriSurumu('deepseek', 'gpt-5.6-terra', 'g1'))->anahtar());
        self::assertNotSame($temel->anahtar(), (new CeviriSurumu('deepseek', 'deepseek-v4-flash', 'g2'))->anahtar());
        self::assertNotSame(
            $temel->anahtar(),
            (new CeviriSurumu('deepseek', 'deepseek-v4-flash', 'g1', '99'))->anahtar(),
        );
    }

    public function testSURUMKOLONAYAZILIR(): void
    {
        // "Bu çeviri hangi modelle üretildi" sorusu sorulabilir olmalı.
        $this->onbellegeYaz('灰色', 'Gri', 'tr', 'abc123');

        self::assertSame(
            'abc123',
            $this->pdo->query('SELECT surum FROM translation_cache LIMIT 1')->fetchColumn(),
        );
    }

    // ─────────────────────── B9: YAZMA KÜMESİ ───────────────────────

    public function testCEVRILECEKDEGERLERRAWVARYANTVEMATRISITOPLAR(): void
    {
        $urun = [
            'raw_attributes' => json_encode([
                'normalized_attributes' => ['品牌' => '其他', '材质' => '不锈钢', '货号' => '1225-1/818'],
            ]),
            'sku_selection' => ['颜色' => '灰色'],
            'sku_matrix' => [['props' => ['颜色' => '黑色']], ['props' => ['颜色' => '白色']]],
            'name_original' => '双层保温杯500ml',
        ];

        $kume = CevrilecekDegerler::topla($urun);

        foreach (['品牌', '其他', '材质', '不锈钢', '颜色', '灰色', '黑色', '白色', '双层保温杯500ml'] as $beklenen) {
            self::assertArrayHasKey($beklenen, $kume, $beklenen . ' kümede olmalı');
        }
    }

    public function testKIMLIKDEGERLERICEVRILMEZ(): void
    {
        $urun = [
            'raw_attributes' => json_encode(['normalized_attributes' => ['货号' => '1225-1/818', '型号' => '058']]),
        ];

        $kume = CevrilecekDegerler::topla($urun);

        // Anahtarlar (Çince) çevrilir ama DEĞERLER kimliktir — çevrilirse eşleşme bozulur.
        self::assertArrayNotHasKey('1225-1/818', $kume);
        self::assertArrayNotHasKey('058', $kume);
        self::assertArrayHasKey('货号', $kume);
    }

    public function testCOKUZUNMETINKUMEYEGIRMEZ(): void
    {
        $uzun = str_repeat('说明', 200);
        $kume = CevrilecekDegerler::topla([
            'raw_attributes' => json_encode(['normalized_attributes' => ['详情' => $uzun]]),
        ]);

        self::assertArrayNotHasKey($uzun, $kume);
    }

    public function testUSTSINIRUYGULANIR(): void
    {
        $oznitelikler = [];
        for ($i = 0; $i < 200; $i++) {
            $oznitelikler['属性' . $i] = '值' . $i;
        }

        $kume = CevrilecekDegerler::topla([
            'raw_attributes' => json_encode(['normalized_attributes' => $oznitelikler]),
        ]);

        self::assertLessThanOrEqual(CevrilecekDegerler::UST_SINIR, count($kume));
    }
}
