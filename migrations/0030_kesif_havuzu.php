<?php

declare(strict_types=1);

/**
 * KEŞİF HAVUZU ALTYAPISI (İE#21 B1).
 *
 * İki kolon, iki somut ihtiyaç:
 *
 * 1. `listings.kume_anahtari` — 同款 (AYNI ÜRÜN) KÜMELEMESİ.
 *    Farklı satıcıların aynı ürünü, havuzda beş ayrı satır olarak görünüyordu.
 *    Kullanıcı "hangisi daha ucuz, hangi satıcı daha iyi" sorusunu ancak tek tek
 *    bakarak yanıtlayabiliyordu. Küme anahtarı aynı ürünün kopyalarını TEK KARTTA
 *    toplar ("5 kaynak · en ucuz ¥12 · en iyi karne X").
 *
 *    Anahtar YAKALAMADAN gelir ya da sonradan hesaplanır; burada yalnız TAŞINIR.
 *    Kümeleme kararı bir veri kalitesi işidir ve şemanın sorumluluğu değildir.
 *
 * 2. `products.arama_normal` — NORMALİZE ARAMA METNİ.
 *    `arama_metni` ham metni tutar (TR + ZH + çeviriler). Ama kullanıcı
 *    "şeffaf çekmeceli ayakkabı kutusu 33x23x14 cm" yazdığında kayıt
 *    "33×23×14cm" içerir: ASCII `x` ile `×` farklıdır, `cm` boşluğu farklıdır,
 *    "Ş" ile "s" farklıdır. Bu üç fark yüzünden doğru kayıt BULUNAMIYORDU
 *    (E2E-PNL-03). Normalize kopya yazma anında üretilir; arama ikisine birden
 *    bakar, böylece hem ham hem sadeleştirilmiş eşleşme çalışır.
 *
 * İDEMPOTENT (K23).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if (!$this->kolonVar($pdo, 'listings', 'kume_anahtari')) {
            $pdo->exec('ALTER TABLE listings ADD COLUMN kume_anahtari VARCHAR(64) NULL');
        }
        if (!$this->kolonVar($pdo, 'products', 'arama_normal')) {
            $pdo->exec('ALTER TABLE products ADD COLUMN arama_normal VARCHAR(2000) NULL');
        }

        if ($sqlite) {
            return;
        }

        if ($this->kolonVar($pdo, 'listings', 'kume_anahtari')
            && !$this->indeksVar($pdo, 'listings', 'idx_listings_kume')) {
            $pdo->exec('ALTER TABLE listings ADD KEY idx_listings_kume (kume_anahtari)');
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
