<?php

declare(strict_types=1);

namespace App\Services\Share;

/**
 * KİLİT EKRANI (İE#18 Görev 6-b · K62 · İE#21 B7 · referans: `erisim-anahtar-ekrani.png`).
 *
 * GÜVENLİK GERÇEK, MAKYAJ DEĞİL: bu sayfa anahtar doğrulanmadan render edilir ve
 * içinde LİSTE VERİSİ YOKTUR — ne ürün adı, ne fiyat, ne adet. Arkadaki buğulu
 * iskelet SABİT BİR DESENDİR (uydurma "temsili" satırlar), gerçek veriden
 * türetilmez. Görünen tek gerçek bilgi liste adı ve firma adıdır; onlar da
 * anahtarı gönderen kişinin doğru yerde olduğunu anlaması içindir.
 *
 * İE#21 EK-4 (PM denetimi, 24 Ağu 2026) — ÖNCEKİ İKİ SAPMA GERİ ALINDI:
 *
 *  1. **TAZELEME SAYACI EKLENDİ.** Halkalı geri sayım 10 dakikada bir sayfayı
 *     KENDİ KENDİNE yeniler. Bu sayaç ANAHTARIN SÜRESİ DEĞİLDİR (K62 değişmedi:
 *     anahtar süresizdir) ve öyle sunulmaz — etiketi "Bu güvenli giriş ekranı
 *     %s içinde tazelenir"dir. Süre dolunca hiçbir şey kilitlenmez, kaybolmaz;
 *     ekran yenilenir ve firma girişine kaldığı yerden devam eder.
 *  2. **"YENİ ANAHTAR İSTE" DÜĞMESİ GERİ GELDİ** — ama yalnız Ayarlar'da
 *     paylaşım iletişim numarası doluysa. Düğme WhatsApp köprüsüdür: hazır
 *     mesajı seçili dilde açar. ANAHTAR MESAJDA ASLA YER ALMAZ ve sistemde
 *     anahtar-talep ucu YOKTUR — üretim tek yetkilidedir. Numara boşsa düğme
 *     basılmaz, bilgi satırı kalır (zarif bozulma).
 *
 * DENEME HAKKI YAZISI KALICI DEĞİLDİR (EK-4 madde 4): hız sınırı aynen çalışır
 * ama ekranda sürekli "dakikada 5 deneme" yazmaz — bu, hata yapmamış kullanıcıyı
 * suçlu gibi karşılıyordu. Uyarı YALNIZ art arda hatalı denemede belirir ve
 * KALAN HAK SAYISINI söylemez (K51).
 *
 * K51 CSP: satır içi script/stil YOK — davranış `p-share.js`te, stiller
 * `p-style.css`te.
 */
final class ShareLockPage
{
    /** Ekranın kendini tazelemesi (saniye) — sayaç bunu sayar. */
    public const TAZELEME_SANIYE = 600;

    /** Bu sayıdan itibaren "art arda hatalı deneme" uyarısı belirir. */
    public const UYARI_ESIGI = 2;

    /** @var array<string, array<string, string>> */
    private const METIN = [
        'tr' => [
            'ustluk' => 'GÜVENLİ LİSTE ERİŞİMİ',
            'aciklama' => 'Bu liste erişim anahtarıyla korunuyor. Size iletilen 6 haneli anahtarı girin.',
            'sifreli' => 'Şifreli bağlantı',
            'bitis' => 'Bağlantı bitişi',
            'suresiz' => 'Süre sınırı yok',
            'anahtar' => 'Erişim anahtarı',
            'ipucu' => 'Kodu yapıştırabilirsiniz · Rakam girildikçe otomatik ilerler',
            'dugme' => 'Listeyi görüntüle',
            'kalan' => 'hane kaldı',
            'enter' => 'Enter ile devam',
            'anahtar_yok' => 'Anahtarınız yok mu?',
            'anahtar_yok_not' => 'Yeni anahtar için listeyi paylaşan kişiyle iletişime geçin.',
            'gizlilik' => 'Kod, oturum ve erişim bilgileri üçüncü taraflarla paylaşılmaz.',
            'hata' => 'Anahtar hatalı.',
            'tazelenir' => 'Bu güvenli giriş ekranı {sure} içinde tazelenir',
            'anahtar_iste' => 'Yeni anahtar iste',
            'wa_mesaj' => 'Merhaba, {liste} listesinin erişim anahtarını rica ediyorum. Bağlantı: {link}',
            'ardisik_hata' => 'Art arda hatalı deneme yapıldı; kısa bir süre sonra yeniden deneyin.',
            'baslik_son' => 'Erişim anahtarı',
        ],
        'en' => [
            'ustluk' => 'SECURE LIST ACCESS',
            'aciklama' => 'This list is protected by an access key. Enter the 6-character key you received.',
            'sifreli' => 'Encrypted connection',
            'bitis' => 'Link expires',
            'suresiz' => 'No time limit',
            'anahtar' => 'Access key',
            'ipucu' => 'You can paste the code · Focus advances as you type',
            'dugme' => 'View list',
            'kalan' => 'characters left',
            'enter' => 'Press Enter to continue',
            'anahtar_yok' => 'No key?',
            'anahtar_yok_not' => 'Contact the person who shared this list to get a new key.',
            'gizlilik' => 'The code, session and access data are not shared with third parties.',
            'hata' => 'Incorrect key.',
            'tazelenir' => 'This secure access screen refreshes in {sure}',
            'anahtar_iste' => 'Request a new key',
            'wa_mesaj' => 'Hello, may I have the access key for the list {liste}? Link: {link}',
            'ardisik_hata' => 'Several incorrect attempts in a row; please try again shortly.',
            'baslik_son' => 'Access key',
        ],
        'zh' => [
            'ustluk' => '安全清单访问',
            'aciklama' => '此清单受访问密钥保护。请输入您收到的 6 位密钥。',
            'sifreli' => '加密连接',
            'bitis' => '链接到期',
            'suresiz' => '无时间限制',
            'anahtar' => '访问密钥',
            'ipucu' => '可以粘贴密钥 · 输入时自动跳到下一格',
            'dugme' => '查看清单',
            'kalan' => '位待输入',
            'enter' => '按回车继续',
            'anahtar_yok' => '没有密钥？',
            'anahtar_yok_not' => '请联系共享此清单的人索取新密钥。',
            'gizlilik' => '密钥、会话和访问信息不会与第三方共享。',
            'hata' => '密钥错误。',
            'tazelenir' => '此安全访问页面将在 {sure} 后刷新',
            'anahtar_iste' => '索取新密钥',
            'wa_mesaj' => '您好，请提供清单「{liste}」的访问密钥。链接：{link}',
            'ardisik_hata' => '连续多次输入错误，请稍后再试。',
            'baslik_son' => '访问密钥',
        ],
    ];

    /**
     * @param array<string, mixed> $list ListPresenter::list çıktısı (yalnız ad/firma/bitiş kullanılır)
     * @param bool $hatali önceki denemede anahtar yanlış mıydı?
     * @param array{iletisim?: string|null, ardisik_hata?: int, adres?: string|null} $ek
     *        iletisim: wa.me için RAKAM dizisi (boşsa düğme basılmaz),
     *        ardisik_hata: son dakikadaki deneme sayısı (uyarı eşiği için),
     *        adres: paylaşım sayfasının kanonik adresi (WhatsApp metnine girer)
     */
    public function render(
        array $list,
        string $token,
        string $surum,
        bool $hatali = false,
        string $dil = 'tr',
        array $ek = [],
    ): string {
        $dil = ShareTexts::dil($dil);
        $m = self::METIN[$dil] ?? self::METIN['tr'];
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        // Buğulu arka plan: SABİT desen. Gerçek satır sayısı bile sızmasın diye
        // her listede aynı sekiz iskelet satırı basılır.
        $iskelet = '';
        for ($i = 0; $i < 8; $i++) {
            $iskelet .= '<div class="kis-satir">'
                . '<span class="kis-kutu"></span>'
                . '<span class="kis-cizgi w1"></span>'
                . '<span class="kis-cizgi w2"></span>'
                . '<span class="kis-cizgi w3"></span>'
                . '<span class="kis-cizgi w4"></span>'
                . '</div>';
        }

        $kutular = '';
        for ($i = 0; $i < 6; $i++) {
            // JS'SİZ ÇALIŞMA (İE#18 G6 düzeltmesi): kutuların KENDİ adı vardır.
            // Eskiden yalnız gizli alan gönderiliyordu; JavaScript kapalıyken o
            // alan boş kalıyor ve kapı 401 veriyordu — yani "aşamalı geliştirme"
            // sözü kâğıt üstünde kalıyordu. Artık haneler de gönderilir.
            $kutular .= '<input class="kis-hane" type="text" name="anahtar_hane[]"'
                . ' inputmode="latin" maxlength="1" autocomplete="off"'
                . ' aria-label="' . $e($m['anahtar']) . ' ' . ($i + 1) . '" data-hane="' . $i . '"'
                . ($i === 0 ? ' autofocus' : '') . '>';
        }

        $firma = is_string($list['supplier_name'] ?? null) && $list['supplier_name'] !== ''
            ? (string) $list['supplier_name']
            : '';

        // GERÇEK bilgi: bağlantının bitişi. Yoksa "süre sınırı yok" yazar —
        // olmayan bir geri sayım gösterilmez (bkz. sınıf başlığı, sapma 1).
        $bitis = is_string($list['share_expires_at'] ?? null) && $list['share_expires_at'] !== ''
            ? $this->tarih((string) $list['share_expires_at'])
            : $m['suresiz'];

        $dilSecici = '';
        foreach (ShareTexts::DILLER as $secenek) {
            $etiket = strtoupper($secenek);
            $dilSecici .= $secenek === $dil
                ? '<span class="kis-dil-aktif" aria-current="true">' . $e($etiket) . '</span>'
                : '<a class="kis-dil" href="/liste/' . $e($token) . '?lang=' . $e($secenek) . '">' . $e($etiket) . '</a>';
        }

        return '<!DOCTYPE html>
<html lang="' . $e($dil) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta http-equiv="refresh" content="' . self::TAZELEME_SANIYE . '">
<title>' . $e($list['name']) . ' — ' . $e($m['baslik_son']) . '</title>
<link rel="icon" type="image/svg+xml" href="/panel/favicon.svg">
<link rel="stylesheet" href="/p-style.css?v=' . $e($surum) . '">
</head>
<body class="kilit-govde">
<div class="kis-fon" aria-hidden="true">' . $iskelet . '</div>

<main class="kis-kapi" role="main">
  <form class="kis-kart' . ($hatali ? ' sallan' : '') . '" method="post"
        action="/liste/' . $e($token) . '/anahtar" data-anahtar-form>
    <div class="kis-ust">
      <img class="kis-logo" src="/panel/apple-touch-icon.png" alt="" width="52" height="52">
      <nav class="kis-diller" aria-label="' . $e($m['anahtar']) . '">' . $dilSecici . '</nav>
    </div>

    <p class="kis-ustluk">' . $e($m['ustluk']) . '</p>
    <h1 class="kis-baslik">' . $e($list['name']) . '</h1>'
    . ($firma === '' ? '' : '<p class="kis-firma">' . $e($firma) . '</p>') . '
    <p class="kis-aciklama">' . $e($m['aciklama']) . '</p>

    <div class="kis-serit">
      <span class="kis-serit-sol">' . $e($m['sifreli']) . '</span>
      <span class="kis-serit-sag">
        <span class="kis-serit-etiket">' . $e($m['bitis']) . '</span>
        <strong class="kis-serit-deger">' . $e($bitis) . '</strong>
      </span>
    </div>

    ' . $this->tazelemeSayaci($m, $e) . '

    <label class="kis-etiket">' . $e($m['anahtar']) . '</label>
    <div class="kis-haneler" data-anahtar-haneler>' . $kutular . '</div>
    <input type="hidden" name="anahtar" data-anahtar-deger>
    <input type="hidden" name="lang" value="' . $e($dil) . '">
    <p class="kis-ipucu">' . $e($m['ipucu']) . '</p>

    <p class="kis-hata"' . ($hatali ? '' : ' hidden') . ' data-anahtar-hata>' . $e($m['hata']) . '</p>

    <button type="submit" class="kis-dugme">
      <span class="kis-dugme-ad">' . $e($m['dugme']) . '</span>
      <span class="kis-dugme-alt" data-anahtar-kalan data-kalan-etiket="' . $e($m['kalan']) . '">6 ' . $e($m['kalan']) . '</span>
    </button>

    <div class="kis-alt-serit">
      <span>' . $e($m['enter']) . '</span>
    </div>

    ' . $this->ardisikHataUyarisi($m, (int) ($ek['ardisik_hata'] ?? 0), $e) . '

    <p class="kis-not"><strong>' . $e($m['anahtar_yok']) . '</strong> ' . $e($m['anahtar_yok_not']) . '</p>
    ' . $this->anahtarIsteDugmesi($m, $list, $token, $ek, $e) . '
    <p class="kis-gizlilik">' . $e($m['gizlilik']) . '</p>
  </form>
</main>
<script src="/p-share.js?v=' . $e($surum) . '" defer></script>
</body>
</html>';
    }

    /**
     * TAZELEME SAYACI (EK-4 madde 2).
     *
     * Halka ve rakam JS ile döner (`p-share.js`); sunucu yalnız başlangıç
     * değerini ve etiketi basar. JavaScript kapalıysa `<meta refresh>` devreye
     * girer — söz "ekran tazelenir"dir ve o söz betiksiz de tutulur.
     *
     * @param array<string, string> $m
     */
    private function tazelemeSayaci(array $m, \Closure $e): string
    {
        $dakika = intdiv(self::TAZELEME_SANIYE, 60);
        $etiket = str_replace('{sure}', sprintf('%02d:00', $dakika), $m['tazelenir']);

        return '<div class="kis-tazele" data-tazele="' . self::TAZELEME_SANIYE . '">'
            . '<span class="kis-halka" aria-hidden="true"><i data-tazele-halka></i></span>'
            . '<span class="kis-tazele-yazi" data-tazele-yazi'
            . ' data-tazele-kalip="' . $e($m['tazelenir']) . '">' . $e($etiket) . '</span>'
            . '</div>';
    }

    /**
     * ART ARDA HATALI DENEME UYARISI (EK-4 madde 4).
     *
     * Kalan hak sayısı SÖYLENMEZ (K51 sabit dil): "3 hakkın kaldı" demek,
     * saldırgana bütçesini bildirmektir. Söylenen şey davranıştır: art arda
     * hata var, biraz bekle.
     *
     * @param array<string, string> $m
     */
    private function ardisikHataUyarisi(array $m, int $deneme, \Closure $e): string
    {
        if ($deneme < self::UYARI_ESIGI) {
            return '';
        }

        return '<p class="kis-ardisik" data-ardisik>' . $e($m['ardisik_hata']) . '</p>';
    }

    /**
     * "YENİ ANAHTAR İSTE" — WhatsApp köprüsü (EK-4 madde 3).
     *
     * Numara YOKSA düğme BASILMAZ: tıklanınca hiçbir şey yapmayan bir düğme,
     * olmayan bir kanal vaat eder. Hazır mesaj seçili dildedir ve içinde ANAHTAR
     * YOKTUR — anahtar yalnız listeyi paylaşan kişide üretilir; mesaj sadece
     * "rica ediyorum" der.
     *
     * @param array<string, string> $m
     * @param array<string, mixed> $list
     * @param array{iletisim?: string|null, ardisik_hata?: int, adres?: string|null} $ek
     */
    private function anahtarIsteDugmesi(array $m, array $list, string $token, array $ek, \Closure $e): string
    {
        $numara = is_string($ek['iletisim'] ?? null) ? preg_replace('/\D+/', '', (string) $ek['iletisim']) : '';
        if ($numara === null || $numara === '') {
            return '';
        }

        $adres = is_string($ek['adres'] ?? null) && $ek['adres'] !== ''
            ? (string) $ek['adres']
            : '/liste/' . $token;

        $mesaj = str_replace(
            ['{liste}', '{link}'],
            [(string) $list['name'], $adres],
            $m['wa_mesaj'],
        );

        return '<a class="kis-iste" rel="noreferrer noopener" target="_blank" data-anahtar-iste'
            . ' href="https://wa.me/' . $e($numara) . '?text=' . rawurlencode($mesaj) . '">'
            . $e($m['anahtar_iste']) . '</a>';
    }

    /**
     * ISO tarihi gün/ay/yıl olarak kısaltır; ayrıştırılamazsa olduğu gibi bırakılır.
     *
     * Tarih, DİZEDEKİ SAAT DİLİMİYLE biçimlenir — `date()` sunucunun varsayılan
     * dilimini kullanır ve UTC bir sunucuda "31.12.2026 00:00+03:00" bir gün geri
     * kayıp "30.12.2026" olarak basılırdı. Firma için bir günlük kayma, bitmiş
     * sanılan bir bağlantı demektir.
     */
    private function tarih(string $iso): string
    {
        try {
            return (new \DateTimeImmutable($iso))->format('d.m.Y');
        } catch (\Exception) {
            return $iso;
        }
    }
}
