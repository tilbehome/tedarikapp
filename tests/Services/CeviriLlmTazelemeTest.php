<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\ProductRepository;
use App\Models\TranslationCacheRepository;
use Tests\Support\AuthTestCase;

/**
 * D6 — LLM TURU MAKİNE ÇEVİRİSİNİN ÜSTÜNE YAZAR (saha bulgusu, 25 Ağu 2026).
 *
 * BULGU: altın set koşumunda TR 4/4 doluydu ama sağlayıcı `mymemory`di ve kalite
 * düşüktü ("无脚踏 → Bisiklet Yok"; doğrusu "pedalsız", "乐扣杯 → Le toka fincan").
 * EN ise 2/4 ve `llm:deepseek` kalitesindeydi. İki mekanik sebep vardı:
 *
 *   1. ADAYLIK ÖLÇÜTÜ: ürün "önbellekte herhangi bir satırı varsa çevrilmiş"
 *      sayılıyordu. Yakalamada makine katmanı TR'yi doldurduğu için LLM turu
 *      ürüne HİÇ uğramıyordu.
 *   2. YAZMA: `store()` yalnız INSERT ediyordu; aynı anahtarda satır varsa
 *      sessizce geçiyordu. LLM tura girse bile makine satırı yerinde kalıyordu.
 *
 * K56'nın "TR+EN tek LLM isteğinde birlikte" ilkesi böylece fiilen bozuluyordu.
 * Bu süit iki mekanizmayı da kilitler ve K54 sınırını korur: ONAYLI ELLE
 * DÜZELTME hiçbir otomatik tur tarafından ezilmez.
 */
final class CeviriLlmTazelemeTest extends AuthTestCase
{
    private TranslationCacheRepository $onbellek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onbellek = new TranslationCacheRepository($this->connection);
    }

    private function satirYaz(string $kaynak, string $hedefDil, string $metin, string $saglayici): string
    {
        $hash = TranslationCacheRepository::hash($kaynak, 'zh', $hedefDil);
        $this->onbellek->store($hash, $kaynak, $metin, $saglayici, 'zh', $hedefDil, $this->clock->now());

        return $hash;
    }

    private function urunEkle(string $ad, string $orijinal, int $listeId = 1, string $kaynakDil = 'zh'): int
    {
        // D12: kaynak dili artık kayıtta durur — adaylık ölçütü ona göredir.
        $statement = $this->pdo->prepare(
            'INSERT INTO products (list_id, name, name_original, source_lang, created_at, updated_at)
             VALUES (:liste, :ad, :orijinal, :kaynak, :simdi, :simdi)',
        );
        $statement->execute([
            'liste' => $listeId,
            'ad' => $ad,
            'orijinal' => $orijinal,
            'kaynak' => $kaynakDil,
            'simdi' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // ── 1) YAZMA: makine satırı tazelenir, kalıcı satır korunur ──────────────

    public function testMAKINESATIRIL_LM_SONUCUYLA_TAZELENIR(): void
    {
        $hash = $this->satirYaz('无脚踏', 'tr', 'Bisiklet Yok', 'mymemory');

        $sonuc = $this->onbellek->tazele(
            $hash,
            '无脚踏',
            'pedalsız',
            'llm:deepseek',
            'zh',
            'tr',
            $this->clock->now(),
        );

        self::assertSame('tazelendi', $sonuc);
        $satir = $this->onbellek->find($hash);
        self::assertSame('pedalsız', $satir['suggested_text'] ?? null);
        self::assertSame('llm:deepseek', $satir['provider'] ?? null);
    }

    public function testONAYLIELLEDUZELTMEEZILMEZ(): void
    {
        $hash = $this->satirYaz('乐扣杯', 'tr', 'Lock&Lock termos', TranslationCacheRepository::ELLE_SAGLAYICI);

        $sonuc = $this->onbellek->tazele(
            $hash,
            '乐扣杯',
            'Le toka fincan',
            'llm:deepseek',
            'zh',
            'tr',
            $this->clock->now(),
        );

        // K54: kullanıcı onayladıysa son söz onundur — LLM bile üstüne yazamaz.
        self::assertSame('korundu', $sonuc);
        self::assertSame('Lock&Lock termos', $this->onbellek->find($hash)['suggested_text'] ?? null);
    }

    public function testLLMSATIRIUZERINEIKINCIKEZYAZILMAZ(): void
    {
        $hash = $this->satirYaz('无脚踏', 'tr', 'pedalsız', 'llm:deepseek');

        self::assertSame(
            'korundu',
            $this->onbellek->tazele($hash, '无脚踏', 'başka', 'llm:deepseek', 'zh', 'tr', $this->clock->now()),
        );
    }

    public function testSATIRYOKSAEKLENIR(): void
    {
        $hash = TranslationCacheRepository::hash('保温杯', 'zh', 'tr');

        self::assertSame(
            'eklendi',
            $this->onbellek->tazele($hash, '保温杯', 'termos', 'llm:deepseek', 'zh', 'tr', $this->clock->now()),
        );
        self::assertSame('termos', $this->onbellek->find($hash)['suggested_text'] ?? null);
    }

    public function testKALICILIKOLCUTU(): void
    {
        self::assertTrue(TranslationCacheRepository::llmMi('llm:deepseek'));
        self::assertFalse(TranslationCacheRepository::llmMi('mymemory'));
        self::assertTrue(TranslationCacheRepository::kaliciMi(TranslationCacheRepository::ELLE_SAGLAYICI));
        // Makine ve sözlük satırları geçici doldurmadır: üzerine yazılabilir.
        self::assertFalse(TranslationCacheRepository::kaliciMi('mymemory'));
        self::assertFalse(TranslationCacheRepository::kaliciMi('katmanli'));
    }

    public function testHEMSURUMLUHEMSURUMSUZANAHTARTAZELENIR(): void
    {
        // Saha durumu: makine çevirisi SÜRÜMSÜZ anahtarda duruyor; kullanıcıya
        // ve altın set sınavına görünen satır bu.
        $surumsuz = $this->satirYaz('无脚踏', 'tr', 'Bisiklet Yok', 'mymemory');

        $sonuclar = $this->onbellek->tazeleTumAnahtarlar(
            '无脚踏',
            'pedalsız',
            'llm:deepseek',
            'zh',
            'tr',
            $this->clock->now(),
            'a1b2c3d4e5f6',
        );

        self::assertSame('eklendi', $sonuclar['a1b2c3d4e5f6']);
        self::assertSame('tazelendi', $sonuclar['']);
        // Kullanıcının gördüğü satır artık LLM sonucudur — bulgunun özü buydu.
        self::assertSame('pedalsız', $this->onbellek->find($surumsuz)['suggested_text'] ?? null);
        self::assertSame(
            'pedalsız',
            $this->onbellek->find(
                TranslationCacheRepository::hash('无脚踏', 'zh', 'tr', 'a1b2c3d4e5f6'),
            )['suggested_text'] ?? null,
        );
    }

    public function testTAZELEMEONAYLISATIRIHERIKIANAHTARDADAKORUR(): void
    {
        $hash = $this->satirYaz('乐扣杯', 'tr', 'Lock&Lock termos', TranslationCacheRepository::ELLE_SAGLAYICI);

        $sonuclar = $this->onbellek->tazeleTumAnahtarlar(
            '乐扣杯',
            'Le toka fincan',
            'llm:deepseek',
            'zh',
            'tr',
            $this->clock->now(),
            'a1b2c3d4e5f6',
        );

        self::assertSame('korundu', $sonuclar['']);
        self::assertSame('Lock&Lock termos', $this->onbellek->find($hash)['suggested_text'] ?? null);
    }

    // ── 2) ADAYLIK: makineyle dolu TR ürünü LLM turundan MUAF TUTMAZ ─────────

    public function testMAKINECEVIRISIYLEDOLUURUNYINEADAYDIR(): void
    {
        $urunId = $this->urunEkle('Bisiklet Yok', '无脚踏');
        $this->satirYaz('无脚踏', 'tr', 'Bisiklet Yok', 'mymemory');

        $adaylar = (new ProductRepository($this->connection))->cevrilmemisler(null, 500);

        // Saha vakası tam olarak buydu: TR "dolu" diye ürün atlanıyordu.
        self::assertContains($urunId, $adaylar);
    }

    public function testTUMHEDEFDILLERLLMDENGELDIYSEADAYDEGIL(): void
    {
        $urunId = $this->urunEkle('pedalsız', '无脚踏');
        $this->satirYaz('无脚踏', 'tr', 'pedalsız', 'llm:deepseek');
        $this->satirYaz('无脚踏', 'en', 'no pedals', 'llm:deepseek');

        $adaylar = (new ProductRepository($this->connection))->cevrilmemisler(null, 500);

        self::assertNotContains($urunId, $adaylar);
    }

    public function testTEKDILEKSIKSEURUNYINEISLENIR(): void
    {
        $urunId = $this->urunEkle('pedalsız', '无脚踏');
        $this->satirYaz('无脚踏', 'tr', 'pedalsız', 'llm:deepseek');
        // EN yok: K56 gereği iki dil TEK istekte üretilir, eksik dil turu tetikler.

        $adaylar = (new ProductRepository($this->connection))->cevrilmemisler(null, 500);

        self::assertContains($urunId, $adaylar);
    }

    public function testONAYLIELLECEVIRIODILICINTURGEREKTIRMEZ(): void
    {
        $urunId = $this->urunEkle('Lock&Lock termos', '乐扣杯');
        $this->satirYaz('乐扣杯', 'tr', 'Lock&Lock termos', TranslationCacheRepository::ELLE_SAGLAYICI);
        $this->satirYaz('乐扣杯', 'en', 'Lock&Lock flask', 'llm:deepseek');

        $adaylar = (new ProductRepository($this->connection))->cevrilmemisler(null, 500);

        // Onaylı çeviri zaten kalıcıdır; onu yeniden üretmek kota harcamaktır.
        self::assertNotContains($urunId, $adaylar);
    }

    public function testORIJINALIOLMAYANURUNSINAVADEGIRMEZ(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (list_id, name, name_original, created_at, updated_at)
             VALUES (1, :ad, NULL, :simdi, :simdi)',
        );
        $statement->execute(['ad' => 'Elle girilen ürün', 'simdi' => $this->clock->now()->format('Y-m-d H:i:s')]);
        $urunId = (int) $this->pdo->lastInsertId();

        $adaylar = (new ProductRepository($this->connection))->cevrilmemisler(null, 500);

        self::assertNotContains($urunId, $adaylar);
    }

    /**
     * D12 — ÖLÇÜT ARTIK AYARLARDAKİ LİSTE DEĞİL, ÜRÜNÜN KAYNAK DİLİ.
     *
     * Çince kaynaklı ürün için TR ve EN gerekir; ZH zaten orijinaldir ve
     * üretilmez. Eski ölçüt (ayarlardaki hedef dil listesi) ürüne bakmıyordu.
     */
    public function testCINCEKAYNAKTATRVEENGEREKIR(): void
    {
        $urunId = $this->urunEkle('pedalsız', '无脚踏');
        $this->satirYaz('无脚踏', 'tr', 'pedalsız', 'llm:deepseek');

        $depo = new ProductRepository($this->connection);
        // EN eksik: aday.
        self::assertContains($urunId, $depo->cevrilmemisler(null, 500));

        $this->satirYaz('无脚踏', 'en', 'no pedals', 'llm:deepseek');
        // TR+EN tamam, ZH orijinal → aday DEĞİL. ZH satırı hiç aranmaz.
        self::assertNotContains($urunId, $depo->cevrilmemisler(null, 500));
    }

    /** TÜRKÇE kaynakta motor TR'ye DOKUNMAZ; EN ve ZH üretilir. */
    public function testTURKCEKAYNAKTATRISTENMEZ(): void
    {
        $urunId = $this->urunEkle('Kalın Tabanlı Terlik', 'Kalın Tabanlı Terlik', 1, 'tr');
        $this->satirYaz('Kalın Tabanlı Terlik', 'en', 'Thick Sole Slipper', 'llm:deepseek');
        $this->satirYaz('Kalın Tabanlı Terlik', 'zh', '厚底拖鞋', 'llm:deepseek');

        // TR çevirisi YOK ama gerek de yok: kaynak dil TR, orijinal odur.
        self::assertNotContains($urunId, (new ProductRepository($this->connection))->cevrilmemisler(null, 500));
    }
}
