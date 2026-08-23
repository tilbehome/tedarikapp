<?php

declare(strict_types=1);

/**
 * SÜRÜMLÜ ÇEVİRİ BELLEĞİ (İE#21 B12).
 *
 * `translation_cache.surum` — satırın hangi üretim koşullarında (sağlayıcı, model,
 * prompt sürümü, sözlük sürümü, normalizasyon sürümü) oluştuğunu taşır.
 *
 * NEDEN KOLON DA GEREKLİ, ANAHTAR YETMİYOR: anahtar tek yönlü bir özettir; "bu
 * çeviri hangi modelle yapıldı" sorusunu yanıtlayamaz. Bir kalite şüphesinde
 * (ör. bir modelin ürün adlarına pazarlama sıfatı eklediğini fark ettik) o modelle
 * üretilmiş satırları BULUP temizleyebilmek gerekir. Kolon bunu mümkün kılar.
 *
 * ESKİ SATIRLAR: `surum` NULL kalır ve yeni anahtar şemasıyla eşleşmedikleri için
 * okunmazlar. SİLİNMEZLER — geçmiş bir kayıttır ve sorgulanabilir kalmalıdır;
 * ayrıca canlı kurulum yeni yapıldığı için pratikte boş bir tablodur.
 *
 * İDEMPOTENT (K23).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        if (!$this->kolonVar($pdo, 'translation_cache', 'surum')) {
            $pdo->exec('ALTER TABLE translation_cache ADD COLUMN surum VARCHAR(64) NULL');
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }

        // "Şu modelle üretilmiş satırları getir" sorgusu indekssiz tam tarama olurdu.
        if (!$this->indeksVar($pdo, 'translation_cache', 'idx_translation_surum')) {
            $pdo->exec('ALTER TABLE translation_cache ADD KEY idx_translation_surum (surum)');
        }
    }

    private function kolonVar(PDO $pdo, string $tablo, string $kolon): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?');
            $statement->execute([$tablo, $kolon]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $statement->execute([$tablo, $kolon]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function indeksVar(PDO $pdo, string $tablo, string $indeks): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
        );
        $statement->execute([$tablo, $indeks]);

        return (int) $statement->fetchColumn() > 0;
    }
};
