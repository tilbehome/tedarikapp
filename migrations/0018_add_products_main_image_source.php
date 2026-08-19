<?php

declare(strict_types=1);

/**
 * products.main_image_source — ana görselin ORİJİNAL adresi (İE#10 5d, K37 §C9'un
 * main_image eşi). Arşive alınan görselin dosyası kaybolursa (canlı vaka: DB↔disk
 * ayrışması) onarım bu adresten yeniden indirir. product_images.source_url zaten
 * vardı; ana görsel için karşılığı eksikti.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE products ADD COLUMN main_image_source VARCHAR(1000) NULL AFTER main_image');
    }
};
