<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Ürün adı gösterim kuralları (İE#14 A1) — TEK KAYNAK.
 *
 * Excel, PDF ve paylaşım sayfası bu sınıftan geçer; üç yüzeyde aynı kural işler:
 * TR ad ile orijinal (ZH) başlık AYNIYSA ikinci satır HİÇ BASILMAZ. Aynılık
 * karşılaştırması görünmeyen farklara takılmaz (kırpma, katlanmış boşluk,
 * büyük/küçük harf) — kullanıcı için "aynı" olan metin teknik olarak da aynıdır.
 */
final class ProductNaming
{
    /**
     * Orijinal başlık basılmalı mı?
     *
     * @param array<string, mixed> $product
     */
    public static function showsOriginal(array $product): bool
    {
        return self::originalOf($product) !== null;
    }

    /**
     * Basılacak orijinal başlık; ad ile aynıysa (ya da boşsa) null.
     *
     * @param array<string, mixed> $product
     */
    public static function originalOf(array $product): ?string
    {
        $orijinal = is_string($product['name_original'] ?? null) ? trim((string) $product['name_original']) : '';
        if ($orijinal === '') {
            return null;
        }

        $ad = is_string($product['name'] ?? null) ? trim((string) $product['name']) : '';

        return self::sadelestir($ad) === self::sadelestir($orijinal) ? null : $orijinal;
    }

    /** Karşılaştırma biçimi: kenar boşlukları atılır, iç boşluk tekilleşir, küçük harfe iner. */
    private static function sadelestir(string $metin): string
    {
        $metin = (string) preg_replace('/\s+/u', ' ', trim($metin));

        return mb_strtolower($metin, 'UTF-8');
    }
}
