<?php

declare(strict_types=1);

/**
 * ARAMA ALTYAPISI + KALİTE KAPISI (İE#20 C7 · C8).
 *
 * ── C7: ÇİFT DİLLİ ARAMA ────────────────────────────────────────────────────
 * Bugün arama `name LIKE '%…%'` ile yapılıyor. İki sorunu var:
 *   • BAŞTA JOKER olan LIKE indeks kullanamaz — her arama tam tarama demektir.
 *     100 üründe sorun değil, 5.000 üründe panel donar.
 *   • Yalnız TR ada bakar. Kullanıcı Çince başlıktan ya da İngilizce çeviriden
 *     aradığında sonuç bulamaz — oysa üç dil de elimizde.
 *
 * ÇÖZÜM: `products.arama_metni` — TR ad + orijinal başlık + çeviriler + ilan no
 * tek alanda toplanır. MySQL/MariaDB'de bu alana FULLTEXT indeks konur.
 *
 * NEDEN FULLTEXT VE ÖNERİ (emirde istendi): MariaDB 11.4 InnoDB FULLTEXT'i
 * destekler ama CJK (Çince) için varsayılan ayrıştırıcı KELİME SINIRI BULAMAZ —
 * Çince metinde boşluk yoktur. `ngram` ayrıştırıcısı MySQL'de vardır, MariaDB'de
 * YOKTUR. Bu yüzden ÖNERİMİZ: FULLTEXT'i LATİN metin (TR/EN) için kullanmak,
 * Çince aramayı LIKE ile yapmak ve `arama_metni` alanını KISA tutarak LIKE'ın
 * maliyetini düşürmek. Karma yaklaşım, tek bir motora bel bağlamaktan daha
 * dayanıklıdır ve iki veritabanında da AYNI sonucu verir.
 *
 * ── C8: KALİTE KAPISI ───────────────────────────────────────────────────────
 * `products.hazir` — ürün firmaya gönderilmeye HAZIR mı? Kapı sunucuda zorlanır
 * (K14 ilkesi: kural arayüzde değil sunucuda yaşar). Eksik alanı olan ürün
 * "hazır" işaretlenemez; boş liste tamamlanamaz.
 *
 * ── C9: EŞZAMANLILIK ────────────────────────────────────────────────────────
 * `products.surum` — kayıp yazma koruması (optimistic locking). İki kullanıcı
 * aynı ürünü açıp kaydederse, ikincisi birincinin değişikliğini SESSİZCE
 * eziyordu. Sürüm numarası uyuşmazsa güncelleme reddedilir.
 *
 * İDEMPOTENT (K23).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if (!$this->kolonVar($pdo, 'products', 'arama_metni')) {
            $pdo->exec('ALTER TABLE products ADD COLUMN arama_metni VARCHAR(2000) NULL');
        }
        if (!$this->kolonVar($pdo, 'products', 'hazir')) {
            $pdo->exec('ALTER TABLE products ADD COLUMN hazir TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$this->kolonVar($pdo, 'products', 'hazir_at')) {
            $pdo->exec('ALTER TABLE products ADD COLUMN hazir_at DATETIME NULL');
        }
        if (!$this->kolonVar($pdo, 'products', 'surum')) {
            $pdo->exec('ALTER TABLE products ADD COLUMN surum INT NOT NULL DEFAULT 1');
        }

        if ($sqlite) {
            // SQLite'ta FULLTEXT yoktur; test şeması LIKE ile çalışır.
            return;
        }

        // FULLTEXT: yalnız latin metin için. Çince arama LIKE'a düşer (yukarıdaki not).
        if (!$this->indeksVar($pdo, 'products', 'ft_products_arama')) {
            try {
                $pdo->exec('ALTER TABLE products ADD FULLTEXT KEY ft_products_arama (arama_metni)');
            } catch (Throwable) {
                // Bazı yapılandırmalarda FULLTEXT kapalı olabilir; arama LIKE ile
                // ÇALIŞMAYA DEVAM EDER. Kurulumu bunun için durdurmak yanlış olurdu.
            }
        }

        // C7: sayfalama ve aktivite sorguları için eksik indeksler.
        if (!$this->indeksVar($pdo, 'products', 'idx_products_hazir')) {
            $pdo->exec('ALTER TABLE products ADD KEY idx_products_hazir (list_id, hazir)');
        }
        if (!$this->indeksVar($pdo, 'activity_log', 'idx_activity_action_ip_created')) {
            $pdo->exec('ALTER TABLE activity_log ADD KEY idx_activity_action_ip_created (action, ip, created_at)');
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
