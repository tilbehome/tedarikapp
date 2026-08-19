<?php

declare(strict_types=1);

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel çıktısı (İE#10 Blok 2) — biçim referansı: repo kökündeki ornek-tedarik-listesi.xlsx.
 *
 * BİREBİR kolon düzeni (örnekten okunan gerçek): veri her İKİ kolonda bir (B,D,F,H,J,L,N,
 * P,R,T,V — aralar dar boşluk kolonları), başlık satırı 6 (P6:R6 "1688 FİYATI" ve T6:V6
 * "DDP FİYATI" birleşik), alt başlık satırı 8 (YUAN/TL/DOLAR/TL), veri satır 9'dan başlar,
 * sonda TOPLAM satırı (K15). Görsel kolonu D: /media arşivindeki dosya varsa GÖMÜLÜR.
 *
 * Kütüphane: phpoffice/phpspreadsheet ^2.4 (K19 onaylı listede; composer "php": "^8.1"
 * beyanlı — 8.1 uyumu CI php81-uyum vendor lint'iyle ayrıca kanıtlanır). Bellek dostu
 * kullanım: tek sayfa, stil aralıklara toptan uygulanır, çıktı php://temp akışına yazılır.
 */
final class XlsxRenderer implements ExportRenderer
{
    /** Örnek dosyadan okunan kolon genişlikleri. */
    private const COLUMN_WIDTHS = [
        'A' => 2.9, 'B' => 11.3, 'C' => 0.7, 'D' => 34.4, 'E' => 0.7, 'F' => 18.9, 'G' => 0.7,
        'H' => 30.9, 'I' => 0.7, 'J' => 41.0, 'K' => 0.7, 'L' => 23.9, 'M' => 0.7, 'N' => 22.7,
        'O' => 0.7, 'P' => 12.3, 'Q' => 0.7, 'R' => 12.3, 'S' => 0.7, 'T' => 12.3, 'U' => 0.7, 'V' => 12.3,
    ];

    private const DATA_START_ROW = 9;

    public function __construct(private readonly string $basePath)
    {
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function mime(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function render(array $snapshot): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sayfa1');

        foreach (self::COLUMN_WIDTHS as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $list = $snapshot['list'];

        // Başlık (örnekte B2:V4 birleşik, 18 punto kalın).
        $sheet->mergeCells('B2:V4');
        $period = is_string($list['period'] ?? null) && $list['period'] !== ''
            ? ' - ' . mb_strtoupper((string) $list['period'], 'UTF-8')
            : '';
        $sheet->setCellValue('B2', 'ÇİNDEN DDP SİPARİŞ VERİLECEK ÜRÜNLER LİSTESİ' . $period);
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('B2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Başlık satırı 6 + birleşik fiyat blokları; alt başlık satırı 8.
        $headers = ['B' => 'NO', 'D' => 'ÜRÜN GÖRSELİ', 'F' => 'KATEGORİ', 'H' => 'ÜRÜN ADI', 'J' => 'ÜRÜN DETAY', 'L' => 'ÜRÜN LİNKİ', 'N' => 'MİKTAR', 'P' => '1688 FİYATI', 'T' => 'DDP FİYATI'];
        foreach ($headers as $column => $label) {
            $sheet->setCellValue($column . '6', $label);
        }
        $sheet->mergeCells('P6:R6');
        $sheet->mergeCells('T6:V6');
        foreach (['P8' => 'YUAN', 'R8' => 'TL', 'T8' => 'DOLAR', 'V8' => 'TL'] as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }
        $sheet->getStyle('B6:V8')->getFont()->setBold(true);
        $sheet->getStyle('B6:V8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Veri satırları.
        $row = self::DATA_START_ROW;
        foreach ($snapshot['products'] as $product) {
            $sheet->setCellValueExplicit('B' . $row, (string) $product['sort_no'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F' . $row, (string) $product['category'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H' . $row, (string) $product['name'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('J' . $row, (string) ($product['detail'] ?? ''), DataType::TYPE_STRING);
            if (is_string($product['url'] ?? null) && $product['url'] !== '') {
                $sheet->setCellValueExplicit('L' . $row, (string) $product['url'], DataType::TYPE_STRING);
                $sheet->getCell('L' . $row)->getHyperlink()->setUrl((string) $product['url']);
            }
            $sheet->setCellValue('N' . $row, (int) $product['qty']);
            // Para: snapshot string'i sayıya çevrilip ¥/₺/$ biçimiyle GÖSTERİLİR — değer
            // bcmath ile hesaplanmıştı, Excel yalnız biçimler (K14: aritmetik yapılmaz).
            $this->money($sheet, 'P' . $row, (string) $product['price_yuan'], '¥');
            $this->money($sheet, 'R' . $row, (string) $product['price_yuan_tl'], '₺');
            $this->money($sheet, 'T' . $row, (string) $product['price_ddp_usd'], '$');
            $this->money($sheet, 'V' . $row, (string) $product['price_ddp_tl'], '₺');

            $this->embedImage($sheet, 'D' . $row, $product['main_image'] ?? null);
            $sheet->getRowDimension($row)->setRowHeight(79.5);
            $sheet->getStyle('B' . $row . ':V' . $row)->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $row++;
        }

        // TOPLAM satırı (K15): satır toplamlarının toplamı — paneldeki TOPLAM ile aynı değerler.
        $totals = $snapshot['totals'];
        $sheet->setCellValue('B' . $row, 'TOPLAM');
        $sheet->setCellValue('N' . $row, (int) $totals['qty']);
        $this->money($sheet, 'P' . $row, (string) $totals['yuan'], '¥');
        $this->money($sheet, 'R' . $row, (string) $totals['yuan_tl'], '₺');
        $this->money($sheet, 'T' . $row, (string) $totals['ddp_usd'], '$');
        $this->money($sheet, 'V' . $row, (string) $totals['ddp_tl'], '₺');
        $sheet->getStyle('B' . $row . ':V' . $row)->getFont()->setBold(true);
        $sheet->getStyle('B' . $row . ':V' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        // Tablo çerçevesi.
        $sheet->getStyle('B6:V' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new ExportException('Excel üretilemedi.');
        }
        (new Xlsx($spreadsheet))->save($stream);
        rewind($stream);
        $bytes = (string) stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        return $bytes;
    }

    /** Para hücresi: string değer float'a yalnız GÖSTERİM için çevrilir; biçim ¥/₺/$ + binlik ayraç. */
    private function money(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell, string $amount, string $symbol): void
    {
        $sheet->setCellValue($cell, (float) $amount);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('"' . $symbol . '"#,##0.00');
    }

    /** /media arşivindeki görseli hücreye gömer; dosya yoksa hücre boş kalır (kırık ikon basılmaz). */
    private function embedImage(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell, mixed $mainImage): void
    {
        if (!is_string($mainImage) || !str_starts_with($mainImage, '/media/')) {
            return;
        }
        $path = $this->basePath . '/public' . $mainImage;
        if (!is_file($path)) {
            return;
        }

        $drawing = new Drawing();
        $drawing->setPath($path);
        $drawing->setCoordinates($cell);
        $drawing->setHeight(96);
        $drawing->setOffsetX(6);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);
    }
}
