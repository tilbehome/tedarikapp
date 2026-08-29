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
//
// D4b (saha bulgusu, 25 Ağu 2026): `getopt` TANIMSIZ bayrağı SESSİZCE yok sayar.
// `--surum=1.0.0-rc2` yazılınca sürüm bayrağı hiç görülmedi ve paket eski damgayla
// (rc1) üretildi — yanlış paketin doğru göründüğü bir tuzak. Artık tanınmayan her
// bayrak HATA verir: sessiz yanlış paket, geç fark edilen en pahalı hatadır.
$tanimliBayraklar = ['out', 'version', 'allow-dev-vendor', 'panel-dal'];
$options = getopt('', ['out::', 'version::', 'allow-dev-vendor', 'panel-dal::']);

$bilinmeyenler = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        $bilinmeyenler[] = $arg;

        continue;
    }
    $ad = ltrim(strtok(substr($arg, 2), '='), '-');
    if ($ad !== '' && !in_array($ad, $tanimliBayraklar, true)) {
        $bilinmeyenler[] = '--' . $ad;
    }
}

if ($bilinmeyenler !== []) {
    fwrite(STDERR, "HATA: tanınmayan argüman: " . implode(', ', $bilinmeyenler) . "
");
    fwrite(STDERR, "  Geçerli bayraklar: --panel-dal=<dal> --version=<vX.Y.Z> --out=<dizin> --allow-dev-vendor
");
    fwrite(STDERR, "  Not: sürüm bayrağı --version'dır (--surum DEĞİL).
");
    exit(1);
}
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

// V3-B B4 (K99 EK): SÜRÜM NOTLARI PAKETE GİRER.
//
// `docs/` genel olarak pakete GİRMEZ ve girmemeli — şartname ve tarihçedir.
// Ama `docs/surum-notlari/*.md` KULLANICIYA DÖNÜK içeriktir: panelin
// "Yenilikler" balonu ve Ayarlar > Sürüm notları ekranı doğrudan bu dosyaları
// okur. Pakette olmazsa balon sessizce BOŞ çıkar — kullanıcı yeni sürümde ne
// değiştiğini hiç öğrenemez.
//
// Kataloglar (bildirim/panorama) buraya EKLENMEDİ; onlar `config/` altına
// taşındı (K99): çalışma zamanı verisi `docs/` altından okunmaz.
$surumNotlari = glob($basePath . '/docs/surum-notlari/*.md') ?: [];
if ($surumNotlari === []) {
    $fail('docs/surum-notlari/ altında sürüm notu yok — "Yenilikler" balonu boş çıkar.');
}
foreach ($surumNotlari as $not) {
    $files[] = 'docs/surum-notlari/' . basename($not);
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
// K99 EK — ÇALIŞMA ZAMANI KATALOGLARI PAKETTE Mİ?
//
// Bu blok, v1.2.0'ın ilk paketinde yaşananın doğrudan karşılığıdır: kataloglar
// `docs/` altındaydı, `docs/` pakete girmiyordu ve DOSYA SAYAN doğrulama bunu
// göremedi — 2225 dosya sayıldı, hepsinin SHA'sı tuttu, paket "DOĞRULANDI"
// dedi ve içindeki bildirim sistemi ölüydü.
foreach ([
    'config/bildirim-olay-katalogu.json' => 'bildirim olay kataloğu (K99)',
    'config/panorama-brifing-katalogu.json' => 'panorama brifing kataloğu (K99)',
] as $katalog => $aciklama) {
    if ($verify->locateName($katalog) === false) {
        $missing[] = $katalog . ' — ' . $aciklama;
    }
}
// Sürüm notu olmadan "Yenilikler" balonu sessizce boş çıkar.
$notVar = false;
for ($i = 0; $i < $verify->numFiles; $i++) {
    if (str_starts_with((string) $verify->getNameIndex($i), 'docs/surum-notlari/')) {
        $notVar = true;

        break;
    }
}
if (!$notVar) {
    $missing[] = 'docs/surum-notlari/*.md (Yenilikler balonu boş çıkar)';
}

$verify->close();

if ($missing !== []) {
    @unlink($zipPath);
    $fail("Zip doğrulaması BAŞARISIZ — release üretilmedi:\n  - " . implode("\n  - ", $missing));
}

// ── 5) PAKET ÇALIŞTIRMA DENETİMİ (K99 · V3-B paket düzeltmesi) ──
//
// DOSYA SAYMAK YETMEZ. Yukarıdaki denetim üç şeyi kanıtlıyor: dosyalar var,
// SHA'ları tutuyor, manifest eşleşiyor. Hiçbiri "uygulama BU PAKETLE çalışır
// mı?" sorusunu sormuyordu — ve tam bu boşluk, bildirim sistemi ölü bir paketi
// "DOĞRULANDI" damgasıyla geçirdi.
//
// Bu adım zip'i geçici bir dizine açar ve sınıfları ORADAN yükleyip katalogları
// gerçekten okur. Başarısızsa zip SİLİNİR (mevcut desen korunur).
$calistirmaSorunlari = paketCalistirmaDenetimi($zipPath);
if ($calistirmaSorunlari !== []) {
    @unlink($zipPath);
    $fail(
        "Paket ÇALIŞTIRMA denetimi BAŞARISIZ — release üretilmedi:\n  - "
        . implode("\n  - ", $calistirmaSorunlari)
        . "\n(Dosya listesi denetimi GEÇMİŞTİ; bu adım dosyaları KULLANARAK bakar.)",
    );
}

/**
 * PAKET ÇALIŞTIRMA DENETİMİ (K99 · V3-B paket düzeltmesi EK-1).
 *
 * Zip'i geçici bir dizine açar ve uygulamayı ORADAN kullanır. Dosya saymaz —
 * dosyaları KULLANIR. Sınanan altı şey:
 *
 *   1. Bildirim olay kataloğu yüklenir ve olay taşır.
 *   2. Panorama brifing kataloğu yüklenir ve brifing taşır.
 *   3. Yerel sözlükler (K56 Katman 1) yüklenir ve terim taşır.
 *   4. Sürüm notu okunur ve MADDE üretir ("Yenilikler" balonu dolu çıkar).
 *   5. `KatalogDurumu` paket kökünde SAĞLIKLI der.
 *   6. EK-1 SENARYOSU: migration 0035 ZATEN UYGULANMIŞ bir veritabanında
 *      bekleyen migration KALMAZ. Reddedilen paket canlıya kuruldu ve 0035
 *      koştu; düzeltilmiş paket onun ÜSTÜNE kurulacak. "Bekleyen migration
 *      yok" demezse kullanıcı sihirbazda takılırdı.
 *
 * exec/proc_open KULLANILMAZ (docs/04 §7): denetim aynı süreçte, paketin
 * autoloader'ı ayrı bir dizinden yüklenerek yapılır.
 *
 * @return list<string> sorunlar; boşsa paket çalışıyor
 */
function paketCalistirmaDenetimi(string $zipPath): array
{
    $sorunlar = [];
    $gecici = sys_get_temp_dir() . '/tedarikapp-release-' . bin2hex(random_bytes(6));

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['zip yeniden açılamadı: ' . $zipPath];
    }
    if (!$zip->extractTo($gecici)) {
        $zip->close();

        return ['zip geçici dizine açılamadı: ' . $gecici];
    }
    $zip->close();

    try {
        // 1-2) Kataloglar: VARLIK DEĞİL, YÜKLENEBİLİRLİK sınanır.
        foreach ([
            'config/bildirim-olay-katalogu.json' => 'olaylar',
            'config/panorama-brifing-katalogu.json' => 'brifing_sablonlari',
        ] as $goreli => $anahtar) {
            $ham = @file_get_contents($gecici . '/' . $goreli);
            if ($ham === false) {
                $sorunlar[] = $goreli . ' okunamadı (pakette yok ya da izin yok)';

                continue;
            }
            $veri = json_decode($ham, true);
            if (!is_array($veri) || !isset($veri[$anahtar]) || !is_array($veri[$anahtar]) || $veri[$anahtar] === []) {
                $sorunlar[] = $goreli . " yüklendi ama '" . $anahtar . "' boş — bağlı özellik ÇALIŞMAZ";
            }
        }

        // 3) Sözlükler: dosya PHP dizisi döndürmeli.
        foreach (['config/sozluk-zh-tr.php', 'config/sozluk-en-tr.php'] as $sozluk) {
            $yol = $gecici . '/' . $sozluk;
            if (!is_file($yol)) {
                $sorunlar[] = $sozluk . ' pakette yok';

                continue;
            }
            /** @var mixed $terimler */
            $terimler = require $yol;
            if (!is_array($terimler) || $terimler === []) {
                $sorunlar[] = $sozluk . ' boş dizi döndü — Katman 1 sessizce boş çalışır';
            }
        }

        // 4) Sürüm notu MADDE üretiyor mu? Dosyanın varlığı yetmez; balon
        //    boş bir dosyayla da "boş" çıkardı.
        $notlar = glob($gecici . '/docs/surum-notlari/*.md') ?: [];
        if ($notlar === []) {
            $sorunlar[] = 'docs/surum-notlari/ pakette yok — Yenilikler balonu boş';
        } else {
            $maddeliVar = false;
            foreach ($notlar as $not) {
                if (preg_match('/^- .+/m', (string) file_get_contents($not)) === 1) {
                    $maddeliVar = true;

                    break;
                }
            }
            if (!$maddeliVar) {
                $sorunlar[] = 'sürüm notlarının hiçbiri madde imi taşımıyor — balon boş çıkar';
            }
        }

        // 5) Uygulamanın kendi sağlık denetimi PAKET KÖKÜNDE ne diyor?
        //    Sınıf repodan yüklenir ama KÖK DİZİN olarak paket verilir:
        //    denetlenen şey paketin içeriğidir.
        $durum = new App\Core\KatalogDurumu($gecici);
        if (!$durum->saglikliMi()) {
            foreach ($durum->dokum() as $satir) {
                if (!$satir['saglikli']) {
                    $sorunlar[] = 'KatalogDurumu: ' . (string) $satir['hata'];
                }
            }
        }

        // 6) EK-1: 0035 ZATEN UYGULANMIŞ veritabanında bekleyen kalmamalı.
        $sorunlar = array_merge($sorunlar, mevcutKurulumUstuneDenetimi($gecici));
    } catch (Throwable $hata) {
        $sorunlar[] = 'çalıştırma denetimi istisna attı: ' . $hata->getMessage();
    } finally {
        geciciDiziniSil($gecici);
    }

    return $sorunlar;
}

/**
 * EK-1 SENARYOSU — MEVCUT KURULUMUN ÜSTÜNE.
 *
 * Reddedilen v1.2.0 paketi canlıya kuruldu ve migration 0035 koştu. Düzeltilmiş
 * paket onun üstüne gelecek; sihirbaz "bekleyen migration yok" demeli. Bu
 * denetim, paketteki migration dosyalarını 0035'in UYGULANMIŞ olduğu bir
 * SQLite defterine karşı sayar.
 *
 * @return list<string>
 */
function mevcutKurulumUstuneDenetimi(string $paketKok): array
{
    $migrationDizini = $paketKok . '/migrations';
    if (!is_dir($migrationDizini)) {
        return ['pakette migrations/ dizini yok'];
    }

    $dosyalar = glob($migrationDizini . '/*.php') ?: [];
    if ($dosyalar === []) {
        return ['pakette migration dosyası yok'];
    }

    // Defter şeması `Migrator`ın beklediğiyle AYNI olmalı (name/checksum/
    // execution_ms). Elle uydurulmuş bir şema, denetimi "eski şema" hatasıyla
    // düşürür ve asıl sınanan şey hiç sınanmamış olurdu.
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(
        'CREATE TABLE migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(190) NOT NULL UNIQUE,
            checksum CHAR(64) NOT NULL,
            execution_ms INT UNSIGNED NOT NULL,
            applied_at DATETIME NOT NULL
        )',
    );

    // CANLININ BUGÜNKÜ HÂLİ: paketteki TÜM migration'lar uygulanmış sayılır
    // (0035 dâhil — reddedilen paket onu koşturdu). Checksum dosyanın kendi
    // özetidir; `Migrator` bunu değişiklik denetimi için okur.
    $ekle = $pdo->prepare(
        'INSERT INTO migrations (name, checksum, execution_ms, applied_at)
         VALUES (:ad, :ozet, 0, :zaman)',
    );
    foreach ($dosyalar as $dosya) {
        $ekle->execute([
            'ad' => basename($dosya, '.php'),
            'ozet' => hash_file('sha256', $dosya),
            'zaman' => '2026-08-29 10:57:00',
        ]);
    }

    $migrator = new App\Core\Migrator($pdo, $migrationDizini);
    $bekleyen = $migrator->pending();

    if ($bekleyen !== []) {
        return ['0035 uygulanmış kurulumda BEKLEYEN migration kaldı: ' . implode(', ', $bekleyen)];
    }

    // 0035 paketteki migration listesinde gerçekten var mı? (yoksa yukarıdaki
    // "bekleyen yok" sonucu anlamsız olurdu)
    $adlar = array_map(static fn (string $d): string => basename($d, '.php'), $dosyalar);
    if (!in_array('0035_bildirimler', $adlar, true)) {
        return ['pakette 0035_bildirimler migration dosyası YOK'];
    }

    return [];
}

/** Geçici dizini kökten siler (exec yok — özyinelemeli PHP). */
function geciciDiziniSil(string $dizin): void
{
    if (!is_dir($dizin)) {
        return;
    }

    $ogeler = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dizin, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($ogeler as $oge) {
        if (!$oge instanceof SplFileInfo) {
            continue;
        }
        $oge->isDir() ? @rmdir($oge->getPathname()) : @unlink($oge->getPathname());
    }
    @rmdir($dizin);
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
