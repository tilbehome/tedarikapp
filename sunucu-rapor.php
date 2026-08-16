<?php
/**
 * SUNUCU ÖZELLİK RAPORU
 * Bu dosyayı hostinge yükleyip tarayıcıdan açın (örn: https://siteniz.com/sunucu-rapor.php)
 * Çıkan raporu "Raporu Kopyala" butonuyla kopyalayıp Claude'a yapıştırın.
 * NOT: İşiniz bitince bu dosyayı sunucudan SİLİN (sunucu bilgisi ifşa etmemek için).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');

function yesNo($v) { return $v ? 'VAR' : 'YOK'; }

$r = [];

/* ---------- Genel ---------- */
$r['GENEL'] = [
    'PHP Sürümü'        => PHP_VERSION,
    'SAPI'              => php_sapi_name(),
    'İşletim Sistemi'   => PHP_OS . ' (' . php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m') . ')',
    'Web Sunucusu'      => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'bilinmiyor',
    'Sunucu Adı'        => isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'bilinmiyor',
    'Belge Kökü'        => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'bilinmiyor',
    'Bu Dosyanın Yolu'  => __FILE__,
    'Varsayılan Zaman Dilimi' => date_default_timezone_get(),
    'Sunucu Saati'      => date('Y-m-d H:i:s'),
];

/* ---------- Kritik Eklentiler ---------- */
$criticalExts = [
    'pdo', 'pdo_mysql', 'pdo_sqlite', 'mysqli', 'sqlite3',
    'curl', 'openssl', 'mbstring', 'intl', 'gd', 'imagick',
    'zip', 'fileinfo', 'exif', 'json', 'xml', 'simplexml', 'dom',
    'iconv', 'ctype', 'session', 'bcmath', 'soap', 'sockets',
    'ftp', 'zlib', 'opcache', 'apcu', 'redis', 'memcached', 'imap',
];
$extReport = [];
foreach ($criticalExts as $ext) {
    $extReport[$ext] = yesNo(extension_loaded($ext));
}
$r['KRİTİK EKLENTİLER'] = $extReport;

/* ---------- GD Detayı ---------- */
if (extension_loaded('gd') && function_exists('gd_info')) {
    $gd = gd_info();
    $r['GD DETAYI'] = [
        'GD Sürümü'   => isset($gd['GD Version']) ? $gd['GD Version'] : '?',
        'JPEG'        => yesNo(!empty($gd['JPEG Support'])),
        'PNG'         => yesNo(!empty($gd['PNG Support'])),
        'GIF'         => yesNo(!empty($gd['GIF Read Support'])),
        'WebP'        => yesNo(!empty($gd['WebP Support'])),
        'AVIF'        => yesNo(!empty($gd['AVIF Support'])),
        'FreeType'    => yesNo(!empty($gd['FreeType Support'])),
    ];
}

/* ---------- Veritabanı ---------- */
$dbInfo = [];
$dbInfo['PDO Sürücüleri'] = class_exists('PDO') ? implode(', ', PDO::getAvailableDrivers()) : 'PDO yok';
$dbInfo['mysqli'] = yesNo(extension_loaded('mysqli'));
if (extension_loaded('mysqli') && function_exists('mysqli_get_client_info')) {
    $dbInfo['MySQL İstemci Sürümü'] = mysqli_get_client_info();
}
if (extension_loaded('sqlite3') && class_exists('SQLite3')) {
    $sv = SQLite3::version();
    $dbInfo['SQLite Sürümü'] = $sv['versionString'];
}
$r['VERİTABANI'] = $dbInfo;

/* ---------- Önemli INI Ayarları ---------- */
$iniKeys = [
    'memory_limit', 'max_execution_time', 'max_input_time', 'max_input_vars',
    'upload_max_filesize', 'post_max_size', 'max_file_uploads', 'file_uploads',
    'allow_url_fopen', 'allow_url_include', 'default_socket_timeout',
    'display_errors', 'log_errors', 'error_log',
    'session.save_path', 'session.gc_maxlifetime',
    'open_basedir', 'upload_tmp_dir', 'sys_temp_dir',
    'date.timezone', 'output_buffering', 'zlib.output_compression',
];
$iniReport = [];
foreach ($iniKeys as $k) {
    $v = ini_get($k);
    $iniReport[$k] = ($v === false) ? '(tanımsız)' : (($v === '') ? '(boş)' : $v);
}
$r['PHP AYARLARI (php.ini)'] = $iniReport;

/* ---------- Yasaklı Fonksiyonlar ---------- */
$disabled = ini_get('disable_functions');
$r['YASAKLI FONKSİYONLAR'] = [
    'disable_functions' => $disabled ? $disabled : '(hiçbiri yasaklı değil)',
    'disable_classes'   => ini_get('disable_classes') ? ini_get('disable_classes') : '(hiçbiri)',
];

/* ---------- Komut Çalıştırma ---------- */
$execFns = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];
$execReport = [];
$disabledList = array_map('trim', explode(',', (string)$disabled));
foreach ($execFns as $fn) {
    $ok = function_exists($fn) && !in_array($fn, $disabledList, true);
    $execReport[$fn] = $ok ? 'KULLANILABİLİR' : 'KAPALI';
}
$r['KOMUT ÇALIŞTIRMA'] = $execReport;

/* ---------- Yazma İzinleri ---------- */
$tmpFile = __DIR__ . '/.__yazma_testi_' . uniqid() . '.tmp';
$canWriteHere = @file_put_contents($tmpFile, 'test') !== false;
if ($canWriteHere) { @unlink($tmpFile); }
$r['YAZMA İZİNLERİ'] = [
    'Bu klasöre yazma (' . __DIR__ . ')' => $canWriteHere ? 'YAZILABİLİR' : 'YAZILAMAZ',
    'Geçici klasör (sys_get_temp_dir)'   => is_writable(sys_get_temp_dir()) ? 'YAZILABİLİR (' . sys_get_temp_dir() . ')' : 'YAZILAMAZ (' . sys_get_temp_dir() . ')',
    'Disk boş alan'                      => function_exists('disk_free_space') && @disk_free_space(__DIR__)
                                            ? round(@disk_free_space(__DIR__) / 1024 / 1024 / 1024, 2) . ' GB'
                                            : 'ölçülemedi',
];

/* ---------- Dış Ağ Erişimi (curl testleri) ---------- */
function testUrl($url) {
    if (!function_exists('curl_init')) { return 'curl yok'; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (ServerReport)',
    ]);
    curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    curl_close($ch);
    if ($err) { return 'BAŞARISIZ: ' . $err; }
    return 'OK (HTTP ' . $code . ', ' . $time . ' ms)';
}
$r['DIŞ AĞ ERİŞİMİ'] = [
    'https://www.google.com'          => testUrl('https://www.google.com'),
    'https://detail.1688.com'         => testUrl('https://detail.1688.com'),
    'https://cbu01.alicdn.com (1688 CDN)' => testUrl('https://cbu01.alicdn.com'),
    'https://open.er-api.com (döviz kuru)' => testUrl('https://open.er-api.com/v6/latest/USD'),
    'allow_url_fopen ile dosya okuma' => ini_get('allow_url_fopen') ? 'AÇIK' : 'KAPALI',
];

/* ---------- curl Detayı ---------- */
if (function_exists('curl_version')) {
    $cv = curl_version();
    $r['CURL DETAYI'] = [
        'curl Sürümü'  => $cv['version'],
        'SSL Sürümü'   => $cv['ssl_version'],
        'Protokoller'  => implode(', ', $cv['protocols']),
    ];
}

/* ---------- Composer / Git (varsa) ---------- */
$toolReport = [];
if (function_exists('shell_exec') && !in_array('shell_exec', $disabledList, true)) {
    $php = @shell_exec('php -v 2>&1');
    $composer = @shell_exec('composer --version 2>&1');
    $git = @shell_exec('git --version 2>&1');
    $toolReport['CLI php']  = $php ? trim(strtok($php, "\n")) : 'çalışmadı';
    $toolReport['composer'] = $composer ? trim(strtok($composer, "\n")) : 'bulunamadı';
    $toolReport['git']      = $git ? trim(strtok($git, "\n")) : 'bulunamadı';
} else {
    $toolReport['durum'] = 'shell_exec kapalı olduğu için test edilemedi';
}
$r['CLI ARAÇLARI'] = $toolReport;

/* ---------- Tüm Yüklü Eklentiler ---------- */
$allExts = get_loaded_extensions();
sort($allExts, SORT_FLAG_CASE | SORT_STRING);
$r['TÜM YÜKLÜ EKLENTİLER (' . count($allExts) . ' adet)'] = ['liste' => implode(', ', $allExts)];

/* ---------- Düz metin rapor oluştur ---------- */
$plain = "===== SUNUCU ÖZELLİK RAPORU =====\n";
$plain .= "Oluşturulma: " . date('Y-m-d H:i:s') . "\n\n";
foreach ($r as $section => $items) {
    $plain .= "--- {$section} ---\n";
    foreach ($items as $key => $val) {
        $plain .= sprintf("%-40s : %s\n", $key, $val);
    }
    $plain .= "\n";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sunucu Özellik Raporu</title>
<style>
    body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; background: #f5f6f8; color: #1a1d21; margin: 0; padding: 24px; }
    .wrap { max-width: 960px; margin: 0 auto; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .note { color: #a33; font-size: 14px; margin-bottom: 16px; }
    .btn { display: inline-block; background: #1a6ef5; color: #fff; border: 0; padding: 10px 18px; border-radius: 8px; font-size: 15px; cursor: pointer; margin-bottom: 16px; }
    .btn:active { background: #1256c8; }
    section { background: #fff; border: 1px solid #e2e5ea; border-radius: 10px; margin-bottom: 14px; overflow: hidden; }
    section h2 { font-size: 14px; margin: 0; padding: 10px 14px; background: #eef1f5; border-bottom: 1px solid #e2e5ea; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    td { padding: 6px 14px; border-bottom: 1px solid #f0f2f5; vertical-align: top; }
    td:first-child { width: 320px; color: #555; }
    tr:last-child td { border-bottom: 0; }
    .ok  { color: #0a7d2c; font-weight: 600; }
    .bad { color: #c0262d; font-weight: 600; }
    textarea { width: 100%; height: 260px; font-family: Consolas, monospace; font-size: 12px; border: 1px solid #cfd4db; border-radius: 8px; padding: 10px; box-sizing: border-box; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Sunucu Özellik Raporu</h1>
    <p class="note">⚠️ Bu dosya sunucu bilgisi gösterir — işiniz bitince sunucudan silin.</p>

    <button class="btn" onclick="kopyala()">📋 Raporu Kopyala (Claude'a yapıştırın)</button>
    <textarea id="rapor" readonly><?php echo htmlspecialchars($plain, ENT_QUOTES, 'UTF-8'); ?></textarea>

    <?php foreach ($r as $section => $items): ?>
    <section>
        <h2><?php echo htmlspecialchars($section, ENT_QUOTES, 'UTF-8'); ?></h2>
        <table>
            <?php foreach ($items as $key => $val):
                $cls = '';
                if (in_array($val, ['VAR', 'AÇIK', 'YAZILABİLİR', 'KULLANILABİLİR'], true) || strpos((string)$val, 'OK (') === 0) { $cls = 'ok'; }
                if (in_array($val, ['YOK', 'KAPALI', 'YAZILAMAZ'], true) || strpos((string)$val, 'BAŞARISIZ') === 0) { $cls = 'bad'; }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="<?php echo $cls; ?>"><?php echo htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </section>
    <?php endforeach; ?>
</div>
<script>
function kopyala() {
    var ta = document.getElementById('rapor');
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(ta.value);
        } else {
            document.execCommand('copy');
        }
        alert('Rapor panoya kopyalandı. Claude sohbetine yapıştırabilirsiniz.');
    } catch (e) {
        alert('Otomatik kopyalama olmadı — kutudaki metni elle seçip kopyalayın.');
    }
}
</script>
</body>
</html>
