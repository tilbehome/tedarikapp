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
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE product_images
                ADD COLUMN storage_mode VARCHAR(10) NOT NULL DEFAULT 'local',
                ADD COLUMN source_url VARCHAR(1000) NULL
            SQL);

        $pdo->exec('ALTER TABLE products MODIFY main_image VARCHAR(1000) NULL');
    }
};
