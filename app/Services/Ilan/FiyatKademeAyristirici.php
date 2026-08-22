<?php

declare(strict_types=1);

namespace App\Services\Ilan;

/**
 * HAM YAKALAMA VERİSİNDEN FİYAT KADEMELERİ (İE#20 C2).
 *
 * 1688'de fiyat tek sayı değildir: "2+ adet ¥12,50 · 100+ adet ¥10,90" gibi
 * kademelidir. Bu bilgi bugüne kadar `raw_attributes` JSON'unun içinde gömülü
 * duruyordu — yani SORGULANAMIYORDU. "Benim miktarıma göre birim fiyat nedir?"
 * sorusu SQL'de yanıtlanamıyor, her yerde elle JSON açılıyordu.
 *
 * Ayrıştırma AYRI BİR SINIFTIR çünkü iki yerden kullanılır: göç betiği
 * (`bin/goc-ilan.php`) ve yakalama hattı. İkisinin ayrışması, aynı verinin iki
 * farklı okumasını üretirdi.
 *
 * TANINMAYAN BİÇİM SESSİZCE ATLANIR: platformdan platforma anahtar adları değişir
 * ve bilmediğimiz bir şekli tahminle doldurmak, yanlış fiyatla sipariş vermek
 * demektir. Yoksa "—" basarız, uydurmayız.
 */
final class FiyatKademeAyristirici
{
    /** Bilinen anahtar adları — platform ekledikçe genişler (kod değil VERİ gibi düşünülmeli). */
    private const MIN_ANAHTARLARI = ['min_qty', 'beginAmount', 'min', 'baslangic_adet'];
    private const FIYAT_ANAHTARLARI = ['price_yuan', 'price', 'fiyat', 'birim_fiyat'];

    /** Kademe listesinin ham veri içinde bulunabileceği yerler. */
    private const LISTE_ANAHTARLARI = ['price_tiers', 'priceRanges', 'fiyat_kademeleri'];

    /**
     * @return list<array{min_adet: int, birim_fiyat: string}> artan miktar sırasıyla
     */
    public static function ayristir(?string $hamJson): array
    {
        if ($hamJson === null || trim($hamJson) === '') {
            return [];
        }

        $ham = json_decode($hamJson, true);
        if (!is_array($ham)) {
            return [];
        }

        $aday = null;
        foreach (self::LISTE_ANAHTARLARI as $anahtar) {
            if (is_array($ham[$anahtar] ?? null)) {
                $aday = $ham[$anahtar];

                break;
            }
        }
        if ($aday === null) {
            return [];
        }

        $kademeler = [];
        foreach ($aday as $satir) {
            if (!is_array($satir)) {
                continue;
            }

            $min = self::ilkDeger($satir, self::MIN_ANAHTARLARI);
            $fiyat = self::ilkDeger($satir, self::FIYAT_ANAHTARLARI);
            if (!is_numeric($min) || !is_numeric($fiyat) || (int) $min < 1 || (float) $fiyat < 0) {
                continue; // tanınmayan/anlamsız satır: uydurma yok
            }

            $kademeler[] = [
                'min_adet' => (int) $min,
                // Para K14: string taşınır, 4 hane (DECIMAL(12,4) ile aynı).
                'birim_fiyat' => number_format((float) $fiyat, 4, '.', ''),
            ];
        }

        usort($kademeler, static fn (array $a, array $b): int => $a['min_adet'] <=> $b['min_adet']);

        return $kademeler;
    }

    /**
     * Verilen miktara uyan birim fiyat: miktarı KARŞILAYAN en yüksek kademe.
     *
     * 100 adet alıyorsak "2+" değil "100+" fiyatı geçerlidir. Hiçbir kademe
     * karşılanmıyorsa (miktar en düşük kademenin altında) null döner — bu, satın
     * alınamayacağı anlamına gelir ve arayüzde MOQ uyarısı olarak görünmelidir.
     *
     * @param list<array{min_adet: int, birim_fiyat: string}> $kademeler
     */
    public static function miktaraGoreFiyat(array $kademeler, int $miktar): ?string
    {
        $secilen = null;
        foreach ($kademeler as $kademe) {
            if ($miktar >= $kademe['min_adet']) {
                $secilen = $kademe['birim_fiyat'];
            }
        }

        return $secilen;
    }

    /**
     * @param array<string, mixed> $satir
     * @param list<string> $anahtarlar
     */
    private static function ilkDeger(array $satir, array $anahtarlar): mixed
    {
        foreach ($anahtarlar as $anahtar) {
            if (array_key_exists($anahtar, $satir)) {
                return $satir[$anahtar];
            }
        }

        return null;
    }
}
