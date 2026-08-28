<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * Şablon v2 sabitleri (İE#13 Blok F) — NİHAİ referanslar:
 *   • Excel: docs/sablon/sablon-v2-rev7.xlsx
 *   • PDF:   docs/sablon/sablon-v2-pdf-ornek-rev3.pdf
 * (rev2/rev5/rev6 referansları geçersizdir — PM kararı.)
 *
 * Excel ve PDF AYNI tanımları okur: sütun seti, üç dilli başlıklar, renkler ve durum
 * rozetleri tek yerde durur ki iki çıktı birbirinden ayrışmasın.
 *
 * PLATFORM BAĞIMSIZ (şartname): "Kaynak" sütunu ürünün kendi platformunu yazar ve
 * ürün adı kendi ilanına köprülenir. ÜRÜN SATICISI (vendor) BASILMAZ — belgede
 * tedarikçi kimliği yer almaz; "Firma" antetteki alıcı firmadır, o basılır.
 *
 * SÜTUN SETİ SADEDİR: koli içi/koli, ilan no, MOQ ve satır-toplam sütunları
 * BASILMAZ (veride dururlar); parasal toplamlar üstteki KPI kartlarındadır ve
 * GENEL TOPLAM bandında yalnız miktar toplamı + karta işaret notu vardır.
 */
final class TemplateV2
{
    // ── Kurumsal palet (EK-B girişi ve paylaşım sayfasıyla ORTAK) ──
    public const LACIVERT = '0F2557';
    public const LACIVERT_ACIK = '16336E';
    public const LACIVERT_ORTA = '2A4A8F';
    public const ALTIN = 'D4A017';
    public const MAVI = '2563EB';
    public const ZEBRA = 'F1F5F9';
    public const KPI_ZEMIN = 'F8FAFC';
    public const CIZGI = 'E2E8F0';
    public const AYRAC = 'D9E2EC';
    public const METIN = '0F172A';
    public const SOLUK = '64748B';
    public const BANT_YAZI = '93C5FD';
    public const EN_YAZI = '6B8DC9';
    public const VIDEO = 'FF6600';

    /**
     * Durum rozetleri: [iki satırlı metin, zemin, yazı rengi].
     * Sütun dardır — rozet İKİ SATIRA bölünür (şartname).
     */
    /**
     * İE#21 B8-2/B13 — BU LİSTE ARTIK TEK KAYNAK DEĞİLDİR.
     *
     * Etiketler `config/durumlar.json`dan gelir (docs/04 §5B durum makinesi
     * kazanır). Buradaki dizi yalnız YEDEKTİR: sözlük dosyası okunamazsa belge
     * üretimi durmasın diye durur. Eski metinler bilinçli olarak DEĞİŞTİ —
     * "Bekleme Listesinde" 5B'de "Verilecek"tir; belgenin ve panelin aynı duruma
     * farklı kelimeler demesi canlı bir kusurdu (paylasim-sayfasi.png kanıtı).
     */
    public const STATUS_BADGES = [
        'to_order' => ['● Verilecek', 'F3F4F6', '4B5563'],
        'ordered' => ['● Verildi', 'FEF3C7', '92400E'],
        'in_transit' => ['● Yolda', 'E0E7FF', '3730A3'],
        'received' => ['● Geldi', 'DBEAFE', '1E40AF'],
        'cancelled' => ['● İptal', 'FEE2E2', 'B91C1C'],
    ];

    /** Sözlük tembel bağlanır; bağlanmazsa yukarıdaki YEDEK liste kullanılır. */
    private static ?\App\Services\DurumSozlugu $sozluk = null;

    public static function sozlukBagla(string $basePath): void
    {
        self::$sozluk = new \App\Services\DurumSozlugu($basePath);
    }

    /** Kaynak rozeti: kod → görünen ad (bilinmeyen platform kendi kodunu yazar). */
    public const PLATFORM_LABELS = [
        '1688' => '1688.com',
        'taobao' => 'Taobao',
        'alibaba' => 'Alibaba',
        'made-in-china' => 'Made-in-China',
        'globalsources' => 'Global Sources',
        'yiwugo' => 'Yiwugo',
        'chinagoods' => 'Chinagoods',
    ];

    /**
     * Sütun düzeni (rev7): harf → [genişlik, TR, 中文, EN].
     * A kenar boşluğudur; veri B'den O'ya kadar gider.
     *
     * @var array<string, array{0: float, 1: string, 2: string, 3: string}>
     */
    public const COLUMNS = [
        'A' => [2.0, '', '', ''],
        'B' => [4.5, 'No', '序号', 'No'],
        'C' => [11.5, 'Görsel', '图片', 'Image'],
        'D' => [32.0, 'Ürün Adı', '产品名称', 'Product name'],
        'E' => [36.5, 'Ürün Detayları', '产品详情', 'Details'],
        'F' => [16.0, 'Varyasyon', '规格', 'Variant'],
        'G' => [11.0, 'Kategori', '类目', 'Category'],
        'H' => [9.0, 'Kaynak', '来源', 'Source'],
        'I' => [10.51, 'Durum', '状态', 'Status'],
        'J' => [16.0, 'Not', '备注', 'Notes'],
        'K' => [9.0, 'Miktar', '数量', 'Qty'],
        'L' => [11.0, 'Vitrin Fiyatı', '市场价', 'Market'],
        // İE#19 E13: bu sütun Yuan fiyatının kur karşılığıdır — İTHALAT MALİYETİ DEĞİLDİR.
        // "₺ Karşılığı" okuyanı "bu ürünün bana maliyeti" sanmaya itiyordu; nakliye,
        // gümrük ve DDP kalemleri bu sayının içinde YOKTUR.
        'M' => [11.0, 'Yaklaşık ürün bedeli (₺)', '里拉', 'TRY'],
        'N' => [10.51, 'DDP $', '含税', 'Incl. VAT'],
        'O' => [11.5, 'DDP ₺', '含税', 'Incl. VAT'],
    ];

    /**
     * GİRİLMEMİŞ FİYAT AYRIMI (İE#17 G3) — tek kural, dört yüzey.
     *
     * Yerleşik sözleşme: tutar POZİTİF DEĞİLSE girilmemiştir. Canlı kusur şuydu:
     * DDP fiyatı hiç girilmemiş ürünlerde belgeye ve paylaşım sayfasına
     * "$ 0.00 / ₺ 0.00" basılıyordu — firma bunu "bedeli sıfır" diye okuyabilir.
     * YOKLUĞU SIFIR GÖSTERMEK yanlış bilgidir; boş bırakmak doğrudur.
     *
     * Karar sunum katmanındadır: DB şeması ve ListPresenter alan sözleşmesi
     * değişmez, snapshot içeriği aynen kalır (K50 determinizmi korunur).
     */
    public static function girilmis(mixed $tutar): bool
    {
        if (!is_scalar($tutar)) {
            return false;
        }
        $metin = trim((string) $tutar);
        if ($metin === '') {
            return false;
        }
        // Sunum biçimi binlik ayracı taşıyabilir ("1.234,56"); sayıya indirgenir.
        $sade = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $metin);

        return is_numeric($sade) && (float) $sade > 0.0;
    }

    /** F5 — yalnız İÇ KOPYADA eklenen sütunlar. */
    public const INTERNAL_COLUMNS = [
        'P' => [12.0, 'Hedef Satış (₺)', '目标售价', 'Target'],
        'Q' => [11.0, 'Birim Kâr (₺)', '单位利润', 'Unit profit'],
        'R' => [12.0, 'Toplam Kâr (₺)', '总利润', 'Total profit'],
    ];

    /** KPI kartları: [ilk sütun, son sütun, etiket, formül şablonu, biçim]. */
    public const KPI_CARDS = [
        ['B', 'D', 'TOPLAM ÜRÜN', '=COUNTA(D{ilk}:D{son})', '#,##0'],
        ['E', 'G', 'TOPLAM MİKTAR', '=SUM(K{ilk}:K{son})', '#,##0'],
        ['H', 'J', 'MAL BEDELİ (¥)', '=SUMPRODUCT(K{ilk}:K{son},L{ilk}:L{son})', '\¥#,##0.00'],
        ['K', 'L', 'MAL BEDELİ (₺)', '=SUMPRODUCT(K{ilk}:K{son},M{ilk}:M{son})', '\₺#,##0.00'],
        ['M', 'O', 'DDP TOPLAM (₺ · KDV dahil)', '=SUMPRODUCT(K{ilk}:K{son},O{ilk}:O{son})', '\₺#,##0.00'],
    ];

    public const ROW_BAND = 2;
    public const ROW_GOLD_TOP = 4;
    public const ROW_KPI_LABEL = 6;
    public const ROW_KPI_VALUE = 7;
    public const ROW_HEAD_TR = 8;
    public const ROW_HEAD_CJK = 9;
    public const ROW_HEAD_EN = 10;
    public const ROW_DATA_START = 11;

    /** @return array{0: string, 1: string, 2: string} */
    /**
     * @param string $dil belge dili — rozet de belgenin dilinde basılır (üç dilli çıktı)
     *
     * @return array{0: string, 1: string, 2: string} [metin, arka plan HEX, ön plan HEX]
     */
    public static function badge(string $status, string $dil = 'tr'): array
    {
        if (self::$sozluk !== null) {
            $etiket = self::$sozluk->etiket(\App\Services\DurumSozlugu::URUN, $status, $dil);
            if ($etiket !== $status) {
                $renk = self::$sozluk->renk(\App\Services\DurumSozlugu::URUN, $status);

                return ['● ' . $etiket, ltrim($renk['bg'], '#'), ltrim($renk['fg'], '#')];
            }
        }

        return self::STATUS_BADGES[$status] ?? ['● ' . $status, 'F1F5F9', self::SOLUK];
    }

    public static function platformLabel(?string $platform): string
    {
        if ($platform === null || $platform === '') {
            return '—';
        }

        return self::PLATFORM_LABELS[$platform] ?? $platform;
    }

    /**
     * Belge kodu: TDK-<yıl>-<liste no, 4 hane> · Rev <harf>.
     * F7: aynı listenin sonraki çıktısı Rev B, C… olur ve öncekini geçersiz kılar.
     */
    public static function documentCode(int $listId, int $year, string $revision): string
    {
        return sprintf('TDK-%d-%04d · Rev %s', $year, $listId, $revision);
    }

    /** Sayaçtan revizyon harfi: 1→A, 2→B … 26→Z, 27→AA (F7). */
    public static function revisionLabel(int $sequence): string
    {
        $sequence = max(1, $sequence);
        $harf = '';
        while ($sequence > 0) {
            $sequence--;
            $harf = chr(65 + ($sequence % 26)) . $harf;
            $sequence = intdiv($sequence, 26);
        }

        return $harf;
    }

    /**
     * Antet kimlik satırı: "Tilbe Home · tilbehome.com · info@… · Eylül 2026 Dönemi ·
     * DDP Tedarik · Firma: X". BOŞ ALAN BASILMAZ (şartname) — ayarlarda doldurulmamış
     * her parça satırdan tamamen düşer.
     *
     * @param array{company?: string|null, web?: string|null, email?: string|null} $antet
     * @param array<string, mixed> $list
     */
    public static function headerLine(array $antet, array $list): string
    {
        $parcalar = [];
        foreach (['company', 'web', 'email'] as $anahtar) {
            $deger = $antet[$anahtar] ?? null;
            if (is_string($deger) && trim($deger) !== '') {
                $parcalar[] = trim($deger);
            }
        }
        if (is_string($list['period'] ?? null) && $list['period'] !== '') {
            $parcalar[] = (string) $list['period'] . ' Dönemi';
        }
        $parcalar[] = 'DDP Tedarik';
        if (is_string($list['supplier_name'] ?? null) && $list['supplier_name'] !== '') {
            // "Firma" = belgenin gönderileceği ALICI firma; ürün satıcısı DEĞİL.
            $parcalar[] = 'Firma: ' . (string) $list['supplier_name'];
        }

        return implode(' · ', $parcalar);
    }
}
