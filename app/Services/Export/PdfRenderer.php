<?php

declare(strict_types=1);

namespace App\Services\Export;

use Mpdf\Mpdf;

/**
 * PDF çıktısı (İE#10 Blok 3 · İE#13 F3 ile ŞABLON v2'ye geçti) — aynı snapshot'tan, mPDF ile.
 *
 * Excel ile AYNI tasarım dili (referans: docs/sablon/sablon-v2-pdf-ornek-rev3.pdf):
 * lacivert kimlik bandı + altın çizgi, KPI şeridi, ÜÇ SATIR üç dilli başlık
 * (TR / 中文 / EN italik), iki satırlı durum rozetleri, köprülü ürün adı + altında
 * Çince orijinal, GENEL TOPLAM bandında yalnız miktar + karta işaret notu.
 * Yatay A4; çok sayfada başlık TEKRARLAR (thead) ve alt bilgide "Sayfa X/Y" basılır.
 *
 * Kolon/renk tanımları TemplateV2'dedir — iki çıktı tek kaynaktan beslenir.
 *
 * Kütüphane: mpdf/mpdf ^8.3 (K19 onaylı; composer "php" beyanı 8.1'i kapsar; CI
 * php81-uyum vendor lint'i ayrıca kanıtlar). Türkçe + ¥/₺ için DejaVu fontları
 * (mPDF ile gelir, ₺ U+20BA içerir).
 *
 * DİSKSİZ sunucu gerçeği (K33/K44): mPDF geçici dizin İSTER. Çözüm sırası (İE#10 §3):
 *  (a) sys_get_temp_dir() yazılabilirse o;
 *  (b) değilse public/media/.tmp — no-exec .htaccess kapsamındadır (public/media/
 *      kuralları üst dizinden geçerli), rastgele adlarla çalışır, iş sonunda süpürülür.
 * İkisi de yoksa ExportException fırlatılır — çağıran "ENGELLENDİ" raporlar, Excel ve
 * paylaşım bundan etkilenmez.
 */
final class PdfRenderer implements ExportRenderer
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function mime(): string
    {
        return 'application/pdf';
    }

    public function render(array $snapshot): string
    {
        $tempDir = $this->resolveTempDir();

        try {
            $mpdf = new Mpdf([
                'tempDir' => $tempDir,
                'mode' => 'utf-8',
                'format' => 'A4-L', // geniş tablo — yatay sayfa
                'margin_left' => 8,
                'margin_right' => 8,
                'margin_top' => 10,
                'margin_bottom' => 12,
                'default_font' => 'dejavusans',
                // Çince başlıklar (K31): betik algılama + CJK fontuna otomatik geçiş —
                // DejaVu CJK glif taşımaz; bu iki bayrak olmadan kutu (□) basılır.
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);
            $mpdf->SetTitle('tedarikapp — ' . (string) ($snapshot['list']['name'] ?? 'liste'));
            $icKopya = (($snapshot['options']['copy'] ?? 'firma') === 'ic');
            $mpdf->SetHTMLFooter(
                '<div style="border-top:0.2mm solid #e2e8f0;padding-top:1mm;font-size:7pt;color:#64748b;text-align:center">'
                . 'Tedarikapp — Ürün Tedarik Asistanı · Ürün adı kaynak platformdaki ilana köprülüdür'
                . ($icKopya ? ' · <b style="color:#92400e">İÇ KOPYA — firmaya gönderilmez</b>' : '')
                . ' · Sayfa {PAGENO}/{nbpg}</div>',
            );
            $mpdf->WriteHTML($this->html($snapshot));

            return $mpdf->OutputBinaryData();
        } catch (\Mpdf\MpdfException $e) {
            throw new ExportException('PDF üretilemedi: ' . $e->getMessage(), 0, $e);
        } finally {
            $this->sweepOwnTemp($tempDir);
        }
    }

    /** @throws ExportException iki geçici dizin adayı da yazılamazsa */
    private function resolveTempDir(): string
    {
        $system = sys_get_temp_dir();
        if (is_dir($system) && is_writable($system)) {
            return $system;
        }

        // (b) public/media/.tmp — medya klasörünün no-exec .htaccess'i alt dizinleri de kapsar.
        $fallback = $this->basePath . '/public/media/.tmp';
        if (!is_dir($fallback)) {
            @mkdir($fallback, 0775, true);
        }
        if (is_dir($fallback) && is_writable($fallback)) {
            return $fallback;
        }

        throw new ExportException(
            'PDF üretilemedi: geçici dizin yazılamıyor (sys_temp ve public/media/.tmp denendi). '
            . 'Excel çıktısı kullanılabilir; PDF için sunucuda geçici dizine yazma izni gerekir.',
        );
    }

    /** Yalnız medya-altı yedek dizindeki mPDF artıklarını süpürür — sistem temp'ine dokunulmaz. */
    private function sweepOwnTemp(string $tempDir): void
    {
        if (!str_starts_with($tempDir, $this->basePath . '/public/media/')) {
            return;
        }
        foreach (glob($tempDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Şablon v2 gövdesi (F3) — rev7 sütun setinin HTML eşdeğeri.
     *
     * @param array<string, mixed> $snapshot
     */
    private function html(array $snapshot): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $list = $snapshot['list'];
        $totals = $snapshot['totals'];
        $options = is_array($snapshot['options'] ?? null) ? $snapshot['options'] : [];
        $antet = is_array($snapshot['document_header'] ?? null) ? $snapshot['document_header'] : [];
        $icKopya = ($options['copy'] ?? 'firma') === 'ic';
        $revizyon = (string) ($options['revision_label'] ?? 'A');

        $kod = is_string($options['document_code'] ?? null) && $options['document_code'] !== ''
            ? (string) $options['document_code']
            : TemplateV2::documentCode((int) $list['id'], (int) date('Y'), $revizyon);

        $rows = '';
        foreach ($snapshot['products'] as $index => $product) {
            $gorsel = $this->gorselHucresi($product, is_string($options['share_url'] ?? null) ? (string) $options['share_url'] : null);

            [$rozet, $zemin, $yazi] = TemplateV2::badge((string) $product['status']);
            $ad = $e($product['name']);
            if (is_string($product['url'] ?? null) && $product['url'] !== '') {
                $ad = '<a href="' . $e($product['url']) . '">' . $ad . '</a>';
            }
            $orijinal = is_string($product['name_original'] ?? null) && $product['name_original'] !== ''
                ? '<br><span class="zh">' . $e($product['name_original']) . '</span>'
                : '';

            $kar = '';
            if ($icKopya) {
                foreach (['price_target_try', 'unit_profit_try', 'line_profit_try'] as $alan) {
                    $deger = $product[$alan] ?? null;
                    $kar .= '<td class="r kar">' . ($deger === null ? '—' : '₺' . $e($deger)) . '</td>';
                }
            }

            $rows .= '<tr class="' . ($index % 2 === 1 ? 'zebra' : '') . '">'
                . '<td class="c no">' . $e($product['no'] ?? $product['sort_no']) . '</td>'
                . '<td class="c">' . $gorsel . '</td>'
                . '<td class="ad">' . $ad . $orijinal . '</td>'
                . '<td class="kucuk">' . $e($product['detail'] ?? '') . '</td>'
                . '<td class="kucuk">' . $e($product['variant'] ?? '—') . '</td>'
                . '<td class="kucuk soluk">' . $e($product['category']) . '</td>'
                . '<td class="c kucuk soluk">' . $e(TemplateV2::platformLabel($product['platform'] ?? null)) . '</td>'
                . '<td class="c"><span class="rozet" style="background:#' . $zemin . ';color:#' . $yazi . '">'
                . nl2br($e($rozet)) . '</span></td>'
                . '<td class="kucuk soluk">' . $e($product['note'] ?? '') . '</td>'
                . '<td class="c">' . $e($product['qty']) . '</td>'
                . '<td class="r">¥' . $e($product['price_yuan']) . '</td>'
                . '<td class="r">₺' . $e($product['price_yuan_tl']) . '</td>'
                . '<td class="r">$' . $e($product['price_ddp_usd']) . '</td>'
                . '<td class="r">₺' . $e($product['price_ddp_tl']) . '</td>'
                . $kar
                . '</tr>';
        }

        $baslik = '';
        $sutunlar = TemplateV2::COLUMNS;
        unset($sutunlar['A']);
        if ($icKopya) {
            $sutunlar += TemplateV2::INTERNAL_COLUMNS;
        }
        foreach ($sutunlar as [, $tr, $cjk, $en]) {
            $baslik .= '<th><span class="tr">' . $e($tr) . '</span>'
                . '<br><span class="cn">' . $e($cjk) . '</span>'
                . '<br><span class="en">' . $e($en) . '</span></th>';
        }

        $karToplam = $icKopya
            ? '<td colspan="2"></td><td class="r">₺' . $e($this->karToplami($snapshot)) . '</td>'
            : '';

        $sartlar = 'Sipariş şartları: Teslim DDP · Kur, liste iletildiğinde kilitlenir · Fiyatlar DDP teslim, KDV DAHİLDİR';
        if ($revizyon !== 'A') {
            $sartlar .= ' · Rev ' . $revizyon . ': bu belge aynı listenin önceki çıktılarını GEÇERSİZ KILAR';
        }
        $hazirlayan = is_string($antet['prepared_by'] ?? null) && $antet['prepared_by'] !== ''
            ? ' · Hazırlayan: ' . (string) $antet['prepared_by']
            : '';

        $qr = $this->qrEtiketi($list, $options);

        return '<style>
                body { font-family: dejavusans; font-size: 7.4pt; color: #' . TemplateV2::METIN . '; }
                .bant { background: #' . TemplateV2::LACIVERT . '; color: #fff; }
                .bant td { padding: 3mm 4mm; vertical-align: middle; }
                .bant .baslik { font-size: 13pt; font-weight: bold; }
                .bant .antet { color: #' . TemplateV2::BANT_YAZI . '; font-size: 7.5pt; }
                .kimlik { text-align: right; font-size: 7pt; color: #' . TemplateV2::BANT_YAZI . '; }
                .kimlik b { font-size: 8.5pt; color: #fff; }
                .altin { background: #' . TemplateV2::ALTIN . '; height: 1mm; font-size: 0; line-height: 0; }
                .kpi { width: 100%; border-collapse: collapse; margin: 2mm 0 3mm 0; }
                .kpi td { background: #' . TemplateV2::KPI_ZEMIN . '; padding: 1.6mm; text-align: center; border: 0; }
                .kpi .etiket { color: #' . TemplateV2::SOLUK . '; font-size: 6.5pt; font-weight: bold; }
                .kpi .deger { color: #' . TemplateV2::LACIVERT . '; font-size: 10pt; font-weight: bold; }
                table.veri { width: 100%; border-collapse: collapse; }
                table.veri th { background: #' . TemplateV2::LACIVERT_ACIK . '; padding: 1.2mm 0.8mm;
                    border-left: 0.1mm solid #' . TemplateV2::LACIVERT_ORTA . '; }
                table.veri th .tr { color: #fff; font-size: 7pt; font-weight: bold; }
                table.veri th .cn { color: #' . TemplateV2::BANT_YAZI . '; font-size: 6pt; font-weight: normal; }
                table.veri th .en { color: #' . TemplateV2::EN_YAZI . '; font-size: 5.6pt; font-style: italic; font-weight: normal; }
                table.veri td { border-bottom: 0.2mm solid #' . TemplateV2::CIZGI . ';
                    border-left: 0.08mm solid #' . TemplateV2::AYRAC . '; padding: 1.2mm 0.8mm; vertical-align: middle; }
                .zebra td { background: #' . TemplateV2::ZEBRA . '; }
                .c { text-align: center; } .r { text-align: right; }
                .no { color: #' . TemplateV2::SOLUK . '; font-weight: bold; }
                .ad { font-weight: bold; }
                .ad a { color: #' . TemplateV2::MAVI . '; text-decoration: none; }
                .zh { color: #' . TemplateV2::SOLUK . '; font-size: 6.4pt; font-weight: normal; }
                .kucuk { font-size: 6.8pt; } .soluk { color: #' . TemplateV2::SOLUK . '; }
                .rozet { padding: 0.6mm 1.2mm; border-radius: 1.5mm; font-size: 6.2pt; font-weight: bold; }
                .video { color: #' . TemplateV2::VIDEO . '; font-size: 7pt; font-weight: bold; }
                .toplam td { background: #' . TemplateV2::LACIVERT . '; color: #fff; font-weight: bold;
                    font-size: 8pt; padding: 1.8mm 1.2mm; border: 0; }
                .toplam .not { color: #' . TemplateV2::BANT_YAZI . '; font-weight: normal; font-size: 7pt; text-align: right; }
                .kar { color: #92400e; }
                .sart { font-size: 6.8pt; margin-top: 2.5mm; }
                .imza { text-align: right; font-weight: bold; }
                .bos { color: #cbd5e1; }
            </style>
            <table class="bant" style="width:100%"><tr>
                <td style="width:60%">
                    <div class="baslik">TEDARİK SİPARİŞ LİSTESİ</div>
                    <div class="antet">' . $e(TemplateV2::headerLine($antet, $list)) . '</div>
                </td>
                <td class="kimlik">' . $qr . '
                    BELGE KODU <b>' . $e($kod) . '</b><br>
                    OLUŞTURULMA <b>' . $e($this->tarih((string) $snapshot['generated_at'])) . '</b><br>
                    KUR (KİLİTLİ) <b>¥ ' . $e($list['yuan_rate']) . ' · $ ' . $e($list['usd_rate']) . '</b>
                </td>
            </tr></table>
            <div class="altin">&nbsp;</div>
            <table class="kpi"><tr>
                <td><div class="etiket">TOPLAM ÜRÜN</div><div class="deger">' . count($snapshot['products']) . '</div></td>
                <td><div class="etiket">TOPLAM MİKTAR</div><div class="deger">' . $e($totals['qty']) . '</div></td>
                <td><div class="etiket">MAL BEDELİ (¥)</div><div class="deger">¥' . $e($totals['yuan']) . '</div></td>
                <td><div class="etiket">MAL BEDELİ (₺)</div><div class="deger">₺' . $e($totals['yuan_tl']) . '</div></td>
                <td><div class="etiket">DDP TOPLAM (₺ · KDV dahil)</div><div class="deger">₺' . $e($totals['ddp_tl']) . '</div></td>
            </tr></table>
            <table class="veri">
                <thead><tr>' . $baslik . '</tr></thead>
                <tbody>' . $rows . '
                <tr class="toplam">
                    <td colspan="9">GENEL TOPLAM</td>
                    <td class="c">' . $e($totals['qty']) . '</td>
                    <td class="not" colspan="4">Parasal toplamlar üstteki özet kartlarındadır</td>
                    ' . $karToplam . '
                </tr>
                </tbody>
            </table>
            <div class="altin">&nbsp;</div>
            <table style="width:100%"><tr>
                <td class="sart">' . $e($sartlar) . '</td>
                <td class="sart imza">Firma onayı: ________  Tarih: ____' . $e($hazirlayan) . '</td>
            </tr></table>';
    }

    /**
     * Görsel hücresi: /media arşivinden gömülür; videolu üründe ▶ rozeti paylaşım
     * sayfasındaki ürün kartına köprülenir (link yoksa rozet basılmaz).
     *
     * @param array<string, mixed> $product
     */
    private function gorselHucresi(array $product, ?string $shareUrl): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $govde = '<span class="bos">—</span>';

        if (is_string($product['main_image'] ?? null) && str_starts_with((string) $product['main_image'], '/media/')) {
            $path = $this->basePath . '/public' . $product['main_image'];
            if (is_file($path)) {
                $govde = '<img src="' . $e($path) . '" style="height:15mm">';
            }
        }

        $videolu = is_string($product['video_url'] ?? null) && $product['video_url'] !== '';
        if ($videolu && $shareUrl !== null) {
            $hedef = $shareUrl . '#urun-' . (string) ($product['no'] ?? '');
            $govde .= '<br><a class="video" href="' . $e($hedef) . '">▶ video</a>';
        }

        return $govde;
    }

    /**
     * F6: paylaşım QR'ı — yalnız aktif link varsa. mPDF gömülü base64 PNG kabul eder.
     *
     * @param array<string, mixed> $list
     * @param array<string, mixed> $options
     */
    private function qrEtiketi(array $list, array $options): string
    {
        $url = $options['share_url'] ?? null;
        if (!is_string($url) || $url === '' || ($list['share_token_prefix'] ?? null) === null) {
            return '';
        }

        $image = QrImage::olustur($url);
        if ($image === null) {
            return '';
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return '<img src="data:image/png;base64,' . base64_encode($png) . '" style="height:16mm;float:left;margin-right:3mm">';
    }

    /**
     * İç kopya toplam kârı — snapshot satırlarından toplanır (gösterim amaçlı).
     *
     * @param array<string, mixed> $snapshot
     */
    private function karToplami(array $snapshot): string
    {
        $toplam = '0';
        foreach ($snapshot['products'] as $product) {
            $deger = $product['line_profit_try'] ?? null;
            if (is_string($deger) && is_numeric($deger)) {
                $toplam = bcadd($toplam, $deger, 2);
            }
        }

        return $toplam;
    }

    private function tarih(string $iso): string
    {
        try {
            return (new \DateTimeImmutable($iso))->format('d.m.Y H:i');
        } catch (\Throwable) {
            return $iso;
        }
    }
}
