<?php

declare(strict_types=1);

namespace App\Services\Ilan;

/**
 * ÇİFT DİLLİ ARAMA METNİ (İE#20 C7).
 *
 * Kullanıcı ürünü üç şekilde arar: yazdığı Türkçe adla, kaynaktaki Çince
 * başlıkla ya da ilan numarasıyla. Bugün yalnız birincisi çalışıyordu — diğer
 * ikisi "bulunamadı" veriyordu, oysa veri elimizdeydi.
 *
 * Çözüm bir ARAMA ALANI: aranabilir tüm metinler tek kolonda toplanır ve arama
 * oraya bakar. Alan TÜRETİLMİŞTİR (kaynak değildir): ürün değişince yeniden
 * üretilir, elle düzenlenmez.
 *
 * ALAN KISA TUTULUR (2000 karakter): LIKE taraması bu alan üzerinde koşacak;
 * ham veriyi buraya dökmek her aramayı yavaşlatırdı. Yalnız İNSANIN ARAYACAĞI
 * metinler girer — açıklama metninin tamamı değil.
 */
final class AramaMetni
{
    public const MAX_UZUNLUK = 2000;

    /**
     * @param array<string, mixed> $urun         ürün satırı
     * @param list<string>         $ceviriler    diğer dillerdeki adlar (EN vb.)
     * @param string|null          $kategoriAdi  kategori adı (join'den gelir)
     */
    public static function uret(array $urun, array $ceviriler = [], ?string $kategoriAdi = null): string
    {
        $parcalar = [
            (string) ($urun['name'] ?? ''),
            (string) ($urun['name_original'] ?? ''),
            (string) ($urun['external_id'] ?? ''),
            (string) ($urun['vendor_name'] ?? ''),
            $kategoriAdi ?? '',
        ];

        foreach ($ceviriler as $ceviri) {
            $parcalar[] = $ceviri;
        }

        // Tekilleştir: aynı metin iki kez girerse alan boşuna şişer.
        $temiz = [];
        foreach ($parcalar as $parca) {
            $parca = trim(preg_replace('/\s+/u', ' ', $parca) ?? '');
            if ($parca !== '' && !in_array($parca, $temiz, true)) {
                $temiz[] = $parca;
            }
        }

        return mb_substr(implode(' · ', $temiz), 0, self::MAX_UZUNLUK);
    }

    /**
     * Arama sorgusunu SQL parçasına çevirir.
     *
     * Çince sorgu FULLTEXT ile bulunamaz (CJK'da kelime sınırı yoktur ve MariaDB'de
     * ngram ayrıştırıcısı bulunmaz), bu yüzden karma yaklaşım kullanılır:
     * latin harfli sorgular FULLTEXT'e, CJK içeren sorgular LIKE'a gider.
     * Sonuç iki veritabanında da AYNIDIR — tek motora bel bağlamadık.
     *
     * @return array{0: string, 1: array<string, string>} WHERE parçası ve parametreler
     */
    public static function sorguParcasi(string $arama, bool $fulltextVar): array
    {
        $arama = trim($arama);
        if ($arama === '') {
            return ['', []];
        }

        $cjkVar = preg_match('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', $arama) === 1;

        if ($fulltextVar && !$cjkVar && mb_strlen($arama) >= 3) {
            return ['MATCH(p.arama_metni) AGAINST (:arama IN BOOLEAN MODE)', ['arama' => self::booleanIfade($arama)]];
        }

        return ['p.arama_metni LIKE :arama', ['arama' => '%' . $arama . '%']];
    }

    /** Kullanıcı girdisini BOOLEAN MODE ifadesine çevirir (operatörler kaçırılır). */
    private static function booleanIfade(string $arama): string
    {
        // BOOLEAN MODE operatörleri (+ - > < ( ) ~ * " @) kullanıcı metninde
        // ARAMA TERİMİDİR, operatör değil: temizlenmezse sorgu hata verir.
        $temiz = preg_replace('/[+\-><()~*"@]+/u', ' ', $arama) ?? $arama;

        $kelimeler = [];
        foreach (preg_split('/\s+/u', trim($temiz)) ?: [] as $kelime) {
            if ($kelime !== '') {
                $kelimeler[] = '+' . $kelime . '*';
            }
        }

        return $kelimeler === [] ? $arama : implode(' ', $kelimeler);
    }
}
