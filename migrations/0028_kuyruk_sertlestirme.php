<?php

declare(strict_types=1);

/**
 * KUYRUK SERTLEŞTİRME (İE#21 B11 · #12).
 *
 * Üç yeni kolon, üç somut arıza için:
 *
 * 1. `kilit_token` — SAHİPLİK KANITI.
 *    Bugün kilit "hangi işleyici aldı" bilgisini tutuyor ama işi BİTİREN taraf
 *    kimliğini kanıtlamıyor. Senaryo: A işi alır, takılır, kirası dolar, B işi
 *    devralır ve bitirir; sonra A uyanır ve `basarili()` çağırır — B'nin sonucu
 *    A tarafından ezilir, iki kez koşan iş "bir kez koştu" görünür. Token,
 *    her sahiplenmede yeniden üretilir; durum geçişleri token eşleşmezse YAZMAZ.
 *
 * 2. `kilit_bitis` — KİRA BİTİŞİ AÇIK YAZILIR.
 *    Eskiden kira "kilitlendi_at + 900 sn" diye HESAPLANIYORDU; yani süre koda
 *    gömülüydü ve uzatılamıyordu. Uzun süren bir iş (50 ürünlük çeviri turu)
 *    kirası dolduğu için başkası tarafından devralınabiliyordu — üstelik hâlâ
 *    çalışırken. Açık bitiş, kalp atışıyla (heartbeat) UZATILABİLİR.
 *
 * 3. `hata_sinifi` — NEDEN başarısız oldu.
 *    "Geçici ağ hatası" ile "kalıcı yapılandırma hatası" aynı muameleyi görüyordu:
 *    ikisi de 3 kez denenip ölü rafına gidiyordu. Sınıf bilgisi hem yeniden deneme
 *    politikasını hem de paneldeki ölü mektup ekranını besler.
 *
 * İDEMPOTENT (K23).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if (!$this->kolonVar($pdo, 'jobs', 'kilit_token')) {
            $pdo->exec('ALTER TABLE jobs ADD COLUMN kilit_token VARCHAR(64) NULL');
        }
        if (!$this->kolonVar($pdo, 'jobs', 'kilit_bitis')) {
            $pdo->exec('ALTER TABLE jobs ADD COLUMN kilit_bitis DATETIME NULL');
        }
        if (!$this->kolonVar($pdo, 'jobs', 'hata_sinifi')) {
            $pdo->exec('ALTER TABLE jobs ADD COLUMN hata_sinifi VARCHAR(24) NULL');
        }

        if ($sqlite) {
            return;
        }

        // Kirası dolmuş işleri bulmak tam tarama olmamalı.
        if (!$this->indeksVar($pdo, 'jobs', 'idx_jobs_kira')) {
            $pdo->exec('ALTER TABLE jobs ADD KEY idx_jobs_kira (durum, kilit_bitis)');
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
