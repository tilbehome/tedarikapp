<?php

declare(strict_types=1);

/**
 * CHROME WEB STORE YAYIN PAKETİ ÜRETİCİSİ (İE#21 A9) — yalnızca CLI.
 *
 * BAŞVURUYU YAPMAZ. Yükleyecek dosyaları tek klasörde toplar, manifesti politika
 * maddelerine karşı DENETLER ve eksik varsa paketi ÜRETMEZ. Başvuru Ürün Sahibi
 * tarafından elle yapılır (emir A9).
 *
 * Neden betik: yayın paketi elle toplanırsa bir görsel ya da metin eksik kalır ve
 * bu, mağaza incelemesinde doğrudan red sebebidir (store-politika-teyidi §5 riski).
 * Denetimi koda almak, "acaba unuttuk mu" sorusunu ortadan kaldırır.
 *
 * Kullanım:
 *   php bin/store-paketi.php            → denetle + paketle (dist/store/)
 *   php bin/store-paketi.php --denetle  → yalnız denetle, dosya üretme
 *
 * Çıkış kodu: 0 tamam · 1 eksik/uyumsuz
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

$kok = dirname(__DIR__);
$yalnizDenetle = in_array('--denetle', array_slice($argv ?? [], 1), true);

$hatalar = [];
$uyarilar = [];

// ── 1) MANİFEST DENETİMİ ─────────────────────────────────────────────────────
$manifestYolu = $kok . '/extension/dist/chrome-mv3/manifest.json';
if (!is_file($manifestYolu)) {
    fwrite(STDERR, "HATA: eklenti derlemesi yok. Önce: cd extension && npx wxt build\n");
    exit(1);
}

/** @var array<string, mixed> $manifest */
$manifest = json_decode((string) file_get_contents($manifestYolu), true, 512, JSON_THROW_ON_ERROR);

// Ad ve açıklama PLATFORM BAĞIMSIZ olmalı (Ürün Sahibi kararı, 23 Ağu 2026).
foreach (['name', 'description'] as $alan) {
    $deger = (string) ($manifest[$alan] ?? '');
    if ($deger === '') {
        $hatalar[] = sprintf('manifest.%s boş.', $alan);

        continue;
    }
    if (str_contains($deger, '1688')) {
        $hatalar[] = sprintf(
            'manifest.%s "1688" içeriyor — ad ve açıklama platform bağımsızdır (kimlik genel, kapsam beyanı somut).',
            $alan,
        );
    }
}

// Chrome kısa açıklama sınırı 132 karakterdir; aşan metin sessizce kırpılır.
if (mb_strlen((string) ($manifest['description'] ?? '')) > 132) {
    $hatalar[] = 'manifest.description 132 karakteri aşıyor (mağaza kırpar).';
}

// İkon seti: 16/32/48/128 (store-politika-teyidi §2).
foreach ([16, 32, 48, 128] as $boyut) {
    $yol = $manifest['icons'][(string) $boyut] ?? $manifest['icons'][$boyut] ?? null;
    if (!is_string($yol)) {
        $hatalar[] = sprintf('manifest.icons[%d] yok.', $boyut);

        continue;
    }
    if (!is_file($kok . '/extension/dist/chrome-mv3/' . ltrim($yol, '/'))) {
        $hatalar[] = sprintf('İkon dosyası pakette yok: %s', $yol);
    }
}

// İzinler: beyan edilenden fazlası mağaza incelemesinde "aşırı izin"dir.
$beklenenIzinler = ['storage', 'activeTab', 'scripting'];
$izinler = is_array($manifest['permissions'] ?? null) ? $manifest['permissions'] : [];
$fazla = array_diff($izinler, $beklenenIzinler);
if ($fazla !== []) {
    $hatalar[] = 'Beyan edilmemiş izin: ' . implode(', ', $fazla);
}

// Host izni DAR olmalı: wildcard, aşırı izin ihlalidir (§4).
$hostIzinleri = is_array($manifest['host_permissions'] ?? null) ? $manifest['host_permissions'] : [];
foreach ($hostIzinleri as $host) {
    if (str_contains((string) $host, '://*/') || (string) $host === '<all_urls>') {
        $hatalar[] = 'Geniş host izni: ' . $host . ' — dar origin kullanın (§4).';
    }
}

// ── 2) YAYIN VARLIKLARI ──────────────────────────────────────────────────────
$varliklar = [
    'store-icon-128.png' => 'docs/marka/chrome-web-store/store-icon-128.png',
    'small-promo-440x280.png' => 'docs/marka/chrome-web-store/small-promo-440x280.png',
    'marquee-1400x560.png' => 'docs/marka/chrome-web-store/marquee-1400x560.png',
    'screenshot-template-1280x800.png' => 'docs/marka/chrome-web-store/screenshot-template-1280x800.png',
];
foreach ($varliklar as $ad => $kaynak) {
    if (!is_file($kok . '/' . $kaynak)) {
        $hatalar[] = 'Yayın görseli yok: ' . $kaynak;
    }
}

$metinler = [
    'store-yayin-paketi.md' => 'docs/v3/hazirlik/store-yayin-paketi.md',
    'store-politika-teyidi.md' => 'docs/v3/hazirlik/store-politika-teyidi.md',
    'STORE-LISTING-COPY.md' => 'docs/marka/chrome-web-store/STORE-LISTING-COPY.md',
];
foreach ($metinler as $ad => $kaynak) {
    if (!is_file($kok . '/' . $kaynak)) {
        $hatalar[] = 'Yayın metni yok: ' . $kaynak;
    }
}

// EKRAN GÖRÜNTÜSÜ: şablon yeterli DEĞİLDİR — mağaza gerçek arayüz ister (§5 riski).
$gercekEkran = glob($kok . '/docs/marka/chrome-web-store/ekran-*.png') ?: [];
if ($gercekEkran === []) {
    $uyarilar[] = 'GERÇEK ekran görüntüsü yok (yalnız şablon var). Mağaza şablonu reddeder: '
        . 'eklentinin çalışan arayüzünden 1–5 adet 1280×800 kare çekilip '
        . 'docs/marka/chrome-web-store/ekran-1.png … olarak eklenmelidir.';
}

// ── 3) RAPOR ─────────────────────────────────────────────────────────────────
echo "CHROME WEB STORE YAYIN PAKETİ — DENETİM\n";
echo str_repeat('─', 60) . "\n";
printf("Eklenti  : %s\n", (string) ($manifest['name'] ?? '?'));
printf("Sürüm    : %s\n", (string) ($manifest['version'] ?? '?'));
printf("Açıklama : %s\n", (string) ($manifest['description'] ?? '?'));
printf("İzinler  : %s\n", implode(', ', $izinler));
printf("Host     : %s\n", implode(', ', $hostIzinleri));
echo str_repeat('─', 60) . "\n";

foreach ($uyarilar as $uyari) {
    echo "UYARI: " . $uyari . "\n";
}
foreach ($hatalar as $hata) {
    echo "HATA : " . $hata . "\n";
}

if ($hatalar !== []) {
    fwrite(STDERR, "\nPaket ÜRETİLMEDİ: " . count($hatalar) . " engel var.\n");
    exit(1);
}

if ($yalnizDenetle) {
    echo "\nDenetim TEMİZ (paket üretilmedi — --denetle).\n";
    exit(0);
}

// ── 4) PAKETLEME ─────────────────────────────────────────────────────────────
$hedef = $kok . '/dist/store';
if (!is_dir($hedef) && !mkdir($hedef, 0775, true) && !is_dir($hedef)) {
    fwrite(STDERR, "HATA: dist/store oluşturulamadı.\n");
    exit(1);
}

// Eklenti ZIP'i: mağazaya yüklenecek olan budur.
$zipYolu = $hedef . '/tedarikapp-eklenti-' . (string) ($manifest['version'] ?? '0') . '.zip';
@unlink($zipYolu);
$zip = new ZipArchive();
if ($zip->open($zipYolu, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "HATA: zip açılamadı.\n");
    exit(1);
}

$kaynakDizin = $kok . '/extension/dist/chrome-mv3';
$dosyalar = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kaynakDizin, FilesystemIterator::SKIP_DOTS));
$sayac = 0;
foreach ($dosyalar as $dosya) {
    if (!$dosya instanceof SplFileInfo || !$dosya->isFile()) {
        continue;
    }
    $goreli = str_replace('\\', '/', substr($dosya->getPathname(), strlen($kaynakDizin) + 1));
    $zip->addFile($dosya->getPathname(), $goreli);
    $sayac++;
}
$zip->close();

// Görseller ve metinler yanına kopyalanır — başvuru sırasında tek klasör açılır.
foreach ($varliklar + $metinler as $ad => $kaynak) {
    copy($kok . '/' . $kaynak, $hedef . '/' . $ad);
}

$sha = hash_file('sha256', $zipYolu);
printf("\nPAKET HAZIR\n  klasör : %s\n  zip    : %s (%d dosya)\n  sha256 : %s\n", $hedef, basename($zipYolu), $sayac, $sha);
echo "\nBaşvuruyu Ürün Sahibi yapar. Yükleme sırası ve alan alan ne girileceği:\n";
echo "  docs/v3/hazirlik/store-yayin-paketi.md\n";
echo "Kategori: Workflow & Planning · Görünürlük: Unlisted\n";

exit(0);
