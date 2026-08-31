<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Core\Config;
use App\Core\Connection;
use App\Core\Encrypter;
use App\Models\SettingsRepository;
use App\Models\TranslationCacheRepository;

/**
 * ValueSet kurulumunun TEK YERİ (İE#21 B9/B12).
 *
 * ValueSet artık dört parça ister: sözlük, önbellek, hedef dil ve sürüm anahtarı.
 * Bunu iki ayrı yerde (AppBuilder ve PublicRoutes) elle kurmak, birinin sürümü
 * unutması demekti — o zaman panel ile paylaşım sayfası FARKLI önbellek satırlarını
 * okur ve aynı ürün iki yerde iki türlü görünürdü. Kurulum burada tek noktadadır.
 */
final class ValueSetFabrikasi
{
    public static function kur(
        Connection $connection,
        Config $config,
        string $basePath,
        string $hedefDil = 'tr',
    ): ValueSet {
        $sozluk = SozlukFabrikasi::kur($basePath);

        // Sürüm ayarlardan okunur; ayar okunamıyorsa (kurulum yarım, tablo yok)
        // önbelleksiz ama ÇALIŞAN bir ValueSet döner — belge üretimi durmaz.
        try {
            $ayarlar = new CeviriAyarlari(new SettingsRepository($connection), new Encrypter($config));
            $surum = CeviriSurumu::kur($ayarlar, $sozluk)->anahtar();
        } catch (\Throwable) {
            return new ValueSet($sozluk, null, $hedefDil);
        }

        return new ValueSet($sozluk, new TranslationCacheRepository($connection), $hedefDil, $surum);
    }
}
