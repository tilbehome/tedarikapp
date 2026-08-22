<?php

declare(strict_types=1);

namespace App\Services\Share;

/**
 * KİLİT EKRANI (İE#18 Görev 6-b · K62).
 *
 * GÜVENLİK GERÇEK, MAKYAJ DEĞİL: bu sayfa anahtar doğrulanmadan render edilir ve
 * içinde LİSTE VERİSİ YOKTUR — ne ürün adı, ne fiyat, ne adet. Arkadaki buğulu
 * iskelet SABİT BİR DESENDİR (uydurma "temsili" satırlar), gerçek veriden
 * türetilmez. Görünen tek gerçek bilgi liste adı ve firma adıdır; onlar da
 * anahtarı gönderen kişinin doğru yerde olduğunu anlaması içindir.
 *
 * K51 CSP: satır içi script/stil YOK — davranış `p-share.js`te, stiller
 * `p-style.css`te.
 */
final class ShareLockPage
{
    /**
     * @param array<string, mixed> $list ListPresenter::list çıktısı (yalnız ad/firma kullanılır)
     * @param bool $hatali önceki denemede anahtar yanlış mıydı?
     */
    public function render(array $list, string $token, string $surum, bool $hatali = false, string $dil = 'tr'): string
    {
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
                . ' aria-label="Anahtar hanesi ' . ($i + 1) . '" data-hane="' . $i . '"'
                . ($i === 0 ? ' autofocus' : '') . '>';
        }

        $firma = is_string($list['supplier_name'] ?? null) && $list['supplier_name'] !== ''
            ? (string) $list['supplier_name']
            : '';

        return '<!DOCTYPE html>
<html lang="' . $e($dil) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . $e($list['name']) . ' — Erişim anahtarı</title>
<link rel="icon" type="image/svg+xml" href="/panel/favicon.svg">
<link rel="stylesheet" href="/p-style.css?v=' . $e($surum) . '">
</head>
<body class="kilit-govde">
<div class="kis-fon" aria-hidden="true">' . $iskelet . '</div>

<main class="kis-kapi" role="main">
  <form class="kis-kart' . ($hatali ? ' sallan' : '') . '" method="post"
        action="/liste/' . $e($token) . '/anahtar" data-anahtar-form>
    <img class="kis-logo" src="/panel/apple-touch-icon.png" alt="" width="52" height="52">
    <h1 class="kis-baslik">' . $e($list['name']) . '</h1>'
    . ($firma === '' ? '' : '<p class="kis-firma">' . $e($firma) . '</p>') . '
    <p class="kis-aciklama">Bu liste erişim anahtarıyla korunuyor. Size iletilen
       6 haneli anahtarı girin.</p>

    <div class="kis-haneler" data-anahtar-haneler>' . $kutular . '</div>
    <input type="hidden" name="anahtar" data-anahtar-deger>

    <p class="kis-hata"' . ($hatali ? '' : ' hidden') . ' data-anahtar-hata>Anahtar hatalı.</p>

    <button type="submit" class="kis-dugme">Görüntüle</button>
    <p class="kis-not">Anahtarı bilmiyorsanız listeyi paylaşan kişiden isteyin.</p>
  </form>
</main>
<script src="/p-share.js?v=' . $e($surum) . '" defer></script>
</body>
</html>';
    }
}
