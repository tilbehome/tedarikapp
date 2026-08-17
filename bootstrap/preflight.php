<?php

/**
 * ÖN KONTROL KAPISI (K40/K41) + erken evre hata sayfası (K42).
 *
 * Bu dosya vendor/autoload'dan ÖNCE koşar ve BİLEREK eski/temkinli PHP
 * sözdizimiyle yazılmıştır (tip bildirimi yok, ok fonksiyonu yok, null
 * birleştirme yok): yanlış PHP sürümünde bile PARSE EDİLİR ve kullanıcıya
 * çıplak 500 yerine NE OLDUĞUNU söyleyen bir sayfa gösterir.
 *
 * Bugünkü üretim olayının dersi (K42): "site 500 verdi"nin teşhisi yoktu.
 * Bu kapı geçemeyen her durumda 503 + madde madde eksik + çözüm gösterir;
 * geçtiğinde tamamen SESSİZDİR.
 *
 * SIR KURALI: bu sayfalar hiçbir koşulda .env içeriği, şifre, anahtar
 * veya token göstermez — bu evrede zaten hiçbiri okunmamıştır.
 */

/**
 * Minimal statik hata/durum sayfası (saf PHP; framework ve vendor YOK).
 *
 * @param int    $status HTTP durum kodu (503 ön kontrol, 500 erken evre hatası)
 * @param string $title  Sayfa başlığı
 * @param array  $lines  Madde listesi (düz metin; HTML'e burada kaçırılır)
 * @param string $detail Teknik detay bloğu ('' = gizli)
 */
function tedarikapp_erken_hata_sayfasi($status, $title, $lines, $detail)
{
    if (!headers_sent()) {
        header('HTTP/1.1 ' . $status . ' ' . ($status === 503 ? 'Service Unavailable' : 'Internal Server Error'));
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }

    $items = '';
    foreach ($lines as $line) {
        $items .= '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
    }

    $detailHtml = '';
    if ($detail !== '') {
        $detailHtml = '<details><summary>Teknik detay (destek icin)</summary><pre>'
            . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre></details>';
    }

    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:44rem;margin:4rem auto;padding:0 1rem;'
        . 'line-height:1.6;color:#1f2430}h1{font-size:1.4rem}ul{padding-left:1.2rem}'
        . 'pre{background:#f4f5f7;border:1px solid #d9dce3;border-radius:6px;padding:.8rem;'
        . 'white-space:pre-wrap;word-break:break-word;font-size:.85rem}'
        . 'details{margin-top:1rem}summary{cursor:pointer;font-weight:600}'
        . '.badge{display:inline-block;background:#fde8e8;color:#9b1c1c;border-radius:4px;'
        . 'padding:.1rem .5rem;font-size:.8rem;font-weight:700}</style></head><body>'
        . '<p class="badge">tedarikapp — ' . (int) $status . '</p>'
        . '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<ul>' . $items . '</ul>'
        . $detailHtml
        . '<p>Bu ekrandaki bilgileri destek talebinize aynen kopyalayabilirsiniz; '
        . 'gizli bilgi (şifre, anahtar) içermez.</p>'
        . '</body></html>';

    exit;
}

/**
 * K40 ön kontrol: PHP sürümü + zorunlu eklentiler + vendor varlığı.
 * docs/SUNUCU-PROFILI.md ile birebir aynı liste; sodium BİLEREK yok (K39).
 */
function tedarikapp_on_kontrol($basePath)
{
    $sorunlar = array();

    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        $sorunlar[] = 'PHP surumu cok eski: ' . PHP_VERSION . ' (en az 8.1 gerekir). '
            . 'cPanel > MultiPHP Manager ile ea-php81 veya ustunu secin.';
    }

    $zorunlu = array('pdo_mysql', 'curl', 'gd', 'mbstring', 'zip', 'bcmath', 'openssl');
    foreach ($zorunlu as $eklenti) {
        if (!extension_loaded($eklenti)) {
            $sorunlar[] = 'PHP eklentisi eksik: ' . $eklenti . '. '
                . 'cPanel > Select PHP Version / sunucu yoneticisi ile etkinlestirin.';
        }
    }

    if (!is_file($basePath . '/vendor/autoload.php')) {
        $sorunlar[] = 'vendor/ klasoru eksik veya yarim: release zip eksiksiz acilmamis olabilir. '
            . 'Zip icinde vendor/autoload.php bulunmali (sunucuda composer CALISTIRILMAZ - docs/07).';
    }

    if (count($sorunlar) > 0) {
        tedarikapp_erken_hata_sayfasi(
            503,
            'Sunucu bu uygulamayı henüz çalıştıramıyor',
            $sorunlar,
            'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ') - ' . php_uname('s') . ' - ' . date('c')
        );
    }
    // Kapı geçildi: tamamen sessiz (K40).
}
