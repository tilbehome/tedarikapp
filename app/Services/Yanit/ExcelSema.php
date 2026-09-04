<?php

declare(strict_types=1);

namespace App\Services\Yanit;

/**
 * EXCEL GEL-GİT ŞEMASI — `excel-gelgit-spec.md` §1, §4, §5'in tek kopyası.
 *
 * Yazıcı (`ExcelSablonu`) ve okuyucu (`ExcelIceAktarici`) sütun harflerini
 * BURADAN alır; iki tarafın ayrı listesi olsaydı bir gün biri kayardı ve
 * "MOQ sütunu termin sütununa yazıldı" hatası sessizce geçerdi.
 */
final class ExcelSema
{
    public const SURUM = 1;
    public const SAYFA_START = 'START';
    public const SAYFA_QUOTATION = 'QUOTATION';
    public const SAYFA_TIERS = 'PRICE_TIERS';
    public const SAYFA_VALIDATION = 'VALIDATION';
    public const SAYFA_MANIFEST = 'MANIFEST';

    public const VERI_BASLANGIC = 2;
    public const EN_COK_SATIR = 500;
    public const BOS_KADEME_SATIRI = 60;

    /**
     * QUOTATION sütunları: harf → [makine adı, TR, EN, ZH, genişlik, kilitli mi].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: int, 5: bool}>
     */
    public const QUOTATION = [
        'A' => ['rfq_satir_id', 'Satır kimliği', 'Row ID', '行ID', 14, true],
        'B' => ['supplier_round_id', 'Tur kimliği', 'Round ID', '轮次ID', 10, true],
        'C' => ['satir_imzasi', 'Satır imzası', 'Row signature', '行签名', 12, true],
        'D' => ['urun_kodu', 'Ürün kodu', 'Product code', '产品编号', 11, true],
        'E' => ['urun_adi', 'Ürün adı', 'Product name', '产品名称', 34, true],
        'F' => ['kaynak_urun_url', 'Kaynak bağlantı', 'Source URL', '来源链接', 26, true],
        'G' => ['talep_edilen_varyant', 'İstenen varyant', 'Requested variant', '所需规格', 16, true],
        'H' => ['talep_miktari', 'İstenen miktar', 'Requested qty', '需求数量', 10, true],
        'I' => ['talep_birimi', 'Birim', 'Unit', '单位', 7, true],
        'J' => ['alici_satir_notu', 'Alıcı notu', 'Buyer note', '买方备注', 22, true],
        'K' => ['yanit_durumu', 'Yanıt durumu', 'Response status', '回复状态', 20, false],
        'L' => ['ddp_birim_fiyat_kdv_dahil', 'KDV dâhil DDP birim fiyat', 'DDP unit price incl. Turkish VAT', '含土耳其增值税的DDP单价', 16, false],
        'M' => ['para_birimi', 'Para birimi', 'Currency', '币种', 9, false],
        'N' => ['ddp_turkiye_kdv_dahil_onayi', 'DDP Türkiye KDV dâhil onayı', 'DDP incl. Turkish VAT confirmed', '确认含土耳其增值税DDP', 12, false],
        'O' => ['moq_deger', 'MOQ', 'MOQ', '起订量', 9, false],
        'P' => ['moq_birim', 'MOQ birimi', 'MOQ unit', '起订量单位', 9, false],
        'Q' => ['termin_baslangici', 'Termin başlangıcı', 'Lead time starts at', '交期起算点', 18, false],
        'R' => ['termin_baslangici_aciklamasi', 'Başlangıç açıklaması (özel)', 'Start description (custom)', '起算说明(自定义)', 20, false],
        'S' => ['termin_suresi', 'Termin süresi', 'Lead time', '交期', 9, false],
        'T' => ['termin_birimi', 'Termin birimi', 'Lead time unit', '交期单位', 13, false],
        'U' => ['koli_ici_adet', 'Koli içi adet', 'Pcs per carton', '每箱数量', 9, false],
        'V' => ['koli_uzunluk_cm', 'Koli uzunluk (cm)', 'Carton length (cm)', '外箱长(cm)', 9, false],
        'W' => ['koli_genislik_cm', 'Koli genişlik (cm)', 'Carton width (cm)', '外箱宽(cm)', 9, false],
        'X' => ['koli_yukseklik_cm', 'Koli yükseklik (cm)', 'Carton height (cm)', '外箱高(cm)', 9, false],
        'Y' => ['koli_cbm', 'Koli CBM', 'Carton CBM', '每箱体积', 9, false],
        'Z' => ['koli_brut_kg', 'Brüt kg', 'Gross kg', '毛重kg', 9, false],
        'AA' => ['koli_net_kg', 'Net kg', 'Net kg', '净重kg', 9, false],
        'AB' => ['ambalaj', 'Ambalaj', 'Packaging', '包装', 18, false],
        'AC' => ['firma_notu', 'Firma notu', 'Supplier note', '供应商备注', 26, false],
        'AD' => ['alternatif_urun_baglantisi', 'Alternatif ürün bağlantısı', 'Alternative product URL', '替代品链接', 24, false],
        'AE' => ['alternatif_aciklamasi', 'Alternatif açıklaması', 'Alternative description', '替代品说明', 24, false],
        'AF' => ['satir_dogrulama', 'Satır doğrulama', 'Row validation', '行校验', 14, true],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: int, 5: bool}> */
    public const TIERS = [
        'A' => ['rfq_satir_id', 'Satır kimliği', 'Row ID', '行ID', 14, false],
        'B' => ['supplier_round_id', 'Tur kimliği', 'Round ID', '轮次ID', 10, true],
        'C' => ['min_adet', 'Min adet', 'Min qty', '最小数量', 10, false],
        'D' => ['max_adet', 'Maks adet (son kademe boş)', 'Max qty (blank on last tier)', '最大数量(末档留空)', 12, false],
        'E' => ['birim_fiyat', 'Birim fiyat', 'Unit price', '单价', 11, false],
        'F' => ['para_birimi', 'Para birimi', 'Currency', '币种', 9, false],
        'G' => ['aciklama', 'Açıklama', 'Note', '说明', 20, false],
        'H' => ['kademe_dogrulama', 'Kademe doğrulama', 'Tier validation', '档位校验', 14, true],
    ];

    /**
     * Yanıt sütunları (K–AE): makine adı → harf.
     * @return array<string, string>
     */
    public static function yanitSutunlari(): array
    {
        $sonuc = [];
        foreach (self::QUOTATION as $harf => $tanim) {
            if (!$tanim[5]) {
                $sonuc[$tanim[0]] = $harf;
            }
        }

        return $sonuc;
    }

    /** Makine adı → harf (tüm QUOTATION). */
    public static function harf(string $makineAdi): string
    {
        foreach (self::QUOTATION as $harf => $tanim) {
            if ($tanim[0] === $makineAdi) {
                return $harf;
            }
        }
        throw new \LogicException('Bilinmeyen sütun: ' . $makineAdi);
    }

    /**
     * Üç dilli başlık metni.
     * @param array{0: string, 1: string, 2: string, 3: string, 4: int, 5: bool} $tanim
     */
    public static function baslik(array $tanim): string
    {
        return $tanim[1] . "\n" . $tanim[2] . "\n" . $tanim[3];
    }
}
