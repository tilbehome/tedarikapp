<?php

declare(strict_types=1);

namespace App\Services\Export;

use Mpdf\Mpdf;

/**
 * PDF çıktısı (İE#10 Blok 3) — aynı snapshot'tan, mPDF ile.
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

    /** @param array<string, mixed> $snapshot */
    private function html(array $snapshot): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $list = $snapshot['list'];
        $totals = $snapshot['totals'];

        $rows = '';
        foreach ($snapshot['products'] as $product) {
            $image = '';
            if (is_string($product['main_image'] ?? null) && str_starts_with((string) $product['main_image'], '/media/')) {
                $path = $this->basePath . '/public' . $product['main_image'];
                if (is_file($path)) {
                    $image = '<img src="' . $e($path) . '" style="height:18mm">';
                }
            }
            $rows .= '<tr>'
                . '<td class="c">' . $e($product['sort_no']) . '</td>'
                . '<td class="c">' . $image . '</td>'
                . '<td>' . $e($product['category']) . '</td>'
                . '<td>' . $e($product['name'])
                . (is_string($product['name_original'] ?? null) && $product['name_original'] !== ''
                    ? '<br><span class="zh">' . $e($product['name_original']) . '</span>' : '')
                . '</td>'
                . '<td>' . $e($product['detail'] ?? '') . '</td>'
                . '<td class="c">' . $e($product['qty']) . '</td>'
                . '<td class="r">¥' . $e($product['price_yuan']) . '</td>'
                . '<td class="r">₺' . $e($product['price_yuan_tl']) . '</td>'
                . '<td class="r">$' . $e($product['price_ddp_usd']) . '</td>'
                . '<td class="r">₺' . $e($product['price_ddp_tl']) . '</td>'
                . '</tr>';
        }

        $period = is_string($list['period'] ?? null) && $list['period'] !== ''
            ? ' — ' . mb_strtoupper((string) $list['period'], 'UTF-8') : '';

        return '<style>
                body { font-family: dejavusans; font-size: 8.5pt; }
                h1 { font-size: 12pt; text-align: center; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 0.2mm solid #94a3b8; padding: 1.2mm 1.6mm; vertical-align: middle; }
                th { background: #f1f5f9; }
                .c { text-align: center; } .r { text-align: right; }
                .zh { color: #64748b; font-size: 7.5pt; }
                .toplam td { font-weight: bold; background: #f1f5f9; }
                .kunye { color: #64748b; font-size: 7.5pt; text-align: center; margin-bottom: 3mm; }
            </style>
            <h1>ÇİNDEN DDP SİPARİŞ VERİLECEK ÜRÜNLER LİSTESİ' . $e($period) . '</h1>
            <div class="kunye">' . $e($list['name']) . ' · Kur: ¥ ' . $e($list['yuan_rate']) . ' / $ ' . $e($list['usd_rate'])
            . ' · Üretim: ' . $e($snapshot['generated_at']) . '</div>
            <table>
                <thead><tr>
                    <th>NO</th><th>GÖRSEL</th><th>KATEGORİ</th><th>ÜRÜN ADI</th><th>DETAY</th>
                    <th>MİKTAR</th><th>YUAN</th><th>TL</th><th>DOLAR (DDP)</th><th>TL (DDP)</th>
                </tr></thead>
                <tbody>' . $rows . '
                <tr class="toplam">
                    <td colspan="5">TOPLAM</td>
                    <td class="c">' . $e($totals['qty']) . '</td>
                    <td class="r">¥' . $e($totals['yuan']) . '</td>
                    <td class="r">₺' . $e($totals['yuan_tl']) . '</td>
                    <td class="r">$' . $e($totals['ddp_usd']) . '</td>
                    <td class="r">₺' . $e($totals['ddp_tl']) . '</td>
                </tr>
                </tbody>
            </table>';
    }
}
