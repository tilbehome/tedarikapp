<?php

declare(strict_types=1);

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel çıktısı — ŞABLON v2 (İE#13 F1; referans: docs/sablon/sablon-v2-rev7.xlsx).
 *
 * Düzen: kimlik bandı (logo · başlık · antet satırı; sağda BELGE KODU/OLUŞTURULMA/
 * KİLİTLİ KUR) → altın çizgi → KPI şeridi (formüllü) → ÜÇ SATIR başlık (TR / 中文 /
 * EN) → veri satırları (zebra + hair dikey ayraç, gömülü görsel, iki satırlı durum
 * rozeti, köprülü ürün adı + altında Çince orijinal) → GENEL TOPLAM bandı (yalnız
 * miktar + "parasal toplamlar kartlarda" notu) → altın kapanış → şartlar/imza → alt bilgi.
 *
 * SADE SÜTUN SETİ: koli içi/koli, ilan no, MOQ ve satır-toplam sütunları BASILMAZ
 * (veride dururlar). PLATFORM BAĞIMSIZ: Kaynak sütunu ürünün platformunu yazar, ad
 * kendi ilanına köprülüdür. ÜRÜN SATICISI BASILMAZ — antetteki "Firma" ALICI firmadır.
 *
 * F5 `copy=ic`: üç ek sütun (hedef satış ₺ / birim kâr ₺ / toplam kâr ₺) — firma
 * kopyasında bu sütunlar HİÇ OLUŞTURULMAZ, veri dosyaya girmez.
 * F6: aktif paylaşım linki verildiyse üst banda QR.
 * F7: belge kodunda Rev harfi; Rev A dışındakiler "öncekini geçersiz kılar" ibaresi taşır.
 *
 * Para (K14): değerler snapshot'ta bcmath ile HESAPLANMIŞ string'lerdir; burada yalnız
 * gösterim biçimi verilir. TL karşılıkları Excel formülüdür ki kullanıcı dosyada
 * oynadığında tablo kendi içinde tutarlı kalsın.
 */
final class XlsxRenderer implements ExportRenderer
{
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
        $sheet->setTitle('Sipariş Listesi');
        $sheet->setShowGridlines(false);

        $options = is_array($snapshot['options'] ?? null) ? $snapshot['options'] : [];
        $icKopya = ($options['copy'] ?? 'firma') === 'ic';

        $columns = TemplateV2::COLUMNS;
        if ($icKopya) {
            $columns += TemplateV2::INTERNAL_COLUMNS;
        }
        $sonSutun = (string) array_key_last($columns);

        foreach ($columns as $harf => $tanim) {
            $sheet->getColumnDimension($harf)->setWidth($tanim[0]);
        }

        $this->kimlikBandi($sheet, $snapshot, $sonSutun);
        $this->kpiSeridi($sheet, $snapshot, $sonSutun, $icKopya);
        $this->basliklar($sheet, $columns, $sonSutun);
        $sonVeri = $this->veriSatirlari($sheet, $snapshot, $icKopya, $sonSutun);
        $this->kapanis($sheet, $snapshot, $sonVeri, $sonSutun, $icKopya);

        // Dondurulmuş başlıklar (şartname): veri kayarken başlık ve No sütunu sabit.
        $sheet->freezePane('B' . TemplateV2::ROW_DATA_START);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(TemplateV2::ROW_HEAD_TR, TemplateV2::ROW_HEAD_EN);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);

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

    /**
     * Üst kimlik bandı + altın çizgi.
     *
     * @param array<string, mixed> $snapshot
     */
    private function kimlikBandi(Worksheet $sheet, array $snapshot, string $sonSutun): void
    {
        /** @var array<string, mixed> $list */
        $list = $snapshot['list'];
        $options = is_array($snapshot['options'] ?? null) ? $snapshot['options'] : [];
        $antet = is_array($snapshot['document_header'] ?? null) ? $snapshot['document_header'] : [];

        $sheet->getStyle('B2:' . $sonSutun . '3')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TemplateV2::LACIVERT);
        $sheet->getRowDimension(2)->setRowHeight(30);
        $sheet->getRowDimension(3)->setRowHeight(30);

        $sheet->mergeCells('D2:H2');
        $sheet->setCellValue('D2', 'TEDARİK SİPARİŞ LİSTESİ');
        $this->font($sheet, 'D2', 19, 'FFFFFF', true);

        $sheet->mergeCells('D3:H3');
        $sheet->setCellValueExplicit('D3', TemplateV2::headerLine($antet, $list), DataType::TYPE_STRING);
        $this->font($sheet, 'D3', 10, TemplateV2::BANT_YAZI);

        $revizyon = (string) ($options['revision_label'] ?? 'A');
        $kod = is_string($options['document_code'] ?? null) && $options['document_code'] !== ''
            ? (string) $options['document_code']
            : TemplateV2::documentCode((int) $list['id'], (int) date('Y'), $revizyon);

        foreach ([
            ['I', 'J', 'BELGE KODU', $kod],
            ['K', 'L', 'OLUŞTURULMA', $this->tarih((string) $snapshot['generated_at'])],
            ['M', 'N', 'KUR (KİLİTLİ)', $this->kurMetni($list)],
        ] as [$ilk, $son, $etiket, $deger]) {
            $sheet->mergeCells($ilk . '2:' . $son . '2');
            $sheet->mergeCells($ilk . '3:' . $son . '3');
            $sheet->setCellValue($ilk . '2', $etiket);
            $this->font($sheet, $ilk . '2', 8, TemplateV2::BANT_YAZI, true);
            $sheet->setCellValueExplicit($ilk . '3', $deger, DataType::TYPE_STRING);
            $this->font($sheet, $ilk . '3', 9.5, 'FFFFFF', true);
        }

        $sheet->getStyle('B2:' . $sonSutun . '3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // İE#21 B13: marka amblemi (marka kitinden) — yoksa panel simgesine düşer.
        // Belge bir görselin varlığına bağlanmaz; ikisi de yoksa bant yazıyla durur.
        $marka = new BelgeMarkasi($this->basePath);
        $logo = $marka->amblem() ?? $this->basePath . '/public/panel/apple-touch-icon.png';
        if (is_file($logo)) {
            $drawing = new Drawing();
            $drawing->setPath($logo);
            $drawing->setCoordinates('B2');
            $drawing->setHeight(46);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(6);
            $drawing->setWorksheet($sheet);
        }

        $this->paylasimQr($sheet, $list, $options);

        $sheet->getRowDimension(TemplateV2::ROW_GOLD_TOP)->setRowHeight(3);
        $sheet->getStyle('B4:' . $sonSutun . '4')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TemplateV2::ALTIN);
        $sheet->getRowDimension(5)->setRowHeight(6);
    }

    /**
     * F6: QR yalnız listede AKTİF paylaşım linki varsa ve tam adres verildiyse basılır.
     * Tam token sunucuda saklanmaz (K51) — adres istekle gelir, hash'i doğrulanmıştır.
     *
     * @param array<string, mixed> $list
     * @param array<string, mixed> $options
     */
    private function paylasimQr(Worksheet $sheet, array $list, array $options): void
    {
        $url = $options['share_url'] ?? null;
        if (!is_string($url) || $url === '' || ($list['paylasim_onek'] ?? null) === null) {
            return;
        }

        $image = QrImage::olustur($url);
        if ($image === null) {
            return;
        }

        $drawing = new MemoryDrawing();
        $drawing->setImageResource($image);
        $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
        $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
        $drawing->setCoordinates('C2');
        $drawing->setHeight(52);
        $drawing->setOffsetX(6);
        $drawing->setOffsetY(3);
        $drawing->setWorksheet($sheet);
    }

    /**
     * KPI şeridi — parasal toplamların TEK yeri (GENEL TOPLAM bandında yalnız miktar var).
     *
     * @param array<string, mixed> $snapshot
     */
    private function kpiSeridi(Worksheet $sheet, array $snapshot, string $sonSutun, bool $icKopya): void
    {
        $ilk = TemplateV2::ROW_DATA_START;
        $son = $ilk + max(0, count($snapshot['products']) - 1);
        $bosMu = count($snapshot['products']) === 0;

        $sheet->getStyle('B6:' . $sonSutun . '7')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TemplateV2::KPI_ZEMIN);
        $sheet->getRowDimension(6)->setRowHeight(15);
        $sheet->getRowDimension(7)->setRowHeight(24);

        foreach (TemplateV2::KPI_CARDS as [$ilkSutun, $sonSutunKart, $etiket, $formul, $bicim]) {
            $sheet->mergeCells($ilkSutun . '6:' . $sonSutunKart . '6');
            $sheet->mergeCells($ilkSutun . '7:' . $sonSutunKart . '7');
            $sheet->setCellValue($ilkSutun . '6', $etiket);
            $this->font($sheet, $ilkSutun . '6', 8, TemplateV2::SOLUK, true);
            $sheet->setCellValue(
                $ilkSutun . '7',
                $bosMu ? 0 : str_replace(['{ilk}', '{son}'], [(string) $ilk, (string) $son], $formul),
            );
            $this->font($sheet, $ilkSutun . '7', 13, TemplateV2::LACIVERT, true);
            $sheet->getStyle($ilkSutun . '7')->getNumberFormat()->setFormatCode($bicim);
        }

        if ($icKopya && !$bosMu) {
            // İç kopyada kâr kartı da eklenir — ek sütunların üstünde amber renkle durur.
            $sheet->mergeCells('P6:R6');
            $sheet->mergeCells('P7:R7');
            $sheet->setCellValue('P6', 'TOPLAM KÂR (₺ · iç kopya)');
            $this->font($sheet, 'P6', 8, '92400E', true);
            $sheet->setCellValue('P7', '=SUM(R' . $ilk . ':R' . $son . ')');
            $this->font($sheet, 'P7', 13, '92400E', true);
            $sheet->getStyle('P7')->getNumberFormat()->setFormatCode('\₺#,##0.00');
        }

        $sheet->getStyle('B6:' . $sonSutun . '7')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Üç satır başlık: TR (beyaz kalın) · 中文 (açık mavi) · EN (italik soluk).
     *
     * @param array<string, array{0: float, 1: string, 2: string, 3: string}> $columns
     */
    private function basliklar(Worksheet $sheet, array $columns, string $sonSutun): void
    {
        $tr = TemplateV2::ROW_HEAD_TR;
        $cjk = TemplateV2::ROW_HEAD_CJK;
        $en = TemplateV2::ROW_HEAD_EN;

        $sheet->getStyle('B' . $tr . ':' . $sonSutun . $en)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TemplateV2::LACIVERT_ACIK);
        $sheet->getRowDimension($tr)->setRowHeight(15);
        $sheet->getRowDimension($cjk)->setRowHeight(12);
        $sheet->getRowDimension($en)->setRowHeight(10.5);

        foreach ($columns as $harf => [, $trBaslik, $cjkBaslik, $enBaslik]) {
            if ($harf === 'A') {
                continue;
            }
            $sheet->setCellValueExplicit($harf . $tr, $trBaslik, DataType::TYPE_STRING);
            $this->font($sheet, $harf . $tr, 9, 'FFFFFF', true);

            $sheet->setCellValueExplicit($harf . $cjk, $cjkBaslik, DataType::TYPE_STRING);
            $this->font($sheet, $harf . $cjk, 7.5, TemplateV2::BANT_YAZI);
            $sheet->getStyle($harf . $cjk)->getFont()->setName('Noto Sans CJK SC');

            $sheet->setCellValueExplicit($harf . $en, $enBaslik, DataType::TYPE_STRING);
            $this->font($sheet, $harf . $en, 7, TemplateV2::EN_YAZI);
            $sheet->getStyle($harf . $en)->getFont()->setItalic(true);
        }

        $sheet->getStyle('B' . $tr . ':' . $sonSutun . $en)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    /**
     * Veri satırları; son satır numarasını döndürür.
     *
     * @param array<string, mixed> $snapshot
     */
    private function veriSatirlari(Worksheet $sheet, array $snapshot, bool $icKopya, string $sonSutun): int
    {
        $list = $snapshot['list'];
        $yuanRate = (float) $list['yuan_rate'];
        $usdRate = (float) $list['usd_rate'];
        $options = is_array($snapshot['options'] ?? null) ? $snapshot['options'] : [];
        $shareUrl = is_string($options['share_url'] ?? null) ? (string) $options['share_url'] : null;

        $row = TemplateV2::ROW_DATA_START;
        foreach ($snapshot['products'] as $index => $product) {
            $sheet->getRowDimension($row)->setRowHeight(57.75);

            $sheet->setCellValue('B' . $row, (int) ($product['no'] ?? $index + 1));
            $this->font($sheet, 'B' . $row, 10, TemplateV2::SOLUK, true);

            // Ürün adı + ORİJİNAL (Çince) başlık ikinci satırda; ad kendi ilanına köprülü.
            $ad = (string) $product['name'];
            $orijinal = is_string($product['name_original'] ?? null) ? trim((string) $product['name_original']) : '';
            $sheet->setCellValueExplicit('D' . $row, $orijinal === '' ? $ad : $ad . "\n" . $orijinal, DataType::TYPE_STRING);
            $this->font($sheet, 'D' . $row, 10, TemplateV2::MAVI, true);
            $sheet->getStyle('D' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
            if (is_string($product['url'] ?? null) && $product['url'] !== '') {
                $sheet->getCell('D' . $row)->getHyperlink()->setUrl((string) $product['url']);
            }

            $this->metin($sheet, 'E' . $row, (string) ($product['detail'] ?? ''), 9, TemplateV2::METIN, true);
            $this->metin($sheet, 'F' . $row, (string) ($product['variant'] ?? ''), 9, TemplateV2::METIN, true);
            $this->metin($sheet, 'G' . $row, (string) ($product['category'] ?? ''), 9, TemplateV2::SOLUK);
            $this->metin($sheet, 'H' . $row, TemplateV2::platformLabel($product['platform'] ?? null), 8.5, TemplateV2::SOLUK);
            $this->metin($sheet, 'J' . $row, (string) ($product['note'] ?? ''), 8.5, TemplateV2::SOLUK, true);

            // İki satırlı durum rozeti (dar sütun — şartname).
            [$etiket, $zemin, $yazi] = TemplateV2::badge((string) $product['status']);
            $sheet->setCellValueExplicit('I' . $row, $etiket, DataType::TYPE_STRING);
            $this->font($sheet, 'I' . $row, 9, $yazi, true);
            $sheet->getStyle('I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($zemin);
            $sheet->getStyle('I' . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $sheet->setCellValue('K' . $row, (int) $product['qty']);
            $sheet->getStyle('K' . $row)->getNumberFormat()->setFormatCode('#,##0');

            // Birim fiyatlar; TL karşılıkları FORMÜLLE (kur bandıyla tutarlı kalsın).
            // TL karşılıkları FORMÜLLE (kur bandıyla tutarlı kalsın); kaynak hücre
            // girilmemişse formül #VALUE! verirdi — o hücre de "—" basılır (G3).
            $this->para($sheet, 'L' . $row, (string) $product['price_yuan'], '¥');
            if (TemplateV2::girilmis($product['price_yuan'] ?? null)) {
                $sheet->setCellValue('M' . $row, '=L' . $row . '*' . $yuanRate);
                $sheet->getStyle('M' . $row)->getNumberFormat()->setFormatCode('\₺#,##0.00');
            } else {
                $this->para($sheet, 'M' . $row, '', '₺');
            }

            $this->para($sheet, 'N' . $row, (string) $product['price_ddp_usd'], '$');
            if (TemplateV2::girilmis($product['price_ddp_usd'] ?? null)) {
                $sheet->setCellValue('O' . $row, '=N' . $row . '*' . $usdRate);
                $sheet->getStyle('O' . $row)->getNumberFormat()->setFormatCode('\₺#,##0.00');
            } else {
                $this->para($sheet, 'O' . $row, '', '₺');
            }

            if ($icKopya) {
                $this->para($sheet, 'P' . $row, (string) ($product['price_target_try'] ?? ''), '₺');
                $this->para($sheet, 'Q' . $row, (string) ($product['unit_profit_try'] ?? ''), '₺');
                $this->para($sheet, 'R' . $row, (string) ($product['line_profit_try'] ?? ''), '₺');
            }

            $this->gorsel($sheet, 'C' . $row, $product, $shareUrl);

            $sheet->getStyle('B' . $row . ':' . $sonSutun . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            foreach (['B', 'H', 'K'] as $harf) {
                $sheet->getStyle($harf . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            foreach (['L', 'M', 'N', 'O'] as $harf) {
                $sheet->getStyle($harf . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            // Zarif ayraçlar: alt ince çizgi + sütunlar arası HAIR (0,25pt) dikey çizgi.
            $sheet->getStyle('B' . $row . ':' . $sonSutun . $row)->getBorders()->getBottom()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(TemplateV2::CIZGI);
            foreach (range('C', $sonSutun) as $harf) {
                $sheet->getStyle($harf . $row)->getBorders()->getLeft()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(TemplateV2::AYRAC);
            }

            if (($index % 2) === 1) {
                foreach (range('B', $sonSutun) as $harf) {
                    if ($harf === 'I') {
                        continue; // rozet kendi rengini korur
                    }
                    $sheet->getStyle($harf . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TemplateV2::ZEBRA);
                }
            }

            $row++;
        }

        return $row - 1;
    }

    /**
     * GENEL TOPLAM bandı (yalnız miktar) + altın kapanış + şartlar/imza + alt bilgi.
     *
     * @param array<string, mixed> $snapshot
     */
    private function kapanis(Worksheet $sheet, array $snapshot, int $sonVeri, string $sonSutun, bool $icKopya): void
    {
        $bosMu = count($snapshot['products']) === 0;
        $row = ($bosMu ? TemplateV2::ROW_DATA_START : $sonVeri) + 1;
        $ilk = TemplateV2::ROW_DATA_START;
        $options = is_array($snapshot['options'] ?? null) ? $snapshot['options'] : [];
        $antet = is_array($snapshot['document_header'] ?? null) ? $snapshot['document_header'] : [];

        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->getStyle('B' . $row . ':' . $sonSutun . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TemplateV2::LACIVERT);
        $sheet->mergeCells('B' . $row . ':J' . $row);
        $sheet->setCellValue('B' . $row, 'GENEL TOPLAM');
        $this->font($sheet, 'B' . $row, 10.5, 'FFFFFF', true);
        $sheet->getStyle('B' . $row)->getAlignment()->setIndent(1)->setVertical(Alignment::VERTICAL_CENTER);

        if (!$bosMu) {
            $sheet->setCellValue('K' . $row, '=SUM(K' . $ilk . ':K' . $sonVeri . ')');
            $sheet->getStyle('K' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $this->font($sheet, 'K' . $row, 10, 'FFFFFF', true);
            $sheet->getStyle('K' . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Parasal toplamlar KPI kartlarındadır — bant yalnız oraya işaret eder (şartname).
        $sheet->mergeCells('L' . $row . ':' . $sonSutun . $row);
        $sheet->setCellValueExplicit('L' . $row, 'Parasal toplamlar üstteki özet kartlarındadır', DataType::TYPE_STRING);
        $this->font($sheet, 'L' . $row, 8.5, TemplateV2::BANT_YAZI);
        $sheet->getStyle('L' . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension($row + 1)->setRowHeight(2.25);
        $sheet->getStyle('B' . ($row + 1) . ':' . $sonSutun . ($row + 1))->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TemplateV2::ALTIN);

        $sartRow = $row + 3;
        $revizyon = (string) ($options['revision_label'] ?? 'A');
        $sartlar = 'Sipariş şartları: Teslim DDP · Kur, liste iletildiğinde kilitlenir · Fiyatlar DDP teslim, KDV DAHİLDİR';
        if ($revizyon !== 'A') {
            $sartlar .= ' · Rev ' . $revizyon . ': bu belge aynı listenin önceki çıktılarını GEÇERSİZ KILAR';
        }
        $sheet->mergeCells('B' . $sartRow . ':I' . $sartRow);
        $sheet->setCellValueExplicit('B' . $sartRow, $sartlar, DataType::TYPE_STRING);
        $this->font($sheet, 'B' . $sartRow, 8.5, TemplateV2::METIN);
        $sheet->getStyle('B' . $sartRow)->getAlignment()->setIndent(1);

        $hazirlayan = is_string($antet['prepared_by'] ?? null) && $antet['prepared_by'] !== ''
            ? ' · Hazırlayan: ' . (string) $antet['prepared_by']
            : '';
        $sheet->mergeCells('J' . $sartRow . ':' . $sonSutun . $sartRow);
        $sheet->setCellValueExplicit('J' . $sartRow, 'Firma onayı: ________  Tarih: ____' . $hazirlayan, DataType::TYPE_STRING);
        $this->font($sheet, 'J' . $sartRow, 8.5, TemplateV2::METIN, true);
        $sheet->getStyle('J' . $sartRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $altRow = $sartRow + 1;
        $sheet->mergeCells('B' . $altRow . ':' . $sonSutun . $altRow);
        $sheet->setCellValueExplicit(
            'B' . $altRow,
            'TedarikApp — Ürün Tedarik Asistanı · Ürün adı ve görsel, kaynak platformdaki ilana köprülüdür '
            . '(tüm platformlar) · ▶ rozetli görsel = ürün videosu (dijital kopyada)'
            . ($icKopya ? ' · İÇ KOPYA — firmaya gönderilmez' : ''),
            DataType::TYPE_STRING,
        );
        $this->font($sheet, 'B' . $altRow, 8, TemplateV2::SOLUK);
        $sheet->getStyle('B' . $altRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Ürün görseli: /media arşivinden gömülür. Videolu üründe hücre PAYLAŞIM
     * SAYFASINDAKİ ürün kartına köprülenir ve ▶ rozeti basılır; paylaşım linki
     * yoksa köprü konmaz (hedefsiz rozet basılmaz).
     *
     * @param array<string, mixed> $product
     */
    private function gorsel(Worksheet $sheet, string $cell, array $product, ?string $shareUrl): void
    {
        $mainImage = $product['main_image'] ?? null;
        $videolu = is_string($product['video_url'] ?? null) && $product['video_url'] !== '';

        if ($videolu && $shareUrl !== null) {
            $sheet->getCell($cell)->getHyperlink()->setUrl($shareUrl . '#urun-' . (string) ($product['no'] ?? ''));
            $sheet->setCellValueExplicit($cell, '▶', DataType::TYPE_STRING);
            $this->font($sheet, $cell, 11, TemplateV2::VIDEO, true);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_BOTTOM);
        }

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
        $drawing->setHeight(70);
        $drawing->setOffsetX(6);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);
    }

    /** @param array<string, mixed> $list */
    private function kurMetni(array $list): string
    {
        return '¥ ' . number_format((float) $list['yuan_rate'], 4, ',', '.')
            . ' · $ ' . number_format((float) $list['usd_rate'], 4, ',', '.');
    }

    private function tarih(string $iso): string
    {
        try {
            return (new \DateTimeImmutable($iso))->format('d.m.Y H:i');
        } catch (\Throwable) {
            return $iso;
        }
    }

    private function font(Worksheet $sheet, string $cell, float $size, string $rgb, bool $bold = false): void
    {
        $font = $sheet->getStyle($cell)->getFont();
        $font->setSize($size)->setBold($bold);
        $font->getColor()->setRGB($rgb);
    }

    private function metin(Worksheet $sheet, string $cell, string $value, float $size, string $rgb, bool $wrap = false): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
        $this->font($sheet, $cell, $size, $rgb);
        if ($wrap) {
            $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
        }
    }

    /** Para hücresi: string değer GÖSTERİM için sayıya çevrilir; hesap bcmath'teydi (K14). */
    private function para(Worksheet $sheet, string $cell, string $amount, string $symbol): void
    {
        // İE#17 G3: girilmemiş fiyat (boş, sayı değil ya da POZİTİF DEĞİL) "—" basılır.
        if (!TemplateV2::girilmis($amount)) {
            $sheet->setCellValueExplicit($cell, '—', DataType::TYPE_STRING);
            $this->font($sheet, $cell, 9.5, TemplateV2::SOLUK);

            return;
        }
        $sheet->setCellValue($cell, (float) $amount);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('\\' . $symbol . '#,##0.00');
        $this->font($sheet, $cell, 9.5, TemplateV2::METIN);
    }
}
