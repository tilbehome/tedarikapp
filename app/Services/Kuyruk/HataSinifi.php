<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use Throwable;

/**
 * BAŞARISIZLIK SINIFLARI (İE#21 B11).
 *
 * Eskiden her hata aynıydı: 3 kez dene, sonra ölü rafına koy. Bu üç ayrı yanlış
 * üretiyordu:
 *
 *  • KALICI hata (yapılandırma yok, ürün silinmiş) 3 kez deneniyordu — aynı
 *    sonucu üç kez üretip kuyruğu meşgul ediyor ve gerçek arızayı geciktiriyordu.
 *  • HIZ SINIRI (429) normal bir hata sanılıyordu; sağlayıcı "60 saniye sonra
 *    gel" dediğinde biz 60 saniye BEKLEMİYOR, 60 saniye içinde iki kez daha
 *    vurup deneme haklarını yakıyorduk.
 *  • GEÇİCİ hatada bekleme SABİTTİ (60·2^n); aynı anda başarısız olan 40 iş,
 *    tam olarak aynı saniyede yeniden deniyordu — sağlayıcıya senkron dalga.
 *
 * Sınıflandırma MESAJA bakar. Bu kırılgan görünür ama alternatifi her sağlayıcı
 * için ayrı istisna tipi tanımlamaktı; mesaj eşlemesi hem daha az kod hem de
 * yeni bir sağlayıcı eklendiğinde tek yerde güncellenir. Tanınmayan hata
 * GEÇİCİ sayılır — yanlış tarafa düşmek gerekiyorsa "tekrar dene" tarafına
 * düşmek, veriyi kaybetmekten iyidir.
 */
final class HataSinifi
{
    /** Ağ, zaman aşımı, 5xx — tekrar denemek makul. */
    public const GECICI = 'gecici';
    /** 429 / kota — sağlayıcının söylediği süre kadar beklenir. */
    public const HIZ_SINIRI = 'hiz_siniri';
    /** Yapılandırma/veri hatası — tekrar denemek DÜZELTMEZ. */
    public const KALICI = 'kalici';

    /** @return array{sinif: string, bekleme: int|null} bekleme: sağlayıcının istediği saniye */
    public static function siniflandir(Throwable $hata): array
    {
        $mesaj = mb_strtolower($hata->getMessage());

        // 1) HIZ SINIRI — sağlayıcı ne kadar bekleyeceğimizi söylüyorsa ona uyulur.
        if (self::iceriyorMu($mesaj, ['429', 'rate limit', 'too many requests', 'kota', 'quota'])) {
            return ['sinif' => self::HIZ_SINIRI, 'bekleme' => self::retryAfter($hata->getMessage())];
        }

        // 2) KALICI — tekrar denemek aynı sonucu verir.
        if (self::iceriyorMu($mesaj, [
            'bulunamadı',
            'silinmiş',
            'tanınmayan',
            'geçersiz api',
            'invalid api key',
            'unauthorized',
            '401',
            '403',
            'model_not_found',
            'yapılandırılmamış',
            'desteklenmeyen',
        ])) {
            return ['sinif' => self::KALICI, 'bekleme' => null];
        }

        return ['sinif' => self::GECICI, 'bekleme' => null];
    }

    /**
     * Bekleme süresi (saniye) — JITTER'LI artan geri çekilme.
     *
     * Jitter neden ZORUNLU: aynı anda başarısız olan işler aynı anda geri döner
     * ve sağlayıcıya senkron bir dalga vurur ("thundering herd"). Rastgele bir
     * pay, dalgayı zaman içine yayar. Pay yukarı doğrudur (%0–%25): aşağı doğru
     * olsaydı sağlayıcının istediği süreden ERKEN dönebilirdik.
     *
     * @param int|null $saglayiciBeklemesi 429 yanıtındaki Retry-After (saniye)
     */
    public static function bekleme(string $sinif, int $deneme, ?int $saglayiciBeklemesi = null): int
    {
        if ($sinif === self::HIZ_SINIRI && $saglayiciBeklemesi !== null && $saglayiciBeklemesi > 0) {
            // Sağlayıcının dediği süreye SAYGI: üstüne küçük bir pay eklenir,
            // saniyesi saniyesine dönmek sınırı yeniden tetikleyebilir.
            return min(3600, $saglayiciBeklemesi + random_int(1, 10));
        }

        $taban = $sinif === self::HIZ_SINIRI ? 120 : 60;
        $ussel = min(3600, $taban * (2 ** max(0, $deneme - 1)));
        $pay = (int) floor($ussel * random_int(0, 25) / 100);

        return min(3600, $ussel + $pay);
    }

    /** Retry-After başlığı/mesajı içinden saniye çıkarır. */
    private static function retryAfter(string $mesaj): ?int
    {
        if (preg_match('/retry[- ]?after[":\s=]+(\d+)/i', $mesaj, $m) === 1) {
            return (int) $m[1];
        }
        if (preg_match('/(\d+)\s*(saniye|seconds?)\s*sonra/iu', $mesaj, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /** @param list<string> $parcalar */
    private static function iceriyorMu(string $metin, array $parcalar): bool
    {
        foreach ($parcalar as $parca) {
            if (str_contains($metin, $parca)) {
                return true;
            }
        }

        return false;
    }
}
