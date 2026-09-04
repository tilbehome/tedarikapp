<?php

declare(strict_types=1);

namespace App\Services\Yanit;

/**
 * METNİ RFQ SATIRLARINA BÖLER (V3-C Aşama 2.2 · #28-EK güvenlik kuralı).
 *
 * TEK EŞLEŞTİRME ANAHTARI ÜRÜN KODUDUR. Kod yoksa ad ile eşleşme yalnız
 * TEK ADAY varsa yapılır; aynı ad iki satırdaysa (YA-016) parça hiçbir satıra
 * yazılmaz, iki aday ile "belirsiz" listesine düşer. Yanlış ürüne fiyat
 * yazmak tek olayda dahi otomatik rettir (altın set kabul kapısı) — bu sınıf
 * o kuralın yaşadığı yerdir; "en benzer ürün" tahmini burada YOKTUR.
 *
 * Satır kuralları:
 *   · Kod içeren satır kodun bulunduğu yerden bölünür; aynı kod ikinci kez
 *     görülürse (e-posta konusu + tablo satırı) parçalar BİRLEŞTİRİLİR.
 *   · Kodsuz satır: ad eşleşmesi tek adaysa o satıra açılır/eklenir; birden
 *     çoksa belirsiz; hiç yoksa ÖNCEKİ satırın devamıdır (çok satırlı WhatsApp).
 *   · Bu turda olmayan kod biçimindeki token YABANCI koddur: adaysız belirsiz.
 */
final class SatirEslestirici
{
    /** Kod gibi görünen ama bu turda olmayan token (örn. V-88, TH-004-ALT1). */
    private const YABANCI_KOD = '/(?<![A-Za-z0-9])([A-Z]{1,5}-\d{2,6}(?:-[A-Z0-9]{1,6})?)(?![A-Za-z0-9])/u';

    /**
     * @param  list<string>                                                      $satirlar
     * @param  list<array{satir_id: string, kod: string, adlar: list<string>}> $baglam
     * @return array{
     *     bolumler: array<string, string>,
     *     belirsiz: list<array{parca: string, aday_satir_idleri: list<string>, neden: string, yasak_otomatik_islem: string}>
     * }
     */
    public function bol(array $satirlar, array $baglam): array
    {
        $kodlar = [];
        foreach ($baglam as $b) {
            if ($b['kod'] !== '') {
                $kodlar[$b['kod']] = $b['satir_id'];
            }
        }
        $kodDeseni = $kodlar === [] ? null : '/(?<![A-Za-z0-9])(' . implode('|', array_map(
            static fn (string $k): string => preg_quote($k, '/'),
            array_keys($kodlar),
        )) . ')(?![A-Za-z0-9])/u';

        $bolumler = [];
        $belirsiz = [];
        $aktif = null;

        foreach ($satirlar as $satir) {
            $parcalar = $kodDeseni === null ? [] : $this->kodParcalari($satir, $kodDeseni);
            if ($parcalar !== []) {
                foreach ($parcalar as [$kod, $metin]) {
                    if ($kod === null) {
                        // Satırın kod öncesi kısmı ("先报第二个:") — bilgi taşımaz, önceki bölüme değil hiçbir yere gitmez.
                        continue;
                    }
                    $aktif = $kodlar[$kod];
                    $this->ekle($bolumler, $aktif, $metin);
                }
                continue;
            }

            $yabanci = $this->yabanciKod($satir, $kodlar);
            if ($yabanci !== null) {
                $belirsiz[] = $this->belirsiz($satir, [], 'Kod "' . $yabanci . '" bu turun RFQ satırlarında yok.', 'Yabancı kodu bir satıra tahminle bağlama');
                $aktif = null;
                continue;
            }

            $adaylar = $this->adAdaylari($satir, $baglam);
            if (count($adaylar) === 1) {
                $aktif = $adaylar[0];
                $this->ekle($bolumler, $aktif, $satir);
                continue;
            }
            if (count($adaylar) > 1) {
                $belirsiz[] = $this->belirsiz($satir, $adaylar, 'Birden çok RFQ satırının adı bu parçayla eşleşiyor; kod/varyant ayrımı yok.', 'Parçayı adaylardan birine tahminle yazma');
                $aktif = null;
                continue;
            }
            if ($aktif !== null) {
                $this->ekle($bolumler, $aktif, $satir);
            }
        }

        return ['bolumler' => $bolumler, 'belirsiz' => $belirsiz];
    }

    /**
     * Satırı kod konumlarından böler: [[null, öncesi], [kod, metin], [kod, metin] …].
     *
     * @return list<array{0: ?string, 1: string}>
     */
    private function kodParcalari(string $satir, string $desen): array
    {
        if (preg_match_all($desen, $satir, $m, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }
        $parcalar = [];
        $onceki = 0;
        $oncekiKod = null;
        foreach ($m[1] as [$kod, $konum]) {
            $parcalar[] = [$oncekiKod, trim(substr($satir, $onceki, $konum - $onceki))];
            $onceki = $konum + strlen($kod);
            $oncekiKod = $kod;
        }
        $parcalar[] = [$oncekiKod, trim(substr($satir, $onceki))];

        return $parcalar;
    }

    /** @param array<string, string> $kodlar */
    private function yabanciKod(string $satir, array $kodlar): ?string
    {
        if (preg_match(self::YABANCI_KOD, $satir, $m) !== 1) {
            return null;
        }

        return isset($kodlar[$m[1]]) ? null : $m[1];
    }

    /**
     * Ad eşleşmesi: tam ad geçiyorsa ya da (CJK adlarda) yalnız TEK adayda
     * geçen ≥2 karakterlik ortak parça varsa aday. Rakam/ölçü kısımları
     * ("70×140cm") ayırt edici sayılmaz — iki ürün aynı ölçüde olabilir.
     *
     * @param  list<array{satir_id: string, kod: string, adlar: list<string>}> $baglam
     * @return list<string>
     */
    private function adAdaylari(string $satir, array $baglam): array
    {
        $satirKucuk = mb_strtolower($satir);
        $tam = [];
        $parcali = [];
        foreach ($baglam as $b) {
            foreach ($b['adlar'] as $ad) {
                $adKucuk = mb_strtolower(trim($ad));
                if ($adKucuk !== '' && str_contains($satirKucuk, $adKucuk)) {
                    $tam[$b['satir_id']] = true;
                    continue;
                }
                $ortak = $this->enUzunOrtakHan($satir, $ad);
                if ($ortak !== null) {
                    $parcali[$b['satir_id']][] = $ortak;
                }
            }
        }
        if ($tam !== []) {
            return array_keys($tam);
        }

        // Ortak parça başka bir adayın adında da geçiyorsa ayırt edici değildir.
        $adaylar = [];
        foreach ($parcali as $id => $parcalar) {
            foreach ($parcalar as $parca) {
                $ayirtEdici = true;
                foreach ($baglam as $b) {
                    if ($b['satir_id'] === $id) {
                        continue;
                    }
                    foreach ($b['adlar'] as $ad) {
                        if (str_contains($ad, $parca)) {
                            $ayirtEdici = false;
                        }
                    }
                }
                if ($ayirtEdici) {
                    $adaylar[$id] = true;
                }
            }
        }

        return array_keys($adaylar);
    }

    /** Han karakterlerinden oluşan en uzun ortak alt dize (≥2). */
    private function enUzunOrtakHan(string $satir, string $ad): ?string
    {
        $a = $this->hanKarakterleri($satir);
        $b = $this->hanKarakterleri($ad);
        if (count($a) < 2 || count($b) < 2) {
            return null;
        }
        $enUzun = '';
        $tablo = [];
        foreach ($a as $i => $ca) {
            foreach ($b as $j => $cb) {
                if ($ca !== $cb) {
                    continue;
                }
                $uzunluk = ($tablo[$i - 1][$j - 1] ?? 0) + 1;
                $tablo[$i][$j] = $uzunluk;
                if ($uzunluk > mb_strlen($enUzun)) {
                    $enUzun = implode('', array_slice($b, $j - $uzunluk + 1, $uzunluk));
                }
            }
        }

        return mb_strlen($enUzun) >= 2 ? $enUzun : null;
    }

    /** @return list<string> */
    private function hanKarakterleri(string $metin): array
    {
        preg_match_all('/\p{Han}/u', $metin, $m);

        return $m[0];
    }

    /** @param array<string, string> $bolumler */
    private function ekle(array &$bolumler, string $satirId, string $metin): void
    {
        $metin = trim($metin, " \t,;:|-");
        if (!isset($bolumler[$satirId])) {
            $bolumler[$satirId] = $metin;

            return;
        }
        if ($metin !== '') {
            $bolumler[$satirId] .= ($bolumler[$satirId] === '' ? '' : ' . ') . $metin;
        }
    }

    /**
     * @param  list<string> $adaylar
     * @return array{parca: string, aday_satir_idleri: list<string>, neden: string, yasak_otomatik_islem: string}
     */
    private function belirsiz(string $parca, array $adaylar, string $neden, string $yasak): array
    {
        return ['parca' => $parca, 'aday_satir_idleri' => $adaylar, 'neden' => $neden, 'yasak_otomatik_islem' => $yasak];
    }
}
