<?php

declare(strict_types=1);

/**
 * GERİ YÜKLEME TATBİKATI (İE#14 D2) — yalnızca CLI.
 *
 * "Yedek alınıyor" demek "geri yüklenebiliyor" demek DEĞİLDİR. Bu betik bunu
 * kanıtlar: bir yedek SETİNİ provadan geçirir, GEÇİCİ bir veritabanına yükler,
 * tablo ve satır sayılarını raporlar, sonra o geçici veritabanını DÜŞÜRÜR.
 *
 * CANLI VERİTABANINA DOKUNMAZ — bu bir güvenlik şartıdır, tercih değil:
 *   • hedef ad her koşuda `<db>_restoretest_<zaman>` biçiminde üretilir,
 *   • hedef adın canlı veritabanı adına eşit çıkması durumunda betik DURUR,
 *   • yükleme ve düşürme yalnız bu geçici ad üzerinde yapılır.
 *
 * Kullanım:
 *   php bin/restore-test.php                       → en yeni yedeği dener
 *   php bin/restore-test.php set-20260821-030004
 *   php bin/restore-test.php --tut                 → geçici veritabanını SİLMEZ (inceleme)
 *   php bin/restore-test.php --kismi-kabul         → KISMİ seti (config eksik) kabul et
 *
 * Çıkış kodu: 0 tatbikat başarılı · 1 başarısız (yedek bozuk/erişilemez/yükleme hatası).
 */

use App\Core\Config;
use App\Core\Database;
use App\Services\BackupService;
use App\Services\Yedek\YedekGeriYukleyici;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$argumanlar = array_slice($argv ?? [], 1);
$tut = in_array('--tut', $argumanlar, true);
// H1: tatbikat da KISMİ seti bayraksız reddeder — canlı geri yüklemeyle AYNI kapı.
$kismiKabul = in_array('--kismi-kabul', $argumanlar, true);
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

    // ── 1) Yedek SETİNİ seç (v1.2.2 B1: yedek artık dosya değil, set)
    $liste = $service->list();
    if ($liste === []) {
        throw new RuntimeException('storage/backups altında yedek yok — önce `php bin/backup.php` koşun.');
    }
    $setAdi = $istenenAd ?? (string) $liste[0]['name'];
    $setDizini = $service->pathFor($setAdi);
    if ($setDizini === null || !is_dir($setDizini)) {
        throw new RuntimeException('Yedek seti bulunamadı: ' . $setAdi);
    }

    // ── 2) KAPI: prova geçmeden tek parça bile açılmaz
    // Tatbikat geçici bir veritabanına yazsa da kapı aynıdır: kısmi bir set
    // "tatbikat başarılı" raporu üretirse o rapor yalan söyler ve asıl
    // felaket gününde ona güvenilir. APP_KEY yanlışsa da burada patlar —
    // tatbikatın ilk kazanımı hep buydu.
    $geriYukleyici = new YedekGeriYukleyici($service);
    $manifest = $geriYukleyici->kapiyiAc($setDizini, [], $kismiKabul);
    if ($manifest->durum() === App\Services\Yedek\YedekManifesti::DURUM_KISMI) {
        echo 'DURUM  : KISMİ — eksik: ' . implode(', ', $manifest->eksikBilesenler()) . ' (elle girilecek)' . PHP_EOL;
    }
    $ozet = $manifest->ozet();
    echo 'SET    : ' . $setAdi . ' (' . $ozet['parca_sayisi'] . ' parça, '
        . number_format($ozet['toplam_bayt'] / 1048576, 2) . " MB)" . PHP_EOL;
    echo "PROVA  : manifest ve parça özetleri tuttu." . PHP_EOL;

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

    // ── 4) Dökümü yükle — CANLIYLA AYNI KOD YOLU
    // Tatbikat, gerçek geri yüklemeden başka bir yoldan yükleseydi neyi
    // provaya çektiğimiz belirsizleşirdi: yeşil bir tatbikat, kırmızı bir
    // gerçek geri yüklemeyi gizleyebilirdi.
    $baslangic = microtime(true);
    $sayim = $geriYukleyici->veritabaniniYukle($pdo, $setDizini, $manifest);
    $sure = microtime(true) - $baslangic;

    // ── 5) Tablo ve satır sayıları — "yüklendi" demek yetmez, İÇİNDE NE VAR?
    if ($sayim['tablo_sayisi'] === 0) {
        throw new RuntimeException('Yükleme bitti ama TABLO YOK — yedek kullanılamaz.');
    }

    $toplamSatir = $sayim['satir_sayisi'];
    $rapor = [];
    foreach ($sayim['tablolar'] as $tablo => $satir) {
        $rapor[] = ['tablo' => (string) $tablo, 'satir' => $satir];
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
