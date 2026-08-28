<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CategoryRepository;

/**
 * KATEGORİ TAHMİNİ (İE#21 B4 · B10) — 8B ağacı + eşleme tablosuyla.
 *
 * Gelen Kutusu'ndaki bir yakalamaya kategori ÖNERİR. Öneriyi uygulamak
 * kullanıcının işidir (K54 deseni: makine önerir, insan onaylar).
 *
 * ÜÇ KAYNAK, SIRAYLA:
 *   1. `category_path` — kaynak sitenin kırıntı yolu; en güvenilir sinyaldir
 *      çünkü satıcının kendi sınıflandırmasıdır.
 *   2. Çince öznitelik/başlık eşlemesi (`eslemeler_1688`) — "保鲜盒" gibi
 *      terimler doğrudan bir kategoriye bağlanır.
 *   3. Bulunamazsa: BOŞ. Uydurma atama yapılmaz — 8B'nin açık kuralı budur.
 *      Yanlış kategori, boş kategoriden pahalıdır: boş olan görünür ve düzeltilir,
 *      yanlış olan raporları sessizce bozar.
 *
 * GÜVEN DERECESİ taşınır: `kesin` eşleşme doğrudan önerilir, `gozden_gecir`
 * işaretlenir. Panel ikisini farklı gösterir — "kesin" demeyen bir öneriye
 * kullanıcı iki kez bakmalıdır.
 */
final class KategoriTahmini
{
    /** @var array<string, mixed>|null */
    private static ?array $agac = null;

    public function __construct(
        private readonly string $basePath,
        private readonly CategoryRepository $kategoriler,
    ) {
    }

    /**
     * @param list<string>|null      $categoryPath kaynak sitenin kırıntı yolu
     * @param array<string, string>  $ozellikler   RAW öznitelikler (Çince anahtar/değer)
     *
     * @return array{kod: string|null, ad: string|null, kategori_id: int|null, guven: string, kaynak: string}
     */
    public function tahminEt(?array $categoryPath, array $ozellikler = [], string $baslik = ''): array
    {
        $agac = $this->agac();

        // 1) Kırıntı yolu — demo/gerçek eşleme tablosundan.
        if ($categoryPath !== null && $categoryPath !== []) {
            foreach ($agac['demo_category_path_eslemeleri'] ?? [] as $esleme) {
                if (!is_array($esleme) || !is_array($esleme['category_path'] ?? null)) {
                    continue;
                }
                if ($this->yolEsit($esleme['category_path'], $categoryPath)) {
                    return $this->sonuc((string) ($esleme['kod'] ?? ''), 'kesin', 'category_path');
                }
            }
        }

        // 2) Çince terim eşlemesi — öznitelik değerleri ve başlık taranır.
        $havuz = trim(implode(' ', array_merge(array_keys($ozellikler), array_values($ozellikler), [$baslik])));
        if ($havuz !== '') {
            // UZUN terim ÖNCE denenir: "厨房收纳" ile "厨房" ikisi de eşleşir ama
            // uzun olan daha özgüldür ve daha alt bir kategoriye işaret eder.
            $eslemeler = $agac['eslemeler_1688'] ?? [];
            usort($eslemeler, static fn (array $a, array $b): int => mb_strlen((string) ($b['zh'] ?? ''))
                <=> mb_strlen((string) ($a['zh'] ?? '')));

            foreach ($eslemeler as $esleme) {
                $terim = trim((string) ($esleme['zh'] ?? ''));
                if ($terim !== '' && str_contains($havuz, $terim)) {
                    return $this->sonuc(
                        (string) ($esleme['kod'] ?? ''),
                        (string) ($esleme['guven'] ?? 'gozden_gecir'),
                        'terim:' . $terim,
                    );
                }
            }
        }

        // 3) Eşlenemedi — 8B kuralı: boş kalır, panel "kategori ata" önerir.
        return ['kod' => null, 'ad' => null, 'kategori_id' => null, 'guven' => 'yok', 'kaynak' => 'eslesmedi'];
    }

    /**
     * Ağaçtaki kodun görünen adı ("Mutfak > Saklama Kapları") ve varsa DB kimliği.
     *
     * @return array{kod: string|null, ad: string|null, kategori_id: int|null, guven: string, kaynak: string}
     */
    private function sonuc(string $kod, string $guven, string $kaynak): array
    {
        if ($kod === '') {
            return ['kod' => null, 'ad' => null, 'kategori_id' => null, 'guven' => 'yok', 'kaynak' => $kaynak];
        }

        $ad = $this->kodunAdi($kod);
        $kategoriId = null;
        if ($ad !== null) {
            $kayit = $this->kategoriler->findByName($ad);
            $kategoriId = is_array($kayit) ? (int) $kayit['id'] : null;
        }

        return ['kod' => $kod, 'ad' => $ad, 'kategori_id' => $kategoriId, 'guven' => $guven, 'kaynak' => $kaynak];
    }

    /** Kod → "Üst > Alt" görünen adı (içe aktarımın ürettiği adla AYNI biçim). */
    public function kodunAdi(string $kod): ?string
    {
        foreach ($this->agac()['kategoriler'] ?? [] as $dugum) {
            if (!is_array($dugum) || (string) ($dugum['kod'] ?? '') !== $kod) {
                continue;
            }
            $ad = (string) ($dugum['tr'] ?? '');
            $ust = $dugum['ust'] ?? null;

            return is_string($ust) && $ust !== ''
                ? $ust . KategoriIceAktarim::AYRAC . $ad
                : $ad;
        }

        return null;
    }

    /**
     * @param list<mixed> $beklenen
     * @param list<string> $gelen
     */
    private function yolEsit(array $beklenen, array $gelen): bool
    {
        $normalize = static fn (array $yol): string => mb_strtolower(implode('/', array_map(
            static fn (mixed $p): string => trim((string) $p),
            $yol,
        )));

        return $normalize($beklenen) === $normalize($gelen);
    }

    /** @return array<string, mixed> */
    private function agac(): array
    {
        if (self::$agac !== null) {
            return self::$agac;
        }

        $yol = $this->basePath . '/config/kategori-agaci.json';
        $ham = is_file($yol) ? (string) file_get_contents($yol) : '';
        /** @var mixed $veri */
        $veri = $ham === '' ? null : json_decode($ham, true);

        return self::$agac = is_array($veri) ? $veri : [];
    }

    public static function onbellegiTemizle(): void
    {
        self::$agac = null;
    }
}
