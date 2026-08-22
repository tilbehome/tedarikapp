<?php

declare(strict_types=1);

/**
 * GERİ YÜKLEME TATBİKATI (İE#14 D2) — yalnızca CLI.
 *
 * "Yedek alınıyor" demek "geri yüklenebiliyor" demek DEĞİLDİR. Bu betik bunu
 * kanıtlar: şifreli bir `.sql.enc` yedeğini çözer, GEÇİCİ bir veritabanına yükler,
 * tablo ve satır sayılarını raporlar, sonra o geçici veritabanını DÜŞÜRÜR.
 *
 * CANLI VERİTABANINA DOKUNMAZ — bu bir güvenlik şartıdır, tercih değil:
 *   • hedef ad her koşuda `<db>_restoretest_<zaman>` biçiminde üretilir,
 *   • hedef adın canlı veritabanı adına eşit çıkması durumunda betik DURUR,
 *   • yükleme ve düşürme yalnız bu geçici ad üzerinde yapılır.
 *
 * Kullanım:
 *   php bin/restore-test.php                       → en yeni yedeği dener
 *   php bin/restore-test.php yedek-20260821-030004.sql.enc
 *   php bin/restore-test.php --tut                 → geçici veritabanını SİLMEZ (inceleme)
 *
 * Çıkış kodu: 0 tatbikat başarılı · 1 başarısız (yedek bozuk/erişilemez/yükleme hatası).
 */

use App\Core\Config;
use App\Core\Database;
use App\Services\BackupService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$argumanlar = array_slice($argv ?? [], 1);
$tut = in_array('--tut', $argumanlar, true);
$istenenAd = null;
foreach ($argumanlar as $arguman) {
    if (!str_starts_with($arguman, '--')) {
        $istenenAd = $arguman;
        break;
    }
}

$geciciAd = null;
$pdo = null;

/**
 * İE#19 G3 — ÇIKIŞ KODU DEĞİŞKENDE TUTULUR, TEK EXIT NOKTASI VARDIR.
 *
 * Kanıtlı hata: PHP'de `exit()` çağrısı `finally` bloğunu ÇALIŞTIRMAZ. Bu betikte
 * temizlik (DROP DATABASE) `finally` içindeydi ama başarı/başarısızlık yolları
 * `exit(0)` / `exit(1)` ile bloktan atlıyordu — yani tatbikatın bıraktığı geçici
 * veritabanı HİÇBİR ZAMAN düşmüyordu. Her koşu sunucuda bir `_restoretest_*`
 * veritabanı bırakıyordu (paylaşımlı hostingde kota dolduran sessiz sızıntı).
 * Artık sonuç bir değişkende toplanır, `finally` gerçekten koşar ve çıkış EN SONDA
 * bir kez yapılır.
 */
$cikisKodu = 1;

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

    $service = new BackupService($config, $basePath);

    // ── 1) Yedek dosyasını seç
    $liste = $service->list();
    if ($liste === []) {
        throw new RuntimeException('storage/backups altında yedek yok — önce `php bin/backup.php` koşun.');
    }
    $ad = $istenenAd ?? (string) $liste[0]['name'];
    $path = $service->pathFor($ad);
    if ($path === null || !is_file($path)) {
        throw new RuntimeException('Yedek bulunamadı: ' . $ad);
    }
    echo 'YEDEK  : ' . $ad . ' (' . number_format((int) filesize($path) / 1048576, 2) . " MB)\n";

    // ── 2) Çöz (APP_KEY yanlışsa burada patlar — tatbikatın ilk kazanımı budur)
    $sql = $service->decrypt((string) file_get_contents($path));
    if (trim($sql) === '') {
        throw new RuntimeException('Yedek çözüldü ama İÇİ BOŞ — bu yedek işe yaramaz.');
    }
    echo 'ÇÖZÜM  : tamam (' . number_format(strlen($sql) / 1048576, 2) . " MB SQL)\n";

    // ── 3) Geçici veritabanı adı — canlıya ASLA eşit olamaz
    $canliAd = (string) $config->get('DB_NAME', '');
    if ($canliAd === '') {
        throw new RuntimeException('DB_NAME boş — .env okunamadı.');
    }
    $geciciAd = substr($canliAd, 0, 40) . '_restoretest_' . date('Ymd_His');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $geciciAd) || $geciciAd === $canliAd) {
        throw new RuntimeException('Geçici veritabanı adı üretilemedi; tatbikat durduruldu.');
    }

    // Sunucu bağlantısı (veritabanı seçmeden) — CREATE DATABASE yetkisi gerekir.
    $pdo = Database::connect($config);
    $pdo->exec('CREATE DATABASE `' . $geciciAd . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $geciciAd . '`');
    echo 'HEDEF  : ' . $geciciAd . " (geçici, koşu sonunda düşürülür)\n";

    // ── 4) Dökümü yükle
    $baslangic = microtime(true);
    $pdo->exec($sql);
    $sure = microtime(true) - $baslangic;

    // ── 5) Tablo ve satır sayıları — "yüklendi" demek yetmez, İÇİNDE NE VAR?
    $tablolar = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($tablolar === []) {
        throw new RuntimeException('Yükleme bitti ama TABLO YOK — yedek kullanılamaz.');
    }

    $toplamSatir = 0;
    $rapor = [];
    foreach ($tablolar as $tablo) {
        $tablo = (string) $tablo;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tablo)) {
            continue; // beklenmeyen ad — sayma, sorguya da sokma
        }
        $sayi = (int) $pdo->query('SELECT COUNT(*) FROM `' . $tablo . '`')->fetchColumn();
        $toplamSatir += $sayi;
        $rapor[] = ['tablo' => $tablo, 'satir' => $sayi];
    }

    echo "\nTABLOLAR (" . count($rapor) . "):\n";
    foreach ($rapor as $satir) {
        printf("  %-28s %8s satır\n", $satir['tablo'], number_format($satir['satir']));
    }

    // ── 6) Anlamlılık denetimi: kritik tablolar var mı ve kullanıcı kaydı geldi mi?
    $adlar = array_column($rapor, 'tablo');
    $eksik = array_values(array_diff(['users', 'lists', 'products', 'migrations'], $adlar));
    $kullanicilar = 0;
    foreach ($rapor as $satir) {
        if ($satir['tablo'] === 'users') {
            $kullanicilar = $satir['satir'];
        }
    }

    echo "\nÖZET   : " . count($rapor) . ' tablo · ' . number_format($toplamSatir) . ' satır · '
        . number_format($sure, 1) . " sn\n";

    if ($eksik !== []) {
        throw new RuntimeException('Kritik tablo eksik: ' . implode(', ', $eksik));
    }
    if ($kullanicilar === 0) {
        throw new RuntimeException('users tablosu BOŞ — bu yedekle sisteme girilemez.');
    }

    echo "SONUÇ  : TATBİKAT BAŞARILI — bu yedekten geri dönülebilir.\n";
    $cikisKodu = 0;
} catch (Throwable $e) {
    fwrite(STDERR, "\nSONUÇ  : TATBİKAT BAŞARISIZ — " . $e->getMessage() . "\n");
    fwrite(STDERR, "Bu yedeğe GÜVENMEYİN; nedeni giderilip yeniden yedek alınmalı.\n");
    $cikisKodu = 1;
} finally {
    // Geçici veritabanı her koşulda düşer — hata da olsa artık bırakmayız.
    if ($pdo instanceof PDO && is_string($geciciAd) && !$tut) {
        try {
            $pdo->exec('DROP DATABASE IF EXISTS `' . $geciciAd . '`');
            echo 'TEMİZLİK: ' . $geciciAd . " düşürüldü.\n";
        } catch (Throwable $temizlikHatasi) {
            fwrite(STDERR, 'UYARI: geçici veritabanı düşürülemedi (' . $geciciAd . '): '
                . $temizlikHatasi->getMessage() . "\n");
        }
    } elseif ($tut && is_string($geciciAd)) {
        echo 'TUTULDU: ' . $geciciAd . " — incelemeniz bitince elle DROP edin.\n";
    }
}

// TEK ÇIKIŞ NOKTASI: temizlik (finally) koştuktan SONRA, bir kez.
exit($cikisKodu);
