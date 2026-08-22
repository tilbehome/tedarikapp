<?php

declare(strict_types=1);

/**
 * K37 §C9 — medya yaşam döngüsü kolonları.
 *
 * `product_images.storage_mode` — görselin nerede yaşadığı: `local` (public/media/
 * altında bizim dosyamız) | `remote` (hotlink modu, K33: orijinal URL'den servis).
 * Temizlik (GC) yalnızca `local` kayıtların dosyalarına dokunur.
 *
 * `product_images.source_url` — indirilen görselin ORİJİNAL adresi. Dosya kaybolursa
 * / mod değişirse yeniden indirme ve denetim izi için saklanır.
 *
 * `products.main_image` VARCHAR(1000) — hotlink modunda buraya tam 1688/alicdn URL'si
 * yazılır; 255 gerçek dünyada kesiliyordu (imza parametreli CDN adresleri uzundur).
 * `source_url` da aynı gerekçeyle 1000'dir.
 *
 * İDEMPOTENT (İE#19 G7 · K23): bu dosya İKİ ALTER içeriyordu ve MySQL'de her DDL
 * ÖRTÜK COMMIT yapar. İlk ALTER geçip ikincisi düştüğünde (ör. bağlantı koptu,
 * `main_image` üzerinde kilit bekledi) transaction geri sarmaz: `storage_mode`
 * eklenmiş, defterde ise migration "uygulanmamış" kalır. Tekrar koşumda ilk ALTER
 * "Duplicate column name" ile patlar ve migration BİR DAHA ASLA tamamlanamaz —
 * sistem yarım şemayla kilitlenir. Artık her kolon ayrı ayrı, VARLIĞI DENETLENEREK
 * eklenir; yarım kalmış bir koşum tekrar çalıştırıldığında kaldığı yerden tamamlar.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        if (!$this->kolonVar($pdo, 'product_images', 'storage_mode')) {
            $pdo->exec("ALTER TABLE product_images ADD COLUMN storage_mode VARCHAR(10) NOT NULL DEFAULT 'local'");
        }

        if (!$this->kolonVar($pdo, 'product_images', 'source_url')) {
            $pdo->exec('ALTER TABLE product_images ADD COLUMN source_url VARCHAR(1000) NULL');
        }

        // MODIFY idempotenttir (aynı tipe getirmek hata vermez) ama SQLite bu sözdizimini
        // desteklemez; test şemasında kolon zaten geniş tanımlıdır.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $pdo->exec('ALTER TABLE products MODIFY main_image VARCHAR(1000) NULL');
        }
    }

    /** Kolon var mı? MySQL'de information_schema, SQLite'ta pragma (0020/0021 ile aynı desen). */
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
