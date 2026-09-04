<?php

declare(strict_types=1);

namespace App\Services\Yanit;

/**
 * YAPIŞTIRILAN METNİN NORMALİZASYONU (V3-C Aşama 2.2 · #28-EK).
 *
 * WhatsApp / e-posta / Excel'den kopyalanan firma cevabı tam genişlik
 * noktalama (，；：｜), emoji, süs karakteri ve CJK arasına serpiştirilmiş
 * boşluk taşır ("有 货", "含 土耳其 增值税"). Ayrıştırıcı kurallarının tek bir
 * biçimle çalışması için önce metin sadeleştirilir; KAYNAK METİN AYRICA
 * KORUNUR (ham_kaynak) — normalizasyon izi silmez.
 *
 * E-posta tabloları ("Item | Status | MOQ | …") satır satır DÜZLEŞTİRİLİR:
 * her hücre kendi başlığıyla ön eklenir ("MOQ 600 pcs") ki başlıksız bir
 * "600 pcs" hücresi başka bir alana kaymasın.
 */
final class MetinNormalizer
{
    /** Tam genişlik → ASCII noktalama. */
    private const NOKTALAMA = [
        '，' => ',', '；' => ';', '：' => ':', '｜' => '|', '（' => '(', '）' => ')', '。' => '. ',
        '、' => ',', '／' => '/', '＄' => '$', '％' => '%', '＝' => '=', '＋' => '+', '～' => '~',
        '－' => '-', '–' => '-', '—' => '-', '「' => '"', '」' => '"', '【' => '[', '】' => ']',
        '！' => '!', '？' => '?', '＊' => '*', '＃' => '#', "\u{00A0}" => ' ', "\u{3000}" => ' ',
    ];

    /**
     * Normalize edilmiş, boş satırları atılmış satır listesi.
     *
     * @return list<string>
     */
    public static function satirlar(string $metin): array
    {
        $metin = str_replace(array_keys(self::NOKTALAMA), array_values(self::NOKTALAMA), $metin);
        $metin = self::tamGenislikAlfasayisal($metin);
        // Emoji, süs sembolleri, varyasyon seçicileri ve sıfır genişlikli karakterler.
        $metin = (string) preg_replace('/[\p{So}\p{Sk}\p{Cs}\x{FE0F}\x{200B}-\x{200D}\x{2060}]/u', ' ', $metin);
        // Sayılar arasında olmayan tilde/vurgu süsü ("～～", "~~").
        $metin = (string) preg_replace('/(?<!\d)\s*~+\s*(?!\d)/u', ' ', $metin);
        $metin = (string) preg_replace('/\h+/u', ' ', $metin);

        $satirlar = [];
        foreach (preg_split('/\R/u', $metin) ?: [] as $satir) {
            $satir = trim((string) $satir);
            // CJK karakterleri arasına serpiştirilmiş boşluklar ("有 货", "含 土耳其 增值税").
            $satir = (string) preg_replace('/(\p{Han})\s+(?=\p{Han})/u', '$1', $satir);
            if ($satir !== '') {
                $satirlar[] = $satir;
            }
        }

        return self::tablolariDuzlestir($satirlar);
    }

    /**
     * "Başlık | Başlık | …" satırını izleyen "hücre | hücre | …" satırları
     * "Başlık hücre. Başlık hücre." biçimine çevrilir; başlık satırı atılır.
     *
     * @param  list<string> $satirlar
     * @return list<string>
     */
    private static function tablolariDuzlestir(array $satirlar): array
    {
        $sonuc = [];
        $basliklar = null;
        foreach ($satirlar as $satir) {
            $hucreler = self::hucreler($satir);
            if ($hucreler === null) {
                $basliklar = null;
                $sonuc[] = $satir;
                continue;
            }
            if ($basliklar === null) {
                // Rakamsız ilk tablo satırı başlıktır; rakamlıysa başlıksız tablodur — olduğu gibi kalır.
                if (preg_match('/\d/', $satir) !== 1) {
                    $basliklar = $hucreler;
                    continue;
                }
                $sonuc[] = str_replace('|', ' . ', $satir);
                continue;
            }
            $parcalar = [];
            foreach ($hucreler as $i => $hucre) {
                if ($hucre === '') {
                    continue;
                }
                $parcalar[] = trim(($basliklar[$i] ?? '') . ' ' . $hucre);
            }
            $sonuc[] = implode('. ', $parcalar);
        }

        return $sonuc;
    }

    /** @return list<string>|null Tablo satırı değilse null. */
    private static function hucreler(string $satir): ?array
    {
        if (substr_count($satir, '|') < 2) {
            return null;
        }

        return array_map('trim', explode('|', trim($satir, '| ')));
    }

    /** Ａ-Ｚ ａ-ｚ ０-９ → ASCII. */
    private static function tamGenislikAlfasayisal(string $metin): string
    {
        return (string) preg_replace_callback('/[\x{FF10}-\x{FF19}\x{FF21}-\x{FF3A}\x{FF41}-\x{FF5A}]/u', static function (array $m): string {
            // Tam genişlik blok ASCII'nin 0xFEE0 ötelenmiş kopyasıdır.
            return chr(mb_ord($m[0], 'UTF-8') - 0xFEE0);
        }, $metin);
    }
}
