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
 * REFERANS KARESİNDEN SAPMALAR (İE#21 B7 — bilinçli, ÇIKTI RAPORU'nda bildirildi):
 *
 *  1. **"Anahtar süresi 09:42" geri sayımı KONULMADI.** Erişim anahtarının süresi
 *     YOKTUR (K62): anahtar listeye bağlıdır, sahibi iptal edene kadar geçerlidir.
 *     Sayaç koymak, olmayan bir kuralı varmış gibi göstermek olurdu; firma sayaç
 *     bitmesin diye acele eder, sonra sayaç sıfırlanınca güveni kırılırdı. Yerine
 *     GERÇEK olan bilgi yazıyor: bağlantının bitiş tarihi (varsa) ve deneme hakkı.
 *  2. **"Yeni anahtar iste" DÜĞMESİ değil, YAZI.** Panelin firmaya mesaj gönderen
 *     bir kanalı yok; düğme koymak tıklandığında hiçbir şey yapmayan bir vaat
 *     olurdu. Referans karenin kendi alt satırı da bunu söylüyor: "Yeni anahtar
 *     için listeyi paylaşan kişiyle iletişime geçin."
 *
 * K51 CSP: satır içi script/stil YOK — davranış `p-share.js`te, stiller
 * `p-style.css`te.
 */
final class ShareLockPage
{
    /** Dakikada izin verilen anahtar denemesi — ShareGate ile aynı sayı. */
    private const DENEME_HAKKI = ShareGate::MAX_ANAHTAR_PER_MINUTE;

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
            'deneme' => 'dakikada %d deneme hakkı',
            'enter' => 'Enter ile devam',
            'anahtar_yok' => 'Anahtarınız yok mu?',
            'anahtar_yok_not' => 'Yeni anahtar için listeyi paylaşan kişiyle iletişime geçin.',
            'gizlilik' => 'Kod, oturum ve erişim bilgileri üçüncü taraflarla paylaşılmaz.',
            'hata' => 'Anahtar hatalı.',
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
            'deneme' => '%d attempts per minute',
            'enter' => 'Press Enter to continue',
            'anahtar_yok' => 'No key?',
            'anahtar_yok_not' => 'Contact the person who shared this list to get a new key.',
            'gizlilik' => 'The code, session and access data are not shared with third parties.',
            'hata' => 'Incorrect key.',
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
            'deneme' => '每分钟 %d 次尝试',
            'enter' => '按回车继续',
            'anahtar_yok' => '没有密钥？',
            'anahtar_yok_not' => '请联系共享此清单的人索取新密钥。',
            'gizlilik' => '密钥、会话和访问信息不会与第三方共享。',
            'hata' => '密钥错误。',
            'baslik_son' => '访问密钥',
        ],
    ];

    /**
     * @param array<string, mixed> $list ListPresenter::list çıktısı (yalnız ad/firma/bitiş kullanılır)
     * @param bool $hatali önceki denemede anahtar yanlış mıydı?
     */
    public function render(array $list, string $token, string $surum, bool $hatali = false, string $dil = 'tr'): string
    {
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

    <label class="kis-etiket">' . $e($m['anahtar']) . '</label>
    <div class="kis-haneler" data-anahtar-haneler>' . $kutular . '</div>
    <input type="hidden" name="anahtar" data-anahtar-deger>
    <p class="kis-ipucu">' . $e($m['ipucu']) . '</p>

    <p class="kis-hata"' . ($hatali ? '' : ' hidden') . ' data-anahtar-hata>' . $e($m['hata']) . '</p>

    <button type="submit" class="kis-dugme">
      <span class="kis-dugme-ad">' . $e($m['dugme']) . '</span>
      <span class="kis-dugme-alt" data-anahtar-kalan data-kalan-etiket="' . $e($m['kalan']) . '">6 ' . $e($m['kalan']) . '</span>
    </button>

    <div class="kis-alt-serit">
      <span>' . $e(sprintf($m['deneme'], self::DENEME_HAKKI)) . '</span>
      <span>' . $e($m['enter']) . '</span>
    </div>

    <p class="kis-not"><strong>' . $e($m['anahtar_yok']) . '</strong> ' . $e($m['anahtar_yok_not']) . '</p>
    <p class="kis-gizlilik">' . $e($m['gizlilik']) . '</p>
  </form>
</main>
<script src="/p-share.js?v=' . $e($surum) . '" defer></script>
</body>
</html>';
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
