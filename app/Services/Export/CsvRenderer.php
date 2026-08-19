<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * CSV çıktısı (İE#10 Blok 1 — motorun ilk biçimi; docs/10 format=csv).
 *
 * Türkçe Excel uyumu: UTF-8 BOM + noktalı virgül ayracı. Ondalıklar snapshot'taki
 * string değerlerdir (nokta ayraçlı) — CSV veri taşır, biçimleme Excel çıktısının işidir.
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

        $list = $snapshot['list'];
        $write(['Liste', (string) $list['name'], 'Dönem', (string) ($list['period'] ?? '')]);
        $write(['Kur', '¥ ' . $list['yuan_rate'] . ' / $ ' . $list['usd_rate'], 'Üretim', (string) $snapshot['generated_at']]);
        $write([]);
        $write(['NO', 'KATEGORİ', 'ÜRÜN ADI', 'ÜRÜN DETAY', 'ÜRÜN LİNKİ', 'MİKTAR', 'YUAN', 'TL', 'DOLAR (DDP)', 'TL (DDP)']);

        foreach ($snapshot['products'] as $product) {
            $write([
                (string) $product['sort_no'],
                (string) $product['category'],
                (string) $product['name'],
                (string) ($product['detail'] ?? ''),
                (string) ($product['url'] ?? ''),
                (string) $product['qty'],
                (string) $product['price_yuan'],
                (string) $product['price_yuan_tl'],
                (string) $product['price_ddp_usd'],
                (string) $product['price_ddp_tl'],
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
