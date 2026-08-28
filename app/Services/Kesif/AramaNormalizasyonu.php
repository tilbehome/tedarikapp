<?php

declare(strict_types=1);

namespace App\Services\Kesif;

/**
 * ARAMA NORMALİZASYONU (İE#21 B1 · E2E-PNL-02/03).
 *
 * SORUN: kullanıcı "şeffaf çekmeceli ayakkabı kutusu 33x23x14 cm" yazıyor; kayıtta
 * "Şeffaf çekmeceli ayakkabı kutusu 33×23×14cm" yazıyor. Üç ayrı fark var ve üçü de
 * tek başına aramayı boşa düşürüyor:
 *   • Türkçe harfler: Ş/ş, İ/ı, Ğ/ğ, Ü/ü, Ö/ö, Ç/ç,
 *   • çarpı işareti: ASCII `x` ile Unicode `×`,
 *   • ölçü boşluğu: "14cm" ile "14 cm".
 *
 * ÇÖZÜM: hem kayıt hem sorgu AYNI fonksiyondan geçer. Sadeleştirme kaybettirir
 * (büyük/küçük, aksan) ama arama için kayıp değil, KAZANÇTIR: kullanıcı yazdığını
 * bulur. Gösterimde her zaman HAM metin kullanılır — normalize kopya yalnız
 * eşleştirme içindir.
 *
 * NEDEN ÇİNCEYE DOKUNULMUYOR: CJK'da büyük/küçük harf ve aksan yoktur; boşluk
 * sıkıştırma dışında yapılacak bir şey yok. Çince metni "sadeleştirmeye"
 * çalışmak (pinyin'e çevirmek gibi) yanlış eşleşme üretirdi.
 */
final class AramaNormalizasyonu
{
    /** Türkçe harfler → ASCII karşılıkları. */
    private const HARFLER = [
        'ş' => 's', 'Ş' => 's', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        'â' => 'a', 'Â' => 'a', 'î' => 'i', 'Î' => 'i', 'û' => 'u', 'Û' => 'u',
    ];

    /** Ölçü/çarpı işaretleri → tek biçim. */
    private const ISARETLER = [
        '×' => 'x', '✕' => 'x', '＊' => 'x', '·' => ' ', '–' => '-', '—' => '-',
        '＋' => '+', '％' => '%', '，' => ' ', '、' => ' ',
    ];

    /**
     * Eşleştirme biçimi: küçük harf, ASCII Türkçe, tek tip çarpı, sıkıştırılmış
     * boşluk ve sayı-birim bitişikliği ("33 x 23 cm" → "33x23cm").
     */
    public static function normalize(string $ham): string
    {
        $metin = strtr($ham, self::HARFLER);
        $metin = strtr($metin, self::ISARETLER);
        $metin = mb_strtolower($metin, 'UTF-8');

        // Görünmez boşluklar (1688 değerlerinde sık): NBSP, ZWSP, BOM.
        $metin = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], [' ', '', ''], $metin);

        // Ölçü ifadelerinde boşluk ANLAMSIZDIR: "33 x 23 x 14 cm" = "33x23x14cm".
        // Yalnız SAYI ile birim/çarpı arasındaki boşluk silinir; kelimeler arası
        // boşluk korunur (yoksa "cam yağlık" → "camyaglik" olur ve hiç eşleşmez).
        $metin = (string) preg_replace('/(\d)\s*x\s*(\d)/u', '$1x$2', $metin);
        $metin = (string) preg_replace('/(\d)\s+(mm|cm|m|ml|l|g|kg|w|v|adet|set|inch|")/u', '$1$2', $metin);

        // Noktalama eşleştirmeye girmez; yerine boşluk konur ki kelimeler birleşmesin.
        $metin = (string) preg_replace('/[^\p{L}\p{N}\s%\.\-\+\/]+/u', ' ', $metin);

        return trim((string) preg_replace('/\s+/u', ' ', $metin));
    }

    /**
     * Sorgu için LIKE deseni. Boş sorgu için null döner — çağıran o zaman
     * arama koşulunu HİÇ eklemez (boş desen her satırı getirirdi).
     */
    public static function desen(string $sorgu): ?string
    {
        $normal = self::normalize($sorgu);

        return $normal === '' ? null : '%' . $normal . '%';
    }
}
