<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * BİR ÜRÜNÜN ÇEVRİLECEK DEĞERLERİNİ TOPLAR (İE#21 B9).
 *
 * KÖK NEDEN: kuyruk çeviri işi `attributes: []` gönderiyordu — yani LLM'e YALNIZ
 * ürün ADI soruluyordu. Marka, renk, malzeme ve varyasyon adları hiç sorulmadığı
 * için önbelleğe hiç girmiyor, sayfada ham Çince kalıyordu (canlı kanıt:
 * TDK-2026-0001). Bu sınıf "neyi çevireceğiz" sorusunun TEK cevabıdır; okuma
 * tarafı (ValueSet) ile aynı kümeyi görmezse bir taraf hep eksik kalırdı.
 *
 * ÇEVRİLMEYENLER — bilinçli dışlamalar:
 *   • ilan no, stok kodu, model kodu, ölçü/sayı değerleri: bunlar KİMLİKTİR,
 *     çevrilirse eşleşme bozulur ("058" → "elli sekiz" saçmalığı),
 *   • Latin harfli değerler ("ABS", "PP"): zaten anlaşılır ve LLM'e sorulması
 *     kota israfıdır,
 *   • kullanıcının kendi yazdığı Türkçe not: kaynağı zaten Türkçedir.
 */
final class CevrilecekDegerler
{
    /** Bir üründen en çok kaç değer sorulur (kota koruması). */
    public const UST_SINIR = 60;

    /**
     * Öznitelik anahtarları da çevrilir (品牌 → Marka) ama SABİT alanların
     * etiketleri ProductFacts'tedir; burada yalnız RAW'dan gelen serbest
     * anahtarlar için gerekir.
     *
     * @param array<string, mixed> $product ListPresenter::product çıktısı
     *
     * @return array<string, string> ham değer => ham değer (tekilleştirilmiş küme)
     */
    public static function topla(array $product): array
    {
        $kume = [];

        // 1) RAW öznitelikler: hem ANAHTAR hem DEĞER (品牌 / 其他).
        foreach (self::rawAttributes($product['raw_attributes'] ?? null) as $anahtar => $deger) {
            self::ekle($kume, (string) $anahtar);
            self::ekle($kume, (string) $deger);
        }

        // 2) Seçilen varyant: "颜色: 灰色" biçiminde gelir — iki parça da çevrilir.
        $secim = $product['sku_selection'] ?? null;
        if (is_array($secim)) {
            foreach ($secim as $anahtar => $deger) {
                if (is_string($anahtar) && !is_numeric($anahtar)) {
                    self::ekle($kume, $anahtar);
                }
                if (is_scalar($deger)) {
                    self::ekle($kume, (string) $deger);
                }
            }
        }

        // 3) Varyasyon matrisi: tüm seçenek adları (firma bunlardan seçim yapar).
        $matris = $product['sku_matrix'] ?? null;
        if (is_array($matris)) {
            foreach ($matris as $satir) {
                if (!is_array($satir)) {
                    continue;
                }
                $props = is_array($satir['props'] ?? null) ? $satir['props'] : $satir;
                foreach ($props as $anahtar => $deger) {
                    if (is_string($anahtar) && !is_numeric($anahtar)) {
                        self::ekle($kume, $anahtar);
                    }
                    if (is_scalar($deger)) {
                        self::ekle($kume, (string) $deger);
                    }
                }
            }
        }

        // 4) Orijinal başlık: ürün adı zaten çevriliyor ama kaynak başlık
        //    varyasyon adlarıyla aynı önbellek satırını paylaşabilir.
        $orijinal = $product['name_original'] ?? null;
        if (is_string($orijinal)) {
            self::ekle($kume, $orijinal);
        }

        return array_slice($kume, 0, self::UST_SINIR, true);
    }

    /**
     * Değer kümeye girer mi? (normalize eder, eler, tekilleştirir)
     *
     * @param array<string, string> $kume
     */
    private static function ekle(array &$kume, string $ham): void
    {
        $deger = ValueSet::normalize($ham);
        if ($deger === '' || isset($kume[$deger])) {
            return;
        }

        // Yalnız CJK içerenler çevrilir (gerekçe sınıf başlığında).
        if (Glossary::detect($deger) !== 'zh') {
            return;
        }

        // Aşırı uzun metin bir öznitelik değeri değil, açıklama gövdesidir:
        // önbellek satırı 1000 karakterle sınırlıdır ve böyle bir metni
        // öznitelik diye çevirmek hem pahalı hem yanlıştır.
        if (mb_strlen($deger) > 200) {
            return;
        }

        $kume[$deger] = $deger;
    }

    /** @return array<string, string> */
    private static function rawAttributes(mixed $raw): array
    {
        if (is_string($raw)) {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return [];
        }

        // Yakalama sözleşmesi v2: öznitelikler `normalized_attributes` altındadır;
        // eski kayıtlarda düz dizi olabilir — ikisi de okunur.
        $kaynak = is_array($raw['normalized_attributes'] ?? null) ? $raw['normalized_attributes'] : $raw;

        $out = [];
        foreach ($kaynak as $anahtar => $deger) {
            if (is_scalar($deger)) {
                $out[(string) $anahtar] = (string) $deger;
            }
        }

        return $out;
    }
}
