<?php

declare(strict_types=1);

/**
 * İLAN TOPLAM SATIŞI (İE#21 C3 — skor kalibrasyonunun bulgusu).
 *
 * `listings.satis_adedi` son 30 günün satışıdır. Skor motoru "ivme" hesaplayabilmek
 * için TOPLAM satışa da ihtiyaç duyar: 1.468 toplam satışın 1.163'ünü son ayda yapan
 * yeni ilan ile 55.000 satışın 4.000'ini yapan olgun ilan aynı sayıyı verse bile aynı
 * hikâyeyi anlatmaz. Mutlak hacim olgunu bulur, ivme yükseleni.
 *
 * Kolon NULL kalabilir: her kaynak toplam satışı vermez ve bilinmeyeni sıfır saymak
 * ivmeyi %100 gösterirdi (motorda bu yüzden "toplam yoksa ivme de yok" kuralı var).
 *
 * İDEMPOTENT (K23).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        if (!$this->kolonVar($pdo, 'listings', 'satis_toplam')) {
            $pdo->exec('ALTER TABLE listings ADD COLUMN satis_toplam INT NULL');
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
