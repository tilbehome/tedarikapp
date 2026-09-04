<?php

declare(strict_types=1);

namespace App\Services\Yanit;

/**
 * KANONİK YANIT SATIRI — üç kaynağın (yapıştır, Excel, DB) ortak şekli.
 *
 * `quote_lines` kolonlarıyla birebir aynı adlar; `kademeler` ve `alternatif`
 * alt yapılar. Yapıştır-ayrıştırıcı çıktısı (altın set biçimi) ve DB satırı
 * buradan kanonik biçime çevrilir; doğrulama (`YanitAlanKurallari`) ve yazım
 * (`YanitUygulayici`) yalnız bu şekli tanır — "iki yüzey tek gerçek" kuralı.
 */
final class YanitDonusturucu
{
    /** Kanonik alanlar (kademeler hariç) — boş satır bu şablonla üretilir. */
    public const ALANLAR = [
        'yanit_durumu', 'ddp_birim_fiyat', 'para_birimi', 'ddp_kdv_dahil_onayi',
        'moq_deger', 'moq_birim', 'termin_baslangici', 'termin_baslangici_aciklamasi', 'termin_suresi', 'termin_birimi',
        'koli_ici_adet', 'koli_uzunluk_cm', 'koli_genislik_cm', 'koli_yukseklik_cm', 'koli_cbm', 'koli_brut_kg', 'koli_net_kg',
        'ambalaj', 'firma_notu', 'alternatif_baglanti', 'alternatif_aciklama',
    ];

    /** @return array<string, mixed> */
    public static function bos(string $rfqSatirId): array
    {
        $satir = ['rfq_satir_id' => $rfqSatirId];
        foreach (self::ALANLAR as $alan) {
            $satir[$alan] = null;
        }
        $satir['yanit_durumu'] = 'unanswered';
        $satir['kademeler'] = [];

        return $satir;
    }

    /**
     * Yapıştır-ayrıştırıcı eşleşmesi → kanonik satır.
     *
     * @param  array<string, mixed> $e
     * @return array<string, mixed>
     */
    public static function yapistirdan(array $e): array
    {
        $s = self::bos((string) $e['satir_id']);
        $s['yanit_durumu'] = (string) $e['durum'];
        if (is_array($e['ddp'] ?? null)) {
            $s['ddp_birim_fiyat'] = (string) $e['ddp']['deger'];
            $s['para_birimi'] = (string) $e['ddp']['para_birimi'];
            $s['ddp_kdv_dahil_onayi'] = (bool) $e['ddp']['turkiye_kdv_dahil_beyani'];
        }
        if (is_array($e['moq'] ?? null)) {
            $s['moq_deger'] = (string) $e['moq']['deger'];
            $s['moq_birim'] = (string) $e['moq']['birim'];
        }
        if (is_array($e['termin'] ?? null)) {
            $s['termin_baslangici'] = $e['termin']['baslangic'];
            $s['termin_baslangici_aciklamasi'] = $e['termin']['baslangic_aciklamasi'] ?? null;
            $s['termin_suresi'] = (int) $e['termin']['sure'];
            $s['termin_birimi'] = (string) $e['termin']['birim'];
        }
        if (is_array($e['koli'] ?? null)) {
            $k = $e['koli'];
            $s['koli_ici_adet'] = $k['koli_ici_adet'];
            $s['koli_uzunluk_cm'] = $k['uzunluk_cm'];
            $s['koli_genislik_cm'] = $k['genislik_cm'];
            $s['koli_yukseklik_cm'] = $k['yukseklik_cm'];
            $s['koli_cbm'] = $k['cbm'];
            $s['koli_brut_kg'] = $k['brut_kg'];
            $s['koli_net_kg'] = $k['net_kg'];
            $s['ambalaj'] = $k['ambalaj'];
        }
        if (is_array($e['alternatif'] ?? null)) {
            $s['alternatif_baglanti'] = $e['alternatif']['url'];
            $s['alternatif_aciklama'] = $e['alternatif']['aciklama'];
        }
        $s['firma_notu'] = $e['not'] ?? null;
        $s['kademeler'] = array_map(static fn (array $k): array => [
            'min_adet' => (string) $k['min_adet'],
            'max_adet' => $k['max_adet'] === null ? null : (string) $k['max_adet'],
            'birim_fiyat' => (string) $k['birim_fiyat'],
            'para_birimi' => $k['para_birimi'] ?? null,
            'kademe_tipi' => $k['kademe_tipi'] ?? 'esik',
        ], $e['kademeler'] ?? []);

        return $s;
    }

    /**
     * DB satırı (quote_lines + kademeler + alternatif) → kanonik satır.
     *
     * @param  array<string, mixed>       $row
     * @param  list<array<string, mixed>> $kademeler
     * @param  ?array<string, mixed>      $alternatif
     * @return array<string, mixed>
     */
    public static function veritabanindan(array $row, array $kademeler, ?array $alternatif): array
    {
        $s = self::bos((string) $row['rfq_satir_id']);
        foreach (self::ALANLAR as $alan) {
            if (in_array($alan, ['alternatif_baglanti', 'alternatif_aciklama'], true)) {
                continue;
            }
            if (array_key_exists($alan, $row)) {
                $s[$alan] = $row[$alan];
            }
        }
        $s['ddp_kdv_dahil_onayi'] = $row['ddp_kdv_dahil_onayi'] === null ? null : (bool) $row['ddp_kdv_dahil_onayi'];
        $s['termin_suresi'] = $row['termin_suresi'] === null ? null : (int) $row['termin_suresi'];
        $s['koli_ici_adet'] = $row['koli_ici_adet'] === null ? null : (int) $row['koli_ici_adet'];
        foreach (['ddp_birim_fiyat', 'moq_deger', 'koli_uzunluk_cm', 'koli_genislik_cm', 'koli_yukseklik_cm', 'koli_cbm', 'koli_brut_kg', 'koli_net_kg'] as $para) {
            $s[$para] = $row[$para] === null ? null : self::sade((string) $row[$para]);
        }
        $s['alternatif_baglanti'] = $alternatif['baglanti'] ?? null;
        $s['alternatif_aciklama'] = $alternatif['aciklama'] ?? null;
        $s['kademeler'] = array_map(static fn (array $k): array => [
            'min_adet' => self::sade((string) $k['min_adet']),
            'max_adet' => $k['max_adet'] === null ? null : self::sade((string) $k['max_adet']),
            'birim_fiyat' => self::sade((string) $k['birim_fiyat']),
            'para_birimi' => $k['para_birimi'],
            'kademe_tipi' => (string) $k['kademe_tipi'],
        ], $kademeler);

        return $s;
    }

    /**
     * İstemciden gelen (önizlemeden seçilmiş) satır → kanonik; bilinmeyen anahtarlar düşer.
     *
     * @param  array<string, mixed> $girdi
     * @return array<string, mixed>
     */
    public static function istemciden(array $girdi): array
    {
        $s = self::bos((string) ($girdi['rfq_satir_id'] ?? ''));
        foreach (self::ALANLAR as $alan) {
            if (!array_key_exists($alan, $girdi)) {
                continue;
            }
            $v = $girdi[$alan];
            $s[$alan] = match ($alan) {
                'ddp_kdv_dahil_onayi' => $v === null ? null : (bool) $v,
                'termin_suresi', 'koli_ici_adet' => $v === null || $v === '' ? null : (int) $v,
                default => $v === null ? null : (is_string($v) ? trim($v) : (string) $v),
            };
            if ($s[$alan] === '') {
                $s[$alan] = null;
            }
        }
        $s['yanit_durumu'] = (string) ($girdi['yanit_durumu'] ?? 'unanswered');
        $s['kademeler'] = [];
        foreach (is_array($girdi['kademeler'] ?? null) ? $girdi['kademeler'] : [] as $k) {
            if (!is_array($k)) {
                continue;
            }
            $s['kademeler'][] = [
                'min_adet' => (string) ($k['min_adet'] ?? ''),
                'max_adet' => ($k['max_adet'] ?? null) === null || $k['max_adet'] === '' ? null : (string) $k['max_adet'],
                'birim_fiyat' => (string) ($k['birim_fiyat'] ?? ''),
                'para_birimi' => is_string($k['para_birimi'] ?? null) ? $k['para_birimi'] : null,
                'kademe_tipi' => ($k['kademe_tipi'] ?? 'esik') === 'aralik' ? 'aralik' : 'esik',
            ];
        }

        return $s;
    }

    /**
     * Eski satırın üstüne yeni değerler: BOŞ ALAN TEMİZLEMEZ (spec §8 "boş hücre
     * ile temizleme yapılmaz"); yalnız `$temizle` listesindeki alanlar açıkça
     * null'a çekilir. Kademeler yeni listede varsa bütünüyle değişir.
     *
     * @param  array<string, mixed> $eski
     * @param  array<string, mixed> $yeni
     * @param  list<string>         $temizle
     * @return array<string, mixed>
     */
    public static function birlestir(array $eski, array $yeni, array $temizle = []): array
    {
        $sonuc = $eski;
        foreach (self::ALANLAR as $alan) {
            if ($alan !== 'yanit_durumu' && ($yeni[$alan] ?? null) !== null) {
                $sonuc[$alan] = $yeni[$alan];
            }
        }
        // Durum yalnız AÇIKÇA seçilmişse değişir; "unanswered" boş hücrenin karşılığıdır, eski durumu ezmez.
        if (($yeni['yanit_durumu'] ?? 'unanswered') !== 'unanswered') {
            $sonuc['yanit_durumu'] = $yeni['yanit_durumu'];
        }
        if (($yeni['kademeler'] ?? []) !== []) {
            $sonuc['kademeler'] = $yeni['kademeler'];
        }
        foreach ($temizle as $alan) {
            if ($alan === 'kademeler') {
                $sonuc['kademeler'] = [];
            } elseif (in_array($alan, self::ALANLAR, true) && $alan !== 'yanit_durumu') {
                $sonuc[$alan] = null;
            }
        }

        return $sonuc;
    }

    /**
     * İki kanonik satır anlamca aynı mı? (para alanları bccomp ile)
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    public static function ayni(array $a, array $b): bool
    {
        foreach (self::ALANLAR as $alan) {
            $x = $a[$alan] ?? null;
            $y = $b[$alan] ?? null;
            if (is_string($x) && is_string($y) && preg_match('/^\d+(\.\d+)?$/', $x) === 1 && preg_match('/^\d+(\.\d+)?$/', $y) === 1) {
                if (bccomp($x, $y, 6) !== 0) {
                    return false;
                }
                continue;
            }
            if ($x != $y) {
                return false;
            }
        }
        $ka = $a['kademeler'] ?? [];
        $kb = $b['kademeler'] ?? [];
        if (count($ka) !== count($kb)) {
            return false;
        }
        foreach ($ka as $i => $k) {
            foreach (['min_adet', 'max_adet', 'birim_fiyat'] as $alan) {
                $x = $k[$alan] ?? null;
                $y = $kb[$i][$alan] ?? null;
                if (($x === null) !== ($y === null) || ($x !== null && bccomp((string) $x, (string) $y, 6) !== 0)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** "4.200000" → "4.2", "1000.000" → "1000". */
    public static function sade(string $v): string
    {
        if (!str_contains($v, '.')) {
            return $v;
        }

        return rtrim(rtrim($v, '0'), '.');
    }
}
