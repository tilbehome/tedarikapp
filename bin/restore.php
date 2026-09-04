<?php

declare(strict_types=1);

/**
 * YEDEK SETİNDEN GERİ YÜKLEME (v1.2.2 B3) — yalnızca CLI.
 *
 * `bin/restore-test.php` TATBİKATTIR: geçici bir veritabanına yükler, canlıya
 * dokunmaz. BU BETİK GERÇEK GERİ YÜKLEMEDİR: hedef veritabanının tablolarını
 * düşürür ve setten yeniden kurar. Ayrı olmalarının sebebi, tatbikatın hiçbir
 * korkuya yol açmadan sık sık koşabilmesi gerektiğidir.
 *
 * KAPILAR (hepsi fail-closed):
 *   1. Set PROVADAN GEÇMEDEN tek parça bile açılmaz — kısmi setten sessiz geri
 *      yükleme imkânsızdır (PM ara hükmü, 3 Eyl).
 *   2. `--onayla` yoksa hiçbir şey yazılmaz; betik ne yapacağını anlatıp çıkar.
 *   3. Hedef veritabanı adı `.env`den okunur ve ekrana basılır: yanlış sunucuda
 *      koşturulduğunu anlamanın en ucuz yolu, adı görmektir.
 *
 * AYARLAR GERİ YAZILMAZ, yalnız listelenir: `config.php` içinde APP_KEY ve DB
 * parolası vardır; onu sessizce geri koymak çalışan bir kurulumun kimliğini
 * değiştirebilir. Operatör hangi dosyayı geri koyacağına kendi karar verir.
 *
 * Kullanım:
 *   php bin/restore.php                          → en yeni seti ANLATIR (yazmaz)
 *   php bin/restore.php set-20260903-030000 --onayla
 *   php bin/restore.php --onayla --medyasiz      → yalnız veritabanı
 *   php bin/restore.php <set> --onayla --kismi-kabul
 *                                                → KISMİ seti (config eksik) kabul et
 *
 * KISMİ SET (H1): config alınamamış bir set, `--kismi-kabul` olmadan
 * YÜKLENMEZ — kuru koşuda da uyarı basılır. Bayrak, "ayarları elle
 * gireceğim" kararının kaydıdır; onsuz ayarsız bir kurulum sessizce oluşurdu.
 *
 * Çıkış kodu: 0 başarılı · 1 başarısız (set bozuk, kapı kapalı, yükleme hatası).
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
$onayli = in_array('--onayla', $argumanlar, true);
$medyasiz = in_array('--medyasiz', $argumanlar, true);
$kismiKabul = in_array('--kismi-kabul', $argumanlar, true);
$istenenSet = null;
foreach ($argumanlar as $arguman) {
    if (!str_starts_with($arguman, '--')) {
        $istenenSet = $arguman;

        break;
    }
}

$cikisKodu = 1;

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

    $servis = new BackupService($config, $basePath);
    $geriYukleyici = new YedekGeriYukleyici($servis);

    // ── 1) Seti seç
    $setler = $servis->list();
    if ($setler === []) {
        throw new RuntimeException('storage/backups altında yedek seti yok.');
    }
    $setAdi = $istenenSet ?? (string) $setler[0]['name'];
    $setDizini = $servis->pathFor($setAdi);
    if ($setDizini === null || !is_dir($setDizini)) {
        throw new RuntimeException('Yedek seti bulunamadı: ' . $setAdi);
    }

    // ── 2) KAPI: prova geçmeden hiçbir parça açılmaz; KISMİ set bayrak ister
    $manifest = $geriYukleyici->kapiyiAc($setDizini, [], $kismiKabul);
    $ozet = $manifest->ozet();

    $hedefAd = (string) $config->get('DB_NAME', '');
    if ($hedefAd === '') {
        throw new RuntimeException('DB_NAME boş — .env okunamadı.');
    }

    echo 'SET      : ' . $setAdi . PHP_EOL;
    echo 'PARÇA    : ' . $ozet['parca_sayisi'] . ' (' . $ozet['medya_parca_sayisi'] . ' medya) · '
        . number_format($ozet['toplam_bayt'] / 1048576, 2) . ' MB' . PHP_EOL;
    echo 'HEDEF DB : ' . $hedefAd . PHP_EOL;
    echo 'MİGRATION: ' . count($manifest->migrationDefteri()) . ' kayıt (yedek alındığı andaki defter)' . PHP_EOL;
    if ($ozet['durum'] === App\Services\Yedek\YedekManifesti::DURUM_KISMI) {
        // Kuru koşuda da görünür: operatör "--onayla" demeden önce bilmeli.
        echo 'DURUM    : KISMİ — eksik: ' . implode(', ', $ozet['eksik'])
            . ($ozet['sebep'] !== null ? ' (' . $ozet['sebep'] . ')' : '') . PHP_EOL;
        echo '           --kismi-kabul verildi: eksik bileşen GERİ YÜKLENMEYECEK, elle girilecek.' . PHP_EOL;
    }

    if (!$onayli) {
        echo PHP_EOL . 'KURU KOŞU — hiçbir şey yazılmadı.' . PHP_EOL;
        echo 'Gerçekten geri yüklemek için: php bin/restore.php ' . $setAdi . ' --onayla' . PHP_EOL;
        echo 'UYARI: bu işlem "' . $hedefAd . '" veritabanının MEVCUT tablolarını düşürür.' . PHP_EOL;
        exit(0);
    }

    // ── 3) Veritabanı
    $pdo = Database::connect($config);
    $dusen = $geriYukleyici->hedefiTemizle($pdo);
    echo PHP_EOL . 'TEMİZLİK : ' . $dusen . ' tablo düşürüldü.' . PHP_EOL;

    $baslangic = microtime(true);
    $sayim = $geriYukleyici->veritabaniniYukle($pdo, $setDizini, $manifest);
    $sure = microtime(true) - $baslangic;

    echo 'YÜKLEME  : ' . $sayim['tablo_sayisi'] . ' tablo · '
        . number_format($sayim['satir_sayisi']) . ' satır · ' . number_format($sure, 1) . ' sn' . PHP_EOL;

    foreach ($sayim['tablolar'] as $tablo => $satir) {
        printf("  %-28s %8s satır\n", $tablo, number_format($satir));
    }

    // ── 4) Medya
    if ($medyasiz) {
        echo 'MEDYA    : atlandı (--medyasiz).' . PHP_EOL;
    } else {
        $medya = $geriYukleyici->medyayiYukle($basePath . '/public/media', $setDizini, $manifest);
        echo 'MEDYA    : ' . $medya['parca_sayisi'] . ' parça · '
            . $medya['dosya_sayisi'] . ' dosya açıldı.' . PHP_EOL;
    }

    // ── 5) Ayarlar: yalnız gösterilir
    $ayarlar = [];

    if (!in_array('config', $ozet['eksik'], true)) {
        try {
            $ayarlar = $geriYukleyici->ayarlariCoz($setDizini, $manifest);
        } catch (Throwable $ayarHatasi) {
            echo 'AYARLAR  : okunamadı (' . $ayarHatasi->getMessage() . ')' . PHP_EOL;
        }
    }
    if (in_array('config', $ozet['eksik'], true)) {
        echo 'AYARLAR  : config geri yüklenmedi, elle girilecek — sette ayar parçası yok'
            . ($ozet['sebep'] !== null ? ' (' . $ozet['sebep'] . ')' : '') . '.' . PHP_EOL;
        echo '           config.php\'yi kurulum sihirbazıyla ya da elle oluşturun; APP_KEY' . PHP_EOL;
        echo '           için kurtarma anahtarı emanetinizi kullanın.' . PHP_EOL;
    } elseif ($ayarlar !== []) {
        echo 'AYARLAR  : GERİ YAZILMADI — sette şu dosyalar var: '
            . implode(', ', array_keys($ayarlar)) . PHP_EOL;
        echo '           İçlerinde APP_KEY ve DB parolası bulunur; hangisini geri' . PHP_EOL;
        echo '           koyacağınıza siz karar verin (çalışan kurulumun kimliğini' . PHP_EOL;
        echo '           sessizce değiştirmemek için elle yapılır).' . PHP_EOL;
    }

    // ── 6) Anlamlılık: yükleme "bitti" demek yetmez
    $eksik = array_values(array_diff(
        ['users', 'lists', 'products', 'migrations'],
        array_keys($sayim['tablolar']),
    ));
    if ($eksik !== []) {
        throw new RuntimeException('Kritik tablo eksik: ' . implode(', ', $eksik));
    }
    if (($sayim['tablolar']['users'] ?? 0) === 0) {
        throw new RuntimeException('users tablosu BOŞ — bu kuruluma girilemez.');
    }

    echo PHP_EOL . 'SONUÇ    : GERİ YÜKLEME BAŞARILI.' . PHP_EOL;
    $cikisKodu = 0;
} catch (Throwable $hata) {
    fwrite(STDERR, PHP_EOL . 'SONUÇ    : GERİ YÜKLEME BAŞARISIZ — ' . $hata->getMessage() . PHP_EOL);
    $cikisKodu = 1;
}

exit($cikisKodu);
