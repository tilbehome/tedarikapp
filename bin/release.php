<?php

declare(strict_types=1);

/**
 * RELEASE ÜRETİCİ + DOĞRULAYICI (İE#9.3 / K43) — yalnızca CLI, tek yol budur.
 *
 * Üretimde iki kez yaşanan "eksik dosya" sınıfı hatanın (vendor/ yok, setup/ yok)
 * kök çözümü: zip'i ÜRETEN script, docs/07 §4 tarifindeki her girdinin zip'te
 * GERÇEKTEN bulunduğunu üretimden SONRA doğrular; biri eksikse zip'i SİLER ve
 * hata koduyla çıkar — eksik release hiç var olamaz.
 *
 * Zip köküne MANIFEST.txt yazılır (yol + sha256 + toplam): sunucu tarafında
 * `GET /api/system/integrity` ve sihirbazın gereksinim adımı bu manifeste karşı
 * eksik/bozuk dosyaları İSİM İSİM raporlar.
 *
 * ÖN ŞARTLAR (script exec ÇALIŞTIRMAZ — docs/04 §7; kendisi doğrular):
 *   1. `composer install --no-dev --optimize-autoloader` koşulmuş olmalı
 *      (vendor'da phpunit OLMAMALI — dev bağımlılığı üretime taşınmaz).
 *   2. `cd frontend && npm run build` koşulmuş olmalı (public/panel/ dolu).
 *
 * Kullanım: php bin/release.php [--out=dist] [--version=v0.9.2-faz1] [--allow-dev-vendor]
 */

use App\Services\IntegrityChecker;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

// ── Argümanlar ──
$options = getopt('', ['out::', 'version::', 'allow-dev-vendor']);
$outDir = is_string($options['out'] ?? null) && $options['out'] !== '' ? $options['out'] : $basePath . '/dist';
$version = is_string($options['version'] ?? null) && $options['version'] !== ''
    ? $options['version']
    : 'v' . \App\Core\AppVersion::VALUE;
$allowDevVendor = array_key_exists('allow-dev-vendor', $options);

$fail = static function (string $message): never {
    fwrite(STDERR, "HATA: {$message}\n");
    exit(1);
};

// ── 1) Ön şart denetimleri ──
if (!is_file($basePath . '/vendor/autoload.php')) {
    $fail('vendor/autoload.php yok. Önce: composer install --no-dev --optimize-autoloader');
}
if (!$allowDevVendor && is_dir($basePath . '/vendor/phpunit')) {
    $fail('vendor/ dev bağımlılıkları içeriyor (phpunit bulundu). Üretim zip\'i için önce: '
        . 'composer install --no-dev --optimize-autoloader  (test ortamına dönüş: composer install)');
}
if (!is_file($basePath . '/public/panel/index.html')) {
    $fail('Panel derlemesi yok (public/panel/index.html). Önce: cd frontend && npm ci && npm run build');
}
foreach (['setup/views/wizard.html', 'setup/views/wizard.js', 'setup/views/wizard.css', 'bootstrap/preflight.php'] as $required) {
    if (!is_file($basePath . '/' . $required)) {
        $fail($required . ' çalışma ağacında yok — depo eksik/bozuk.');
    }
}

// ── 2) Paket dosya listesi (docs/07 §4 tarifi) ──

/** @var list<string> zip'e girecek göreli yollar */
$files = [];

$excludedBasenames = ['.DS_Store', 'Thumbs.db', 'desktop.ini'];

$collect = function (string $relativeDir) use ($basePath, &$files, $excludedBasenames): void {
    $absolute = $basePath . '/' . $relativeDir;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if (in_array($file->getBasename(), $excludedBasenames, true)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($basePath) + 1));
        // Sır ve yerel artıklar hiçbir koşulda pakete girmez.
        if ($relative === '.env' || str_ends_with($relative, '/.env') || str_ends_with($relative, '.log')) {
            continue;
        }
        // public/media: yalnız koruma dosyası taşınır; geliştirme makinesindeki
        // deneme görselleri pakete sızmaz.
        if (str_starts_with($relative, 'public/media/') && $relative !== 'public/media/.htaccess') {
            continue;
        }
        $files[] = $relative;
    }
};

foreach (['app', 'bin', 'bootstrap', 'migrations', 'public', 'setup', 'vendor'] as $directory) {
    $collect($directory);
}
foreach (['.env.example', '.htaccess', 'composer.json', 'composer.lock'] as $rootFile) {
    if (!is_file($basePath . '/' . $rootFile)) {
        $fail($rootFile . ' bulunamadı.');
    }
    $files[] = $rootFile;
}
if (is_file($basePath . '/storage/.htaccess')) {
    $files[] = 'storage/.htaccess';
}

sort($files);
$files = array_values(array_unique($files));

// ── 2b) SÜRÜM DAMGASI (İE#9.8): zip'e kopyalanan AppVersion.php VALUE'yu bu release'in
// sürümüyle taşır — panel "0.1.0-dev" göstermez. REPODAKİ dosya değişmez ('0.1.0-dev'
// kalır; çift kaynak oluşmaz). MANIFEST özeti de DAMGALI içerikten alınır ki sunucudaki
// integrity denetimi damgalı dosyayı "bozuk" sanmasın.
$appVersionRelative = 'app/Core/AppVersion.php';
$stampValue = ltrim($version, 'vV');
$appVersionSource = file_get_contents($basePath . '/' . $appVersionRelative);
if ($appVersionSource === false) {
    $fail($appVersionRelative . ' okunamadı.');
}
$appVersionStamped = preg_replace(
    "/public const VALUE = '[^']*';/",
    "public const VALUE = '" . $stampValue . "';",
    $appVersionSource,
    1,
    $stampCount,
);
if (!is_string($appVersionStamped) || $stampCount !== 1) {
    $fail($appVersionRelative . " içinde \"public const VALUE = '...';\" satırı bulunamadı — damga başarısız.");
}

// ── 3) MANIFEST üretimi ──
$manifestLines = [
    '# tedarikapp release manifesti — GET /api/system/integrity bu dosyaya göre doğrular (K43)',
    '# surum: ' . $version,
    '# uretim: ' . date(DATE_ATOM),
    '# dosya_sayisi: ' . count($files),
];
foreach ($files as $relative) {
    $hash = $relative === $appVersionRelative
        ? hash('sha256', $appVersionStamped) // damgalı içerik zip'e girer; manifest de onu doğrular
        : hash_file('sha256', $basePath . '/' . $relative);
    if ($hash === false) {
        $fail('Özet alınamadı: ' . $relative);
    }
    $manifestLines[] = IntegrityChecker::manifestLine($hash, $relative);
}
$manifestContent = implode("\n", $manifestLines) . "\n";

// ── 4) Zip üretimi ──
if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) {
    $fail('Çıktı klasörü oluşturulamadı: ' . $outDir);
}
$zipPath = rtrim($outDir, '/\\') . '/tedarikapp-' . $version . '.zip';
@unlink($zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    $fail('Zip açılamadı: ' . $zipPath);
}
foreach ($files as $relative) {
    if ($relative === $appVersionRelative) {
        $zip->addFromString($relative, $appVersionStamped); // damgalı kopya (İE#9.8)

        continue;
    }
    if (!$zip->addFile($basePath . '/' . $relative, $relative)) {
        $fail('Zip\'e eklenemedi: ' . $relative);
    }
}
$zip->addFromString(IntegrityChecker::MANIFEST_FILE, $manifestContent);
$zip->addEmptyDir('storage/logs'); // yazılabilirse loglar buraya; K33'te DB'ye düşer
$zip->close();

// ── 5) ÜRETİM SONRASI DOĞRULAMA (kök çözüm: eksik release var olamaz) ──
$verify = new ZipArchive();
if ($verify->open($zipPath, ZipArchive::RDONLY) !== true) {
    $fail('Üretilen zip geri açılamadı.');
}

/** docs/07 §4 tablosunun temsilci girdileri — biri yoksa release YOK. */
$requiredEntries = [
    'app/Core/AppBuilder.php',
    'app/Core/SetupAppBuilder.php',
    'bin/migrate.php',
    'bin/purge-trash.php',
    'bin/release.php',
    'bootstrap/preflight.php',
    'migrations/0001_create_users.php',
    'public/index.php',
    'public/.htaccess',
    'public/panel/index.html',
    'public/media/.htaccess',
    'public/robots.txt',
    'setup/views/wizard.html',
    'setup/views/wizard.js',
    'setup/views/wizard.css',
    'vendor/autoload.php',
    'vendor/composer/autoload_real.php',
    'vendor/slim/slim/Slim/App.php',
    '.env.example',
    '.htaccess',
    'composer.json',
    'composer.lock',
    IntegrityChecker::MANIFEST_FILE,
];

$missing = [];
foreach ($requiredEntries as $entry) {
    if ($verify->locateName($entry) === false) {
        $missing[] = $entry;
    }
}
if ($verify->locateName('storage/logs/') === false) {
    $missing[] = 'storage/logs/ (klasör)';
}
if ($verify->locateName('.env') !== false) {
    $missing[] = 'İHLAL: .env zip\'e girmiş!';
}

// İE#9.8: paketteki AppVersion.php gerçekten bu sürümü mü taşıyor?
// (/api/system/status aynı sabiti okur — panel artık release sürümünü gösterir.)
$packedAppVersion = (string) $verify->getFromName($appVersionRelative);
if (!str_contains($packedAppVersion, "public const VALUE = '" . $stampValue . "';")) {
    $missing[] = $appVersionRelative . " (sürüm damgası yok: '" . $stampValue . "' bekleniyordu)";
}

// Manifest ile zip birebir mi? (manifest'teki her dosya zip'te olmalı)
$storedManifest = (string) $verify->getFromName(IntegrityChecker::MANIFEST_FILE);
$manifestEntries = IntegrityChecker::parseManifest($storedManifest);
foreach ($manifestEntries as [, $relative]) {
    if ($verify->locateName($relative) === false) {
        $missing[] = $relative . ' (manifestte var, zip\'te yok)';
    }
}
$verify->close();

if ($missing !== []) {
    @unlink($zipPath);
    $fail("Zip doğrulaması BAŞARISIZ — release üretilmedi:\n  - " . implode("\n  - ", $missing));
}

printf(
    "RELEASE HAZIR ve DOĞRULANDI\n  zip     : %s\n  boyut   : %.2f MB\n  dosya   : %d (+ MANIFEST.txt)\n  sha256  : %s\n  surum   : %s\n",
    $zipPath,
    filesize($zipPath) / 1048576,
    count($files),
    hash_file('sha256', $zipPath),
    $version,
);
exit(0);
