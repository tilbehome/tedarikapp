<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * KAYNAK DİLİ SAPTAMA (D12) — yakalama anında ilana işlenir.
 *
 * Yakalanan metnin dilini bilmeden kanonik üçlü kurulamaz: TR kaynaklı bir ürünü
 * TR'ye çevirmeye kalkmak ya da Çince başlığı "İngilizce" sanıp motora öyle
 * göndermek, sahada gördüğümüz karışık dilli çıktının kaynağıdır.
 *
 * YÖNTEM — yazı sistemi önce, sözcük sonra. Yazı sistemi KESİN bir imzadır:
 * Han karakteri varsa metin Çincedir, tartışma bitmiştir. Latin alfabesinde ise
 * ayrım Türkçeye özgü harfler (ç ğ ı ö ş ü) ve sık işlev sözcükleriyle yapılır.
 * Hiçbiri yoksa İngilizce varsayılır — üçlünün Latin alfabeli varsayılanı odur.
 *
 * BU BİR TAHMİNDİR ve öyle davranılır: sonuç `products.source_lang` alanına
 * yazılır, kullanıcı isterse üründen değiştirebilir. Yanlış tahmin, fazladan bir
 * dilin üretilmesinden başka zarar vermez (orijinal metin hiçbir koşulda ezilmez).
 */
final class DilSaptayici
{
    /** Türkçeye özgü harfler — İngilizce ve Çince pinyin'de bulunmaz. */
    private const TR_HARFLER = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'İ', 'Ç', 'Ğ', 'Ö', 'Ş', 'Ü'];

    /** Sık Türkçe işlev sözcükleri — aksansız yazılmış metinler için. */
    private const TR_SOZCUKLER = [
        've', 'ile', 'için', 'icin', 'adet', 'takım', 'takim', 'renk', 'beden',
        'kalın', 'kalin', 'erkek', 'kadın', 'kadin', 'çocuk', 'cocuk', 'yeni',
    ];

    /** Sık İngilizce işlev sözcükleri. */
    private const EN_SOZCUKLER = ['the', 'and', 'for', 'with', 'new', 'size', 'color', 'colour', 'men', 'women', 'kids'];

    public static function sapta(string $metin): string
    {
        $temiz = trim($metin);
        if ($temiz === '') {
            return KanonikDiller::PANEL;
        }

        // 1) YAZI SİSTEMİ: Han karakteri varsa Çince. Kesin imza, ilk bakılır.
        if (preg_match('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{F900}-\x{FAFF}]/u', $temiz) === 1) {
            return 'zh';
        }

        $kucuk = mb_strtolower($temiz, 'UTF-8');

        // 2) TÜRKÇEYE ÖZGÜ HARF: tek bir tanesi bile İngilizceyi eler.
        foreach (self::TR_HARFLER as $harf) {
            if (mb_strpos($temiz, $harf) !== false) {
                return 'tr';
            }
        }

        // 3) SÖZCÜK SAYIMI: aksansız yazılmış Türkçe başlıklar için son çare.
        $sozcukler = preg_split('/[^\p{L}\p{N}]+/u', $kucuk, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tr = 0;
        $en = 0;
        foreach ($sozcukler as $sozcuk) {
            if (in_array($sozcuk, self::TR_SOZCUKLER, true)) {
                $tr++;
            }
            if (in_array($sozcuk, self::EN_SOZCUKLER, true)) {
                $en++;
            }
        }

        if ($tr > $en) {
            return 'tr';
        }

        // 4) Latin alfabesinin varsayılanı İngilizcedir.
        return 'en';
    }
}
