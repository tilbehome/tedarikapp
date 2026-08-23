<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CategoryRepository;

/**
 * KATEGORİ İÇE AKTARIMI (İE#21 B10) — idempotent.
 *
 * ŞEKİL TOLERANSI: gelen veri üç biçimden biri olabilir ve üçü de kabul edilir.
 *   1. Düz liste:     ["Mutfak", "Ev"]
 *   2. Nesne listesi: [{"ad": "Mutfak", "sira": 10}]
 *   3. AĞAÇ:          [{"ad": "Mutfak", "alt": [{"ad": "Pişirme"}]}]
 *
 * NEDEN TOLERANS: 8B kategori ağacı ayrı bir çalışmanın çıktısıdır ve biçimi
 * bizim elimizde değildir. Tek bir şekle bağlanmak, dosya geldiğinde "önce
 * dönüştür" adımı doğururdu; dönüştürme adımı da elle yapılınca hataya açıktır.
 *
 * AĞAÇ NASIL SAKLANIR: `categories` tablosu DÜZDÜR (ad + sıra, ad UNIQUE). Ağaç
 * "Üst > Alt" biçiminde YOL ADINA düzleştirilir. Bu bilinçli bir sadeleştirmedir:
 * gerçek bir `parent_id` hiyerarşisi kategori ekranını, ürün formunu ve filtreleri
 * birlikte değiştirmeyi gerektirir — İE#21'in kapsamı bu değildir ve şartname
 * dosyası (kategori-agaci.json) henüz elimizde yoktur. Düzleştirme veriyi
 * KAYBETMEZ: ayraç korunduğu için gerçek hiyerarşiye geçiş sonradan mümkündür.
 *
 * İDEMPOTENT: aynı ad ikinci kez EKLENMEZ. İçe aktarım iki kez koşarsa sonuç
 * birincisiyle aynıdır; bu, "acaba koştu mu" diye tekrar koşmayı güvenli kılar.
 */
final class KategoriIceAktarim
{
    /** Ağaç düzleştirmede kullanılan yol ayracı. */
    public const AYRAC = ' > ';

    /** Tek seferde en çok kaç kategori (kazara devasa dosya koruması). */
    public const UST_SINIR = 500;

    public function __construct(private readonly CategoryRepository $kategoriler)
    {
    }

    /**
     * @param array<int|string, mixed> $veri
     *
     * @return array{eklenen: int, atlanan: int, adlar: list<string>, uyarilar: list<string>}
     */
    public function calistir(array $veri): array
    {
        $uyarilar = [];
        $adlar = $this->duzlestir($veri, '', $uyarilar);

        if (count($adlar) > self::UST_SINIR) {
            $uyarilar[] = sprintf(
                'Liste %d kategori içeriyor; ilk %d tanesi alındı.',
                count($adlar),
                self::UST_SINIR,
            );
            $adlar = array_slice($adlar, 0, self::UST_SINIR);
        }

        $mevcut = [];
        foreach ($this->kategoriler->all() as $kategori) {
            $mevcut[mb_strtolower($kategori['name'])] = true;
        }

        $eklenen = 0;
        $atlanan = 0;
        $sira = 0;
        foreach ($adlar as $ad) {
            $sira += 10;
            if (isset($mevcut[mb_strtolower($ad)])) {
                $atlanan++;

                continue;
            }

            $this->kategoriler->create($ad, $sira);
            $mevcut[mb_strtolower($ad)] = true;
            $eklenen++;
        }

        return ['eklenen' => $eklenen, 'atlanan' => $atlanan, 'adlar' => $adlar, 'uyarilar' => $uyarilar];
    }

    /**
     * Üç biçimi de tek bir ad listesine indirger.
     *
     * @param array<int|string, mixed> $dugumler
     * @param list<string> $uyarilar
     *
     * @return list<string>
     */
    private function duzlestir(array $dugumler, string $onEk, array &$uyarilar): array
    {
        $out = [];

        foreach ($dugumler as $anahtar => $dugum) {
            // Biçim: {"Mutfak": {"Pişirme": []}} — anahtar ad, değer alt ağaç.
            if (is_string($anahtar) && !is_numeric($anahtar)) {
                $ad = $this->temizle($anahtar);
                if ($ad === null) {
                    continue;
                }
                $tamAd = $onEk === '' ? $ad : $onEk . self::AYRAC . $ad;
                $out[] = $tamAd;
                if (is_array($dugum)) {
                    $out = array_merge($out, $this->duzlestir($dugum, $tamAd, $uyarilar));
                }

                continue;
            }

            // Biçim: "Mutfak"
            if (is_string($dugum)) {
                $ad = $this->temizle($dugum);
                if ($ad !== null) {
                    $out[] = $onEk === '' ? $ad : $onEk . self::AYRAC . $ad;
                }

                continue;
            }

            // Biçim: {"ad": "Mutfak", "alt": [...]}
            if (is_array($dugum)) {
                $hamAd = $dugum['ad'] ?? $dugum['name'] ?? $dugum['baslik'] ?? null;
                $ad = is_string($hamAd) ? $this->temizle($hamAd) : null;
                if ($ad === null) {
                    $uyarilar[] = 'Adı olmayan bir düğüm atlandı.';

                    continue;
                }
                $tamAd = $onEk === '' ? $ad : $onEk . self::AYRAC . $ad;
                $out[] = $tamAd;

                $alt = $dugum['alt'] ?? $dugum['children'] ?? $dugum['cocuklar'] ?? null;
                if (is_array($alt)) {
                    $out = array_merge($out, $this->duzlestir($alt, $tamAd, $uyarilar));
                }

                continue;
            }

            $uyarilar[] = 'Tanınmayan bir düğüm atlandı.';
        }

        // Tekilleştirme: aynı ad dosyada iki kez geçebilir; sıra korunur.
        return array_values(array_unique($out));
    }

    private function temizle(string $ham): ?string
    {
        $ad = trim(preg_replace('/\s+/u', ' ', $ham) ?? $ham);
        if ($ad === '') {
            return null;
        }

        // Kolon sınırı 100; kesmek yerine kısaltmak veriyi bozar, bu yüzden
        // uzun ad KIRPILIR ve bu bir uyarı değil, kayıt olarak kalır.
        return mb_substr($ad, 0, 100);
    }
}
