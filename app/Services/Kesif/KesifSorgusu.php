<?php

declare(strict_types=1);

namespace App\Services\Kesif;

use App\Core\Connection;
use App\Services\Ilan\SkorHesaplayici;

/**
 * KEŞİF HAVUZU SORGUSU (İE#21 B1) — V3'ün kalbi.
 *
 * Havuz, listelere GİRMEMİŞ ürünleri de kapsayan bir istihbarat yüzeyidir:
 * "elimde ne var, hangisi iyi, hangisi aynı ürünün başka satıcısı?"
 *
 * TASARIM KARARLARI:
 *
 * • FİLTRELER "VE" İLE BİRLEŞİR (E2E-PNL-01). Gevşek OR, üç filtre seçen
 *   kullanıcıya daha ÇOK sonuç gösterir — filtrelemenin amacının tam tersi.
 *
 * • ARAMA İKİ ALANA BAKAR: ham `arama_metni` ve normalize `arama_normal`.
 *   Türkçe sorgu Çince kaydı bulmalıdır (E2E-PNL-02) ve "33x23x14 cm" ile
 *   "33×23×14cm" aynı sayılmalıdır (E2E-PNL-03).
 *
 * • SKOR SORGUDA HESAPLANMAZ, OKUNUR. Skor kuyruğun yazdığı `listings.skor`
 *   alanındadır; 10.000 üründe her istekte yeniden hesaplamak filtreyi
 *   saniyelerce bekletirdi (§10 hedefi: filtre < 2 sn). Ağırlık kaydırıcısı
 *   kullanıldığında (nadir) yeniden hesap AÇIKÇA istenir.
 *
 * • SAYFALAMA ZORUNLUDUR. Sınırsız sorgu hem sunucuyu hem tarayıcıyı yorar.
 */
final class KesifSorgusu
{
    public const VARSAYILAN_LIMIT = 50;
    public const AZAMI_LIMIT = 200;

    /** Hazır modlar (§7.2) — her biri bir filtre önayarıdır. */
    public const HAZIR_MODLAR = [
        'yeni_yukselen' => ['siralama' => 'ivme', 'skor_bandi' => ['yuksek', 'orta']],
        'kanitlanmis_cok_satan' => ['siralama' => 'satis', 'satis_min' => 1000, 'puan_min' => 4.5],
        'mavi_okyanus' => ['siralama' => 'skor', 'satis_max' => 500, 'puan_min' => 4.5],
        'ucuz_yuksek_puan' => ['siralama' => 'fiyat', 'puan_min' => 4.7],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, mixed> $suzgec
     *
     * @return array{satirlar: list<array<string, mixed>>, toplam: int, sayfa: int, limit: int}
     */
    public function calistir(array $suzgec): array
    {
        [$where, $params] = $this->kosullar($suzgec);

        $limit = max(1, min(self::AZAMI_LIMIT, (int) ($suzgec['limit'] ?? self::VARSAYILAN_LIMIT)));
        $sayfa = max(1, (int) ($suzgec['sayfa'] ?? 1));
        $offset = ($sayfa - 1) * $limit;

        $sirala = $this->siralama((string) ($suzgec['siralama'] ?? 'skor'), (string) ($suzgec['yon'] ?? 'desc'));

        $pdo = $this->connection->pdo();

        $sayim = $pdo->prepare("SELECT COUNT(*) FROM products p
            LEFT JOIN listings i ON i.product_id = p.id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE {$where}");
        $sayim->execute($params);
        $toplam = (int) $sayim->fetchColumn();

        $statement = $pdo->prepare(
            "SELECT p.id, p.name, p.name_original, p.category_id, p.qty, p.price_yuan,
                    p.main_image, p.video_url, p.list_id, p.hazir,
                    c.name AS kategori_ad,
                    i.id AS ilan_id, i.platform_kod, i.external_id, i.url,
                    i.satici_ad, i.satici_puan, i.satici_yil,
                    i.satis_adedi, i.satis_toplam, i.degerlendirme_puani, i.degerlendirme_adedi,
                    i.moq, i.birim_fiyat, i.skor, i.kume_anahtari, i.yakalandi_at
             FROM products p
             LEFT JOIN listings i ON i.product_id = p.id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE {$where}
             ORDER BY {$sirala}
             LIMIT {$limit} OFFSET {$offset}",
        );
        $statement->execute($params);

        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $statement->fetchAll();

        return [
            'satirlar' => array_map(fn (array $satir): array => $this->satir($satir), $satirlar),
            'toplam' => $toplam,
            'sayfa' => $sayfa,
            'limit' => $limit,
        ];
    }

    /**
     * 同款 KÜMELEMESİ: aynı ürünün farklı satıcı/platformdaki kopyaları.
     *
     * Küme anahtarı OLMAYAN ürün kendi başına bir kümedir — kümesizleri tek bir
     * "diğerleri" torbasına atmak, ilgisiz ürünleri aynı kartta gösterirdi.
     *
     * @param list<array<string, mixed>> $satirlar
     *
     * @return list<array<string, mixed>>
     */
    public function kumele(array $satirlar): array
    {
        $kumeler = [];
        foreach ($satirlar as $satir) {
            $anahtar = is_string($satir['kume_anahtari'] ?? null) && $satir['kume_anahtari'] !== ''
                ? (string) $satir['kume_anahtari']
                : 'tekil:' . $satir['id'];

            $kumeler[$anahtar][] = $satir;
        }

        $out = [];
        foreach ($kumeler as $anahtar => $uyeler) {
            $fiyatlar = array_values(array_filter(array_map(
                static fn (array $u): ?string => is_string($u['birim_fiyat'] ?? null) ? $u['birim_fiyat'] : null,
                $uyeler,
            )));
            $skorlar = array_values(array_filter(array_map(
                static fn (array $u): ?int => is_int($u['skor'] ?? null) ? $u['skor'] : null,
                $uyeler,
            )));

            // Küme temsilcisi EN YÜKSEK SKORLU üyedir: kullanıcı kümeye baktığında
            // "bu üründen en iyi teklif hangisi" sorusunun cevabını görmelidir.
            usort($uyeler, static fn (array $a, array $b): int => ($b['skor'] ?? -1) <=> ($a['skor'] ?? -1));

            // EN UCUZ bcmath ile seçilir (K14): `min()` metin karşılaştırmasına
            // düşebilir ve "9.5" ile "12.0000" arasında yanlış cevap verebilir.
            $enUcuz = null;
            foreach ($fiyatlar as $fiyat) {
                if ($enUcuz === null || bccomp($fiyat, $enUcuz, 4) < 0) {
                    $enUcuz = $fiyat;
                }
            }

            $out[] = [
                'kume_anahtari' => str_starts_with($anahtar, 'tekil:') ? null : $anahtar,
                'kaynak_sayisi' => count($uyeler),
                'en_ucuz' => $enUcuz,
                'en_yuksek_skor' => $skorlar === [] ? null : max($skorlar),
                'temsilci' => $uyeler[0],
                'uyeler' => $uyeler,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $satir
     *
     * @return array<string, mixed>
     */
    private function satir(array $satir): array
    {
        $skor = $satir['skor'] === null ? null : (int) $satir['skor'];
        $kategori = is_string($satir['kategori_ad'] ?? null) ? $satir['kategori_ad'] : null;
        $kapsamDisi = $kategori !== null && str_starts_with($kategori, SkorHesaplayici::KAPSAM_DISI_KOK);

        return [
            'id' => (int) $satir['id'],
            'ad' => (string) $satir['name'],
            'ad_orijinal' => $satir['name_original'] === null ? null : (string) $satir['name_original'],
            'kategori' => $kategori,
            'platform' => $satir['platform_kod'] === null ? null : (string) $satir['platform_kod'],
            'ilan_no' => $satir['external_id'] === null ? null : (string) $satir['external_id'],
            'url' => $satir['url'] === null ? null : (string) $satir['url'],
            'gorsel' => $satir['main_image'] === null ? null : (string) $satir['main_image'],
            'video_var' => is_string($satir['video_url'] ?? null) && $satir['video_url'] !== '',
            'satici' => $satir['satici_ad'] === null ? null : (string) $satir['satici_ad'],
            'satis' => $satir['satis_adedi'] === null ? null : (int) $satir['satis_adedi'],
            'satis_toplam' => $satir['satis_toplam'] === null ? null : (int) $satir['satis_toplam'],
            'puan' => $satir['degerlendirme_puani'] === null ? null : (float) $satir['degerlendirme_puani'],
            'yorum' => $satir['degerlendirme_adedi'] === null ? null : (int) $satir['degerlendirme_adedi'],
            'moq' => $satir['moq'] === null ? null : (int) $satir['moq'],
            'birim_fiyat' => $satir['birim_fiyat'] === null ? null : (string) $satir['birim_fiyat'],
            'skor' => $skor,
            // Bant motordan gelir; panel kendi eşiğini TUTMAZ (tek kaynak).
            'bant' => SkorHesaplayici::bant($skor, $kapsamDisi),
            'kapsam_disi' => $kapsamDisi,
            'kume_anahtari' => $satir['kume_anahtari'] === null ? null : (string) $satir['kume_anahtari'],
            'listede' => $satir['list_id'] !== null,
            'hazir' => (int) ($satir['hazir'] ?? 0) === 1,
            'yakalandi_at' => $satir['yakalandi_at'] === null ? null : (string) $satir['yakalandi_at'],
        ];
    }

    /**
     * @param array<string, mixed> $suzgec
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function kosullar(array $suzgec): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        // ── ARAMA: ham VE normalize alan birlikte ────────────────────────────
        $sorgu = trim((string) ($suzgec['q'] ?? ''));
        if ($sorgu !== '') {
            $normalDesen = AramaNormalizasyonu::desen($sorgu);
            $hamDesen = '%' . $sorgu . '%';

            // Yer tutucular AYRI adlandırılır (native prepare tekrarı HY093 verir).
            $where[] = '(p.arama_normal LIKE :q_normal OR p.arama_metni LIKE :q_ham'
                . ' OR p.name LIKE :q_ad OR p.name_original LIKE :q_orijinal'
                . ' OR i.external_id LIKE :q_ilan OR i.satici_ad LIKE :q_satici)';
            $params['q_normal'] = $normalDesen ?? $hamDesen;
            $params['q_ham'] = $hamDesen;
            $params['q_ad'] = $hamDesen;
            $params['q_orijinal'] = $hamDesen;
            $params['q_ilan'] = $hamDesen;
            $params['q_satici'] = $hamDesen;
        }

        // ── ÇOKLU SEÇİM FİLTRELERİ (aile içinde OR, aileler arası AND) ───────
        $this->cokluKosul($where, $params, 'platform', 'i.platform_kod', $suzgec['platform'] ?? null);
        $this->cokluKosul($where, $params, 'kategori', 'c.name', $suzgec['kategori'] ?? null);

        // Skor bandı: eşikler MOTORDAN gelir, burada yeniden yazılmaz.
        $bantlar = $this->dizi($suzgec['skor_bandi'] ?? null);
        if ($bantlar !== []) {
            $bantKosullari = [];
            foreach ($bantlar as $bant) {
                $bantKosullari[] = match ($bant) {
                    'yuksek' => 'i.skor >= ' . SkorHesaplayici::BANT_YUKSEK_ESIK,
                    'orta' => '(i.skor >= ' . SkorHesaplayici::BANT_ORTA_ESIK
                        . ' AND i.skor < ' . SkorHesaplayici::BANT_YUKSEK_ESIK . ')',
                    'dusuk' => '(i.skor IS NOT NULL AND i.skor < ' . SkorHesaplayici::BANT_ORTA_ESIK . ')',
                    'gizli' => 'i.skor IS NULL',
                    default => null,
                };
            }
            $bantKosullari = array_values(array_filter($bantKosullari));
            if ($bantKosullari !== []) {
                $where[] = '(' . implode(' OR ', $bantKosullari) . ')';
            }
        }

        // ── SAYISAL ARALIKLAR ────────────────────────────────────────────────
        $this->aralik($where, $params, 'i.birim_fiyat', 'fiyat', $suzgec);
        $this->aralik($where, $params, 'i.satis_adedi', 'satis', $suzgec);
        $this->aralik($where, $params, 'i.degerlendirme_puani', 'puan', $suzgec);
        $this->aralik($where, $params, 'i.moq', 'moq', $suzgec);

        // ── EVET/HAYIR FİLTRELERİ ────────────────────────────────────────────
        if (($suzgec['video'] ?? null) === true) {
            $where[] = "(p.video_url IS NOT NULL AND p.video_url <> '')";
        }
        if (array_key_exists('listede', $suzgec) && $suzgec['listede'] !== null) {
            $where[] = ((bool) $suzgec['listede']) ? 'p.list_id IS NOT NULL' : 'p.list_id IS NULL';
        }

        // Ürün kimliği filtresi (karşılaştırma matrisi bunu kullanır).
        $idler = array_values(array_filter(array_map('intval', $this->dizi($suzgec['id'] ?? null))));
        if ($idler !== []) {
            $parcalar = [];
            foreach ($idler as $i => $id) {
                $ad = 'urun_id_' . $i;
                $parcalar[] = ':' . $ad;
                $params[$ad] = $id;
            }
            $where[] = 'p.id IN (' . implode(', ', $parcalar) . ')';
        }

        // Kimlik filtresi: E2E senaryoları görünür kümeyi ilan numarasıyla daraltır.
        $ilanNolari = $this->dizi($suzgec['ilan_no'] ?? null);
        if ($ilanNolari !== []) {
            $parcalar = [];
            foreach ($ilanNolari as $i => $no) {
                $ad = 'ilan_no_' . $i;
                $parcalar[] = ':' . $ad;
                $params[$ad] = $no;
            }
            $where[] = 'i.external_id IN (' . implode(', ', $parcalar) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     */
    private function cokluKosul(array &$where, array &$params, string $ad, string $kolon, mixed $deger): void
    {
        $degerler = $this->dizi($deger);
        if ($degerler === []) {
            return;
        }

        $parcalar = [];
        foreach ($degerler as $i => $tek) {
            $anahtar = $ad . '_' . $i;
            $parcalar[] = ':' . $anahtar;
            $params[$anahtar] = $tek;
        }

        $where[] = $kolon . ' IN (' . implode(', ', $parcalar) . ')';
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $suzgec
     */
    private function aralik(array &$where, array &$params, string $kolon, string $ad, array $suzgec): void
    {
        foreach (['min' => '>=', 'max' => '<='] as $uc => $operator) {
            $anahtar = $ad . '_' . $uc;
            // `isset` zaten null'ı eler; ayrıca boş metin de filtre sayılmaz.
            if (!isset($suzgec[$anahtar]) || $suzgec[$anahtar] === '') {
                continue;
            }
            $where[] = sprintf('%s %s :%s', $kolon, $operator, $anahtar);
            $params[$anahtar] = $suzgec[$anahtar];
        }
    }

    /** @return list<string> */
    private function dizi(mixed $deger): array
    {
        if (is_string($deger) && $deger !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $deger))));
        }
        if (is_array($deger)) {
            return array_values(array_filter(array_map('strval', $deger)));
        }

        return [];
    }

    /**
     * Sıralama — beyaz liste. Kullanıcıdan gelen alan adı doğrudan SQL'e GİRMEZ.
     *
     * NULL SKOR SONA: "gizli" ürünler listenin başında durursa, skor sıralaması
     * en değerli soruyu (en iyi hangisi?) yanıtlamaz olur.
     */
    private function siralama(string $alan, string $yon): string
    {
        $yon = strtolower($yon) === 'asc' ? 'ASC' : 'DESC';

        return match ($alan) {
            'fiyat' => 'i.birim_fiyat IS NULL, i.birim_fiyat ' . ($yon === 'DESC' ? 'DESC' : 'ASC') . ', p.id',
            'satis' => 'i.satis_adedi IS NULL, i.satis_adedi ' . $yon . ', p.id',
            'puan' => 'i.degerlendirme_puani IS NULL, i.degerlendirme_puani ' . $yon . ', p.id',
            'ivme' => 'i.satis_toplam IS NULL, (CAST(i.satis_adedi AS REAL) / NULLIF(i.satis_toplam, 0)) '
                . $yon . ', p.id',
            'tarih' => 'i.yakalandi_at IS NULL, i.yakalandi_at ' . $yon . ', p.id',
            'ad' => 'p.name ' . $yon . ', p.id',
            default => 'i.skor IS NULL, i.skor ' . $yon . ', p.id',
        };
    }
}
