<?php

declare(strict_types=1);

namespace App\Services\Share;

use App\Core\AppVersion;
use App\Services\Export\TemplateV2;
use App\Services\ProductDetails;
use App\Services\Translation\ValueSet;

/**
 * Paylaşım sayfası HTML'i (İE#10 Blok 4 · İE#13 F4 TAM YENİLEME).
 *
 * ŞARTNAME: docs/sablon/paylasim-v4-premium.html — bu sınıf o düzeni üretir:
 * koyu kurumsal üst bant (logo + antet + belge/kur/güncelleme), araç çubuğu
 * (Yazdır · Excel · PDF · WhatsApp · Linki kopyala), 5'li KPI şeridi, ayraçlı
 * sütun tablosu (mobilde etiketli kart), satır altı detay paneli (16 alanlık
 * bilgi ızgarası + varyasyonlar + not + galeri), GENEL TOPLAM bandı, künye.
 *
 * DIŞA AÇIK TEK YÜZEY: her değer istisnasız escape edilir (XSS — CLAUDE.md §5).
 * Sayfa CANLI listeyi gösterir (export snapshot'ının aksine): firma güncel durumu görür.
 * CSP (K51): stil `/p-style.css`, davranış `/p-share.js`, fontlar `/fonts/…` —
 * satır içi stil/script ve dış istek YOK.
 *
 * TEDARİK PUANI bölümü şimdilik GİZLİDİR: skor verisi V3-A ile gelecek; hesaplanmamış
 * bir puanı uydurmak yerine bölüm hiç basılmaz (şartname notu).
 *
 * İÇ KOPYA VERİSİ BURAYA GİRMEZ (F5): hedef satış/kâr alanları hiçbir koşulda görünmez.
 */
final class SharePage
{
    /**
     * İE#14 A3: varyasyon ve öznitelik DEĞERLERİ sözlükten geçirilir; sözlük
     * verilmezse (eski çağrılar, testler) ham değerler basılır — davranış bozulmaz.
     */
    public function __construct(
        private readonly ?ValueSet $values = null,
        // İE#15 A1: indirme bağlantıları sayfa ÜRETİLİRKEN imzalanır; imzalayıcı
        // verilmezse (eski çağrılar, testler) çıktı düğmeleri oturumlu davranışa döner.
        private readonly ?ShareDownload $downloads = null,
    ) {
    }

    /**
     * @param array<string, mixed> $list ListPresenter::list çıktısı
     * @param list<array<string, mixed>> $products ListPresenter::productsOf çıktısı
     * @param array<int, string> $categoryNames
     * @param array{company?: string|null, web?: string|null, email?: string|null, prepared_by?: string|null} $documentHeader
     * @param bool $showExports Excel/PDF düğmeleri — YALNIZ panel oturumu olan görüntüleyende
     */
    public function render(
        array $list,
        array $products,
        array $categoryNames,
        string $canonicalUrl = '',
        array $documentHeader = [],
        bool $showExports = false,
        // İE#15: imzalı indirme bağlantısı token ister; dil paylaşım metinlerini seçer.
        string $token = '',
        string $dil = 'tr',
        ?\DateTimeImmutable $now = null,
    ): string {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        // İE#17 G1 — VARLIK SÜRÜMLEME: canlı kusurun kökü buydu. Bağlantılar
        // sürümsüzdü; zip açılımı dosya tarihlerini koruduğu için tarayıcının
        // sezgisel önbelleği v0.11.0'ın p-style.css'ini "taze" sayıyor, İE#15
        // kuralları (.tgrp/.pmenu/.qrm/.ynot) hiç yüklenmiyordu — paylaş menüsü
        // akışa dökülüyor, yazdırma uyarısı görünmez kalıyordu. Sürüm TEK
        // KAYNAKTAN gelir: AppVersion::VALUE (bin/release.php her pakette damgalar).
        $surum = $e(AppVersion::VALUE);

        $satirlar = '';
        $galeriler = [];
        $sira = 0;
        foreach ($products as $product) {
            if ((string) $product['status'] === 'cancelled') {
                continue; // iptal edilen ürün firmaya gösterilmez (toplamlara da girmez — K24)
            }
            $sira++;
            $galeri = $this->galeriAdresleri($product);
            $galeriler[] = $galeri;
            $satirlar .= $this->satir($product, $categoryNames, $sira, count($galeriler) - 1, $galeri, $e);
        }

        $origin = $canonicalUrl !== '' ? (string) preg_replace('#(^https?://[^/]+).*#', '$1', $canonicalUrl) : '';
        $totals = $list['totals'];

        // İE#18 G2 — HERO BİLGİ MİMARİSİ (PDF antediyle AYNI):
        //   sol : marka etiketi + liste adı + TEK SATIR meta (boş alan basılmaz)
        //   sağ : BELGE (kod + Rev) · KUR (kilitli/güncel + ¥/$) · GÜNCELLEME
        $markaEtiketi = is_string($documentHeader['company'] ?? null) && $documentHeader['company'] !== ''
            ? mb_strtoupper((string) $documentHeader['company'], 'UTF-8')
            : 'TEDARİKAPP';
        $antetSatiri = $this->metaSatiri($documentHeader, $list);
        $kurEtiketi = ($list['rate_locked_at'] ?? null) !== null ? 'KUR · KİLİTLİ' : 'KUR · GÜNCEL';
        $revizyonHarfi = TemplateV2::revisionLabel(max(1, (int) ($list['revision'] ?? 0) + 1));

        $araclar = $this->araclar($list, $sira, $canonicalUrl, $token, $dil, $now, $e);
        $lightboxVerisi = htmlspecialchars(
            json_encode($galeriler, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            ENT_QUOTES,
            'UTF-8',
        );

        return '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="Tedarikapp (Ürün Tedarik Asistanı) ile paylaşılan sipariş listesi — salt okunur görünüm.">
<title>' . $e($list['name']) . ' — Tedarikapp</title>
<link rel="icon" type="image/svg+xml" href="/panel/favicon.svg">
<link rel="apple-touch-icon" sizes="180x180" href="/panel/apple-touch-icon.png">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Tedarikapp">
<meta property="og:locale" content="tr_TR">
<meta property="og:title" content="' . $e($list['name']) . ' — Tedarikapp">
<!-- İE#15 F2: önizlemede liste adı, ürün sayısı ve dönem görünür; FİYAT/TUTAR ASLA.
     Link önizlemesi sohbet uygulamalarında herkese açılır — özel veri oraya sızmaz. -->
<meta property="og:description" content="' . $e($this->onizlemeMetni($list, $sira)) . '">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="' . $e($list['name']) . ' — Tedarikapp">
<meta name="twitter:description" content="' . $e($this->onizlemeMetni($list, $sira)) . '">' . ($canonicalUrl !== '' ? '
<meta property="og:url" content="' . $e($canonicalUrl) . '">' : '') . '
<meta property="og:image" content="' . $e($origin) . '/panel/og-image.png">
<link rel="stylesheet" href="/p-style.css?v=' . $surum . '">
</head>
<body data-galeriler="' . $lightboxVerisi . '" data-liste="' . $e($list['id']) . '">
<div class="page">
  <div class="shell">

    <div class="hero">
      <div class="hb">
        <img src="/panel/apple-touch-icon.png" alt="" width="46" height="46">
        <div>
          <div class="hmarka">' . $e($markaEtiketi) . '</div>
          <div class="ht">' . $e($list['name']) . '</div>
          <div class="hs">' . $e($antetSatiri) . '</div>
        </div>
      </div>
      <div class="hm">
        <div><b>BELGE</b><span>' . $e($this->belgeKodu($list)) . '</span>
          <span class="alt">Rev ' . $e($revizyonHarfi) . '</span></div>
        <div><b>' . $e($kurEtiketi) . '</b><span>¥ ' . $e($list['yuan_rate']) . '</span>
          <span class="alt">$ ' . $e($list['usd_rate']) . '</span></div>
        <div><b>GÜNCELLEME</b><span>' . $e($this->tarih((string) $list['updated_at'], 'd.m.Y')) . '</span>
          <span class="alt">' . $e($this->tarih((string) $list['updated_at'], 'H:i')) . '</span></div>
      </div>
    </div>

    <div class="tools">' . $araclar . '</div>

    <div class="kpis">
      <div class="kpi"><b>ÜRÜN</b><span>' . $e($sira) . '</span></div>
      <div class="kpi"><b>TOPLAM MİKTAR</b><span>' . $e($totals['qty']) . '</span></div>
      <div class="kpi"><b>MAL BEDELİ</b><span><span class="u">¥</span> ' . $e($totals['yuan']) . '</span></div>
      <div class="kpi"><b>MAL BEDELİ</b><span><span class="u">₺</span> ' . $e($totals['yuan_tl']) . '</span></div>
      <div class="kpi"><b>DDP · KDV DAHİL</b><span>'
        . (self::pozitif($totals['ddp_tl'] ?? null)
            ? '<span class="u">₺</span> ' . $e($totals['ddp_tl'])
            : '—')
        . '</span></div>
    </div>

    <div class="twrap"><table>
      <thead><tr>
        <th style="text-align:center">No<span class="z2 zh">序号</span><span class="z3">No</span></th>
        <th style="text-align:left">ÜRÜN ADI<span class="z2 zh">产品名称</span><span class="z3">Product name</span></th>
        <th style="text-align:left">ÜRÜN DETAYLARI<span class="z2 zh">产品详情</span><span class="z3">Details</span></th>
        <th>VARYASYON<span class="z2 zh">规格</span><span class="z3">Variant</span></th>
        <th>KATEGORİ<span class="z2 zh">类目</span><span class="z3">Category</span></th>
        <th>KAYNAK<span class="z2 zh">来源</span><span class="z3">Source</span></th>
        <th>DURUM<span class="z2 zh">状态</span><span class="z3">Status</span></th>
        <th>NOT<span class="z2 zh">备注</span><span class="z3">Notes</span></th>
        <th>MİKTAR<span class="z2 zh">数量</span><span class="z3">Qty</span></th>
        <th>VİTRİN FİYATI<span class="z2 zh">市场价</span><span class="z3">Market</span></th>
        <th>₺ KARŞILIĞI<span class="z2 zh">里拉</span><span class="z3">TRY</span></th>
        <th>DDP $<span class="z2 zh">含税</span><span class="z3">Incl. VAT</span></th>
        <th>DDP ₺<span class="z2 zh">含税</span><span class="z3">Incl. VAT</span></th>
        <th></th>
      </tr></thead>
      <tbody>' . ($satirlar === ''
                ? '<tr class="r"><td colspan="14">Bu listede gösterilecek ürün yok.</td></tr>'
                : $satirlar) . '</tbody>
    </table></div>

    <div class="tot"><span>GENEL TOPLAM — ' . $e($totals['qty']) . ' adet</span>
      <small>Parasal toplamlar üstteki özet şerididir</small></div>
  </div>

  <div class="legal">
    Sipariş şartları: Teslim DDP · Kur, liste iletildiğinde kilitlenir · Fiyatlar DDP teslim, KDV DAHİLDİR<br>
    Tedarikapp — Ürün Tedarik Asistanı · Görsele tıkla: galeri · Boş alan — ile gösterilir
  </div>
</div>
<div class="lbx" id="lbx">
  <img id="lbi" alt=""><video id="lbv" controls playsinline hidden></video>
  <p id="lbnot" class="lbnot" hidden></p>
  <div><span id="lbs"></span> · kapat: tıkla / ESC</div>
</div>

<!-- İE#15 C1/C3: WeChat ve DingTalk dış link şemasıyla açılmaz; doğru yol KAREDİR.
     QR sunucuda üretilir (/p/<token>/qr.png), dış QR servisi kullanılmaz (K45).
     Karenin içeriği YALNIZ paylaşım adresidir — imzalı indirme adresi konmaz. -->
<div class="qrm" id="qrm" hidden>
  <div class="qrk" role="dialog" aria-modal="true" aria-labelledby="qrb">
    <h2 id="qrb">Kare kodu okutun</h2>
    <p class="qra" id="qra">WeChat 微信</p>
    <img id="qri" alt="Paylaşım bağlantısının kare kodu" width="260" height="260">
    <p class="qrn" id="qrn"></p>
    <div class="qrd">
      <button type="button" data-qr-metin>Özet metnini kopyala</button>
      <a id="qrp" download="tedarik-listesi-qr.png">Kareyi indir (PNG)</a>
      <button type="button" data-qr-kapat>Kapat</button>
    </div>
  </div>
</div>

<!-- İE#15 B2: yazdırma öncesi TEK SEFERLİK hatırlatma; "bir daha gösterme" seçilirse
     tarayıcıda saklanır (localStorage) ve bir daha çıkmaz. -->
<div class="ynot" id="ynot" hidden>
  <div class="ynk" role="dialog" aria-modal="true" aria-labelledby="ynb">
    <h2 id="ynb">Yazdırma ayarları</h2>
    <p>Tarayıcı penceresinde <strong>Düzen: Yatay</strong> ve
       <strong>Arka plan grafikleri: açık</strong> seçin — aksi hâlde sağdaki
       fiyat sütunları kâğıda sığmayabilir ve renkler basılmaz.</p>
    <label class="ynl"><input type="checkbox" id="ynh"> Bir daha gösterme</label>
    <div class="ynd">
      <button type="button" class="pri" data-yazdir-devam>Yazdır</button>
      <button type="button" data-yazdir-iptal>Vazgeç</button>
    </div>
  </div>
</div>
<script src="/p-share.js?v=' . $surum . '" defer></script>
</body>
</html>';
    }

    /**
     * Araç çubuğu. Excel/PDF YALNIZ panel oturumu olan görüntüleyende basılır:
     * export uçları oturum + CSRF ister (K51 girişsiz sayfayı yetkilendirmez), bu
     * yüzden firmaya çalışmayan düğme GÖSTERİLMEZ.
     *
     * @param array<string, mixed> $list
     * @param callable(mixed): string $e
     */
    /**
     * Araç çubuğu (İE#15 C1/C4/F1).
     *
     * ÇIKTILAR GRUBU firma tarafında da GÖRÜNÜR ve ÇALIŞIR: bağlantılar burada
     * imzalanır (A1), oturum istemez. İmzalayıcı yoksa (eski çağrı) yalnız Yazdır
     * kalır — çalışmayan düğme göstermeyiz.
     *
     * PAYLAŞ menüsü Çin tarafını da kapsar: WhatsApp ve Telegram doğrudan bağlantı,
     * WeChat ve DingTalk QR modalı (bu uygulamalar dış link şemasıyla açılmaz —
     * doğru yol karedir), QQ web paylaşım aracı, e-posta mailto.
     *
     * @param array<string, mixed> $list
     * @param callable(mixed): string $e
     */
    private function araclar(
        array $list,
        int $urunSayisi,
        string $canonicalUrl,
        string $token,
        string $dil,
        ?\DateTimeImmutable $now,
        callable $e,
    ): string {
        $html = '<div class="tgrp" role="group" aria-label="Çıktılar">';

        if ($this->downloads !== null && $token !== '' && $now !== null) {
            foreach ([['xlsx', 'Excel'], ['pdf', 'PDF'], ['csv', 'CSV']] as [$bicim, $etiket]) {
                // İE#17 G5: href imzalı KALIR (JS'siz kullanıcı için aşamalı
                // geliştirme); JS varsa tıklama anında taze imza alınır.
                $html .= '<a class="tb" href="' . $e($this->downloads->adres($token, $bicim, $dil, $now))
                    . '" data-indir="' . $e($etiket) . '"'
                    . ' data-format="' . $e($bicim) . '" data-lang="' . $e($dil) . '" download>'
                    . ShareIcons::indir() . '<span>' . $e($etiket) . '</span></a>';
            }
        }
        $html .= '<button type="button" class="tb" data-yazdir>' . ShareIcons::yazdir() . '<span>Yazdır</span></button>'
            . '</div>';

        $link = $canonicalUrl !== '' ? $canonicalUrl : '';
        $mesaj = ShareTexts::mesaj($dil, [
            'liste' => (string) $list['name'],
            'adet' => $urunSayisi,
            'link' => $link,
            'tarih' => $this->gecerlilik($list),
        ]);
        $konu = ShareTexts::metin($dil, 'eposta_konu', [
            'liste' => (string) $list['name'],
            'adet' => $urunSayisi,
        ]);

        $html .= '<div class="pmenu">'
            . '<button type="button" class="tb pri" data-paylas-ac aria-expanded="false" aria-haspopup="true">'
            . ShareIcons::link() . '<span>Paylaş</span></button>'
            . '<div class="pmenu-liste" data-paylas-menu hidden'
            . ' data-link="' . $e($link) . '"'
            . ' data-mesaj="' . $e($mesaj) . '"'
            . ' data-konu="' . $e($konu) . '"'
            . ' data-dil="' . $e($dil) . '">'
            . '<button type="button" data-kopyala>' . ShareIcons::link() . 'Linki kopyala</button>'
            . '<button type="button" data-kanal="whatsapp">' . ShareIcons::whatsapp() . 'WhatsApp</button>'
            . '<button type="button" data-kanal="wechat" data-qr="1">' . ShareIcons::kare() . 'WeChat 微信</button>'
            . '<button type="button" data-kanal="qq">' . ShareIcons::disLink() . 'QQ</button>'
            . '<button type="button" data-kanal="dingtalk" data-qr="1">' . ShareIcons::kare() . 'DingTalk 钉钉</button>'
            . '<button type="button" data-kanal="telegram">' . ShareIcons::disLink() . 'Telegram</button>'
            . '<button type="button" data-kanal="eposta">' . ShareIcons::disLink() . 'E-posta</button>'
            . '<div class="pmenu-dil"><span>Gönderim dili</span>'
            . $this->dilSecici($token, $dil, $e)
            . '</div>'
            . '</div></div>';

        return $html;
    }

    /**
     * İE#15 C4 — "dile ayarlı gönder": seçilen dil bağlantıya `?lang=` olarak eklenir;
     * sayfa o dille açılır, paylaşım metni ve indirme çıktısı o dile göre gelir.
     *
     * @param callable(mixed): string $e
     */
    private function dilSecici(string $token, string $aktif, callable $e): string
    {
        $html = '';
        foreach (ShareTexts::DILLER as $secenek) {
            $adres = $token === ''
                ? '#'
                : '/liste/' . $token . ($secenek === 'tr' ? '' : '?lang=' . $secenek);
            $html .= '<a href="' . $e($adres) . '"' . ($secenek === $aktif ? ' class="secili" aria-current="true"' : '')
                . '>' . $e(ShareTexts::dilAdi($secenek)) . '</a>';
        }

        return $html;
    }

    /**
     * Paylaşım metnindeki geçerlilik tarihi — YOKSA satır hiç yazılmaz (uydurma yok).
     *
     * @param array<string, mixed> $list
     */
    private function gecerlilik(array $list): ?string
    {
        $tarih = $list['share_expires_at'] ?? null;
        if (!is_string($tarih) || $tarih === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($tarih))->format('d.m.Y');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * İE#15 F2 — link önizleme metni: liste adı + ürün sayısı + dönem.
     * PARA GEÇMEZ: önizleme sohbet uygulamasında herkese görünür.
     *
     * @param array<string, mixed> $list
     */
    private function onizlemeMetni(array $list, int $urunSayisi): string
    {
        $parcalar = [$urunSayisi . ' ürün'];
        $donem = $list['period'] ?? null;
        if (is_string($donem) && trim($donem) !== '') {
            $parcalar[] = trim($donem);
        }
        $parcalar[] = 'DDP teklif için paylaşılan tedarik listesi';

        return implode(' · ', $parcalar);
    }

    /**
     * Geçersiz/iptal/süresi dolmuş link sayfası (İE#10.5 ek — Ürün Sahibi talebi).
     * SABİT YANIT ilkesi (K51): neden ne olursa olsun AYNI sayfa döner.
     */
    public function renderNotFound(): string
    {
        // 404 sayfası da AYNI stil dosyasını kullanır — sürümsüz kalırsa bayat
        // önbellek burada da kurumsal görünümü bozar (İE#17 G1).
        $surum = htmlspecialchars(AppVersion::VALUE, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Bağlantı geçerli değil — Tedarikapp</title>
<link rel="icon" type="image/svg+xml" href="/panel/favicon.svg">
<link rel="stylesheet" href="/p-style.css?v=' . $surum . '">
</head>
<body>
<main class="hata-sayfasi">
    <section class="hata-karti">
        <img class="hata-marka" src="/panel/favicon.svg" alt="Tedarikapp" width="64" height="64">
        <h1>Bu bağlantı artık geçerli değil</h1>
        <p>Paylaşım bağlantısı kaldırılmış, yenilenmiş veya süresi dolmuş olabilir.
        Listeyi sizinle paylaşan kişiden <strong>güncel bağlantıyı</strong> isteyebilirsiniz.</p>
        <p class="hata-ipucu">Bağlantıyı elle yazdıysanız eksiksiz kopyaladığınızdan emin olun.</p>
    </section>
    <footer class="alt">Tedarikapp — Ürün Tedarik Asistanı</footer>
</main>
</body>
</html>';
    }

    /**
     * Ürün satırı + altındaki detay paneli.
     *
     * @param array<string, mixed> $product
     * @param array<int, string> $categoryNames
     * @param list<string> $galeri
     * @param callable(mixed): string $e
     */
    private function satir(array $product, array $categoryNames, int $sira, int $galeriIndex, array $galeri, callable $e): string
    {
        $statusKey = (string) $product['status'];
        [$rozetMetni] = TemplateV2::badge($statusKey);
        $rozet = str_replace("\n", ' ', $rozetMetni);
        $rozet = ltrim($rozet, '● ');
        $pill = $statusKey === 'cancelled' ? 'p-no' : ($statusKey === 'to_order' ? 'p-wt' : 'p-ok');

        // İE#14 A4: kategori panelden ya da kırıntı yolundan gelir; hiçbiri yoksa
        // hücre BOŞ kalır — "Kategorisiz" damgası basılmaz.
        $kategori = ProductDetails::kategori($product, $categoryNames, $this->values);
        $detay = ProductDetails::detay($product, $this->values);

        $gorsel = $galeri === []
            ? '<span class="pi"><span class="yok-gorsel">görsel<br>yok</span></span>'
            : '<span class="pi" data-galeri="' . $galeriIndex . '" data-sira="0" tabindex="0" role="button" aria-label="Galeriyi aç">'
                . '<img src="' . $e($galeri[0]) . '" alt="" loading="lazy">'
                . $this->videoRozeti($product, $e)
                . '</span>';

        // İE#15 D1 — LİNK DİSİPLİNİ: ürün adı DÜZ METİNDİR. Sayfadan dış siteye
        // çıkan TEK öğe "Ürüne git" düğmesidir; firma yanlışlıkla kaynak siteye
        // düşmesin, çıkış bilinçli ve tek noktadan olsun.
        $ad = (string) $product['name'];
        $adHtml = '<span class="pn">' . $e($ad) . '</span>';
        // İE#14 A1: ad ile orijinal AYNIYSA ikinci satır BASILMAZ (ortak kural).
        $orijinalMetin = \App\Services\Translation\ProductNaming::originalOf($product);
        $orijinal = $orijinalMetin === null ? '' : '<div class="pz zh">' . $e($orijinalMetin) . '</div>';

        // İE#15 D2: tek dış çıkış — yeni sekme, noopener/noreferrer/nofollow.
        $git = is_string($product['url'] ?? null) && $product['url'] !== ''
            ? '<a class="op-git" href="' . $e($product['url']) . '" target="_blank" rel="noopener noreferrer nofollow">'
                . ShareIcons::disLink() . 'Ürüne git</a>'
            : '';

        $satir = '<tr class="r" id="urun-' . $sira . '">
            <td class="c" style="font-weight:700;color:var(--t3)">' . $sira . '</td>
            <td><div class="prod">' . $gorsel . '<div><div class="pno">№ ' . $sira . '</div>'
                . $adHtml . $orijinal . '</div></div></td>
            <td class="mut"><span class="lab">DETAYLAR</span><span class="val">' . $this->hucre($detay, $e) . '</span></td>
            <td class="mut c"><span class="lab">VARYASYON</span><span class="val">' . $this->hucre($this->varyasyonMetni($product), $e) . '</span></td>
            <td class="mut c"><span class="lab">KATEGORİ</span><span class="val">' . $this->hucre($kategori, $e) . '</span></td>
            <td class="c"><span class="lab">KAYNAK</span><span class="val"><span class="src">'
                . $e(TemplateV2::platformLabel($product['platform'] ?? null)) . '</span></span></td>
            <td class="c"><span class="lab">DURUM</span><span class="val"><span class="pill ' . $pill . '">'
                . '<span class="d"></span>' . $e($rozet) . '</span></span></td>
            <td class="mut c not-hucre"><span class="lab">NOT</span><span class="val">' . $this->hucre($product['note'] ?? null, $e) . '</span></td>
            <td class="c"><span class="lab">MİKTAR</span><span class="val"><b>' . $e($product['qty']) . '</b></span></td>
            <td class="n"><span class="lab">VİTRİN FİYATI</span><span class="val">'
                . $this->para($product['price_yuan'] ?? null, '¥', $e) . '</span></td>
            <td class="n"><span class="lab">₺ KARŞILIĞI</span><span class="val">'
                . $this->para($product['price_yuan_tl'] ?? null, '₺', $e) . '</span></td>
            <td class="n mut"><span class="lab">DDP $</span><span class="val">'
                . $this->para($product['price_ddp_usd'] ?? null, '$', $e) . '</span></td>
            <td class="n mut"><span class="lab">DDP ₺</span><span class="val">'
                . $this->para($product['price_ddp_tl'] ?? null, '₺', $e) . '</span></td>
            <td><div class="ops">' . $git
                . '<button type="button" class="op-det" data-detay>Detaylar ' . ShareIcons::asagiOk() . '</button>'
                . '</div></td>
        </tr>';

        return $satir . $this->detaySatiri($product, $galeriIndex, $galeri, $e);
    }

    /**
     * Detay paneli: 16 alanlık bilgi ızgarası + varyasyonlar + not + galeri.
     * TEDARİK PUANI bölümü skor verisi gelene dek BASILMAZ (V3-A).
     *
     * @param array<string, mixed> $product
     * @param list<string> $galeri
     * @param callable(mixed): string $e
     */
    private function detaySatiri(array $product, int $galeriIndex, array $galeri, callable $e): string
    {
        // İE#14 A6: dolu alanlar üstte; boşlar katlanmış "Eksik bilgileri göster (N)"
        // içinde. Hepsi boşsa ÜRÜN BİLGİLERİ bölümü hiç basılmaz.
        ['dolu' => $dolu, 'bos' => $bos] = ProductFacts::grouped($product, $this->values);

        $bilgiler = '';
        if ($dolu !== []) {
            $izgara = '';
            foreach ($dolu as [$tr, $cjk, $deger]) {
                $izgara .= '<div><b>' . $e($tr) . ' <span class="zh">' . $e($cjk) . '</span></b>'
                    . '<span>' . $e($deger) . '</span></div>';
            }

            $eksik = '';
            if ($bos !== []) {
                $eksikIzgara = '';
                foreach ($bos as [$tr, $cjk]) {
                    $eksikIzgara .= '<div><b>' . $e($tr) . ' <span class="zh">' . $e($cjk) . '</span></b>'
                        . '<span class="yok">—</span></div>';
                }
                // <details>: satır içi script YOK — açılır davranış tarayıcının kendisi (K51 CSP).
                $eksik = '<details class="eks"><summary>Eksik bilgileri göster (' . count($bos) . ')</summary>'
                    . '<div class="sg">' . $eksikIzgara . '</div></details>';
            }

            $bilgiler = '<div>
                <div class="dh">ÜRÜN BİLGİLERİ <span class="zh">商品属性</span></div>
                <div class="sg">' . $izgara . '</div>' . $eksik . '
            </div>';
        }

        // İE#14 A3: değerler sözlükten geçer; arayüzde ilk 3 + "+N seçenek" (açılır).
        $varyasyonlar = $this->varyasyonListesi($product);
        $sag = '';
        if ($varyasyonlar !== []) {
            $gorunen = array_slice($varyasyonlar, 0, ValueSet::LIMIT);
            $gizli = array_slice($varyasyonlar, ValueSet::LIMIT, 40);
            $sag .= '<div class="dh">VARYASYONLAR <span class="zh">规格</span></div><div class="vr">';
            foreach ($gorunen as $varyasyon) {
                $sag .= '<div><span>' . $e($varyasyon) . '</span><b></b></div>';
            }
            $sag .= '</div>';
            if ($gizli !== []) {
                $sag .= '<details class="eks"><summary>+' . count($gizli) . ' seçenek</summary><div class="vr">';
                foreach ($gizli as $varyasyon) {
                    $sag .= '<div><span>' . $e($varyasyon) . '</span><b></b></div>';
                }
                $sag .= '</div></details>';
            }
        }
        if (is_string($product['note'] ?? null) && $product['note'] !== '') {
            $sag .= '<div class="nt">Not: ' . $e($product['note']) . '</div>';
        }

        // İE#17 G10 (PM kararı, 21 Ağu): detay panelindeki GALERİ ŞERİDİ KALDIRILDI.
        // Lightbox galeri ana görsel tıklamasıyla ÇALIŞMAYA DEVAM EDER — panelde
        // ikinci bir küçük resim şeridi tutmanın bilgi değeri yoktu, yer kaplıyordu.

        return '<tr class="dt"><td colspan="14"><div class="din">
            ' . $bilgiler . '
            <div class="sag">' . $sag . '</div>
        </div></td></tr>';
    }

    /**
     * İE#17 G3 — GİRİLMEMİŞ FİYAT BASILMAZ.
     *
     * Yerleşik sözleşme: fiyat POZİTİF DEĞİLSE girilmemiştir (ListPresenter::profit
     * aynı sözleşmeyle çalışır). Eskiden "$ 0.00" basılıyordu; firma bunu "DDP
     * bedeli sıfır" diye okuyabilir — yokluğu sıfır göstermek yanlış bilgidir.
     * Boş hücre, hücrenin kendi boşluk diliyle gösterilir; PARA SİMGESİ de basılmaz.
     *
     * Karar SUNUM katmanındadır: DB şeması ve ListPresenter alan sözleşmesi
     * değişmez (G3-e).
     *
     * @param callable(mixed): string $e
     */
    private function para(mixed $tutar, string $simge, callable $e): string
    {
        if (!self::pozitif($tutar)) {
            return '<span class="yok"></span>';
        }

        return $e($simge) . ' ' . $e($tutar);
    }

    /**
     * Biçimlenmiş para metni girilmiş mi? Kural TemplateV2'dedir — Excel, PDF,
     * CSV ve bu sayfa AYNI kaynaktan beslenir (tek yerde değişsin).
     */
    public static function pozitif(mixed $tutar): bool
    {
        return TemplateV2::girilmis($tutar);
    }

    /**
     * İE#14 A4: değer yoksa hücre BOŞ kalır — "—" bile basılmaz; boş sütun görsel
     * gürültüdür. Mobil kart düzeninde etiket görünür kaldığı için boş bir işaret
     * bırakılır, uydurma metin basılmaz.
     *
     * @param callable(mixed): string $e
     */
    private function hucre(mixed $deger, callable $e): string
    {
        $metin = is_scalar($deger) ? trim((string) $deger) : '';

        return $metin === '' ? '<span class="yok"></span>' : $e($metin);
    }

    /**
     * @param array<string, mixed> $product
     * @param callable(mixed): string $e
     */
    /**
     * İE#15 E3/E4 — VİDEO ROZETİ.
     *
     * Rozet iki durumda basılır: (1) oynatılabilir adres var, (2) yakalama "video
     * var" diyor ama oynatılabilir adres alınamamış (1688 videoları imzalı MTOP
     * isteği ister). İKİNCİ durumda modal boş açılmaz: nazik bir açıklama ve
     * varsa kaynak sayfa bağlantısı gösterilir. Veri hiç yoksa rozet BASILMAZ —
     * sahte rozet kullanıcıyı boş modala götürürdü.
     *
     * @param array<string, mixed> $product
     * @param callable(mixed): string $e
     */
    private function videoRozeti(array $product, callable $e): string
    {
        $video = $product['video_url'] ?? null;
        if (is_string($video) && $video !== '') {
            return '<span class="vb" data-video="' . $e($video) . '" role="button" tabindex="0" aria-label="Videoyu oynat">'
                . ShareIcons::oynat() . '</span>';
        }

        if (!self::videoVar($product)) {
            return '';
        }

        $kaynak = is_string($product['url'] ?? null) && $product['url'] !== '' ? (string) $product['url'] : '';

        return '<span class="vb" data-video-yok="1" data-video-kaynak="' . $e($kaynak) . '"'
            . ' role="button" tabindex="0" aria-label="Video bilgisi">' . ShareIcons::oynat() . '</span>';
    }

    /**
     * Yakalama "bu üründe video var" dedi mi? (raw.video bloğu — İE#11 C3'ten beri
     * id/poster orada taşınıyor; oynatılabilir adres v1'de alınamıyordu.)
     *
     * @param array<string, mixed> $product
     */
    public static function videoVar(array $product): bool
    {
        /** @var mixed $raw */
        $raw = $product['raw_attributes'] ?? null;
        if (is_string($raw)) {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return false;
        }

        $video = $raw['video'] ?? ($raw['raw']['video'] ?? null);
        if (!is_array($video)) {
            return false;
        }

        $id = $video['id'] ?? null;
        $poster = $video['poster'] ?? null;

        return (is_string($id) && $id !== '' && $id !== '0') || (is_string($poster) && $poster !== '');
    }

    /** @param array<string, mixed> $list */
    private function belgeKodu(array $list): string
    {
        return TemplateV2::documentCode((int) $list['id'], (int) date('Y'), 'A');
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
     * İE#18 G2 — TEK SATIR META: firma · web · e-posta · dönem · kopya türü ·
     * tedarikçi. Ayraç TEK TİP ("·"); BOŞ ALAN BASILMAZ (antet ayarları eksikse
     * satır kısalır, "· ·" gibi boşluk artığı oluşmaz).
     *
     * @param array{company?: string|null, web?: string|null, email?: string|null, prepared_by?: string|null} $antet
     * @param array<string, mixed> $list
     */
    private function metaSatiri(array $antet, array $list): string
    {
        $parcalar = [
            $antet['company'] ?? null,
            $antet['web'] ?? null,
            $antet['email'] ?? null,
            $list['period'] ?? null,
            'Firma kopyası',
            $list['supplier_name'] ?? null,
        ];

        $temiz = [];
        foreach ($parcalar as $parca) {
            if (is_string($parca) && trim($parca) !== '') {
                $temiz[] = trim($parca);
            }
        }

        return implode(' · ', $temiz);
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return list<string>
     */
    private function galeriAdresleri(array $product): array
    {
        $adresler = [];
        if (is_string($product['main_image'] ?? null) && $product['main_image'] !== '') {
            $adresler[] = (string) $product['main_image'];
        }
        foreach (is_array($product['images'] ?? null) ? $product['images'] : [] as $image) {
            $url = is_array($image) ? ($image['url'] ?? null) : null;
            if (is_string($url) && $url !== '' && !in_array($url, $adresler, true)) {
                $adresler[] = $url;
            }
        }

        return $adresler;
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return list<string>
     */
    private function varyasyonListesi(array $product): array
    {
        $out = [];
        $secim = $product['sku_selection'] ?? null;
        if (is_array($secim)) {
            foreach ($secim as $anahtar => $deger) {
                if (is_scalar($deger)) {
                    $out[] = (is_string($anahtar) && !is_numeric($anahtar) ? $anahtar . ': ' : '') . (string) $deger;
                }
            }
        }

        $matris = $product['sku_matrix'] ?? null;
        if (is_array($matris)) {
            foreach ($matris as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $props = is_array($entry['props'] ?? null) ? $entry['props'] : $entry;
                $parcalar = [];
                foreach ($props as $deger) {
                    if (is_scalar($deger)) {
                        $parcalar[] = (string) $deger;
                    }
                }
                if ($parcalar !== []) {
                    $out[] = implode(' / ', $parcalar);
                }
                if (count($out) >= 40) {
                    break;
                }
            }
        }

        // İE#14 A3: değerler A2 hattının belirlenimci katmanından geçer (灰色 → Gri).
        return $this->values !== null ? $this->values->values($out) : $out;
    }

    /** @param array<string, mixed> $product */
    private function varyasyonMetni(array $product): ?string
    {
        $liste = $this->varyasyonListesi($product);
        if ($liste === []) {
            return null;
        }
        // İE#17 G8-b: satır hücresinde YALNIZ kompakt rozet ("40 seçenek").
        // Tam liste detay panelindeki VARYASYONLAR bölümündedir.
        if ($this->values !== null) {
            return $this->values->ozet($liste);
        }
        if (count($liste) === 1 && mb_strlen($liste[0]) <= 40) {
            return $liste[0];
        }

        return count($liste) . ' seçenek';
    }
}
