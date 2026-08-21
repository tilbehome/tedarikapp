<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\ListPresenter;
use App\Services\ProductDetails;
use App\Services\Translation\ValueSet;

/**
 * Export anlık görüntüsü (K25/K50 — İE#10 Blok 1).
 *
 * Çıktı, üretildiği ANDAKİ liste halini temsil eder: liste + ürünler + kurlar +
 * toplamlar tek JSON'da donar. Para kuralları K4/K48: tüm değerler ListPresenter
 * üzerinden bcmath ile HESAPLANMIŞ string'lerdir; TL değeri DB'de tutulmaz —
 * snapshot "o anki kurla hesaplanmış görünümü" saklar (kilitli listede kilitli kur,
 * taslakta güncel kur). Render'cılar (csv/xlsx/pdf) yalnız bu yapıyı okur; DB'ye
 * bir daha dönülmez — aynı kaydın indirmesi her zaman AYNI içeriği üretir.
 */
final class ExportSnapshot
{
    /** 2: İE#13 Blok F — şablon v2 alanları (platform/ilan no/varyasyon/MOQ/kâr/kopya türü). */
    public const VERSION = 2;

    /**
     * @param ValueSet|null $values İE#14 A3/A4 — varyasyon ve öznitelik DEĞERLERİ
     *                              sözlükten geçer, kategori/detay kırıntı yolundan
     *                              türer. Verilmezse ham değerler donar (eski davranış).
     */
    public function __construct(
        private readonly ListPresenter $presenter,
        private readonly ?ValueSet $values = null,
    ) {
    }

    /**
     * @param array<string, mixed> $listRow ham liste satırı
     * @param list<array<string, mixed>> $productRows ham ürün satırları (sort_no sıralı)
     * @param array<int, string> $categoryNames id → ad (kategori adı snapshot'ta DONAR —
     *                                          sonradan yeniden adlandırma eski çıktıyı değiştirmez)
     * @param array{copy?: string, statuses?: list<string>, lang?: string, document_code?: string|null, revision_label?: string, share_url?: string|null, document_header?: array{company: string|null, web: string|null, email: string|null, prepared_by: string|null}} $options
     *
     * @return array<string, mixed>
     */
    public function build(
        array $listRow,
        array $productRows,
        array $categoryNames,
        \DateTimeImmutable $now,
        array $options = [],
    ): array {
        $list = $this->presenter->list($listRow);
        $products = $this->presenter->productsOf($productRows, $listRow);

        return [
            'snapshot_version' => self::VERSION,
            'generated_at' => $now->format(DATE_ATOM),
            // İE#13 F2/F5/F6/F7: çıktının KİMLİĞİ — hangi filtreyle, hangi kopya türüyle,
            // kaçıncı revizyonla üretildi. Geçmişten indirme aynı kimlikle yeniden üretir.
            'options' => [
                'copy' => $options['copy'] ?? 'firma',
                'statuses' => $options['statuses'] ?? [],
                'document_code' => $options['document_code'] ?? null,
                // İE#15 A3: çıktı dili. Panel çıktısı TR'dir; /p/ üzerinden alınan
                // çıktıda firma dili seçebilir. 'zh' seçilirse ürün adı ORİJİNAL
                // başlıktır (Çinli muhatap kendi başlığını görsün — çeviri değil).
                'lang' => self::dil($options),
                'revision_label' => $options['revision_label'] ?? 'A',
                'share_url' => $options['share_url'] ?? null,
            ],
            // İE#13 F1: belge antedi çıktı ANINDA dondurulur — ayar sonradan değişse
            // geçmişten indirilen belge yine o günkü anteti taşır (K50 snapshot ilkesi).
            'document_header' => $options['document_header'] ?? ['company' => null, 'web' => null, 'email' => null, 'prepared_by' => null],
            'list' => [
                'id' => $list['id'],
                'name' => $list['name'],
                'share_token_prefix' => $list['share_token_prefix'],
                'period' => $list['period'],
                'supplier_name' => $list['supplier_name'],
                'status' => $list['status'],
                'revision' => $list['revision'],
                'yuan_rate' => $list['yuan_rate'],
                'usd_rate' => $list['usd_rate'],
                'rate_locked_at' => $list['rate_locked_at'],
            ],
            'totals' => $list['totals'],
            'products' => array_map(fn (array $product, int $index): array => [
                // İE#10.5 ek (b): çıktıdaki NO 1'den ARDIŞIK — silinen ürünün numarası
                // atlanmaz (sort_no boşluklu kalabilir; firma listesi 1..N okur).
                'no' => $index + 1,
                'sort_no' => $product['sort_no'],
                // İE#14 A4: kategori panelden ya da yakalamanın kırıntı yolundan gelir;
                // yoksa null DONAR ve belgede hücre BOŞ basılır — "Kategorisiz" yazılmaz.
                'category' => ProductDetails::kategori($product, $categoryNames, $this->values),
                'name' => self::ad($product, self::dil($options)),
                'name_original' => $product['name_original'],
                // İE#14 A4: detay yoksa en dolu 3-4 öznitelikten türetilir; o da yoksa null.
                'detail' => ProductDetails::detay($product, $this->values),
                'url' => $product['url'],
                'main_image' => $product['main_image'],
                'qty' => $product['qty'],
                'price_yuan' => $product['price_yuan'],
                'price_yuan_tl' => $product['price_yuan_tl'],
                'price_ddp_usd' => $product['price_ddp_usd'],
                'price_ddp_tl' => $product['price_ddp_tl'],
                'line_total_yuan' => $product['line_total_yuan'],
                'line_total_yuan_tl' => $product['line_total_yuan_tl'],
                'status' => $product['status'],
                // ── İE#13 Blok F: şablon v2 alanları ──
                // PLATFORM BAĞIMSIZ: kaynak rozeti + ilan no + köprü her platformda
                // kendi değerini taşır. SATICI BİLGİSİ BİLEREK TAŞINMAZ (şartname).
                'platform' => $product['platform'],
                'external_id' => $product['external_id'],
                'variant' => $this->variant($product),
                'moq' => self::moq($product),
                'units_per_carton' => $product['units_per_carton'],
                'note' => $product['note'],
                'video_url' => $product['video_url'],
                'price_target_try' => $product['price_target_try'],
                'unit_profit_try' => $product['unit_profit_try'],
                'line_profit_try' => $product['line_profit_try'],
            ], $products, array_keys($products)),
        ];
    }

    /**
     * Geçerli çıktı dili; tanınmayan değer TR'ye düşer.
     *
     * @param array<string, mixed> $options
     */
    private static function dil(array $options): string
    {
        $dil = is_string($options['lang'] ?? null) ? strtolower((string) $options['lang']) : 'tr';

        return in_array($dil, ['tr', 'zh', 'en'], true) ? $dil : 'tr';
    }

    /**
     * İE#15 A3 — ÇIKTI DİLİ ürün adını belirler: `zh` istendiğinde ORİJİNAL başlık
     * basılır (varsa). Bu bir ÇEVİRİ DEĞİLDİR; kaynaktaki başlığın kendisidir —
     * Çinli tedarikçi ilanı kendi metniyle tanır. Orijinal yoksa mevcut ad kalır.
     *
     * @param array<string, mixed> $product
     */
    private static function ad(array $product, string $dil): string
    {
        if ($dil === 'zh') {
            $orijinal = $product['name_original'] ?? null;
            if (is_string($orijinal) && trim($orijinal) !== '') {
                return trim($orijinal);
            }
        }

        return (string) $product['name'];
    }

    /**
     * Varyasyon özeti: kullanıcı seçimi varsa o, yoksa matristen türetilir.
     *
     * İE#14 A3: değerler sözlükten geçer (灰色 → Gri) ve belge özeti İLK 3 +
     * "… (N seçenek)" biçimindedir — uzun varyasyon listesi hücreyi taşırmaz.
     *
     * @param array<string, mixed> $product
     */
    private function variant(array $product): ?string
    {
        $degerler = $this->varyasyonDegerleri($product);
        if ($degerler === []) {
            return null;
        }

        $ozet = $this->values !== null
            ? $this->values->ozet($degerler)
            : self::ozetle($degerler);

        return $ozet;
    }

    /**
     * @param list<string> $degerler
     */
    private static function ozetle(array $degerler): ?string
    {
        if ($degerler === []) {
            return null;
        }
        $ilk = implode(' · ', array_slice($degerler, 0, ValueSet::LIMIT));

        return count($degerler) > ValueSet::LIMIT
            ? $ilk . ' … (' . count($degerler) . ' seçenek)'
            : $ilk;
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return list<string>
     */
    private function varyasyonDegerleri(array $product): array
    {
        $secim = $product['sku_selection'] ?? null;
        if (is_array($secim) && $secim !== []) {
            $parcalar = [];
            foreach ($secim as $anahtar => $deger) {
                if (is_scalar($deger)) {
                    $parcalar[] = is_string($anahtar) && !is_numeric($anahtar)
                        ? $anahtar . ': ' . (string) $deger
                        : (string) $deger;
                }
            }
            if ($parcalar !== []) {
                return $parcalar;
            }
        }

        $matris = $product['sku_matrix'] ?? null;
        if (!is_array($matris) || $matris === []) {
            return [];
        }

        $adlar = [];
        foreach ($matris as $entry) {
            $props = is_array($entry) ? ($entry['props'] ?? null) : null;
            if (!is_array($props)) {
                continue;
            }
            $parcalar = [];
            foreach ($props as $deger) {
                if (is_scalar($deger)) {
                    $parcalar[] = (string) $deger;
                }
            }
            if ($parcalar !== []) {
                $adlar[] = implode(' / ', $parcalar);
            }
        }
        if ($adlar === []) {
            return [count($matris) . ' seçenek'];
        }

        return $adlar;
    }

    /**
     * MOQ (asgari sipariş): yakalamanın RAW bloğunda `min_order` varsa oradan gelir.
     * Ayrı bir kolon UYDURULMAZ — veri yoksa null döner ve çıktıda "—" basılır.
     *
     * @param array<string, mixed> $product
     */
    private static function moq(array $product): ?string
    {
        $raw = $product['raw_attributes'] ?? null;
        if (is_string($raw)) {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return null;
        }

        $deger = $raw['min_order'] ?? ($raw['raw']['min_order'] ?? null);
        if (is_array($deger)) {
            $deger = $deger['value'] ?? $deger['amount'] ?? null;
        }

        return is_scalar($deger) && (string) $deger !== '' ? (string) $deger : null;
    }
}
