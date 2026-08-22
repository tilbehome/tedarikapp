<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * CSV çıktısı (İE#10 Blok 1 — motorun ilk biçimi; docs/10 format=csv).
 *
 * Türkçe Excel uyumu: UTF-8 BOM + noktalı virgül ayracı. Ondalıklar snapshot'taki
 * string değerlerdir (nokta ayraçlı) — CSV veri taşır, biçimleme Excel çıktısının işidir.
 *
 * FORMÜL ENJEKSİYONU (İE#19 G5): METİN hücreleri `SafeCell::text()` üzerinden yazılır.
 * Sayı hücreleri BİLEREK ham geçer — onlar snapshot'ta doğrulanmış ondalıklardır ve
 * öneklemek negatif değerleri bozardı.
 */
final class CsvRenderer implements ExportRenderer
{
    public function extension(): string
    {
        return 'csv';
    }

    public function mime(): string
    {
        return 'text/csv; charset=utf-8';
    }

    public function render(array $snapshot): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new ExportException('CSV üretilemedi.');
        }

        $write = static function (array $row) use ($handle): void {
            fputcsv($handle, $row, ';', '"', '\\');
        };

        $metin = static fn (mixed $deger): string => SafeCell::text($deger);

        $list = $snapshot['list'];
        $write(['Liste', $metin($list['name']), 'Dönem', $metin($list['period'] ?? '')]);
        $write(['Kur', '¥ ' . $list['yuan_rate'] . ' / $ ' . $list['usd_rate'], 'Üretim', (string) $snapshot['generated_at']]);
        $write([]);
        $write(['NO', 'KATEGORİ', 'ÜRÜN ADI', 'ÜRÜN DETAY', 'ÜRÜN LİNKİ', 'MİKTAR', 'YUAN', 'YAKLAŞIK ÜRÜN BEDELİ (₺)', 'DOLAR (DDP)', 'TL (DDP)']);

        foreach ($snapshot['products'] as $product) {
            $write([
                (string) ($product['no'] ?? $product['sort_no']),
                $metin($product['category'] ?? ''),
                $metin($product['name']),
                $metin($product['detail'] ?? ''),
                $metin($product['url'] ?? ''),
                (string) $product['qty'],
                (string) $product['price_yuan'],
                (string) $product['price_yuan_tl'],
                // İE#17 G3: girilmemiş fiyat BOŞ hücredir; "0.00" yazmak veriyi
                // tüketen tarafta (Excel'e alan firma) yanlış hesaba yol açar.
                TemplateV2::girilmis($product['price_ddp_usd'] ?? null) ? (string) $product['price_ddp_usd'] : '',
                TemplateV2::girilmis($product['price_ddp_tl'] ?? null) ? (string) $product['price_ddp_tl'] : '',
            ]);
        }

        $totals = $snapshot['totals'];
        $write(['TOPLAM', '', '', '', '', (string) $totals['qty'], (string) $totals['yuan'], (string) $totals['yuan_tl'], (string) $totals['ddp_usd'], (string) $totals['ddp_tl']]);

        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);

        return "\u{FEFF}" . $body;
    }
}
