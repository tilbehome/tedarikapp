<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\TranslationCacheRepository;
use App\Services\Translation\SozluksuzCeviriSayaci;
use Tests\Support\AuthTestCase;

/**
 * SERTLEŞTİRME v1.2.1 A6-EK — "SÖZLÜKSÜZ ÇEVRİLMİŞ ÜRÜN" SAYACI.
 *
 * NE ÖLÇÜLÜYOR: kuyruk yolu boş sözlükle koştuğu dönemde üretilen önbellek
 * satırları BAŞKA bir anahtara yazıldı (anahtar sözlük sürümünü içerir).
 * Panel ve belgeler doğru sözlükle anahtar hesapladığı için o satırları hiç
 * bulamaz — ürün "çevrildi" sanılır ama çevrilmemiştir.
 *
 * Kart bu ürünleri sayar; düğme onları yeniden kuyruğa alır. Sayı 0 ise kart
 * GİZLENİR: sıfır gösteren bir uyarı, bir süre sonra okunmaz hâle gelir.
 */
final class SozluksuzCeviriSayaciTest extends AuthTestCase
{
    private function sayac(): SozluksuzCeviriSayaci
    {
        return new SozluksuzCeviriSayaci($this->connection, $this->config(), dirname(__DIR__, 2));
    }

    private function urunEkle(string $orijinalAd): int
    {
        $this->pdo->exec(
            "INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at)
             VALUES ('L', '7', '41', '2026-08-31', '2026-08-31')",
        );
        $listId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO products (list_id, name, name_original, created_at, updated_at)
             VALUES (:l, 'Ürün', :o, '2026-08-31', '2026-08-31')",
        )->execute(['l' => $listId, 'o' => $orijinalAd]);

        return (int) $this->pdo->lastInsertId();
    }

    private function onbellekSatiri(string $kaynakMetin, string $surum): void
    {
        $this->pdo->prepare(
            'INSERT INTO translation_cache
                (source_hash, source_lang, target_lang, source_text, suggested_text, provider, surum, created_at)
             VALUES (:h, :sl, :tl, :st, :sug, :p, :v, :c)',
        )->execute([
            'h' => TranslationCacheRepository::hash($kaynakMetin, 'zh', 'tr', $surum),
            'sl' => 'zh',
            'tl' => 'tr',
            'st' => $kaynakMetin,
            'sug' => 'çeviri',
            'p' => 'llm',
            'v' => $surum,
            'c' => '2026-08-31 12:00:00',
        ]);
    }

    public function testBOZUKVEDOGRUANAHTARFARKLIDIR(): void
    {
        $sayac = $this->sayac();

        self::assertNotSame(
            $sayac->dogruAnahtar(),
            $sayac->bozukAnahtar(),
            'Boş sözlük ile dolu sözlük AYNI anahtarı üretemez; üretirse sayaç hiçbir şey ayırt edemez.',
        );
    }

    public function testHICBOZUKSATIRYOKSASIFIR(): void
    {
        $urunId = $this->urunEkle('不锈钢杯');
        $this->onbellekSatiri('不锈钢杯', $this->sayac()->dogruAnahtar());

        self::assertSame(0, $this->sayac()->urunSayisi(), 'Doğru sürümlü satır sayıma girmemeli.');
        self::assertNotSame(0, $urunId);
    }

    public function testBOZUKSATIRIOLANURUNSAYILIR(): void
    {
        $urunId = $this->urunEkle('不锈钢杯');
        $this->onbellekSatiri('不锈钢杯', $this->sayac()->bozukAnahtar());

        self::assertSame(1, $this->sayac()->urunSayisi());
        self::assertSame([$urunId], $this->sayac()->urunKimlikleri());
    }

    public function testSILINMISURUNSAYILMAZ(): void
    {
        // Çöpteki ürünü yeniden çevirmek boşa iş; kart da onu saymamalı.
        $urunId = $this->urunEkle('折叠伞');
        $this->onbellekSatiri('折叠伞', $this->sayac()->bozukAnahtar());
        $this->pdo->exec("UPDATE products SET deleted_at = '2026-08-31 13:00:00' WHERE id = " . $urunId);

        self::assertSame(0, $this->sayac()->urunSayisi());
    }

    public function testAYNIURUNIKIKEZSAYILMAZ(): void
    {
        // Aynı metin için birden çok bozuk satır olabilir (farklı alanlar);
        // ürün sayısı bundan etkilenmemeli.
        $this->urunEkle('保温杯');
        $this->onbellekSatiri('保温杯', $this->sayac()->bozukAnahtar());
        $this->pdo->prepare(
            'INSERT INTO translation_cache
                (source_hash, source_lang, target_lang, source_text, suggested_text, provider, surum, created_at)
             VALUES (:h, "zh", "tr", "保温杯", "termos", "llm", :v, "2026-08-31 12:00:00")',
        )->execute(['h' => 'ikinci-satir-hash', 'v' => $this->sayac()->bozukAnahtar()]);

        self::assertSame(1, $this->sayac()->urunSayisi());
    }
}
