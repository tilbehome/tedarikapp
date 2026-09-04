<?php

declare(strict_types=1);

namespace App\Services\Yanit;

/**
 * FİRMA YANIT ALAN KURALLARI — `rfq-alan-sozlesmesi.json` §firma_yanit_alanlari'nın
 * SUNUCUDAKİ tek kopyası (V3-C Aşama 2.2).
 *
 * Üç giriş kanalı (yapıştır-ayrıştır, Excel içe aktarım, ileride portal) AYNI
 * kurallardan geçer; Excel'in kendi veri doğrulaması yalnız ilk yardımdır
 * (spec §4.2: "kopyala-yapıştırla doğrulamanın aşılması kabul sayılmaz").
 *
 * Üç çıktı:
 *   · `hatalar`   — uygulanamaz (fiyat 0, termin>365, brüt<net, çakışan kademe…)
 *   · `uyarilar`  — teyit ister (MOQ talep miktarının üstünde, CBM farkı >%5)
 *   · `eksik`     — nihai gönderim için zorunlu ama boş (kısmi yanıt olabilir)
 *
 * Para/miktar STRING olarak bcmath ile sınanır (K14).
 */
final class YanitAlanKurallari
{
    public const DURUMLAR = ['unanswered', 'found', 'not_found', 'alternative_available'];
    public const PARA_BIRIMLERI = ['USD', 'CNY', 'TRY', 'EUR'];
    public const MOQ_BIRIMLERI = ['adet', 'set', 'paket', 'koli', 'kg', 'm', 'm2', 'ozel'];
    public const TERMIN_BASLANGICLARI = ['order_confirmation', 'deposit_received', 'sample_approval', 'artwork_approval', 'custom'];
    public const TERMIN_BIRIMLERI = ['calendar_day', 'working_day', 'week'];
    public const EN_COK_KADEME = 20;

    /**
     * @param  array<string, mixed> $s Kanonik yanıt satırı (`YanitDonusturucu` şekli)
     * @return array{hatalar: list<array{alan: string, deger: mixed, kural: string}>, uyarilar: list<array{alan: string, deger: mixed, kural: string}>, eksik: list<string>}
     */
    public function dogrula(array $s, ?string $talepMiktar = null): array
    {
        $h = [];
        $u = [];
        $eksik = [];
        $durum = (string) ($s['yanit_durumu'] ?? '');

        if (!in_array($durum, self::DURUMLAR, true)) {
            $h[] = $this->k('yanit_durumu', $durum, 'Geçerli bir yanıt durumu değil.');

            return ['hatalar' => $h, 'uyarilar' => $u, 'eksik' => $eksik];
        }

        $this->fiyat($s, $h, $eksik, $durum);
        $this->moq($s, $h, $u, $eksik, $durum, $talepMiktar);
        $this->termin($s, $h, $eksik, $durum);
        $this->koli($s, $h, $u);
        $this->metinler($s, $h, $durum);
        $this->kademeler($s, $h);

        return ['hatalar' => $h, 'uyarilar' => $u, 'eksik' => $eksik];
    }

    /**
     * @param array<string, mixed> $s
     * @param list<array{alan: string, deger: mixed, kural: string}> $h
     * @param list<string> $eksik
     */
    private function fiyat(array $s, array &$h, array &$eksik, string $durum): void
    {
        $fiyat = $this->str($s['ddp_birim_fiyat'] ?? null);
        $para = $this->str($s['para_birimi'] ?? null);
        $cevapli = in_array($durum, ['found', 'alternative_available'], true);

        if ($fiyat !== null) {
            if (!$this->ondalik($fiyat, 6)) {
                $h[] = $this->k('ddp_birim_fiyat_kdv_dahil', $fiyat, 'Fiyat sayı olmalı; en çok 6 ondalık.');
            } elseif (bccomp($fiyat, '0', 6) !== 1) {
                $h[] = $this->k('ddp_birim_fiyat_kdv_dahil', $fiyat, 'Fiyat 0 olamaz.');
            } elseif (bccomp($fiyat, '100000000', 6) > 0) {
                $h[] = $this->k('ddp_birim_fiyat_kdv_dahil', $fiyat, 'Fiyat üst sınırı aşıyor.');
            }
            if ($para === null) {
                $h[] = $this->k('para_birimi', null, 'Fiyat varsa para birimi zorunlu; para birimsiz fiyat uygulanmaz.');
            }
            if (($s['ddp_kdv_dahil_onayi'] ?? null) !== true) {
                $eksik[] = 'ddp_turkiye_kdv_dahil_onayi';
            }
        } elseif ($cevapli) {
            $eksik[] = 'ddp_birim_fiyat_kdv_dahil';
            if ($para === null) {
                $eksik[] = 'para_birimi';
            }
            if (($s['ddp_kdv_dahil_onayi'] ?? null) !== true) {
                $eksik[] = 'ddp_turkiye_kdv_dahil_onayi';
            }
        }
        if ($para !== null && !in_array($para, self::PARA_BIRIMLERI, true)) {
            $h[] = $this->k('para_birimi', $para, 'Para birimi USD, CNY, TRY ya da EUR olmalı.');
        }
    }

    /**
     * @param array<string, mixed> $s
     * @param list<array{alan: string, deger: mixed, kural: string}> $h
     * @param list<array{alan: string, deger: mixed, kural: string}> $u
     * @param list<string> $eksik
     */
    private function moq(array $s, array &$h, array &$u, array &$eksik, string $durum, ?string $talepMiktar): void
    {
        $moq = $this->str($s['moq_deger'] ?? null);
        $birim = $this->str($s['moq_birim'] ?? null);
        if ($moq === null) {
            if (in_array($durum, ['found', 'alternative_available'], true)) {
                $eksik[] = 'moq';
            }

            return;
        }
        if (!$this->ondalik($moq, 3)) {
            $h[] = $this->k('moq', $moq, 'MOQ sayı olmalı; en çok 3 ondalık.');
        } elseif (bccomp($moq, '1', 3) < 0) {
            $h[] = $this->k('moq', $moq, 'MOQ en az 1 olmalıdır.');
        } elseif (bccomp($moq, '100000000', 3) > 0) {
            $h[] = $this->k('moq', $moq, 'MOQ üst sınırı aşıyor.');
        } elseif ($talepMiktar !== null && $this->ondalik($talepMiktar, 3) && bccomp($moq, $talepMiktar, 3) > 0) {
            $u[] = $this->k('moq', $moq, 'MOQ talep miktarının (' . $talepMiktar . ') üstünde; yanıt kaybolmaz, teyit ister.');
        }
        if ($birim !== null && !in_array($birim, self::MOQ_BIRIMLERI, true)) {
            $h[] = $this->k('moq_birim', $birim, 'MOQ birimi izinli listede değil.');
        }
    }

    /**
     * @param array<string, mixed> $s
     * @param list<array{alan: string, deger: mixed, kural: string}> $h
     * @param list<string> $eksik
     */
    private function termin(array $s, array &$h, array &$eksik, string $durum): void
    {
        $sure = $s['termin_suresi'] ?? null;
        $baslangic = $this->str($s['termin_baslangici'] ?? null);
        $birim = $this->str($s['termin_birimi'] ?? null);
        $aciklama = $this->str($s['termin_baslangici_aciklamasi'] ?? null);
        $cevapli = in_array($durum, ['found', 'alternative_available'], true);

        if ($sure === null || $sure === '') {
            if ($cevapli) {
                $eksik[] = 'termin_suresi';
            }
        } elseif (!is_numeric($sure) || (int) $sure != $sure || (int) $sure < 1 || (int) $sure > 365) {
            $h[] = $this->k('termin_suresi', $sure, 'Termin 1-365 tam gün olmalı; 365 günü aşan değer alan hatasıdır.');
        }
        if ($baslangic === null) {
            if ($cevapli) {
                $eksik[] = 'termin_baslangici';
            }
        } elseif (!in_array($baslangic, self::TERMIN_BASLANGICLARI, true)) {
            $h[] = $this->k('termin_baslangici', $baslangic, 'Termin başlangıcı izinli listede değil.');
        } elseif ($baslangic === 'custom' && ($aciklama === null || mb_strlen($aciklama) < 3 || mb_strlen($aciklama) > 300)) {
            $h[] = $this->k('termin_baslangici_aciklamasi', $aciklama, '"Özel" başlangıç 3-300 karakter açıklama ister.');
        }
        if ($birim !== null && !in_array($birim, self::TERMIN_BIRIMLERI, true)) {
            $h[] = $this->k('termin_birimi', $birim, 'Termin birimi calendar_day, working_day ya da week olmalı.');
        }
    }

    /**
     * @param array<string, mixed> $s
     * @param list<array{alan: string, deger: mixed, kural: string}> $h
     * @param list<array{alan: string, deger: mixed, kural: string}> $u
     */
    private function koli(array $s, array &$h, array &$u): void
    {
        $ici = $s['koli_ici_adet'] ?? null;
        if ($ici !== null && $ici !== '' && (!is_numeric($ici) || (int) $ici != $ici || (int) $ici < 1 || (int) $ici > 1000000)) {
            $h[] = $this->k('koli_ici_adet', $ici, 'Koli içi adet 1-1.000.000 tam sayı olmalı.');
        }
        $olculer = [];
        foreach (['koli_uzunluk_cm', 'koli_genislik_cm', 'koli_yukseklik_cm'] as $alan) {
            $d = $this->str($s[$alan] ?? null);
            if ($d === null) {
                continue;
            }
            $olculer[$alan] = $d;
            if (!$this->ondalik($d, 2) || bccomp($d, '0', 2) !== 1 || bccomp($d, '1000', 2) > 0) {
                $h[] = $this->k($alan, $d, 'Koli ölçüsü 0-1000 cm arasında, en çok 2 ondalık olmalı.');
            }
        }
        if ($olculer !== [] && count($olculer) !== 3) {
            $h[] = $this->k('koli_olculeri', implode('×', $olculer), 'Bir ölçü varsa üçü birlikte verilmeli.');
        }
        $cbm = $this->str($s['koli_cbm'] ?? null);
        if ($cbm !== null) {
            if (!$this->ondalik($cbm, 6) || bccomp($cbm, '0', 6) !== 1 || bccomp($cbm, '100', 6) > 0) {
                $h[] = $this->k('koli_cbm', $cbm, 'CBM 0-100 arasında olmalı.');
            } elseif (count($olculer) === 3) {
                $hesap = bcdiv(bcmul(bcmul($olculer['koli_uzunluk_cm'], $olculer['koli_genislik_cm'], 6), $olculer['koli_yukseklik_cm'], 6), '1000000', 6);
                if (bccomp($hesap, '0', 6) === 1) {
                    $fark = bcdiv(bcmul(bcsub($cbm, $hesap, 6) < '0' ? bcsub($hesap, $cbm, 6) : bcsub($cbm, $hesap, 6), '100', 6), $hesap, 4);
                    if (bccomp($fark, '5', 4) > 0) {
                        $u[] = $this->k('koli_cbm', $cbm, 'Ölçülerden hesaplanan CBM (' . rtrim(rtrim($hesap, '0'), '.') . ') ile fark %5\'i aşıyor; teyit ister.');
                    }
                }
            }
        }
        $brut = $this->str($s['koli_brut_kg'] ?? null);
        $net = $this->str($s['koli_net_kg'] ?? null);
        foreach (['koli_brut_kg' => $brut, 'koli_net_kg' => $net] as $alan => $d) {
            if ($d !== null && (!$this->ondalik($d, 3) || bccomp($d, '0', 3) !== 1 || bccomp($d, '10000', 3) > 0)) {
                $h[] = $this->k($alan, $d, 'Ağırlık 0-10000 kg arasında, en çok 3 ondalık olmalı.');
            }
        }
        if ($net !== null && $brut === null) {
            $h[] = $this->k('koli_brut_kg', null, 'Net ağırlık varsa brüt zorunlu.');
        }
        if ($net !== null && $brut !== null && $this->ondalik($brut, 3) && $this->ondalik($net, 3) && bccomp($brut, $net, 3) < 0) {
            $h[] = $this->k('koli_brut_kg', $brut, 'Brüt ağırlık net ağırlıktan küçük olamaz; otomatik düzeltme yapılmaz.');
        }
    }

    /**
     * @param array<string, mixed> $s
     * @param list<array{alan: string, deger: mixed, kural: string}> $h
     */
    private function metinler(array $s, array &$h, string $durum): void
    {
        $ambalaj = $this->str($s['ambalaj'] ?? null);
        if ($ambalaj !== null && mb_strlen($ambalaj) > 1000) {
            $h[] = $this->k('ambalaj', mb_substr($ambalaj, 0, 40) . '…', 'Ambalaj en çok 1000 karakter.');
        }
        $not = $this->str($s['firma_notu'] ?? null);
        if ($durum === 'not_found') {
            if ($not === null || mb_strlen($not) < 3 || mb_strlen($not) > 2000) {
                $h[] = $this->k('firma_notu', $not, 'Bulunamadı satırında 3-2000 karakter açıklama zorunlu.');
            }
        } elseif ($not !== null && mb_strlen($not) > 5000) {
            $h[] = $this->k('firma_notu', mb_substr($not, 0, 40) . '…', 'Firma notu en çok 5000 karakter.');
        }
        $baglanti = $this->str($s['alternatif_baglanti'] ?? null);
        $aciklama = $this->str($s['alternatif_aciklama'] ?? null);
        if ($baglanti !== null && (!str_starts_with($baglanti, 'https://') || mb_strlen($baglanti) > 2048 || preg_match('/\s/u', $baglanti) === 1)) {
            $h[] = $this->k('alternatif_urun_baglantisi', $baglanti, 'Yalnız https bağlantı kabul edilir (en çok 2048 karakter).');
        }
        if ($aciklama !== null && (mb_strlen($aciklama) < 3 || mb_strlen($aciklama) > 3000)) {
            $h[] = $this->k('alternatif_aciklamasi', mb_substr($aciklama, 0, 40), 'Alternatif açıklaması 3-3000 karakter.');
        }
        if ($durum === 'alternative_available' && $baglanti === null && $aciklama === null) {
            $h[] = $this->k('alternatif_urun_baglantisi', null, 'Alternatif için bağlantı ya da açıklama gerekli.');
        }
    }

    /**
     * @param array<string, mixed> $s
     * @param list<array{alan: string, deger: mixed, kural: string}> $h
     */
    private function kademeler(array $s, array &$h): void
    {
        $kademeler = $s['kademeler'] ?? [];
        if (!is_array($kademeler) || $kademeler === []) {
            return;
        }
        if (count($kademeler) > self::EN_COK_KADEME) {
            $h[] = $this->k('kademeli_fiyatlar', count($kademeler), 'Ürün başına en çok 20 kademe.');

            return;
        }
        $para = $this->str($s['para_birimi'] ?? null);
        $onceki = null;
        foreach (array_values($kademeler) as $i => $k) {
            $min = $this->str($k['min_adet'] ?? null);
            $max = $this->str($k['max_adet'] ?? null);
            $fiyat = $this->str($k['birim_fiyat'] ?? null);
            $etiket = 'kademe ' . ($i + 1);
            if ($min === null || !$this->ondalik($min, 3) || bccomp($min, '1', 3) < 0) {
                $h[] = $this->k('kademeli_fiyatlar', $min, $etiket . ': min adet en az 1 olmalı.');
                continue;
            }
            if ($fiyat === null || !$this->ondalik($fiyat, 6) || bccomp($fiyat, '0', 6) !== 1) {
                $h[] = $this->k('kademeli_fiyatlar', $fiyat, $etiket . ': fiyat 0\'dan büyük olmalı.');
            }
            if ($max !== null && (!$this->ondalik($max, 3) || bccomp($max, $min, 3) < 0)) {
                $h[] = $this->k('kademeli_fiyatlar', $max, $etiket . ': üst sınır min adetten küçük olamaz.');
            }
            if ($onceki !== null) {
                if (bccomp($min, $onceki['min'], 3) <= 0) {
                    $h[] = $this->k('kademeli_fiyatlar', $min, 'Kademe min adetleri kesin artan olmalı.');
                } elseif ($onceki['max'] !== null && bccomp($min, $onceki['max'], 3) <= 0) {
                    $h[] = $this->k('kademeli_fiyatlar', $onceki['min'] . '-' . $onceki['max'] . ' ve ' . $min . '-' . ($max ?? '∞'), 'Fiyat kademeleri çakışamaz; sessiz sınır düzeltmesi yapılmaz.');
                }
            }
            $kPara = $this->str($k['para_birimi'] ?? null);
            if ($para !== null && $kPara !== null && $kPara !== $para) {
                $h[] = $this->k('kademeli_fiyatlar', $kPara, $etiket . ': ana fiyatla aynı para biriminde olmalı.');
            }
            $onceki = ['min' => $min, 'max' => $max];
        }
    }

    // ── yardımcılar ────────────────────────────────────────────────────

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    /** Noktalı ondalık, en çok $basamak ondalık (float'a çevrilmez). */
    private function ondalik(string $v, int $basamak): bool
    {
        return preg_match('/^\d{1,12}(\.\d{1,' . $basamak . '})?$/', $v) === 1;
    }

    /** @return array{alan: string, deger: mixed, kural: string} */
    private function k(string $alan, mixed $deger, string $kural): array
    {
        return ['alan' => $alan, 'deger' => $deger, 'kural' => $kural];
    }
}
