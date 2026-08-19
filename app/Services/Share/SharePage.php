<?php

declare(strict_types=1);

namespace App\Services\Share;

/**
 * Paylaşım sayfası HTML'i (İE#10 Blok 4 — K31 iki katmanlı detay, docs/09 P1).
 *
 * DIŞA AÇIK TEK YÜZEY: her değer istisnasız escape edilir (XSS — CLAUDE.md §5).
 * Sayfa CANLI listeyi gösterir (export snapshot'ının aksine): firma güncel durumu
 * görür. JS YOKTUR; genişleyen detay <details> ile, stil ayrı /p/style.css ucundan
 * gelir (CSP `default-src 'self'` — satır içi stil/script kullanılamaz).
 */
final class SharePage
{
    private const STATUS_LABELS = [
        'to_order' => 'Verilecek',
        'ordered' => 'Verildi',
        'in_transit' => 'Yolda',
        'received' => 'Geldi',
        'cancelled' => 'İptal',
    ];

    /**
     * @param array<string, mixed> $list ListPresenter::list çıktısı
     * @param list<array<string, mixed>> $products ListPresenter::productsOf çıktısı
     * @param array<int, string> $categoryNames
     */
    public function render(array $list, array $products, array $categoryNames, string $canonicalUrl = ''): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $cards = '';
        foreach ($products as $product) {
            if ((string) $product['status'] === 'cancelled') {
                continue; // iptal edilen ürün firmaya gösterilmez (toplamlara da girmiyor — K24)
            }
            $cards .= $this->card($product, $categoryNames, $e);
        }

        // og:image mutlak adres ister (önizleme botları göreliyi çözmez).
        $origin = $canonicalUrl !== '' ? (string) preg_replace('#(^https?://[^/]+).*#', '$1', $canonicalUrl) : '';

        $totals = $list['totals'];
        $period = is_string($list['period'] ?? null) && $list['period'] !== '' ? ' · ' . $e($list['period']) : '';

        return '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="Tedarikapp (Ürün Tedarik Asistanı) ile paylaşılan sipariş listesi — salt okunur görünüm.">
<title>' . $e($list['name']) . ' — Tedarikapp</title>
<link rel="icon" type="image/svg+xml" href="/panel/favicon.svg">
<link rel="apple-touch-icon" sizes="180x180" href="/panel/apple-touch-icon.png">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Tedarikapp">
<meta property="og:locale" content="tr_TR">
<meta property="og:title" content="' . $e($list['name']) . ' — Tedarikapp">
<meta property="og:description" content="Paylaşılan sipariş listesi — güncel durum ve toplamlar.">' . ($canonicalUrl !== '' ? '
<meta property="og:url" content="' . $e($canonicalUrl) . '">' : '') . '
<meta property="og:image" content="' . $e($origin) . '/panel/og-image.png">
<link rel="stylesheet" href="/p-style.css">
</head>
<body>
<main>
    <header class="baslik">
        <h1>' . $e($list['name']) . '</h1>
        <p class="kunye">' . $e(mb_strtoupper('Çinden DDP sipariş listesi', 'UTF-8')) . $period
            . ' · Kur: ¥ ' . $e($list['yuan_rate']) . ' / $ ' . $e($list['usd_rate']) . '</p>
    </header>

    <section class="toplam-kart">
        <div><span>Ürün</span><strong>' . $e($list['product_count']) . '</strong></div>
        <div><span>Toplam adet</span><strong>' . $e($totals['qty']) . '</strong></div>
        <div><span>Toplam ¥</span><strong>¥' . $e($totals['yuan']) . '</strong></div>
        <div><span>Toplam ₺</span><strong>₺' . $e($totals['yuan_tl']) . '</strong></div>
    </section>

    <section class="urunler">' . $cards . '</section>

    <footer class="alt">Bu sayfa tedarikapp ile paylaşıldı · liste güncel durumu yansıtır</footer>
</main>
</body>
</html>';
    }

    /**
     * @param array<string, mixed> $product
     * @param array<int, string> $categoryNames
     * @param callable(mixed): string $e
     */
    private function card(array $product, array $categoryNames, callable $e): string
    {
        $statusKey = (string) $product['status'];
        $status = self::STATUS_LABELS[$statusKey] ?? $statusKey;

        $image = '';
        if (is_string($product['main_image'] ?? null) && $product['main_image'] !== '') {
            $image = '<img src="' . $e($product['main_image']) . '" alt="" loading="lazy">';
        }

        $category = 'Kategorisiz';
        if ($product['category_id'] !== null) {
            $category = $categoryNames[(int) $product['category_id']] ?? 'Kategorisiz';
        }

        // ── İkinci katman: galeri + video + varyasyon + Çince başlık (K31) ──
        $detailParts = '';
        if (is_string($product['name_original'] ?? null) && $product['name_original'] !== '') {
            $detailParts .= '<p class="zh">' . $e($product['name_original']) . '</p>';
        }
        if (is_string($product['detail'] ?? null) && $product['detail'] !== '') {
            $detailParts .= '<p>' . nl2br($e($product['detail'])) . '</p>';
        }

        $gallery = '';
        foreach (is_array($product['images'] ?? null) ? $product['images'] : [] as $extra) {
            if (is_array($extra) && is_string($extra['url'] ?? null) && $extra['url'] !== '') {
                $gallery .= '<img src="' . $e($extra['url']) . '" alt="" loading="lazy">';
            }
        }
        if ($gallery !== '') {
            $detailParts .= '<div class="galeri">' . $gallery . '</div>';
        }

        if (is_string($product['video_url'] ?? null) && $product['video_url'] !== '') {
            $detailParts .= '<video controls preload="none" src="' . $e($product['video_url']) . '"></video>';
        }

        $sku = $this->skuTable($product['sku_matrix'] ?? null, $product['sku_selection'] ?? null, $e);
        if ($sku !== '') {
            $detailParts .= $sku;
        }

        $expander = $detailParts === '' ? '' :
            '<details><summary>Detayları göster</summary><div class="detay">' . $detailParts . '</div></details>';

        return '<article class="kart">
            <div class="gorsel">' . ($image !== '' ? $image : '<span class="yok">görsel yok</span>') . '</div>
            <div class="bilgi">
                <h2>' . $e($product['name']) . '</h2>
                <p class="meta">' . $e($category) . ' · <span class="rozet rozet-' . $e($statusKey) . '">' . $e($status) . '</span></p>
                <dl class="fiyat">
                    <div><dt>Adet</dt><dd>' . $e($product['qty']) . '</dd></div>
                    <div><dt>Birim</dt><dd>¥' . $e($product['price_yuan']) . ' · ₺' . $e($product['price_yuan_tl']) . '</dd></div>
                    <div><dt>Satır</dt><dd>¥' . $e($product['line_total_yuan']) . ' · ₺' . $e($product['line_total_yuan_tl']) . '</dd></div>
                </dl>
                ' . $expander . '
            </div>
        </article>';
    }

    /**
     * Varyasyon dökümü — sku_matrix serbest JSON'dur (eklenti şeması docs/04 §2c);
     * anahtar-değer düzleştirilerek tablo yapılır, her hücre escape edilir.
     *
     * @param callable(mixed): string $e
     */
    private function skuTable(mixed $matrix, mixed $selection, callable $e): string
    {
        $rows = '';
        if (is_array($selection) && $selection !== []) {
            foreach ($selection as $key => $value) {
                if (is_scalar($value)) {
                    $rows .= '<tr><th>' . $e(is_string($key) ? $key : 'Seçim') . '</th><td>' . $e($value) . '</td></tr>';
                }
            }
        }
        if (is_array($matrix)) {
            foreach ($matrix as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $label = [];
                $qtyOrPrice = [];
                foreach ($entry as $key => $value) {
                    if (!is_scalar($value)) {
                        continue;
                    }
                    if (in_array($key, ['qty', 'adet', 'price', 'fiyat', 'price_yuan'], true)) {
                        $qtyOrPrice[] = $e($key) . ': ' . $e($value);
                    } else {
                        $label[] = $e($value);
                    }
                }
                if ($label !== [] || $qtyOrPrice !== []) {
                    $rows .= '<tr><th>' . implode(' / ', $label) . '</th><td>' . implode(' · ', $qtyOrPrice) . '</td></tr>';
                }
            }
        }

        return $rows === '' ? '' : '<table class="sku"><caption>Varyasyonlar</caption>' . $rows . '</table>';
    }
}
