<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\ListPresenter;

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
    public const VERSION = 1;

    public function __construct(private readonly ListPresenter $presenter)
    {
    }

    /**
     * @param array<string, mixed> $listRow ham liste satırı
     * @param list<array<string, mixed>> $productRows ham ürün satırları (sort_no sıralı)
     * @param array<int, string> $categoryNames id → ad (kategori adı snapshot'ta DONAR —
     *                                          sonradan yeniden adlandırma eski çıktıyı değiştirmez)
     *
     * @return array<string, mixed>
     */
    public function build(array $listRow, array $productRows, array $categoryNames, \DateTimeImmutable $now): array
    {
        $list = $this->presenter->list($listRow);
        $products = $this->presenter->productsOf($productRows, $listRow);

        return [
            'snapshot_version' => self::VERSION,
            'generated_at' => $now->format(DATE_ATOM),
            'list' => [
                'id' => $list['id'],
                'name' => $list['name'],
                'period' => $list['period'],
                'supplier_name' => $list['supplier_name'],
                'status' => $list['status'],
                'revision' => $list['revision'],
                'yuan_rate' => $list['yuan_rate'],
                'usd_rate' => $list['usd_rate'],
                'rate_locked_at' => $list['rate_locked_at'],
            ],
            'totals' => $list['totals'],
            'products' => array_map(static fn (array $product): array => [
                'sort_no' => $product['sort_no'],
                'category' => $product['category_id'] !== null
                    ? ($categoryNames[(int) $product['category_id']] ?? 'Kategorisiz')
                    : 'Kategorisiz',
                'name' => $product['name'],
                'name_original' => $product['name_original'],
                'detail' => $product['detail'],
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
            ], $products),
        ];
    }
}
