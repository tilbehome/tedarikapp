<?php

declare(strict_types=1);

namespace App\Services\Yanit;

use App\Services\Export\ExportException;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * FİRMAYA GİDEN EXCEL ŞABLONU (spec `excel-gelgit-spec.md` §1-§6).
 *
 * Beş sayfa: START (üç dilli kullanım) · QUOTATION (RFQ satırları + yanıt
 * alanları) · PRICE_TIERS (kademeler) · VALIDATION (çok gizli listeler) ·
 * MANIFEST (çok gizli: şema sürümü, tur/snapshot kimliği, satır sayısı,
 * satır imzaları).
 *
 * GÜVENLİK SINIRI DEĞİL, YANLIŞ DÜZENLEME ÖNLEMİ: sayfa koruması ve parola
 * kazara değişikliği engeller; dosyada link, 6 haneli anahtar ya da sır YOKTUR.
 * Bütün dış metinler STRING tipinde yazılır — "=" ile başlayan ürün adı formül
 * olmaz (§6.8). Makro, dış bağlantı, nesne yok (§1).
 *
 * Mevcut taslak yanıt varsa yanıt hücreleri ÖNCEDEN DOLU gelir: firma
 * kaldığı yerden devam eder, boş şablona sıfırdan yazmaz.
 */
final class ExcelSablonu
{
    private const LACIVERT = '1F3A5F';
    private const ACIK_GRI = 'EEF1F5';
    private const ZORUNLU = 'FFF4D6';
    private const KORUMA_PAROLASI = 'tedarikapp';

    public function __construct(private readonly SatirImzasi $imza)
    {
    }

    /**
     * @param array<string, mixed>                $tur
     * @param list<array<string, mixed>>          $rfqSatirlari `TeklifTuruRepository::rfqSatirlari` satırları
     * @param array<string, array<string, mixed>> $mevcut       rfq_satir_id → kanonik yanıt (varsa)
     */
    public function uret(array $tur, array $rfqSatirlari, array $mevcut, DateTimeImmutable $now, string $dil = 'en'): string
    {
        $turId = (int) $tur['id'];
        $snapshotId = (int) $tur['rfq_snapshot_id'];
        $disaAktarim = $now->format('Y-m-d H:i:s');

        $kitap = new Spreadsheet();
        $kitap->getProperties()->setCreator('TedarikApp')->setTitle('RFQ ' . (string) ($tur['liste_adi'] ?? '') . ' · R' . (int) ($tur['tur_no'] ?? 1));

        $this->start($kitap->getActiveSheet(), $tur, $now);
        $quotation = $kitap->createSheet();
        $this->quotation($quotation, $tur, $rfqSatirlari, $mevcut, $dil);
        $tiers = $kitap->createSheet();
        $this->tiers($tiers, $tur, $rfqSatirlari, $mevcut);
        $validation = $kitap->createSheet();
        $this->validation($validation);
        $manifest = $kitap->createSheet();
        $this->manifest($manifest, $tur, $rfqSatirlari, $disaAktarim);

        $this->dogrulamalar($quotation, $tiers, count($rfqSatirlari));
        $kitap->setActiveSheetIndex(0);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new ExportException('Excel şablonu üretilemedi.');
        }
        (new Xlsx($kitap))->save($stream);
        rewind($stream);
        $bytes = (string) stream_get_contents($stream);
        fclose($stream);
        $kitap->disconnectWorksheets();

        return $bytes;
    }

    /** @param array<string, mixed> $tur */
    private function start(Worksheet $s, array $tur, DateTimeImmutable $now): void
    {
        $s->setTitle(ExcelSema::SAYFA_START);
        $s->getColumnDimension('A')->setWidth(110);
        $gun = $tur['gecerlilik_gun'] ?? null;
        $sonTarih = $tur['valid_until'] ?? null;
        $satirlar = [
            'TEDARİKAPP — TEKLİF FORMU / QUOTATION FORM / 报价表',
            sprintf('Liste / List / 清单: %s · Firma / Supplier / 供应商: %s · Tur / Round / 轮次: R%d', (string) ($tur['liste_adi'] ?? ''), (string) ($tur['firma_adi'] ?? ''), (int) ($tur['tur_no'] ?? 1)),
            sprintf('Oluşturma / Exported / 导出: %s · Son tarih / Deadline / 截止: %s', $now->format('Y-m-d H:i'), is_string($sonTarih) ? $sonTarih : '-'),
            '',
            'TR — Bağlantı/anahtar bu dosyada bulunmaz; portal anahtarı ayrı kanaldadır. Teslim: DDP Türkiye, TÜRKİYE KDV DAHİL birim fiyat. '
            . 'Termin başlangıcı: yazılı sipariş onayı ve kararlaştırılan ön koşulların tamamlanması. '
            . sprintf('Teklif geçerliliği: %s gün. İç karşılaştırma için kullanılan kur snapshot\'ı firmanın ham fiyatını değiştirmez. ', $gun === null ? '-' : (string) $gun)
            . 'Adımlar: satırı bul → durum seç → zorunlu alanları doldur → kaydedip geri gönder. Başka firmanın fiyatı bu dosyada görünmez.',
            'EN — This file contains no link or access key; the portal key is sent separately. Delivery: DDP Türkiye, unit price INCLUDING TURKISH VAT. '
            . 'Lead time starts at written order confirmation and agreed preconditions. '
            . sprintf('Quotation validity: %s days. The exchange-rate snapshot used for internal comparison does not change your raw price. ', $gun === null ? '-' : (string) $gun)
            . 'Steps: find the row → choose status → fill required fields → save and send back. Other suppliers\' prices are not visible here.',
            '中文 — 本文件不含链接或访问密钥；门户密钥另行发送。交付条件：DDP 土耳其，单价含土耳其增值税。'
            . '交期自书面订单确认及约定前提条件完成起算。'
            . sprintf('报价有效期：%s 天。用于内部比较的汇率快照不会改变贵司的原始报价。', $gun === null ? '-' : (string) $gun)
            . '步骤：找到行 → 选择状态 → 填写必填项 → 保存并回传。本文件不显示其他供应商的价格。',
            '',
            'Yanıt durumu / Status / 状态: found = bulundu / 有货 · not_found = bulunamadı / 无货 · alternative_available = alternatif var / 有替代',
            'Termin başlangıcı / Lead time start / 起算点: order_confirmation · deposit_received · sample_approval · artwork_approval · custom (R sütununa açıklama / describe in column R / 在R列说明)',
            'Kademeli fiyat / Price tiers / 阶梯价: PRICE_TIERS sayfası / sheet / 工作表 — satır kimliğini A sütunundan seçin / pick the row ID in column A / 在A列选择行ID',
        ];
        foreach ($satirlar as $i => $metin) {
            $s->setCellValueExplicit('A' . ($i + 1), $metin, DataType::TYPE_STRING);
            $s->getStyle('A' . ($i + 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $s->getStyle('A5:A7')->getAlignment()->setWrapText(true);
        foreach ([5, 6, 7] as $r) {
            $s->getRowDimension($r)->setRowHeight(78);
        }
        $this->koru($s);
    }

    /**
     * @param array<string, mixed>                $tur
     * @param list<array<string, mixed>>          $rfqSatirlari
     * @param array<string, array<string, mixed>> $mevcut
     */
    private function quotation(Worksheet $s, array $tur, array $rfqSatirlari, array $mevcut, string $dil): void
    {
        $s->setTitle(ExcelSema::SAYFA_QUOTATION);
        $this->basliklar($s, ExcelSema::QUOTATION);
        $s->getColumnDimension('B')->setVisible(false);
        $s->getColumnDimension('C')->setVisible(false);

        $turId = (int) $tur['id'];
        $snapshotId = (int) $tur['rfq_snapshot_id'];
        $yanit = ExcelSema::yanitSutunlari();

        foreach ($rfqSatirlari as $i => $r) {
            $satir = ExcelSema::VERI_BASLANGIC + $i;
            $id = (string) $r['rfq_satir_id'];
            $adlar = json_decode((string) $r['urun_adi_json'], true);
            $ad = is_array($adlar) ? (string) ($adlar[$dil] ?? $adlar['en'] ?? $adlar['tr'] ?? '') : '';
            $kaynak = $r['kaynak_urun_json'] === null ? null : json_decode((string) $r['kaynak_urun_json'], true);
            $url = is_array($kaynak) && is_string($kaynak['url'] ?? null) && str_starts_with($kaynak['url'], 'https://') ? $kaynak['url'] : '';
            $not = $r['alici_notu_json'] === null ? null : json_decode((string) $r['alici_notu_json'], true);
            $varyant = $r['talep_varyant_json'] === null ? null : (string) $r['talep_varyant_json'];
            $miktar = YanitDonusturucu::sade((string) $r['talep_miktar']);

            $this->metin($s, 'A' . $satir, $id);
            $this->metin($s, 'B' . $satir, (string) $turId);
            $this->metin($s, 'C' . $satir, $this->imza->satir($turId, $snapshotId, $id, (string) $r['talep_miktar'], $varyant));
            $this->metin($s, 'D' . $satir, (string) ($r['urun_kodu'] ?? ''));
            $this->metin($s, 'E' . $satir, $ad);
            if (is_array($adlar) && ($adlar['zh'] ?? '') !== '' && $adlar['zh'] !== $ad) {
                $s->getComment('E' . $satir)->getText()->createTextRun((string) $adlar['zh']);
            }
            $this->metin($s, 'F' . $satir, $url);
            if ($url !== '') {
                $s->getCell('F' . $satir)->getHyperlink()->setUrl($url);
            }
            $this->metin($s, 'G' . $satir, (string) $varyant);
            $this->metin($s, 'H' . $satir, $miktar);
            $this->metin($s, 'I' . $satir, (string) $r['talep_birim']);
            $this->metin($s, 'J' . $satir, is_array($not) ? (string) ($not[$dil] ?? $not['tr'] ?? '') : '');
            $s->getStyle('A' . $satir . ':J' . $satir)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ACIK_GRI);

            $m = $mevcut[$id] ?? null;
            if ($m !== null) {
                $this->yanitiYaz($s, $satir, $m, $yanit);
            }
            foreach (['yanit_durumu', 'ddp_birim_fiyat_kdv_dahil', 'para_birimi', 'ddp_turkiye_kdv_dahil_onayi', 'moq_deger', 'termin_baslangici', 'termin_suresi'] as $zorunlu) {
                $s->getStyle($yanit[$zorunlu] . $satir)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ZORUNLU);
            }
            $s->getStyle('K' . $satir . ':AE' . $satir)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
            $this->metin($s, 'AF' . $satir, $m === null ? '' : 'OK');
        }
        $s->freezePane('K' . ExcelSema::VERI_BASLANGIC);
        $s->setAutoFilter('A1:AF' . (ExcelSema::VERI_BASLANGIC + max(0, count($rfqSatirlari) - 1)));
        $s->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $this->koru($s);
    }

    /**
     * @param array<string, mixed> $m
     * @param array<string, string> $yanit
     */
    private function yanitiYaz(Worksheet $s, int $satir, array $m, array $yanit): void
    {
        $esleme = [
            'yanit_durumu' => 'yanit_durumu', 'ddp_birim_fiyat' => 'ddp_birim_fiyat_kdv_dahil', 'para_birimi' => 'para_birimi',
            'moq_deger' => 'moq_deger', 'moq_birim' => 'moq_birim', 'termin_baslangici' => 'termin_baslangici',
            'termin_baslangici_aciklamasi' => 'termin_baslangici_aciklamasi', 'termin_suresi' => 'termin_suresi', 'termin_birimi' => 'termin_birimi',
            'koli_ici_adet' => 'koli_ici_adet', 'koli_uzunluk_cm' => 'koli_uzunluk_cm', 'koli_genislik_cm' => 'koli_genislik_cm', 'koli_yukseklik_cm' => 'koli_yukseklik_cm',
            'koli_cbm' => 'koli_cbm', 'koli_brut_kg' => 'koli_brut_kg', 'koli_net_kg' => 'koli_net_kg', 'ambalaj' => 'ambalaj', 'firma_notu' => 'firma_notu',
            'alternatif_baglanti' => 'alternatif_urun_baglantisi', 'alternatif_aciklama' => 'alternatif_aciklamasi',
        ];
        foreach ($esleme as $kanonik => $sutun) {
            $deger = $m[$kanonik] ?? null;
            if ($deger === null || $deger === '' || ($kanonik === 'yanit_durumu' && $deger === 'unanswered')) {
                continue;
            }
            $this->metin($s, $yanit[$sutun] . $satir, (string) $deger);
        }
        if (($m['ddp_kdv_dahil_onayi'] ?? null) !== null) {
            $this->metin($s, $yanit['ddp_turkiye_kdv_dahil_onayi'] . $satir, $m['ddp_kdv_dahil_onayi'] ? 'YES' : 'NO');
        }
    }

    /**
     * @param array<string, mixed>                $tur
     * @param list<array<string, mixed>>          $rfqSatirlari
     * @param array<string, array<string, mixed>> $mevcut
     */
    private function tiers(Worksheet $s, array $tur, array $rfqSatirlari, array $mevcut): void
    {
        $s->setTitle(ExcelSema::SAYFA_TIERS);
        $this->basliklar($s, ExcelSema::TIERS);
        $s->getColumnDimension('B')->setVisible(false);
        $satir = ExcelSema::VERI_BASLANGIC;
        foreach ($rfqSatirlari as $r) {
            $id = (string) $r['rfq_satir_id'];
            foreach ($mevcut[$id]['kademeler'] ?? [] as $k) {
                $this->metin($s, 'A' . $satir, $id);
                $this->metin($s, 'B' . $satir, (string) $tur['id']);
                $this->metin($s, 'C' . $satir, (string) $k['min_adet']);
                $this->metin($s, 'D' . $satir, $k['max_adet'] === null ? '' : (string) $k['max_adet']);
                $this->metin($s, 'E' . $satir, (string) $k['birim_fiyat']);
                $this->metin($s, 'F' . $satir, (string) ($k['para_birimi'] ?? ''));
                $this->metin($s, 'H' . $satir, 'OK');
                $satir++;
            }
        }
        $son = $satir + ExcelSema::BOS_KADEME_SATIRI;
        for ($r = ExcelSema::VERI_BASLANGIC; $r < $son; $r++) {
            $this->metin($s, 'B' . $r, (string) $tur['id']);
            $s->getStyle('A' . $r . ':G' . $r)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
            $s->getStyle('B' . $r)->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        }
        $s->freezePane('A' . ExcelSema::VERI_BASLANGIC);
        $this->koru($s);
    }

    private function validation(Worksheet $s): void
    {
        $s->setTitle(ExcelSema::SAYFA_VALIDATION);
        $listeler = [
            'A' => ['yanit_durumu', 'found', 'not_found', 'alternative_available'],
            'B' => ['para_birimi', ...YanitAlanKurallari::PARA_BIRIMLERI],
            'C' => ['onay', 'YES', 'NO'],
            'D' => ['moq_birim', ...YanitAlanKurallari::MOQ_BIRIMLERI],
            'E' => ['termin_baslangici', ...YanitAlanKurallari::TERMIN_BASLANGICLARI],
            'F' => ['termin_birimi', ...YanitAlanKurallari::TERMIN_BIRIMLERI],
        ];
        foreach ($listeler as $harf => $degerler) {
            foreach ($degerler as $i => $deger) {
                $this->metin($s, $harf . ($i + 1), $deger);
            }
        }
        $s->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $this->koru($s);
    }

    /**
     * @param array<string, mixed> $tur
     * @param list<array<string, mixed>> $rfqSatirlari
     */
    private function manifest(Worksheet $s, array $tur, array $rfqSatirlari, string $disaAktarim): void
    {
        $s->setTitle(ExcelSema::SAYFA_MANIFEST);
        $turId = (int) $tur['id'];
        $snapshotId = (int) $tur['rfq_snapshot_id'];
        $alanlar = [
            ['schema_version', (string) ExcelSema::SURUM],
            ['exported_at', $disaAktarim],
            ['supplier_round_id', (string) $turId],
            ['rfq_snapshot_id', (string) $snapshotId],
            ['row_count', (string) count($rfqSatirlari)],
            ['manifest_signature', $this->imza->manifest($turId, $snapshotId, count($rfqSatirlari), $disaAktarim)],
        ];
        foreach ($alanlar as $i => [$anahtar, $deger]) {
            $this->metin($s, 'A' . ($i + 1), $anahtar);
            $this->metin($s, 'B' . ($i + 1), $deger);
        }
        $satir = count($alanlar) + 2;
        $this->metin($s, 'A' . $satir, 'rfq_satir_id');
        $this->metin($s, 'B' . $satir, 'signature');
        foreach ($rfqSatirlari as $r) {
            $satir++;
            $varyant = $r['talep_varyant_json'] === null ? null : (string) $r['talep_varyant_json'];
            $this->metin($s, 'A' . $satir, (string) $r['rfq_satir_id']);
            $this->metin($s, 'B' . $satir, $this->imza->satir($turId, $snapshotId, (string) $r['rfq_satir_id'], (string) $r['talep_miktar'], $varyant));
        }
        $s->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $this->koru($s);
    }

    /** Excel veri doğrulaması: yalnız ilk kullanıcı yardımı (§4.2); sunucu aynı kuralları yeniden uygular. */
    private function dogrulamalar(Worksheet $q, Worksheet $t, int $satirSayisi): void
    {
        $son = ExcelSema::VERI_BASLANGIC + max(0, $satirSayisi - 1);
        $v = ExcelSema::SAYFA_VALIDATION;
        $listeler = [
            'yanit_durumu' => '$' . $v . '.$A$2:$A$4',
            'para_birimi' => '$' . $v . '.$B$2:$B$' . (count(YanitAlanKurallari::PARA_BIRIMLERI) + 1),
            'ddp_turkiye_kdv_dahil_onayi' => '$' . $v . '.$C$2:$C$3',
            'moq_birim' => '$' . $v . '.$D$2:$D$' . (count(YanitAlanKurallari::MOQ_BIRIMLERI) + 1),
            'termin_baslangici' => '$' . $v . '.$E$2:$E$' . (count(YanitAlanKurallari::TERMIN_BASLANGICLARI) + 1),
            'termin_birimi' => '$' . $v . '.$F$2:$F$' . (count(YanitAlanKurallari::TERMIN_BIRIMLERI) + 1),
        ];
        $yanit = ExcelSema::yanitSutunlari();
        foreach ($listeler as $alan => $kaynak) {
            $harf = $yanit[$alan];
            $this->liste($q, $harf . ExcelSema::VERI_BASLANGIC . ':' . $harf . $son, str_replace('$' . $v . '.', $v . '!', $kaynak));
        }
        $this->sayi($q, 'L' . ExcelSema::VERI_BASLANGIC . ':L' . $son, DataValidation::TYPE_DECIMAL, DataValidation::OPERATOR_GREATERTHAN, '0');
        $this->sayi($q, 'O' . ExcelSema::VERI_BASLANGIC . ':O' . $son, DataValidation::TYPE_DECIMAL, DataValidation::OPERATOR_GREATERTHANOREQUAL, '1');
        $this->sayi($q, 'S' . ExcelSema::VERI_BASLANGIC . ':S' . $son, DataValidation::TYPE_WHOLE, DataValidation::OPERATOR_BETWEEN, '1', '365');
        $this->sayi($q, 'U' . ExcelSema::VERI_BASLANGIC . ':U' . $son, DataValidation::TYPE_WHOLE, DataValidation::OPERATOR_BETWEEN, '1', '1000000');

        $tSon = ExcelSema::VERI_BASLANGIC + ExcelSema::BOS_KADEME_SATIRI + $satirSayisi * 5;
        $this->liste($t, 'A' . ExcelSema::VERI_BASLANGIC . ':A' . $tSon, ExcelSema::SAYFA_QUOTATION . '!$A$' . ExcelSema::VERI_BASLANGIC . ':$A$' . $son);
        $this->liste($t, 'F' . ExcelSema::VERI_BASLANGIC . ':F' . $tSon, $v . '!$B$2:$B$' . (count(YanitAlanKurallari::PARA_BIRIMLERI) + 1));
        $this->sayi($t, 'C' . ExcelSema::VERI_BASLANGIC . ':C' . $tSon, DataValidation::TYPE_DECIMAL, DataValidation::OPERATOR_GREATERTHANOREQUAL, '1');
        $this->sayi($t, 'E' . ExcelSema::VERI_BASLANGIC . ':E' . $tSon, DataValidation::TYPE_DECIMAL, DataValidation::OPERATOR_GREATERTHAN, '0');
    }

    private function liste(Worksheet $s, string $aralik, string $formul): void
    {
        $d = $s->getDataValidation($aralik);
        $d->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
            ->setErrorTitle('Liste dışı değer / Not in list / 不在列表中')
            ->setError('Listeden seçin / Choose from the list / 请从列表中选择')
            ->setFormula1($formul);
    }

    private function sayi(Worksheet $s, string $aralik, string $tip, string $operator, string $f1, ?string $f2 = null): void
    {
        $d = $s->getDataValidation($aralik);
        $d->setType($tip)->setOperator($operator)->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)->setShowErrorMessage(true)
            ->setErrorTitle('Geçersiz sayı / Invalid number / 数值无效')
            ->setError('Sınırlar dışında / Out of range / 超出范围')
            ->setFormula1($f1);
        if ($f2 !== null) {
            $d->setFormula2($f2);
        }
    }

    /** @param array<string, array{0: string, 1: string, 2: string, 3: string, 4: int, 5: bool}> $sutunlar */
    private function basliklar(Worksheet $s, array $sutunlar): void
    {
        foreach ($sutunlar as $harf => $tanim) {
            $s->getColumnDimension($harf)->setWidth($tanim[4]);
            $this->metin($s, $harf . '1', ExcelSema::baslik($tanim));
            $s->getComment($harf . '1')->getText()->createTextRun($tanim[0]);
            $stil = $s->getStyle($harf . '1');
            $stil->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tanim[5] ? self::LACIVERT : '2E6F95');
            $stil->getFont()->getColor()->setRGB('FFFFFF');
            $stil->getFont()->setBold(true);
            $stil->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }
        $s->getRowDimension(1)->setRowHeight(48);
    }

    private function metin(Worksheet $s, string $hucre, string $deger): void
    {
        $s->setCellValueExplicit($hucre, $deger, DataType::TYPE_STRING);
    }

    private function koru(Worksheet $s): void
    {
        $p = $s->getProtection();
        $p->setSheet(true)->setPassword(self::KORUMA_PAROLASI);
        $p->setInsertRows(true)->setDeleteRows(true)->setFormatCells(true)->setSort(true)->setAutoFilter(false);
    }
}
