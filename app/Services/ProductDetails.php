<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Share\ProductFacts;
use App\Services\Translation\ValueSet;

/**
 * Ürünün KATEGORİ ve DETAY metinlerini türetir (İE#14 A4).
 *
 * SORUN: kategori kolonu "Kategorisiz" ve detay kolonu boş basılıyordu — oysa
 * yakalanan sayfada bu bilgi VAR: kırıntı yolu (面包屑) kategoriyi, öznitelikler
 * de kısa bir tanımı verir.
 *
 * KURAL (şartname): VERİ YOKSA ALAN HİÇ BASILMAZ. "Kategorisiz" yazılmaz, boş
 * etiket basılmaz — çağıran taraf null görürse hücreyi boş bırakır. Uydurma yok:
 * kategori yalnız gerçek kırıntı yolundan, detay yalnız gerçek özniteliklerden gelir.
 */
final class ProductDetails
{
    /** Kırıntı yolunda kategori sayılmayan kök adımlar. */
    private const KOK_ADIMLAR = ['首页', '首頁', '1688', '所有分类', '全部分类', 'home', 'all categories', 'alibaba'];

    /** Detay metnine girecek EN ÇOK öznitelik. */
    private const DETAY_ALANI = 4;

    /** Detayda anlamsız kalan alanlar (satır zaten kendi sütununda var). */
    private const DETAY_DISI = ['İlan no', 'Kaynak', 'Video', 'Koli içi'];

    /**
     * Kategori adı: önce panelde atanmış kategori, yoksa yakalamanın kırıntı yolu.
     * Hiçbiri yoksa null — çağıran "Kategorisiz" YAZMAZ, hücreyi boş bırakır.
     *
     * @param array<string, mixed> $product
     * @param array<int, string> $categoryNames
     */
    public static function kategori(array $product, array $categoryNames, ?ValueSet $values = null): ?string
    {
        if ($product['category_id'] !== null) {
            $ad = $categoryNames[(int) $product['category_id']] ?? null;
            if (is_string($ad) && trim($ad) !== '') {
                return trim($ad);
            }
        }

        $kirinti = self::kirintiYolu($product);
        if ($kirinti === []) {
            return null;
        }

        $son = $kirinti[count($kirinti) - 1];

        return $values !== null ? $values->value($son) : $son;
    }

    /**
     * Kırıntı yolunun tamamı ("Ev & Bahçe › Mutfak › Saklama") — detay panelinde
     * gösterilir; kök adımlar (首页/1688) atılır.
     *
     * @param array<string, mixed> $product
     *
     * @return list<string>
     */
    public static function kirintiYolu(array $product): array
    {
        $raw = self::raw($product);
        /** @var mixed $yol */
        $yol = $raw['breadcrumb'] ?? $raw['category_path'] ?? $raw['面包屑'] ?? null;

        if (is_string($yol) && trim($yol) !== '') {
            $yol = preg_split('/\s*[>›»\/|]\s*/u', trim($yol)) ?: [];
        }
        if (!is_array($yol)) {
            $tek = $raw['category_name'] ?? null;

            return is_string($tek) && trim($tek) !== '' ? [trim($tek)] : [];
        }

        $out = [];
        foreach ($yol as $adim) {
            if (!is_scalar($adim)) {
                continue;
            }
            $adim = trim((string) $adim);
            if ($adim === '' || in_array(mb_strtolower($adim, 'UTF-8'), self::KOK_ADIMLAR, true)) {
                continue;
            }
            if (!in_array($adim, $out, true)) {
                $out[] = $adim;
            }
        }

        return $out;
    }

    /**
     * Detay metni: ürünün kendi detayı varsa O; yoksa en dolu 3-4 öznitelikten
     * kısa bir tanım kurulur ("Malzeme: Paslanmaz çelik · Renk: Gri").
     * Öznitelik de yoksa null — boş "Detaylar" satırı basılmaz.
     *
     * @param array<string, mixed> $product
     */
    public static function detay(array $product, ?ValueSet $values = null): ?string
    {
        $mevcut = $product['detail'] ?? null;
        if (is_string($mevcut) && trim($mevcut) !== '') {
            return trim($mevcut);
        }

        $parcalar = [];
        foreach (ProductFacts::build($product, $values) as [$tr, , $deger]) {
            if ($deger === null || in_array($tr, self::DETAY_DISI, true)) {
                continue;
            }
            $parcalar[] = $tr . ': ' . $deger;
            if (count($parcalar) >= self::DETAY_ALANI) {
                break;
            }
        }

        return $parcalar === [] ? null : implode(' · ', $parcalar);
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>
     */
    private static function raw(array $product): array
    {
        /** @var mixed $raw */
        $raw = $product['raw_attributes'] ?? null;
        if (is_string($raw)) {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return [];
        }

        // Yakalama gövdesi bazen {raw: {...}} sarmalıyla gelir.
        $ic = $raw['raw'] ?? null;
        if (is_array($ic)) {
            $raw = array_merge($ic, $raw);
        }

        /** @var array<string, mixed> */
        return $raw;
    }
}
