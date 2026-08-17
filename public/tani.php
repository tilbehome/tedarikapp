<?php

/**
 * TEK DOSYALIK TEŞHİS (K45) — framework YOK, vendor YOK, saf PHP.
 * Amaç: "NOT_FOUND ama neden?" sorusunu bitirmek. Bu sayfa sunucunun isteği
 * NASIL gördüğünü ve dosyaların NEREDE olduğunu olduğu gibi basar.
 * SIR BASMAZ (.env/config içeriği okunmaz, yalnız VAR/YOK denir).
 * Kurulum bitince silinebilir; kalması da bilgi sızdırmaz ama şart değil.
 */

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__); // public/'in bir üstü = uygulama kökü

$var = function ($isim) {
    $deger = $_SERVER[$isim] ?? '(yok)';

    return is_string($deger) ? $deger : '(dizi)';
};

$dosya = function ($yol) use ($root) {
    return (is_file($root . '/' . $yol) ? 'VAR ' : 'YOK ') . $yol;
};

echo "=== tedarikapp TANI (bu çıktıyı olduğu gibi yapıştırın) ===\n\n";
echo 'Zaman           : ' . date('c') . "\n";
echo 'PHP             : ' . PHP_VERSION . ' (' . PHP_SAPI . ")\n\n";

echo "--- İstek sunucuya nasıl ulaşıyor ---\n";
echo 'HTTP_HOST       : ' . $var('HTTP_HOST') . "\n";
echo 'REQUEST_URI     : ' . $var('REQUEST_URI') . "\n";
echo 'SCRIPT_NAME     : ' . $var('SCRIPT_NAME') . "\n";
echo 'SCRIPT_FILENAME : ' . $var('SCRIPT_FILENAME') . "\n";
echo 'DOCUMENT_ROOT   : ' . $var('DOCUMENT_ROOT') . "\n";
echo 'Uygulama kökü   : ' . $root . "\n\n";

echo "--- Kritik dosyalar (uygulama köküne göre) ---\n";
foreach ([
    '.htaccess',
    'public/.htaccess',
    'public/index.php',
    'vendor/autoload.php',
    'bootstrap/preflight.php',
    'setup/views/wizard.html',
    'public/panel/index.html',
    'MANIFEST.txt',
    'config.php',
    '.env',
] as $yol) {
    echo $dosya($yol) . "\n";
}

echo "\n--- Yorum ---\n";
$docroot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$kok = rtrim(str_replace('\\', '/', $root), '/');
$publicKok = $kok . '/public';
if ($docroot === $publicKok) {
    echo "Docroot = uygulama kökü/public → DOĞRU yerleşim.\n";
} elseif ($docroot === $kok) {
    echo "Docroot = uygulama kökü → kök .htaccess public/'e yönlendirmeli (dosya yukarıda VAR olmalı).\n";
} else {
    echo "DİKKAT: Docroot ($docroot) uygulama köküyle ($kok) EŞLEŞMİYOR →\n";
    echo "uygulama bir ALT KLASÖRDE. Adresler bu alt klasör önekiyle çalışır\n";
    echo "veya dosyalar docroot köküne taşınmalı.\n";
}
echo "\nSihirbaz adresi denemesi: bu sayfayı hangi adresle açtıysanız, aynı adreste\n";
echo "'tani.php' yerine 'setup' yazın — o adres sihirbazın adresi olmalı.\n";
