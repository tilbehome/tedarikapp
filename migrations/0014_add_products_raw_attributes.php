<?php

declare(strict_types=1);

/**
 * products.raw_attributes — parser'ın bulduğu HAM özellik/spec verisi (M23 değerlendirmesi §6.1).
 *
 * Neden bugün, kullanılmayacakken: GTİP sınıflandırması için gereken veriler (malzeme,
 * lif bileşimi, işlev, teknik parametre) 1688'in `featureAttributes` alanında duruyor.
 * Bugün saklanmazsa, modül 6 ay sonra yazıldığında GEÇMİŞ ürünlerin teknik verisi
 * kayıp olur — 1688 sayfası silinmiş, fiyat değişmiş olabilir. Geriye dönük doldurulamaz.
 *
 * Faz 3'te parser tarafından doldurulur; o güne kadar NULL kalır.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE products ADD COLUMN raw_attributes JSON NULL AFTER sku_matrix');
    }
};
