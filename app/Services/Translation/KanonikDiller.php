<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * KANONİK ÜÇ DİL — TR + EN + ZH (D12, Ürün Sahibi kararı 28 Ağu 2026).
 *
 * KARAR: her ürün üç dilde tutulur ve bu üçlü PLATFORMDAN BAĞIMSIZDIR. 1688
 * bugün Çince kaynak veriyor; yarın Amazon İngilizce, Trendyol Türkçe verecek.
 * Kural her üçünde de aynıdır:
 *
 *   · Kaynak dil üçlünün İÇİNDEYSE o dil ORİJİNALDİR — çevrilmez, aynen saklanır;
 *     yalnız eksik iki dil üretilir. TR kaynaklı bir üründe motor TR'ye DOKUNMAZ
 *     (kendi dilimize "çeviri" yapmak, insanın yazdığını bozmaktır).
 *   · Kaynak dil üçlünün DIŞINDAYSA (ör. Almanca bir site) ham orijinal ayrıca
 *     saklanır ve üç dilin ÜÇÜ de üretilir.
 *
 * K55 GENELLEŞTİ: belgelerdeki referans satırı artık "Çince satır" değil
 * "KAYNAK DİLİ satırı"dır. Karşı taraf kendi kaydını kendi dilinde bulur;
 * kaynağın Çince olması bir tesadüftü, kural değil.
 */
final class KanonikDiller
{
    /** Sıra ANLAMLIDIR: arayüz ve belgeler bu sırayla listeler. */
    public const HEPSI = ['tr', 'en', 'zh'];

    /** Panelin dili — V3-M: panel HER YERDE Türkçedir, seçime bağlı değildir. */
    public const PANEL = 'tr';

    public static function gecerliMi(?string $dil): bool
    {
        return $dil !== null && in_array($dil, self::HEPSI, true);
    }

    /**
     * Bir ürün için ÜRETİLMESİ gereken diller.
     *
     * Kaynak dil üçlünün içindeyse o dil listeden düşer (orijinaldir, çevrilmez);
     * dışındaysa üçü de üretilir. Kaynak bilinmiyorsa üçü de istenir — eksik
     * bilgi yüzünden bir dili atlamak, kullanıcının o dili hiç görmemesi demektir.
     *
     * @return list<string>
     */
    public static function uretilecekler(?string $kaynakDil): array
    {
        if (!self::gecerliMi($kaynakDil)) {
            return self::HEPSI;
        }

        return array_values(array_filter(self::HEPSI, static fn (string $dil): bool => $dil !== $kaynakDil));
    }

    /** Kaynak dil üçlünün dışında mı? (ham orijinal ayrıca saklanır) */
    public static function ucluDisiMi(?string $kaynakDil): bool
    {
        return $kaynakDil !== null && $kaynakDil !== '' && !self::gecerliMi($kaynakDil);
    }
}
