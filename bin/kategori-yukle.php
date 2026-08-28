<?php

declare(strict_types=1);

/**
 * KATEGORİ AĞACI YÜKLEYİCİ (İE#21 B10) — yalnızca CLI, İDEMPOTENT.
 *
 * 8B kategori ağacını (`config/kategori-agaci.json`) veritabanına aktarır.
 * İki kez koşmak kategorileri ikiye katlamaz; "acaba koştu mu" diye tekrar
 * koşmak güvenlidir.
 *
 * Kullanım:
 *   php bin/kategori-yukle.php            → PLAN (hiçbir şey yazmaz)
 *   php bin/kategori-yukle.php --uygula   → içe aktarır
 *
 * Çıkış kodu: 0 tamam · 1 hata
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Models\CategoryRepository;
use App\Services\KategoriIceAktarim;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$uygula = in_array('--uygula', array_slice($argv ?? [], 1), true);

$yol = $basePath . '/config/kategori-agaci.json';
if (!is_file($yol)) {
    fwrite(STDERR, "HATA: config/kategori-agaci.json bulunamadı.\n");
    exit(1);
}

/** @var mixed $agac */
$agac = json_decode((string) file_get_contents($yol), true);
if (!is_array($agac) || !is_array($agac['kategoriler'] ?? null)) {
    fwrite(STDERR, "HATA: kategori-agaci.json okunamadı ya da 'kategoriler' alanı yok.\n");
    exit(1);
}

// Ağaç {kod, tr, ust} biçimindedir; içe aktarım "Üst > Alt" adını kendi kurar.
$liste = [];
foreach ($agac['kategoriler'] as $dugum) {
    if (!is_array($dugum) || !is_string($dugum['tr'] ?? null)) {
        continue;
    }
    $ust = $dugum['ust'] ?? null;
    $liste[] = is_string($ust) && $ust !== ''
        ? $ust . KategoriIceAktarim::AYRAC . $dugum['tr']
        : $dugum['tr'];
}

echo "Kategori ağacı: " . count($liste) . " kayıt (sürüm " . (string) ($agac['surum'] ?? '?') . ")\n";

if (!$uygula) {
    echo "\nPLAN — hiçbir şey yazılmadı. İlk 10 kayıt:\n";
    foreach (array_slice($liste, 0, 10) as $ad) {
        echo '  · ' . $ad . "\n";
    }
    echo "\nUygulamak için: php bin/kategori-yukle.php --uygula\n";
    exit(0);
}

try {
    $config = Config::load($basePath);
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $sonuc = (new KategoriIceAktarim(new CategoryRepository($connection)))->calistir($liste);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}

printf("Eklendi: %d · zaten vardı: %d · toplam: %d\n", $sonuc['eklenen'], $sonuc['atlanan'], count($sonuc['adlar']));
foreach ($sonuc['uyarilar'] as $uyari) {
    echo '  UYARI: ' . $uyari . "\n";
}

exit(0);
