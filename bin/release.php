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
 * Kullanım: php bin/release.php --panel-dal=<dal> [--out=dist] [--version=v0.9.2-faz1]
 *                                [--allow-dev-vendor]
 *
 * `--panel-dal` ZORUNLUDUR (İE#19 E9): hangi panelin paketlendiği tahmine bırakılamaz.
 */

use App\Services\IntegrityChecker;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

// ── Argümanlar ──
$options = getopt('', ['out::', 'version::', 'allow-dev-vendor', 'panel-dal::']);
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
/**
 * PANEL BUILD DAMGASI (v0.11.3 koruması — sürüm disiplini ihlali dersi).
 *
 * OLAY: v0.11.2 paketine panelin BAŞKA BİR DALDA derlenmiş build'i girdi.
 * `public/panel/` .gitignore'dadır; dal değiştirmek diskteki derlemeyi geri
 * almaz ve bu betik diskte ne varsa paketler. Sonuç: onaylanmamış bir arayüz
 * kimse fark etmeden canlıya çıktı.
 *
 * KORUMA: derleme artık `public/panel/BUILD.json` damgası bırakır (vite eklentisi).
 * Burada damga ARANIR; yoksa paketleme REDDEDİLİR. `--panel-dal=` verilirse
 * damgadaki dal ile eşleşmesi ŞART KOŞULUR — "hangi panel gitti?" sorusu artık
 * tahmine değil kayda dayanır. Damga MANIFEST'e de girer (dosya listesindedir).
 */
$panelDamgaYolu = $basePath . '/public/panel/BUILD.json';
if (!is_file($panelDamgaYolu)) {
    $fail(
        "public/panel/BUILD.json YOK — panel derlemesi damgasız.\n"
        . '  Çözüm: cd frontend && npx vite build   (damgayı vite eklentisi yazar)',
    );
}
/** @var array{dal?: string, commit?: string, temiz?: bool, zaman?: string} $panelDamga */
$panelDamga = json_decode((string) file_get_contents($panelDamgaYolu), true) ?: [];
$panelDal = (string) ($panelDamga['dal'] ?? 'bilinmiyor');
$panelCommit = (string) ($panelDamga['commit'] ?? '');
$panelTemiz = ($panelDamga['temiz'] ?? null) === true;

// E9-1: parametre ZORUNLU. Eskiden opsiyoneldi ve atlanınca damga yalnız BASILIYOR,
// denetlenmiyordu — yani koruma çağıranın hatırlamasına bağlıydı. Artık çağrı,
// hangi paneli paketlediğini beyan etmeden çalışmaz.
$beklenenDal = isset($options['panel-dal']) ? trim((string) $options['panel-dal']) : '';
if ($beklenenDal === '') {
    $fail(
        "--panel-dal ZORUNLU: hangi daldan derlenmiş panelin paketlendiğini beyan edin.\n"
        . "  Örnek: php bin/release.php --panel-dal=v3-faz1\n"
        . '  Diskteki damga: ' . ($panelDal === '' ? '(bos)' : $panelDal),
    );
}
if ($panelDal !== $beklenenDal) {
    $fail(sprintf(
        "Panel build BEKLENEN DALDAN değil: damga '%s', beklenen '%s'.\n"
        . '  Doğru dala geçip paneli yeniden derleyin ya da --panel-dal değerini düzeltin.',
        $panelDal,
        $beklenenDal,
    ));
}

// E9-2: KİRLİ çalışma kopyasından derlenmiş panel PAKETLENEMEZ. Böyle bir damgadaki
// commit yalan söyler: diskteki kaynak o commit'te olmayan değişiklikler içerir,
// yani "hangi panel gitti?" sorusunun cevabı yine yoktur.
if (!$panelTemiz) {
    $fail(
        "Panel damgası KİRLİ çalışma kopyası diyor (temiz=false) — paketleme reddedildi.\n"
        . '  Değişiklikleri commitleyip paneli yeniden derleyin: cd frontend && npx vite build',
    );
}

// E9-3: damgadaki commit, beyan edilen DALIN UCUYLA eşleşmeli. `git` ÇALIŞTIRMAYIZ
// (docs/04 §7 exec yasağı disiplinini araçlarda da koruruz); ref dosyası doğrudan
// okunur. Böylece "v3-faz1'den derledim" beyanı depodaki kayda karşı doğrulanır.
$dalUcu = (static function (string $basePath, string $dal): ?string {
    $refYolu = $basePath . '/.git/refs/heads/' . $dal;
    if (is_file($refYolu)) {
        $deger = trim((string) file_get_contents($refYolu));

        return preg_match('/^[0-9a-f]{40}$/', $deger) === 1 ? $deger : null;
    }
    // Sıkıştırılmış ref'ler (git gc sonrası) packed-refs dosyasındadır.
    $packed = $basePath . '/.git/packed-refs';
    if (!is_file($packed)) {
        return null;
    }
    foreach (explode("\n", (string) file_get_contents($packed)) as $satir) {
        if (preg_match('#^([0-9a-f]{40}) refs/heads/(.+)$#', trim($satir), $m) === 1 && $m[2] === $dal) {
            return $m[1];
        }
    }

    return null;
})($basePath, $beklenenDal);

// Ayrık HEAD (CI'da pull_request çıkarması) durumunda dal adı "HEAD"tir ve
// refs/heads altında karşılığı yoktur. O zaman ölçüt DALIN UCU değil, ÇALIŞMA
// KOPYASININ HEAD'idir: "panel tam da paketlenen ağaçtan derlendi mi?" Asıl
// güvence budur; dal ucu yalnızca bunun günlük kullanımdaki vekiliydi.
$headCommit = (static function (string $basePath): ?string {
    $headYolu = $basePath . '/.git/HEAD';
    if (!is_file($headYolu)) {
        return null;
    }
    $head = trim((string) file_get_contents($headYolu));
    if (preg_match('/^[0-9a-f]{40}$/', $head) === 1) {
        return $head; // ayrık HEAD
    }
    if (preg_match('#^ref: (refs/heads/.+)$#', $head, $m) !== 1) {
        return null;
    }
    $refYolu = $basePath . '/.git/' . $m[1];
    if (is_file($refYolu)) {
        $deger = trim((string) file_get_contents($refYolu));

        return preg_match('/^[0-9a-f]{40}$/', $deger) === 1 ? $deger : null;
    }

    return null;
})($basePath);

$kabulEdilen = array_values(array_filter([$dalUcu, $headCommit]));
if ($kabulEdilen === []) {
    $fail(sprintf('"%s" dalı da HEAD de okunamadı — panel damgası doğrulanamıyor.', $beklenenDal));
}

$eslesti = false;
foreach ($kabulEdilen as $aday) {
    if ($panelCommit !== '' && str_starts_with($aday, $panelCommit)) {
        $eslesti = true;

        break;
    }
}
if (!$eslesti) {
    $fail(sprintf(
        "Panel damgasındaki commit paketlenen ağaçla EŞLEŞMİYOR: damga '%s'; kabul edilen: %s.\n"
        . '  Dalı güncelleyip paneli yeniden derleyin (cd frontend && npx vite build).',
        $panelCommit === '' ? '(bos)' : $panelCommit,
        implode(', ', array_map(static fn (string $x): string => substr($x, 0, 12), $kabulEdilen)),
    ));
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
        // EK-2: config.php SUNUCUNUN sırrıdır — hiçbir koşulda pakete girmez
        // (örneği config.example.php adıyla ayrıca eklenir).
        if ($relative === 'config.php' || str_ends_with($relative, '/config.php')) {
            continue;
        }
        // public/media: yalnız koruma dosyası taşınır; geliştirme makinesindeki
        // deneme görselleri pakete sızmaz.
        if (str_starts_with($relative, 'public/media/') && $relative !== 'public/media/.htaccess') {
            continue;
        }
        // İE#19 G4: public/tani.php PAKETE GİRMEZ. Kimliksiz erişilebilen bu teşhis
        // sayfası sunucu yollarını, PHP sürümünü, dosya varlığını ve rewrite
        // davranışını dışarı basıyordu — kurulum sırasında faydalıydı, kurulmuş
        // sistemde yalnız keşif kolaylığıdır. Depoda kalır (yerel araç), canlıya gitmez.
        if ($relative === 'public/tani.php') {
            continue;
        }
        // İE#10.5 Blok 7: mPDF font ayıklaması — kullanılan yalnız DejaVu (TR/₺/¥) ve
        // Sun-ExtA/B (Çince başlıklar, autoLangToFont). Kalan ~60 font ailesi pakete girmez;
        // PdfRenderer başka font istemez (default_font=dejavusans + CJK oto-geçiş).
        if (str_starts_with($relative, 'vendor/mpdf/mpdf/ttfonts/')) {
            $font = strtolower(basename($relative));
            if (!str_starts_with($font, 'dejavu') && !str_starts_with($font, 'sun-ext')) {
                continue;
            }
        }
        $files[] = $relative;
    }
};

// İE#14 A2 sonrası EK: `config/` de pakete girer — yerel sözlükler (K56 Katman 1)
// orada durur ve pakette gelmezse çeviri katmanı canlıda BOŞ çalışır. Dosyalar
// SALT OKUNUR varsayılandır; kullanıcının kendi terimleri storage/ altındadır (K44),
// yani güncelleme kullanıcı sözlüğünü EZMEZ.
foreach (['app', 'bin', 'bootstrap', 'config', 'migrations', 'public', 'setup', 'vendor'] as $directory) {
    $collect($directory);
}
foreach (['.env.example', 'config.example.php', '.htaccess', 'composer.json', 'composer.lock'] as $rootFile) {
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
    // E9-4: panel damgası MANIFEST'e girer — paketin hangi panel derlemesini
    // taşıdığı zip açıldıktan sonra da okunabilir olsun.
    '# panel_dal: ' . $panelDal,
    '# panel_commit: ' . $panelCommit,
    '# panel_temiz: evet', // kirli damga yukarıda paketlemeyi reddeder
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
    'config.example.php',
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
// Sözlükler pakette olmalı: yoksa canlıda Katman 1 sessizce boş çalışır (İE#14 A2).
foreach (['config/sozluk-zh-tr.php', 'config/sozluk-en-tr.php'] as $sozluk) {
    if ($verify->locateName($sozluk) === false) {
        $missing[] = $sozluk . ' (yerel sözlük — K56 Katman 1)';
    }
}
if ($verify->locateName('.env') !== false) {
    $missing[] = 'İHLAL: .env zip\'e girmiş!';
}
if ($verify->locateName('config.php') !== false) {
    $missing[] = 'İHLAL: config.php zip\'e girmiş! (sunucu sırrı)';
}
if ($verify->locateName('public/tani.php') !== false) {
    $missing[] = 'İHLAL: public/tani.php zip\'e girmiş! (G4 sızıntı kapatma)';
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
    "RELEASE HAZIR ve DOĞRULANDI
  zip     : %s
  boyut   : %.2f MB
"
    . "  dosya   : %d (+ MANIFEST.txt)
  sha256  : %s
"
    . "  panel   : %s @ %s (temiz)
  surum   : %s
",
    $zipPath,
    filesize($zipPath) / 1048576,
    count($files),
    hash_file('sha256', $zipPath),
    $panelDal,
    $panelCommit,
    $version,
);
exit(0);
