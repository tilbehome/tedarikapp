<?php

declare(strict_types=1);

/**
 * listings.skor — TEDARİKAPP SKORU v1 (İE#20 C6) + çeviri güven işareti.
 *
 * Skor İLANA aittir, ürüne değil: aynı ürünün iki platformdaki ilanı farklı
 * güven sinyalleri taşır (biri köklü satıcıda 5.000 satmış, diğeri yeni açılmış
 * mağazada). Ürüne yazsaydık iki ilanı karşılaştıramazdık.
 *
 * `skor` NULL OLABİLİR ve bu bir eksiklik değil BİLGİDİR: yeterli sinyal yoksa
 * skor GİZLENİR. Sıfır yazmak, veri olmayan ilanı "kötü" göstermek olurdu.
 *
 * `skor_bilesenleri` bileşen dökümünü taşır (ürün detayında gösterilir): kullanıcı
 * skorun NEREDEN geldiğini görmeden ona güvenmek zorunda kalmamalı.
 *
 * `translation_cache.guven` (K56): önbellekteki her çeviri hangi katmandan geldi —
 * sozluk (kesin) · llm (öneri) · makine (yedek). Arayüz bu işarete göre "kesin"
 * ile "gözden geçirin"i ayırır.
 *
 * İDEMPOTENT (K23): her kolon varlığı denetlenerek eklenir.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if (!$this->kolonVar($pdo, 'listings', 'skor')) {
            $pdo->exec('ALTER TABLE listings ADD COLUMN skor INT NULL');
        }
        if (!$this->kolonVar($pdo, 'listings', 'skor_bilesenleri')) {
            $pdo->exec('ALTER TABLE listings ADD COLUMN skor_bilesenleri ' . ($sqlite ? 'TEXT' : 'JSON') . ' NULL');
        }
        if (!$this->kolonVar($pdo, 'listings', 'skor_at')) {
            $pdo->exec('ALTER TABLE listings ADD COLUMN skor_at DATETIME NULL');
        }

        if (!$this->kolonVar($pdo, 'translation_cache', 'guven')) {
            $pdo->exec("ALTER TABLE translation_cache ADD COLUMN guven VARCHAR(10) NOT NULL DEFAULT 'makine'");
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
};
