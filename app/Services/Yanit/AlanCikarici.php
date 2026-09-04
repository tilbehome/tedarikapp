<?php

declare(strict_types=1);

namespace App\Services\Yanit;

/**
 * TEK BİR RFQ SATIRINA AİT METİN PARÇASINDAN ALANLARI ÇIKARIR (V3-C 2.2).
 *
 * Kurallar altın setin `normalizasyon` bölümünden gelir (#28-EK):
 *   · Para: sembol/kod fiyatın hemen yanında olmalı. Para birimsiz sayı
 *     fiyat DEĞİLDİR — belirsiz listesine düşer (12.50'yi USD sanmak yasak).
 *   · Fiyat aralığı (6.8-7.4) tek fiyat değildir; alt/üst sınır seçilmez.
 *   · "约/大概/估算/approx" tek fiyatı TAHMİNİ yapar, aralığı belirsiz.
 *   · Termin aralığı (20-25天) tek süreye çevrilmez; 365 üstü alan hatasıdır.
 *   · Başlangıç iki ayrı olaya bağlıysa (kapora + görsel onayı) `custom`.
 *   · Kademeler kaynak sırasından artan sıraya dizilir; ÇAKIŞAN aralık hata
 *     olarak işaretlenir, sınırlar sessizce düzeltilmez (#28 yasak varsayım 4).
 *   · Sıfır fiyat ve MOQ 0 alan hatasıdır; brüt < net alan hatasıdır.
 *
 * Para değerleri STRING taşınır (K14). Bu sınıf DB'ye dokunmaz.
 */
final class AlanCikarici
{
    private const CUR = '(?:USD|US\$|U\$D|美元|CNY|RMB|人民币|¥|￥|元|TRY|TL|₺|里拉|EUR|€|欧元|\$)';
    private const AMT = '(?:\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+(?:[.,]\d+)?)';
    private const BIRIM = '(?:件|个|pcs?|套|条|包|片|sets?|张|只|adet|paket|koli|kg|m2|m)';
    private const YAKLASIK = '/(约|大约|大概|估算|左右|approx\w*|around|circa|yaklaşık|tahmini|~)/iu';

    private const DECL = '/(含[^,;.()]{0,8}?(?:税|VAT|KDV)|税已含|含税|incl\w*\.?\s*(?:Turkish|TR|Türkiye|Turkey|Turkiye)?\s*(?:VAT|KDV)|KDV\s*d[aâ]hil|VAT\s*included|with\s*VAT|DDP\s*\+\s*(?:VAT|KDV))/iu';
    private const ALTERNATIF = '/(替代|代替|替换|可以换|换\S{0,6}款|alternative|alternatif|类似款|同款)/iu';
    private const BULUNAMADI = '/(缺货|无货|没有了|没库存|没有库存|无法报价|不能接单|找不到|停做|停产|not\s+available|not\s+found|unavailable|discontinued|bulunamad|stok\s*yok|out\s+of\s+stock)/iu';
    private const BEKLEMEDE = '/(等\S{0,8}确认|稍后|晚点|later|pending|beklemede|to\s+be\s+confirmed|\bTBC\b|待定|待确认|再确认|还在等|sonra\s+(?:gönder|ilet)|bekliyor)/iu';
    private const AMBALAJ = '/(OPP|polybag|poly\s*bag|袋|poşet|polibag|bubble|气泡|纸盒|白盒|white\s*box|color\s*box)/iu';

    private const BASLANGIC = [
        'deposit_received' => '/(定金|订金|deposit|kapora|avans)/iu',
        'order_confirmation' => '/(订单确认|确认订单|下单|订单后|order\s*confirm\w*|sipariş\s*onay\w*|confirmed|\bPO\b)/iu',
        'sample_approval' => '/(样品|样板|sample|numune)/iu',
        'artwork_approval' => '/(彩盒稿|artwork|稿|görsel\s*onay\w*|tasarım\s*onay\w*)/iu',
    ];

    /**
     * @return array{
     *     durum: string,
     *     ddp: ?array{deger: string, para_birimi: string, turkiye_kdv_dahil_beyani: bool, nitelik: string},
     *     moq: ?array{deger: string, birim: string},
     *     termin: ?array{baslangic: ?string, baslangic_aciklamasi: ?string, sure: int, birim: string},
     *     kademeler: list<array{min_adet: string, max_adet: ?string, birim_fiyat: string, para_birimi: ?string, kademe_tipi: string}>,
     *     koli: ?array<string, mixed>,
     *     alternatif: ?array{url: ?string, aciklama: ?string},
     *     not: ?string,
     *     eksik_zorunlu: list<string>,
     *     belirsiz: list<array{parca: string, neden: string, yasak_otomatik_islem: string}>,
     *     hatalar: list<array{alan: string, deger: mixed, kural: string}>
     * }
     */
    public function cikar(string $metin): array
    {
        $belirsiz = [];
        $hatalar = [];
        $notlar = [];

        $durum = $this->durum($metin);
        if ($durum === 'not_found') {
            return $this->sonuc('not_found', null, null, null, [], null, null, $this->kisaNot($metin), [], [], []);
        }

        $paraBirimi = $this->paraBirimi($metin);
        $beyan = preg_match(self::DECL, $metin) === 1;

        $kademeler = $this->kademeler($metin, $paraBirimi, $hatalar, $notlar);
        $kalan = $kademeler === [] ? $metin : $this->kademeleriSil($metin);
        $ddp = $this->fiyat($kalan, $paraBirimi, $beyan, $belirsiz, $hatalar, $notlar);
        if ($ddp === null && $kademeler !== []) {
            $ddp = ['deger' => $kademeler[0]['birim_fiyat'], 'para_birimi' => (string) $kademeler[0]['para_birimi'], 'turkiye_kdv_dahil_beyani' => $beyan, 'nitelik' => 'kesin'];
        }
        $fiyatBirimi = $this->fiyatBirimi($metin);
        $moq = $this->moq($metin, $fiyatBirimi, $hatalar);
        $termin = $this->termin($metin, $belirsiz, $hatalar, $notlar);
        $koli = $this->koli($metin, $hatalar, $notlar);
        $alternatif = $durum === 'alternative_available' ? $this->alternatif($metin, $hatalar) : null;

        if (preg_match(self::BEKLEMEDE, $metin) === 1) {
            $notlar[] = 'Firma teyit bekliyor: ' . $this->kisaNot($metin);
        }

        $eksik = [];
        if ($ddp === null) {
            $eksik[] = 'ddp_birim_fiyat_kdv_dahil';
        }
        if ($paraBirimi === null) {
            $eksik[] = 'para_birimi';
        }
        if (!$beyan) {
            $eksik[] = 'ddp_turkiye_kdv_dahil_onayi';
        }
        if ($moq === null) {
            $eksik[] = 'moq';
        }
        if ($termin === null) {
            $eksik[] = 'termin_suresi';
            if ($this->baslangic($metin)[0] === null) {
                $eksik[] = 'termin_baslangici';
            }
        }

        return $this->sonuc($durum, $ddp, $moq, $termin, $kademeler, $koli, $alternatif, $notlar === [] ? null : implode(' ', array_unique($notlar)), $eksik, $belirsiz, $hatalar);
    }

    // ── durum ──────────────────────────────────────────────────────────

    private function durum(string $metin): string
    {
        if (preg_match(self::ALTERNATIF, $metin) === 1) {
            return 'alternative_available';
        }
        if (preg_match(self::BULUNAMADI, $metin) === 1 && preg_match('/' . self::CUR . '\s*' . self::AMT . '/u', $metin) !== 1) {
            return 'not_found';
        }

        return 'found';
    }

    // ── para & fiyat ───────────────────────────────────────────────────

    private function paraBirimi(string $metin): ?string
    {
        if (preg_match('/' . self::CUR . '/u', $metin, $m) !== 1) {
            return null;
        }

        return $this->paraKodu($m[0]);
    }

    private function paraKodu(string $sembol): string
    {
        return match (true) {
            (bool) preg_match('/^(CNY|RMB|人民币|¥|￥|元)$/u', $sembol) => 'CNY',
            (bool) preg_match('/^(TRY|TL|₺|里拉)$/u', $sembol) => 'TRY',
            (bool) preg_match('/^(EUR|€|欧元)$/u', $sembol) => 'EUR',
            default => 'USD',
        };
    }

    /**
     * @param list<array{parca: string, neden: string, yasak_otomatik_islem: string}> $belirsiz
     * @param list<array{alan: string, deger: mixed, kural: string}>                 $hatalar
     * @param list<string>                                                            $notlar
     * @return ?array{deger: string, para_birimi: string, turkiye_kdv_dahil_beyani: bool, nitelik: string}
     */
    private function fiyat(string $metin, ?string $paraBirimi, bool $beyan, array &$belirsiz, array &$hatalar, array &$notlar): ?array
    {
        // Aralık: tek fiyat değildir.
        $aralik = '/(' . self::CUR . '\s*' . self::AMT . '\s*[-~至到]\s*' . self::AMT . '|' . self::AMT . '\s*[-~至到]\s*' . self::AMT . '\s*' . self::CUR . ')/u';
        if (preg_match($aralik, $metin, $m) === 1) {
            $belirsiz[] = ['parca' => $m[1], 'neden' => 'Aralık tek kesin birim fiyat değildir; varyant/miktar bağı eksik.', 'yasak_otomatik_islem' => 'Alt veya üst sınırı kesin fiyat olarak kaydetme'];
            $notlar[] = 'Fiyat aralık olarak bildirildi: ' . $m[1];

            return null;
        }

        $desen = '/(?:' . self::CUR . '\s*(' . self::AMT . ')(?![\d.,]*\s*[-~至到]\s*\d)|(?<![\d.])(' . self::AMT . ')\s*' . self::CUR . ')/u';
        if (preg_match($desen, $metin, $m, PREG_OFFSET_CAPTURE) !== 1) {
            if ($paraBirimi === null) {
                $sayisiz = '/((?:DDP|价格|价|price|fiyat|报价)[^\d\n]{0,12}(' . self::AMT . ')\s*(块|元)?[^\d]{0,3})/iu';
                if (preg_match($sayisiz, $metin, $s) === 1) {
                    $belirsiz[] = ['parca' => trim($s[1]), 'neden' => 'Para birimi yazılmamış; sayı fiyat alanına otomatik bağlanamaz.', 'yasak_otomatik_islem' => $s[2] . ' değerini USD/CNY/TRY olarak tahmin etme'];
                }
            }

            return null;
        }
        $ham = $m[1][0] !== '' ? $m[1][0] : $m[2][0];
        $deger = $this->ondalik($ham);
        $konum = $m[0][1];
        $oncesi = mb_substr(substr($metin, 0, $konum), -15);
        $sonrasi = substr($metin, $konum + strlen($m[0][0]), 12);
        $tahmini = preg_match(self::YAKLASIK, $oncesi) === 1 || preg_match('/^\s*(左右|approx|around)/iu', $sonrasi) === 1;

        if (bccomp($deger, '0', 6) !== 1) {
            $hatalar[] = ['alan' => 'ddp_birim_fiyat_kdv_dahil', 'deger' => $deger, 'kural' => 'Fiyat 0 olamaz.'];

            return null;
        }
        if ($tahmini) {
            $notlar[] = 'Fiyat tahmini; kesinleşmesi bekleniyor.';
        }
        $sembol = preg_match('/' . self::CUR . '/u', $m[0][0], $c) === 1 ? $c[0] : 'USD';

        return ['deger' => $deger, 'para_birimi' => $this->paraKodu($sembol), 'turkiye_kdv_dahil_beyani' => $beyan, 'nitelik' => $tahmini ? 'tahmini' : 'kesin'];
    }

    /** "/pc", "/set", "/ 包" gibi fiyat birimi. */
    private function fiyatBirimi(string $metin): ?string
    {
        if (preg_match('/' . self::AMT . '\s*\/\s*(' . self::BIRIM . ')\b/iu', $metin, $m) !== 1 && preg_match('/' . self::AMT . '\s*\/\s*(' . self::BIRIM . ')/u', $metin, $m) !== 1) {
            return null;
        }

        return $this->birimKodu($m[1]);
    }

    // ── kademeler ──────────────────────────────────────────────────────

    private function kademeDeseni(): string
    {
        return '/(?<![\d.])(\d+)(?:\s*[-~至到]\s*(\d+))?\s*(\+|以上)?\s*' . self::BIRIM . '?\s*[:=]?\s*(' . self::CUR . ')\s*(' . self::AMT . ')(?![\d.,]*\s*[-~至到]\s*\d)/u';
    }

    /**
     * @param list<array{alan: string, deger: mixed, kural: string}> $hatalar
     * @param list<string>                                            $notlar
     * @return list<array{min_adet: string, max_adet: ?string, birim_fiyat: string, para_birimi: ?string, kademe_tipi: string}>
     */
    private function kademeler(string $metin, ?string $paraBirimi, array &$hatalar, array &$notlar): array
    {
        if (preg_match_all($this->kademeDeseni(), $metin, $m, PREG_SET_ORDER) < 2) {
            return [];
        }
        $kaynak = [];
        foreach ($m as $k) {
            $acikMax = ($k[2] ?? '') !== '';
            $kaynak[] = [
                'min_adet' => $k[1],
                'max_adet' => $acikMax ? $k[2] : null,
                'birim_fiyat' => $this->ondalik($k[5]),
                'para_birimi' => $this->paraKodu($k[4]),
                'kademe_tipi' => $acikMax || ($k[3] ?? '') !== '' ? 'aralik' : 'esik',
            ];
        }
        $sirali = $kaynak;
        usort($sirali, static fn (array $a, array $b): int => bccomp($a['min_adet'], $b['min_adet'], 3));
        if ($sirali !== $kaynak) {
            $notlar[] = 'Kademeler kaynak sırasından artan min adet sırasına dizildi.';
        }
        // Eşik biçimindeki kademelerde üst sınır bir sonraki eşiğin bir eksiğidir; son kademe açık uçlu.
        $n = count($sirali);
        for ($i = 0; $i < $n; $i++) {
            if ($sirali[$i]['kademe_tipi'] === 'esik') {
                $sirali[$i]['max_adet'] = $i + 1 < $n ? bcsub($sirali[$i + 1]['min_adet'], '1', 0) : null;
            }
            if (bccomp($sirali[$i]['birim_fiyat'], '0', 6) !== 1) {
                $hatalar[] = ['alan' => 'kademeli_fiyatlar', 'deger' => $sirali[$i]['birim_fiyat'], 'kural' => 'Kademe fiyatı 0 olamaz.'];
            }
            if ($paraBirimi !== null && $sirali[$i]['para_birimi'] !== $paraBirimi) {
                $hatalar[] = ['alan' => 'kademeli_fiyatlar', 'deger' => $sirali[$i]['para_birimi'], 'kural' => 'Kademeler ana fiyatla aynı para biriminde olmalı.'];
            }
        }
        // Açık aralıklarda çakışma: sessiz sınır düzeltmesi YOK.
        for ($i = 0; $i + 1 < $n; $i++) {
            if ($sirali[$i]['max_adet'] !== null && bccomp($sirali[$i + 1]['min_adet'], $sirali[$i]['max_adet'], 3) <= 0) {
                $hatalar[] = [
                    'alan' => 'kademeli_fiyatlar',
                    'deger' => $sirali[$i]['min_adet'] . '-' . $sirali[$i]['max_adet'] . ' ve ' . $sirali[$i + 1]['min_adet'] . '-' . ($sirali[$i + 1]['max_adet'] ?? '∞'),
                    'kural' => 'Fiyat kademeleri çakışamaz; sessiz sınır düzeltmesi yapılmaz.',
                ];
                break;
            }
        }

        return $sirali;
    }

    private function kademeleriSil(string $metin): string
    {
        return (string) preg_replace($this->kademeDeseni(), ' ', $metin);
    }

    // ── MOQ ────────────────────────────────────────────────────────────

    /**
     * @param  list<array{alan: string, deger: mixed, kural: string}> $hatalar
     * @return ?array{deger: string, birim: string}
     */
    private function moq(string $metin, ?string $fiyatBirimi, array &$hatalar): ?array
    {
        $desen = '/(?:MOQ|起订量|最小起订|最小|最少|min\.?\s*order(?:\s*qty)?|minimum(?:\s*order)?)[\s:=]*(?:MOQ)?[\s:=]*(\d+(?:\.\d+)?)\s*(' . self::BIRIM . ')?(?![A-Za-z])/iu';
        if (preg_match($desen, $metin, $m, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }
        $deger = $this->ondalik((string) $m[1]);
        if (bccomp($deger, '1', 3) < 0) {
            $hatalar[] = ['alan' => 'moq', 'deger' => $deger, 'kural' => 'MOQ en az 1 olmalıdır.'];

            return null;
        }
        $birim = $m[2] !== null ? $this->birimKodu($m[2]) : ($fiyatBirimi ?? 'adet');

        return ['deger' => $deger, 'birim' => $birim];
    }

    private function birimKodu(string $ham): string
    {
        $ham = mb_strtolower($ham);

        return match (true) {
            in_array($ham, ['套', 'set', 'sets'], true) => 'set',
            in_array($ham, ['包', 'paket'], true) => 'paket',
            $ham === 'koli' => 'koli',
            in_array($ham, ['kg', 'm2', 'm'], true) => $ham,
            default => 'adet',
        };
    }

    // ── termin ─────────────────────────────────────────────────────────

    /**
     * @param list<array{parca: string, neden: string, yasak_otomatik_islem: string}> $belirsiz
     * @param list<array{alan: string, deger: mixed, kural: string}>                 $hatalar
     * @param list<string>                                                            $notlar
     * @return ?array{baslangic: ?string, baslangic_aciklamasi: ?string, sure: int, birim: string}
     */
    private function termin(string $metin, array &$belirsiz, array &$hatalar, array &$notlar): ?array
    {
        $birimler = '(工作日|天|日|周|calendar\s*days?|working\s*days?|business\s*days?|days?|weeks?|wks?|iş\s*günü|gün|hafta)';
        $desen = '/(?<![\d.%])(\d+)(?:\s*[-~至到]\s*(\d+))?\s*(?:个)?\s*' . $birimler . '(?![a-zğüşıöç])/iu';
        if (preg_match($desen, $metin, $m) !== 1) {
            return null;
        }
        [$baslangic, $aciklama] = $this->baslangic($metin);
        if ($m[2] !== '') {
            $belirsiz[] = ['parca' => $m[0], 'neden' => 'Tek bir termin süresi yok; aralık otomatik kesin süreye çevrilmez.', 'yasak_otomatik_islem' => $m[1] . ' veya ' . $m[2] . ' günü tek termin olarak seçme'];
            $notlar[] = 'Termin aralık olarak bildirildi: ' . $m[0];

            return null;
        }
        $sure = (int) $m[1];
        if ($sure < 1 || $sure > 365) {
            $hatalar[] = ['alan' => 'termin_suresi', 'deger' => $sure, 'kural' => 'Termin 1-365 gün aralığında olmalı.'];

            return null;
        }
        $birimHam = mb_strtolower($m[3]);
        $birim = match (true) {
            (bool) preg_match('/工作日|working|business|iş/u', $birimHam) => 'working_day',
            (bool) preg_match('/周|week|wk|hafta/u', $birimHam) => 'week',
            default => 'calendar_day',
        };
        if ($baslangic === 'deposit_received' && preg_match('/\d+\s*%|%\s*\d+/u', (string) $aciklama) === 1) {
            $notlar[] = 'Termin başlangıcı: ' . $aciklama;
        }

        return ['baslangic' => $baslangic, 'baslangic_aciklamasi' => $baslangic === 'custom' ? $aciklama : null, 'sure' => $sure, 'birim' => $birim];
    }

    /**
     * Termin başlangıcı: tek olay → o kod; iki ayrı olay → `custom` + cümle.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function baslangic(string $metin): array
    {
        $bulunan = [];
        foreach (self::BASLANGIC as $kod => $desen) {
            if (preg_match($desen, $metin) === 1) {
                $bulunan[] = $kod;
            }
        }
        if ($bulunan === []) {
            return [null, null];
        }
        $cumleler = array_filter(array_map('trim', preg_split('/[.;,()]/u', $metin) ?: []));
        $ilgili = [];
        foreach ($cumleler as $cumle) {
            foreach (self::BASLANGIC as $desen) {
                if (preg_match($desen, $cumle) === 1) {
                    $ilgili[] = $cumle;
                    break;
                }
            }
        }
        $aciklama = implode(' ', array_unique($ilgili));
        if (count($bulunan) === 1) {
            return [$bulunan[0], $aciklama];
        }

        return ['custom', $aciklama];
    }

    // ── koli ───────────────────────────────────────────────────────────

    /**
     * @param  list<array{alan: string, deger: mixed, kural: string}> $hatalar
     * @param  list<string>                                            $notlar
     * @return ?array<string, mixed>
     */
    private function koli(string $metin, array &$hatalar, array &$notlar): ?array
    {
        $koli = ['koli_ici_adet' => null, 'uzunluk_cm' => null, 'genislik_cm' => null, 'yukseklik_cm' => null, 'cbm' => null, 'brut_kg' => null, 'net_kg' => null, 'ambalaj' => null];

        if (preg_match('/(\d+)\s*' . self::BIRIM . '?\s*\/\s*(?:箱|carton|ctn|koli)/iu', $metin, $m) === 1
            || preg_match('/(?:装箱|carton|koli(?:\s*içi)?|packing)[\s:=]*(\d+)\s*' . self::BIRIM . '?(?![a-z])/iu', $metin, $m) === 1) {
            $koli['koli_ici_adet'] = (int) $m[1];
        }
        if (preg_match('/(?<![\d.])(\d+(?:\.\d+)?)\s*[×xX*]\s*(\d+(?:\.\d+)?)\s*[×xX*]\s*(\d+(?:\.\d+)?)\s*(?:cm|厘米)?/u', $metin, $m) === 1) {
            $koli['uzunluk_cm'] = $m[1];
            $koli['genislik_cm'] = $m[2];
            $koli['yukseklik_cm'] = $m[3];
        }
        if (preg_match('/CBM\s*[:=]?\s*(\d+(?:\.\d+)?)/iu', $metin, $m) === 1) {
            $koli['cbm'] = $m[1];
        }
        if (preg_match('/(?:毛重|G\.?\s*W\.?|gross(?:\s*weight)?|brüt)\s*[:=]?\s*(\d+(?:\.\d+)?)\s*kg/iu', $metin, $m) === 1) {
            $koli['brut_kg'] = $m[1];
        }
        if (preg_match('/(?:净重|N\.?\s*W\.?|net(?:\s*weight)?)\s*[:=]?\s*(\d+(?:\.\d+)?)\s*kg/iu', $metin, $m) === 1) {
            $koli['net_kg'] = $m[1];
        }
        $ambalaj = null;
        foreach (preg_split('/[.;,]/u', $metin) ?: [] as $cumle) {
            if (preg_match(self::AMBALAJ, $cumle) === 1) {
                $ambalaj = trim($cumle);
                break;
            }
        }

        $dolu = array_filter($koli, static fn (mixed $v): bool => $v !== null) !== [];
        if (!$dolu) {
            if ($ambalaj !== null) {
                $notlar[] = 'Ambalaj: ' . $ambalaj;
            }

            return null;
        }
        $koli['ambalaj'] = $ambalaj;
        if ($koli['brut_kg'] !== null && $koli['net_kg'] !== null && bccomp($koli['brut_kg'], $koli['net_kg'], 3) < 0) {
            $hatalar[] = ['alan' => 'koli_brut_kg', 'deger' => $koli['brut_kg'], 'kural' => 'Brüt ağırlık net ağırlıktan küçük olamaz; otomatik düzeltme yapılmaz.'];
        }
        foreach (['uzunluk_cm', 'genislik_cm', 'yukseklik_cm'] as $olcu) {
            if ($koli[$olcu] !== null && bccomp($koli[$olcu], '1000', 2) > 0) {
                $hatalar[] = ['alan' => 'koli_olculeri', 'deger' => $koli[$olcu], 'kural' => 'Koli ölçüsü 1000 cm\'yi aşamaz.'];
            }
        }

        return $koli;
    }

    // ── alternatif ─────────────────────────────────────────────────────

    /**
     * @param  list<array{alan: string, deger: mixed, kural: string}> $hatalar
     * @return array{url: ?string, aciklama: ?string}
     */
    private function alternatif(string $metin, array &$hatalar): array
    {
        $url = null;
        if (preg_match('#(https?://[^\s,;()"\']+)#u', $metin, $m) === 1) {
            $url = rtrim($m[1], '.,;:)');
            if (!str_starts_with($url, 'https://')) {
                $hatalar[] = ['alan' => 'alternatif_urun_baglantisi', 'deger' => $url, 'kural' => 'Yalnız https bağlantı kabul edilir.'];
                $url = null;
            }
        }
        $aciklama = trim((string) preg_replace('#https?://\S+#u', '', $metin));

        return ['url' => $url, 'aciklama' => $aciklama === '' ? null : mb_substr($aciklama, 0, 3000)];
    }

    // ── yardımcılar ────────────────────────────────────────────────────

    /** "2,35" → 2.35 · "1,480" → 1480 · "4.20" → 4.20 (string, K14). */
    private function ondalik(string $ham): string
    {
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $ham) === 1) {
            return str_replace(',', '', $ham);
        }

        return str_replace(',', '.', $ham);
    }

    private function kisaNot(string $metin): string
    {
        return mb_substr(trim($metin, " .;,"), 0, 2000);
    }

    /**
     * @param  ?array<string, mixed> $ddp
     * @param  ?array<string, mixed> $moq
     * @param  ?array<string, mixed> $termin
     * @param  ?array<string, mixed> $koli
     * @param  ?array<string, mixed> $alternatif
     * @param  list<array{min_adet: string, max_adet: ?string, birim_fiyat: string, para_birimi: ?string, kademe_tipi: string}> $kademeler
     * @param  list<string>                                                                                                           $eksik
     * @param  list<array{parca: string, neden: string, yasak_otomatik_islem: string}>                                                $belirsiz
     * @param  list<array{alan: string, deger: mixed, kural: string}>                                                                $hatalar
     * @return array<string, mixed>
     */
    private function sonuc(string $durum, ?array $ddp, ?array $moq, ?array $termin, array $kademeler, ?array $koli, ?array $alternatif, ?string $not, array $eksik, array $belirsiz, array $hatalar): array
    {
        return [
            'durum' => $durum,
            'ddp' => $ddp,
            'moq' => $moq,
            'termin' => $termin,
            'kademeler' => $kademeler,
            'koli' => $koli,
            'alternatif' => $alternatif,
            'not' => $not,
            'eksik_zorunlu' => $eksik,
            'belirsiz' => $belirsiz,
            'hatalar' => $hatalar,
        ];
    }
}
