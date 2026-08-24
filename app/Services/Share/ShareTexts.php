<?php

declare(strict_types=1);

namespace App\Services\Share;

/**
 * Paylaşım metinleri — TR / 中文 / EN (İE#15 C2).
 *
 * Metinler KODA GÖMÜLMEZ, buradadır: yeni bir kanal eklemek ya da bir cümleyi
 * düzeltmek tek dosyayı değiştirir. Çince metin Çinli tedarikçiye gider; bu yüzden
 * "DDP teklif ver" çağrısı doğrudan ve kısa tutulmuştur — uzun nezaket cümleleri
 * WeChat'te kırpılır.
 *
 * Yer tutucular: {liste} {adet} {link} {tarih}. Değer verilmezse o satır DÜŞER
 * (örn. son geçerlilik tarihi yoksa "geçerlilik" cümlesi hiç yazılmaz — uydurma yok).
 */
final class ShareTexts
{
    /**
     * K81 (İE#21 EK-5): paylaşım sayfasının ARAYÜZ metinleri de bu sınıftadır.
     *
     * Önce yalnız kanal metinleri (WhatsApp/e-posta) buradaydı; sayfanın kendi
     * yazıları `SharePage.php` içinde SABİT TÜRKÇEYDİ. Sonuç, EN/ZH sayfada
     * karışık dildi: tablo İngilizce, düğmeler Türkçe. Üç dil tek yerde durur ki
     * bir metin eklenirken üçü birden görünsün; dil başına ayrı sınıf yoktur.
     *
     * İSTİSNA (K81/K55): tablo sütun başlığı bloğu üç dilli kalır ve ürünün
     * orijinal Çince satırı çevrilmez — ikisi de SharePage'de, bilinçli.
     */
    public const DILLER = ['tr', 'zh', 'en'];

    /** @var array<string, array<string, string>> */
    private const METINLER = [
        'tr' => [
            'ozet' => "«{liste}» tedarik listesi · {adet} ürün\nDDP fiyat teklifinizi bu bağlantıdan iletebilirsiniz: {link}",
            'gecerlilik' => 'Teklif geçerlilik tarihi: {tarih}',
            'eposta_konu' => '{liste} — tedarik listesi ({adet} ürün)',
            'baslik' => '{liste} — tedarik listesi',

            // İE#21 EK-5 (K81): paylaşım sayfası ARAYÜZ metinleri — tek kaynak burası.
            'sayfa_aciklama' => 'TedarikApp (Ürün Tedarik Asistanı) ile paylaşılan sipariş listesi — salt okunur görünüm.',
            'yerel' => 'tr_TR',
            'belge' => 'BELGE',
            'rev' => 'Rev',
            'kur_kilitli' => 'KUR · KİLİTLİ',
            'kur_guncel' => 'KUR · GÜNCEL',
            'guncelleme' => 'GÜNCELLEME',
            'firma_kopyasi' => 'Firma kopyası',
            'kpi_urun' => 'ÜRÜN',
            'kpi_miktar' => 'TOPLAM MİKTAR',
            'kpi_bedel' => 'MAL BEDELİ',
            'kpi_ddp' => 'DDP · KDV DAHİL',
            'ciktilar' => 'Çıktılar',
            'yazdir' => 'Yazdır',
            'paylas' => 'Paylaş',
            'linki_kopyala' => 'Linki kopyala',
            'eposta' => 'E-posta',
            'gonderim_dili' => 'Gönderim dili',
            'qr_baslik' => 'Kare kodu okutun',
            'qr_alt' => 'Paylaşım bağlantısının kare kodu',
            'qr_ozet' => 'Özet metnini kopyala',
            'qr_indir' => 'Kareyi indir (PNG)',
            'qr_kapat' => 'Kapat',
            'yaz_baslik' => 'Yazdırma ayarları',
            'yaz_duzen' => 'Düzen: Yatay',
            'yaz_arkaplan' => 'Arka plan grafikleri: açık',
            'yaz_aciklama_bas' => 'Tarayıcı penceresinde',
            'yaz_aciklama_son' => 'seçin — aksi hâlde sağdaki fiyat sütunları kâğıda sığmayabilir ve renkler basılmaz.',
            'yaz_bir_daha' => 'Bir daha gösterme',
            'vazgec' => 'Vazgeç',
            'bos_liste' => 'Bu listede gösterilecek ürün yok.',
            'urune_git' => 'Ürüne git',
            'detaylar' => 'Detaylar',
            'galeriyi_ac' => 'Galeriyi aç',
            'video_oynat' => 'Videoyu oynat',
            'video_bilgi' => 'Video bilgisi',
            'gorsel_yok' => 'görsel<br>yok',
            'ceviri_bekliyor' => 'çeviri bekliyor',
            'ceviri_kuyrukta' => 'Çevirisi kuyrukta',
            'eksik_goster' => 'Eksik bilgileri göster ({adet})',
            'urun_bilgileri' => 'ÜRÜN BİLGİLERİ',
            'varyasyonlar' => 'VARYASYONLAR',
            'secenek_daha' => '+{adet} seçenek',
            'not_oneki' => 'Not:',
            'lightbox_kapat' => 'kapat: tıkla / ESC',
            'genel_toplam' => 'GENEL TOPLAM — {adet} adet',
            'toplam_alt' => 'Parasal toplamlar üstteki özet şerididir',
            'sartlar' => 'Sipariş şartları: Teslim DDP · Kur, liste iletildiğinde kilitlenir · Fiyatlar DDP teslim, KDV DAHİLDİR',
            'kunye' => 'TedarikApp — Ürün Tedarik Asistanı · Görsele tıkla: galeri · Boş alan — ile gösterilir',
            'onizleme_urun' => '{adet} ürün',
            'onizleme_kuyruk' => 'DDP teklif için paylaşılan tedarik listesi',

            // İE#21 EK-5: PDF belge metinleri — ekranla AYNI kaynak.
            'pdf_belge' => 'BELGE',
            'pdf_rev' => 'Rev',
            'pdf_olusturulma' => 'OLUŞTURULMA',
            'pdf_kur_guncel' => 'KUR · GÜNCEL',
            'pdf_kur_kilitli' => 'KUR · KİLİTLİ',
            'pdf_kpi_urun' => 'TOPLAM ÜRÜN',
            'pdf_kpi_miktar' => 'TOPLAM MİKTAR',
            'pdf_kpi_bedel_yuan' => 'MAL BEDELİ (¥)',
            'pdf_kpi_bedel_tl' => 'MAL BEDELİ (₺)',
            'pdf_kpi_ddp' => 'DDP TOPLAM (₺ · KDV dahil)',
            'pdf_genel_toplam' => 'GENEL TOPLAM',
            'pdf_toplam_not' => 'Parasal toplamlar üstteki özet kartlarındadır',
            'pdf_sartlar' => 'Sipariş şartları: Teslim DDP · Kur, liste iletildiğinde kilitlenir · Fiyatlar DDP teslim, KDV DAHİLDİR',
            'pdf_revizyon_uyarisi' => 'Rev {rev}: bu belge aynı listenin önceki çıktılarını GEÇERSİZ KILAR',
            'pdf_imza' => 'Firma onayı: ________  Tarih: ____',
            'pdf_kunye' => 'TedarikApp — Ürün Tedarik Asistanı · Ürün adı kaynak platformdaki ilana köprülüdür',
            'pdf_ic_kopya' => 'İÇ KOPYA — firmaya gönderilmez',
            'pdf_filigran' => 'İÇ KOPYA',
            'pdf_sayfa' => 'Sayfa {PAGENO}/{nbpg}',
        ],
        'zh' => [
            'ozet' => "「{liste}」采购清单 · 共{adet}件商品\n请点击链接填写DDP报价：{link}",
            'gecerlilik' => '报价有效期至 {tarih}',
            'eposta_konu' => '{liste} — 采购清单（{adet}件商品）',
            'baslik' => '{liste} — 采购清单',

            // İE#21 EK-5 (K81): paylaşım sayfası ARAYÜZ metinleri — tek kaynak burası.
            'sayfa_aciklama' => '通过 TedarikApp 共享的采购清单 — 只读视图。',
            'yerel' => 'zh_CN',
            'belge' => '单据',
            'rev' => '版本',
            'kur_kilitli' => '汇率 · 已锁定',
            'kur_guncel' => '汇率 · 当前',
            'guncelleme' => '更新时间',
            'firma_kopyasi' => '供应商副本',
            'kpi_urun' => '商品数',
            'kpi_miktar' => '总数量',
            'kpi_bedel' => '货款',
            'kpi_ddp' => 'DDP · 含税',
            'ciktilar' => '导出',
            'yazdir' => '打印',
            'paylas' => '分享',
            'linki_kopyala' => '复制链接',
            'eposta' => '电子邮件',
            'gonderim_dili' => '发送语言',
            'qr_baslik' => '扫描二维码',
            'qr_alt' => '共享链接的二维码',
            'qr_ozet' => '复制摘要文本',
            'qr_indir' => '下载二维码（PNG）',
            'qr_kapat' => '关闭',
            'yaz_baslik' => '打印设置',
            'yaz_duzen' => '版面：横向',
            'yaz_arkaplan' => '背景图形：开启',
            'yaz_aciklama_bas' => '在浏览器打印窗口中选择',
            'yaz_aciklama_son' => '— 否则右侧价格列可能超出纸张，且颜色不会打印。',
            'yaz_bir_daha' => '不再显示',
            'vazgec' => '取消',
            'bos_liste' => '此清单暂无可显示的商品。',
            'urune_git' => '查看商品',
            'detaylar' => '详情',
            'galeriyi_ac' => '打开图库',
            'video_oynat' => '播放视频',
            'video_bilgi' => '视频信息',
            'gorsel_yok' => '无<br>图片',
            'ceviri_bekliyor' => '待翻译',
            'ceviri_kuyrukta' => '翻译排队中',
            'eksik_goster' => '显示缺失信息（{adet}）',
            'urun_bilgileri' => '商品属性',
            'varyasyonlar' => '规格',
            'secenek_daha' => '+{adet} 个选项',
            'not_oneki' => '备注：',
            'lightbox_kapat' => '关闭：点击 / ESC',
            'genel_toplam' => '总计 — {adet} 件',
            'toplam_alt' => '金额合计见上方汇总条',
            'sartlar' => '订单条款：DDP 交付 · 清单发送时锁定汇率 · 价格为 DDP，含税',
            'kunye' => 'TedarikApp — 采购助手 · 点击图片查看图库 · 空白字段显示为 —',
            'onizleme_urun' => '{adet} 件商品',
            'onizleme_kuyruk' => '为 DDP 报价共享的采购清单',

            // İE#21 EK-5: PDF belge metinleri — ekranla AYNI kaynak.
            'pdf_belge' => '单据',
            'pdf_rev' => '版本',
            'pdf_olusturulma' => '生成时间',
            'pdf_kur_guncel' => '汇率 · 当前',
            'pdf_kur_kilitli' => '汇率 · 已锁定',
            'pdf_kpi_urun' => '商品总数',
            'pdf_kpi_miktar' => '总数量',
            'pdf_kpi_bedel_yuan' => '货款（¥）',
            'pdf_kpi_bedel_tl' => '货款（₺）',
            'pdf_kpi_ddp' => 'DDP 合计（₺ · 含税）',
            'pdf_genel_toplam' => '总计',
            'pdf_toplam_not' => '金额合计见上方汇总卡片',
            'pdf_sartlar' => '订单条款：DDP 交付 · 清单发送时锁定汇率 · 价格为 DDP，含税',
            'pdf_revizyon_uyarisi' => '版本 {rev}：本文件作废同一清单的早期输出',
            'pdf_imza' => '供应商确认：________  日期：____',
            'pdf_kunye' => 'TedarikApp — 采购助手 · 商品名称链接至来源商品页',
            'pdf_ic_kopya' => '内部副本 — 不发送给供应商',
            'pdf_filigran' => '内部副本',
            'pdf_sayfa' => '第 {PAGENO}/{nbpg} 页',
        ],
        'en' => [
            'ozet' => "«{liste}» supply list · {adet} items\nPlease submit your DDP quotation via this link: {link}",
            'gecerlilik' => 'Quotation valid until {tarih}',
            'eposta_konu' => '{liste} — supply list ({adet} items)',
            'baslik' => '{liste} — supply list',

            // İE#21 EK-5 (K81): paylaşım sayfası ARAYÜZ metinleri — tek kaynak burası.
            'sayfa_aciklama' => 'Supply list shared via TedarikApp — read-only view.',
            'yerel' => 'en_US',
            'belge' => 'DOCUMENT',
            'rev' => 'Rev',
            'kur_kilitli' => 'RATE · LOCKED',
            'kur_guncel' => 'RATE · CURRENT',
            'guncelleme' => 'UPDATED',
            'firma_kopyasi' => 'Supplier copy',
            'kpi_urun' => 'ITEMS',
            'kpi_miktar' => 'TOTAL QUANTITY',
            'kpi_bedel' => 'GOODS VALUE',
            'kpi_ddp' => 'DDP · VAT INCLUDED',
            'ciktilar' => 'Downloads',
            'yazdir' => 'Print',
            'paylas' => 'Share',
            'linki_kopyala' => 'Copy link',
            'eposta' => 'E-mail',
            'gonderim_dili' => 'Message language',
            'qr_baslik' => 'Scan the QR code',
            'qr_alt' => 'QR code of the share link',
            'qr_ozet' => 'Copy summary text',
            'qr_indir' => 'Download QR (PNG)',
            'qr_kapat' => 'Close',
            'yaz_baslik' => 'Print settings',
            'yaz_duzen' => 'Layout: Landscape',
            'yaz_arkaplan' => 'Background graphics: on',
            'yaz_aciklama_bas' => 'In the browser dialog select',
            'yaz_aciklama_son' => '— otherwise the price columns on the right may not fit the page and colours will not print.',
            'yaz_bir_daha' => 'Do not show again',
            'vazgec' => 'Cancel',
            'bos_liste' => 'This list has no items to show.',
            'urune_git' => 'Open product',
            'detaylar' => 'Details',
            'galeriyi_ac' => 'Open gallery',
            'video_oynat' => 'Play video',
            'video_bilgi' => 'Video info',
            'gorsel_yok' => 'no<br>image',
            'ceviri_bekliyor' => 'translation pending',
            'ceviri_kuyrukta' => 'Translation queued',
            'eksik_goster' => 'Show missing fields ({adet})',
            'urun_bilgileri' => 'PRODUCT INFORMATION',
            'varyasyonlar' => 'VARIANTS',
            'secenek_daha' => '+{adet} options',
            'not_oneki' => 'Note:',
            'lightbox_kapat' => 'close: click / ESC',
            'genel_toplam' => 'GRAND TOTAL — {adet} pcs',
            'toplam_alt' => 'Monetary totals are in the summary strip above',
            'sartlar' => 'Order terms: DDP delivery · The rate is locked when the list is sent · Prices are DDP, VAT INCLUDED',
            'kunye' => 'TedarikApp — Sourcing Assistant · Click an image for the gallery · Empty fields show as —',
            'onizleme_urun' => '{adet} items',
            'onizleme_kuyruk' => 'Supply list shared for a DDP quotation',

            // İE#21 EK-5: PDF belge metinleri — ekranla AYNI kaynak.
            'pdf_belge' => 'DOCUMENT',
            'pdf_rev' => 'Rev',
            'pdf_olusturulma' => 'ISSUED',
            'pdf_kur_guncel' => 'RATE · CURRENT',
            'pdf_kur_kilitli' => 'RATE · LOCKED',
            'pdf_kpi_urun' => 'TOTAL ITEMS',
            'pdf_kpi_miktar' => 'TOTAL QUANTITY',
            'pdf_kpi_bedel_yuan' => 'GOODS VALUE (¥)',
            'pdf_kpi_bedel_tl' => 'GOODS VALUE (₺)',
            'pdf_kpi_ddp' => 'DDP TOTAL (₺ · VAT incl.)',
            'pdf_genel_toplam' => 'GRAND TOTAL',
            'pdf_toplam_not' => 'Monetary totals are in the summary cards above',
            'pdf_sartlar' => 'Order terms: DDP delivery · The rate is locked when the list is sent · Prices are DDP, VAT INCLUDED',
            'pdf_revizyon_uyarisi' => 'Rev {rev}: this document SUPERSEDES earlier outputs of the same list',
            'pdf_imza' => 'Supplier approval: ________  Date: ____',
            'pdf_kunye' => 'TedarikApp — Sourcing Assistant · Product names link to the source listing',
            'pdf_ic_kopya' => 'INTERNAL COPY — not for the supplier',
            'pdf_filigran' => 'INTERNAL COPY',
            'pdf_sayfa' => 'Page {PAGENO}/{nbpg}',
        ],
    ];

    public static function dil(mixed $istenen): string
    {
        $dil = is_string($istenen) ? strtolower(trim($istenen)) : '';

        return in_array($dil, self::DILLER, true) ? $dil : 'tr';
    }

    /**
     * Tam paylaşım metni: özet + (varsa) geçerlilik satırı.
     *
     * @param array{liste: string, adet: int|string, link: string, tarih?: string|null} $degerler
     */
    public static function mesaj(string $dil, array $degerler): string
    {
        $dil = self::dil($dil);
        $metin = self::degistir(self::METINLER[$dil]['ozet'], $degerler);

        $tarih = $degerler['tarih'] ?? null;
        if (is_string($tarih) && trim($tarih) !== '') {
            $metin .= "\n" . self::degistir(self::METINLER[$dil]['gecerlilik'], $degerler);
        }

        return $metin;
    }

    /** @param array<string, mixed> $degerler */
    public static function metin(string $dil, string $anahtar, array $degerler = []): string
    {
        $dil = self::dil($dil);
        $sablon = self::METINLER[$dil][$anahtar] ?? (self::METINLER['tr'][$anahtar] ?? '');

        return self::degistir($sablon, $degerler);
    }

    /** @param array<string, mixed> $degerler */
    private static function degistir(string $sablon, array $degerler): string
    {
        foreach ($degerler as $anahtar => $deger) {
            if (is_scalar($deger)) {
                $sablon = str_replace('{' . $anahtar . '}', (string) $deger, $sablon);
            }
        }

        return $sablon;
    }

    /** Dil seçicide görünen adlar. */
    public static function dilAdi(string $dil): string
    {
        return match ($dil) {
            'zh' => '中文',
            'en' => 'English',
            default => 'Türkçe',
        };
    }
}
