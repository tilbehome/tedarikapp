<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * `products.source_lang` — ÜRÜNÜN KAYNAK DİLİ (D12, 28 Ağu 2026).
 *
 * NEDEN: kanonik üç dil kararı (TR + EN + ZH) "hangi dil ORİJİNAL?" sorusunun
 * cevabını ister. Kaynak dil üçlünün içindeyse o dil çevrilmez, aynen saklanır
 * ve eksik iki dil üretilir; dışındaysa (ör. Almanca bir site) ham orijinal
 * ayrıca durur ve üçü de üretilir. Bu bilgi kayıtta yoksa her tur baştan tahmin
 * eder ve TR kaynaklı bir ürünü "TR'ye çevirmeye" kalkışabilir.
 *
 * GERİYE DÖNÜK DOLDURMA: bugüne kadarki her kayıt 1688'den geldi, yani Çince.
 * Yine de tahmin edilmez, ÖLÇÜLÜR: `name_original` Çince karakter taşıyorsa
 * `zh`, taşımıyorsa `en` yazılır (platform 1688 olsa bile ASCII başlıklı ilan
 * vardır). Boş orijinali olan satır NULL kalır — bilmediğimizi uydurmayız.
 *
 * İleri yönlüdür (K23): kolon eklenir, mevcut veri yalnız DOLDURULUR.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        if (!$this->kolonVar($pdo, 'products', 'source_lang')) {
            $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $pdo->exec($sqlite
                ? 'ALTER TABLE products ADD COLUMN source_lang VARCHAR(10) NULL'
                : 'ALTER TABLE products ADD COLUMN source_lang VARCHAR(10) NULL AFTER name_original');
        }

        // Çince karakter aralığı (CJK Unified Ideographs + Extension A) REGEXP ile
        // aranamaz: SQLite'ta REGEXP yoktur, MySQL'de sürüme göre davranır.
        // Satırlar PHP'de ölçülür — veri kümesi bir kurulumun ürünleridir, küçüktür.
        $satirlar = $pdo->query(
            "SELECT id, name_original FROM products
             WHERE source_lang IS NULL AND name_original IS NOT NULL AND TRIM(name_original) <> ''",
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $guncelle = $pdo->prepare('UPDATE products SET source_lang = :dil WHERE id = :id');
        foreach ($satirlar as $satir) {
            $metin = (string) ($satir['name_original'] ?? '');
            $guncelle->execute([
                'dil' => preg_match('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u', $metin) === 1 ? 'zh' : 'en',
                'id' => (int) $satir['id'],
            ]);
        }
    }

    private function kolonVar(PDO $pdo, string $tablo, string $kolon): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $satirlar = $pdo->query('PRAGMA table_info(' . $tablo . ')')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($satirlar as $satir) {
                if (($satir['name'] ?? null) === $kolon) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tablo AND COLUMN_NAME = :kolon',
        );
        $statement->execute(['tablo' => $tablo, 'kolon' => $kolon]);

        return (int) $statement->fetchColumn() > 0;
    }
};
