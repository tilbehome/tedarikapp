<?php

declare(strict_types=1);

namespace App\Services\Yanit;

use App\Services\Export\ExportException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * `…-IMPORT-RESULT.xlsx` (spec §10): içe aktarım önizlemesinin/sonucunun
 * satır satır durumu, hata kodu ve düzeltme açıklaması. Firmaya ya da
 * meslektaşa "şu hücreleri düzelt" diye geri gönderilir.
 *
 * Hatalı dosyaya DOĞRUDAN YAZILMAZ — orijinal hücre değerleri burada yalnız
 * okunur biçimde yeniden basılır; üretim kaynağı önizleme JSON'udur.
 */
final class ExcelSonucDosyasi
{
    private const RENK = ['uygulanabilir' => 'DFF5E3', 'uyarili' => 'FFF4D6', 'hatali' => 'FADBD8', 'belirsiz' => 'E8DAEF', 'degisiklik_yok' => 'EEF1F5'];

    /**
     * @param array<string, mixed> $onizleme `ExcelIceAktarici::onizle` çıktısı (+ isteğe bağlı `uygulanan: list<string>`)
     */
    public function uret(array $onizleme, string $baslik): string
    {
        $kitap = new Spreadsheet();
        $s = $kitap->getActiveSheet();
        $s->setTitle('IMPORT-RESULT');
        $basliklar = ['Satır kimliği / Row ID', 'Ürün kodu', 'Ürün adı', 'Hücre / Cell', 'Durum / Status', 'Uygulandı mı', 'Değişen alanlar', 'Hatalar / Errors', 'Uyarılar / Warnings', 'Belirsiz / Ambiguous', 'Yeni değer (özet)'];
        $genislik = [38, 10, 30, 16, 14, 12, 30, 60, 40, 40, 60];
        foreach ($basliklar as $i => $b) {
            $harf = chr(ord('A') + $i);
            $s->setCellValueExplicit($harf . '1', $b, DataType::TYPE_STRING);
            $s->getColumnDimension($harf)->setWidth($genislik[$i]);
            $s->getStyle($harf . '1')->getFont()->setBold(true);
            $s->getStyle($harf . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3A5F');
            $s->getStyle($harf . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }
        $uygulanan = array_flip(array_map('strval', is_array($onizleme['uygulanan'] ?? null) ? $onizleme['uygulanan'] : []));
        $r = 2;
        foreach ($onizleme['satirlar'] ?? [] as $satir) {
            $adlar = is_array($satir['urun_adi'] ?? null) ? $satir['urun_adi'] : [];
            $degerler = [
                (string) $satir['rfq_satir_id'],
                (string) ($satir['urun_kodu'] ?? ''),
                (string) ($adlar['tr'] ?? $adlar['en'] ?? $adlar['zh'] ?? ''),
                (string) ($satir['hucre'] ?? ''),
                (string) $satir['grup'],
                isset($uygulanan[(string) $satir['rfq_satir_id']]) ? 'EVET' : 'HAYIR',
                implode(', ', $satir['degisen'] ?? []),
                implode("\n", $satir['hatalar'] ?? []),
                implode("\n", $satir['uyarilar'] ?? []),
                implode("\n", $satir['belirsiz'] ?? []),
                $this->ozet($satir['yeni'] ?? null),
            ];
            foreach ($degerler as $i => $d) {
                $harf = chr(ord('A') + $i);
                $s->setCellValueExplicit($harf . $r, $d, DataType::TYPE_STRING);
                $s->getStyle($harf . $r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            }
            $s->getStyle('A' . $r . ':K' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RENK[(string) $satir['grup']] ?? 'FFFFFF');
            $r++;
        }
        $s->freezePane('A2');
        $kitap->getProperties()->setTitle($baslik);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new ExportException('Sonuç dosyası üretilemedi.');
        }
        (new Xlsx($kitap))->save($stream);
        rewind($stream);
        $bytes = (string) stream_get_contents($stream);
        fclose($stream);
        $kitap->disconnectWorksheets();

        return $bytes;
    }

    /** @param ?array<string, mixed> $yeni */
    private function ozet(?array $yeni): string
    {
        if ($yeni === null) {
            return '';
        }
        $parcalar = [];
        foreach (['yanit_durumu', 'ddp_birim_fiyat', 'para_birimi', 'moq_deger', 'moq_birim', 'termin_suresi', 'termin_birimi', 'termin_baslangici'] as $alan) {
            if (($yeni[$alan] ?? null) !== null && $yeni[$alan] !== '') {
                $parcalar[] = $alan . '=' . (string) $yeni[$alan];
            }
        }
        if (($yeni['kademeler'] ?? []) !== []) {
            $parcalar[] = 'kademe=' . count($yeni['kademeler']);
        }

        return implode(' · ', $parcalar);
    }
}
