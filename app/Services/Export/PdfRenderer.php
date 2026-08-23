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
                . 'TedarikApp — Ürün Tedarik Asistanı · Ürün adı kaynak platformdaki ilana köprülüdür'
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
            // REV4: ürün adı DÜZ KOYU METİNDİR. Köprü İŞLEVİ korunur (tıklanır)
            // ama mavi/altı çizili görünüm YOK — belge bir web sayfası değildir.
            $ad = $e($product['name']);
            if (is_string($product['url'] ?? null) && $product['url'] !== '') {
                $ad = '<a class="adlink" href="' . $e($product['url']) . '">' . $ad . '</a>';
            }
            // İE#14 A1: ad ile orijinal AYNIYSA ikinci satır BASILMAZ (ortak kural).
            $orijinalMetin = \App\Services\Translation\ProductNaming::originalOf($product);
            $orijinal = $orijinalMetin === null ? '' : '<br><span class="zh">' . $e($orijinalMetin) . '</span>';

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
                . '<td class="kucuk">' . $e($product['variant'] ?? '') . '</td>'
                . '<td class="kucuk soluk">' . $e($product['category'] ?? '') . '</td>'
                . '<td class="c"><span class="kaynak">'
                . $e(TemplateV2::platformLabel($product['platform'] ?? null)) . '</span></td>'
                // REV4: rozet TEK SATIR ve SOLUK ZEMİN — renkli dolgular sayfayı
                // kirletiyordu; durum yine okunur, göz yorulmaz.
                . '<td class="c"><span class="rozet">'
                . $e(trim(str_replace("\n", ' ', $rozet), '● ')) . '</span></td>'
                . '<td class="kucuk soluk">' . $e($product['note'] ?? '') . '</td>'
                . '<td class="c">' . $e($product['qty']) . '</td>'
                // İE#17 G3: girilmemiş fiyat boş basılır — "0.00" yanlış bilgidir.
                . '<td class="r">' . self::para($product['price_yuan'] ?? null, '¥', $e) . '</td>'
                . '<td class="r">' . self::para($product['price_yuan_tl'] ?? null, '₺', $e) . '</td>'
                . '<td class="r">' . self::para($product['price_ddp_usd'] ?? null, '$', $e) . '</td>'
                . '<td class="r">' . self::para($product['price_ddp_tl'] ?? null, '₺', $e) . '</td>'
                . $kar
                . '</tr>';
        }

        $baslik = '';
        $sutunlar = TemplateV2::COLUMNS;
        unset($sutunlar['A']);
        if ($icKopya) {
            $sutunlar += TemplateV2::INTERNAL_COLUMNS;
        }
        // REV4 (İE#18 G3, K55 sapması PM ONAYLI): PDF başlıkları TEK SATIR
        // TÜRKÇE. Üç dilli kademe 13 sütunda başlık bloğunu üçe katlıyor ve
        // okunaksız kılıyordu; EKRANDA üç dil AYNEN kalır (Çinli muhatap sütunu
        // orada tanır). Sayı sütunları sağa hizalanır.
        $sagaHizali = ['No' => 'c', 'Miktar' => 'r', 'Vitrin Fiyatı' => 'r', 'Yaklaşık ürün bedeli (₺)' => 'r',
            'DDP $' => 'r', 'DDP ₺' => 'r', 'Hedef Satış (₺)' => 'r', 'Birim Kâr (₺)' => 'r',
            'Toplam Kâr (₺)' => 'r', 'Görsel' => 'c', 'Kaynak' => 'c', 'Durum' => 'c'];
        foreach ($sutunlar as [$genislik, $tr]) {
            $sinif = $sagaHizali[$tr] ?? '';
            $baslik .= '<th class="' . $sinif . '" style="width:' . $genislik . '%">'
                . $e(mb_strtoupper($tr, 'UTF-8')) . '</th>';
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
                /* ══ pdf-rev4 (İE#18 G3) — GÖVDE BEYAZ ══════════════════════
                 * Ürün Sahibi bulgusu: "şablon ve tasarım çok kötü, renkler
                 * çarpık." Kök neden: lacivert yalnız antette değil TABLO
                 * BAŞLIĞINDA ve TOPLAM BANDINDA da vardı; sayfa üç ayrı koyu
                 * bloka bölünüyordu. Rev4: koyu renk YALNIZ antet bandında,
                 * altın YALNIZ onun altındaki ince ayraçta; tablo açık gri
                 * başlık + beyaz gövde + zebra ile okunur.
                 * K55 yapısal kuralları (sütun seti, yatay A4, başlık tekrarı,
                 * Sayfa X/Y) DEĞİŞMEDİ; yalnız görsel katman yenilendi.
                 */
                body { font-family: dejavusans; font-size: 7.6pt; color: #101828; background: #fff; }

                /* Antet bandı — tek koyu yüzey */
                table.bant { width: 100%; border-collapse: collapse; background: #' . TemplateV2::LACIVERT . '; }
                .bant td { padding: 4mm 5mm 3.4mm 5mm; vertical-align: top; }
                .bant .marka { font-size: 8pt; color: #A9BBDD; letter-spacing: 0.6pt; }
                .bant .baslik { font-size: 15pt; font-weight: bold; color: #fff; margin-top: 0.6mm; }
                .bant .antet { font-size: 7pt; color: #C3D0E8; margin-top: 1.4mm; }
                /* Sağ: hizalı etiket-değer ızgarası — EKRAN HERO DÜZENİYLE AYNI (G2) */
                table.kimlik { border-collapse: collapse; float: right; }
                table.kimlik td { padding: 0 0 0 7mm; text-align: right; }
                .k-etiket { font-size: 5.8pt; color: #8FA5CC; letter-spacing: 0.5pt; }
                .k-deger { font-size: 8.6pt; color: #fff; font-weight: bold; }
                .k-alt { font-size: 6.6pt; color: #C3D0E8; }
                .altin { background: #' . TemplateV2::ALTIN . '; height: 0.9mm; font-size: 0; line-height: 0; }

                /* KPI şeridi: beyaz gövde üstünde çerçeveli, TEK SATIR */
                .kpi { width: 100%; border-collapse: collapse; margin: 3mm 0 3.4mm 0; }
                .kpi td { border: 0.2mm solid #E6EAF1; padding: 2mm 2.4mm; text-align: center; background: #fff; }
                .kpi .etiket { color: #98A2B3; font-size: 5.9pt; font-weight: bold; letter-spacing: 0.4pt; }
                .kpi .deger { color: #' . TemplateV2::LACIVERT . '; font-size: 10.5pt; font-weight: bold; }

                /* Tablo: açık gri başlık + koyu metin */
                table.veri { width: 100%; border-collapse: collapse; }
                table.veri th { background: #F1F4F9; color: #334155; font-size: 6.4pt; font-weight: bold;
                    letter-spacing: 0.3pt; padding: 2.2mm 1.2mm; border-bottom: 0.3mm solid #D3DAE6; text-align: left; }
                table.veri th.c { text-align: center; } table.veri th.r { text-align: right; }
                table.veri td { padding: 2.2mm 1.2mm; border-bottom: 0.15mm solid #EDF0F6; vertical-align: top; }
                .zebra td { background: #FAFBFD; }
                .c { text-align: center; } .r { text-align: right; }
                .no { color: #98A2B3; font-weight: bold; font-size: 7pt; }
                .ad { font-weight: bold; color: #101828; font-size: 7.6pt; }
                /* Köprü İŞLEVİ kalır, GÖRÜNÜMÜ düz metindir. */
                .ad .adlink { color: #101828; text-decoration: none; }
                .zh { color: #98A2B3; font-size: 6.4pt; font-weight: normal; }
                .kucuk { font-size: 6.8pt; } .soluk { color: #5B6472; }
                .kaynak { font-size: 6.2pt; color: #5B6472; background: #F1F4F9;
                    padding: 0.5mm 1.4mm; border-radius: 1mm; }
                .rozet { font-size: 6.2pt; color: #334155; background: #F1F4F9;
                    padding: 0.5mm 1.6mm; border-radius: 1mm; }
                .video { color: #' . TemplateV2::VIDEO . '; font-size: 7pt; font-weight: bold; }

                /* Toplam: koyu bant DEĞİL, açık zemin + lacivert üst çizgi */
                .toplam td { background: #F1F4F9; color: #' . TemplateV2::LACIVERT . '; font-weight: bold;
                    font-size: 8pt; padding: 2.4mm 1.2mm; border-top: 0.3mm solid #' . TemplateV2::LACIVERT . '; }
                .toplam .not { color: #5B6472; font-weight: normal; font-size: 6.8pt; text-align: right; }
                .kar { color: #92400e; }
                .sart { font-size: 6.6pt; color: #5B6472; margin-top: 3mm; }
                .imza { text-align: right; font-weight: bold; color: #101828; }
                .bos { color: #C9D0DC; }
            </style>
            <table class="bant"><tr>
                <td style="width:58%">
                    <div class="marka">' . $e($this->markaEtiketi($antet)) . '</div>
                    <div class="baslik">' . $e($list['name']) . '</div>
                    <div class="antet">' . $e(TemplateV2::headerLine($antet, $list)) . '</div>
                </td>
                <td style="width:42%">' . $qr . '
                    <table class="kimlik"><tr>
                        <td>
                            <div class="k-etiket">BELGE</div>
                            <div class="k-deger">' . $e($kod) . '</div>
                            <div class="k-alt">Rev ' . $e($revizyon) . '</div>
                        </td>
                        <td>
                            <div class="k-etiket">KUR · ' . ($list['rate_locked_at'] === null ? 'GÜNCEL' : 'KİLİTLİ') . '</div>
                            <div class="k-deger">¥ ' . $e($list['yuan_rate']) . '</div>
                            <div class="k-alt">$ ' . $e($list['usd_rate']) . '</div>
                        </td>
                        <td>
                            <div class="k-etiket">OLUŞTURULMA</div>
                            <div class="k-deger">' . $e($this->tarih((string) $snapshot['generated_at'], 'd.m.Y')) . '</div>
                            <div class="k-alt">' . $e($this->tarih((string) $snapshot['generated_at'], 'H:i')) . '</div>
                        </td>
                    </tr></table>
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
                    <td class="r">' . $e($totals['qty']) . '</td>
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

    /**
     * REV4 antet üst satırı: firma adı BÜYÜK HARF (yoksa ürün adı).
     *
     * @param array{company?: string|null, web?: string|null, email?: string|null, prepared_by?: string|null} $antet
     */
    private function markaEtiketi(array $antet): string
    {
        $firma = $antet['company'] ?? null;

        return is_string($firma) && trim($firma) !== ''
            ? mb_strtoupper(trim($firma), 'UTF-8')
            : 'TEDARİKAPP';
    }

    private function tarih(string $iso, string $bicim = 'd.m.Y H:i'): string
    {
        try {
            return (new \DateTimeImmutable($iso))->format($bicim);
        } catch (\Throwable) {
            return $iso;
        }
    }

    /**
     * Girilmemiş fiyat hücresi BOŞ kalır (İE#17 G3); para simgesi de basılmaz.
     *
     * Canlı kusur: DDP girilmemiş ürünlerde belgeye "$0.00" basılıyordu — firma
     * bunu "bedeli sıfır" diye okuyabilir. Yokluğu sıfır göstermek yanlış bilgidir.
     *
     * @param callable(mixed): string $e
     */
    private static function para(mixed $tutar, string $simge, callable $e): string
    {
        return TemplateV2::girilmis($tutar) ? $e($simge) . $e($tutar) : '';
    }

}
