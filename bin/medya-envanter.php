<?php

declare(strict_types=1);

/**
 * MEDYA ENVANTERİ (rc8-01 / dış denetim F-01 + F-14) — VARSAYILAN OLARAK SİLMEZ.
 *
 * NEDEN: `AppBuilder` uzun süre `CaptureApplier`e `MediaService` geçmedi; arşiv
 * modundaki her yakalama diske `<ad>.jpg.tmp` bıraktı ve veritabanına çözülemeyen
 * bir `/media/<ad>.jpg` yazdı. Kusur rc8'de kapandı ama GEÇMİŞ kayıtlar sahada
 * duruyor. Bu betik önce SAYAR: kaç `.tmp`, ne kadar yer, en eskisi ne zaman ve
 * kaç ürünün ana görseli diskte yok.
 *
 * SİLME AYRI BİR KARARDIR: `--onar` verilmedikçe hiçbir dosyaya dokunulmaz.
 * Bir teşhis aracının sessizce veri silmesi, teşhisin kendisini güvenilmez kılar.
 *
 * Kullanım:
 *   php bin/medya-envanter.php                 → yalnız rapor (varsayılan)
 *   php bin/medya-envanter.php --onar          → kırık kayıtları kuyruğa al + eski .tmp sil
 *   php bin/medya-envanter.php --onar --yas=48 → .tmp yaş eşiği (saat, varsayılan 24)
 *
 * Rapor: storage/raporlar/medya-envanter-<tarih>.txt (ve ekrana).
 * Çıkış kodu: 0 temiz · 2 bulgu var · 1 çalıştırılamadı.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\SystemClock;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\KuyrukIsleyicileri;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$argumanlar = array_slice($argv ?? [], 1);
$onar = in_array('--onar', $argumanlar, true);
$yasSaat = 24;
foreach ($argumanlar as $arguman) {
    if (preg_match('/^--yas=(\d+)$/', $arguman, $eslesme) === 1) {
        $yasSaat = max(1, min(720, (int) $eslesme[1]));
    }
}

$bilinmeyen = array_values(array_filter(
    $argumanlar,
    static fn (string $a): bool => $a !== '--onar' && preg_match('/^--yas=\d+$/', $a) !== 1,
));
if ($bilinmeyen !== []) {
    fwrite(STDERR, 'HATA: tanınmayan bayrak: ' . implode(', ', $bilinmeyen) . "\n");
    fwrite(STDERR, "  Geçerli bayraklar: --onar --yas=<saat>\n");
    exit(1);
}

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $now = SystemClock::fromConfig($config)->now();
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: veritabanına bağlanılamadı — ' . $e->getMessage() . "\n");
    exit(1);
}

$medyaYolu = $basePath . '/' . trim($config->get('MEDIA_PATH', 'public/media'), '/');
$satirlar = [];
$yaz = static function (string $metin) use (&$satirlar): void {
    $satirlar[] = $metin;
    echo $metin . "\n";
};

$yaz('MEDYA ENVANTERİ — ' . $now->format('Y-m-d H:i:s P'));
$yaz('Dizin: ' . $medyaYolu . ($onar ? ' · MOD: ONARIM' : ' · MOD: yalnız rapor'));
$yaz(str_repeat('-', 72));

// ── 1) .tmp dosyaları ────────────────────────────────────────────────────────
$tmpler = glob($medyaYolu . '/*.tmp') ?: [];
$toplamBayt = 0;
$enEski = null;
foreach ($tmpler as $tmp) {
    $toplamBayt += (int) @filesize($tmp);
    $zaman = (int) @filemtime($tmp);
    if ($zaman > 0 && ($enEski === null || $zaman < $enEski)) {
        $enEski = $zaman;
    }
}

$yaz(sprintf(
    '.tmp dosyası : %d adet · %.2f MB · en eski: %s',
    count($tmpler),
    $toplamBayt / 1048576,
    $enEski === null ? '—' : date('Y-m-d H:i', $enEski),
));

// ── 2) Diskte karşılığı olmayan ana görseller ────────────────────────────────
$kirik = [];
try {
    $statement = $connection->pdo()->query(
        "SELECT id, main_image FROM products
         WHERE deleted_at IS NULL AND main_image LIKE '/media/%'",
    );
    foreach ($statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC) as $satir) {
        $dosya = $basePath . '/public' . (string) $satir['main_image'];
        if (!is_file($dosya)) {
            $kirik[] = ['id' => (int) $satir['id'], 'url' => (string) $satir['main_image']];
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'UYARI: ürün taraması yapılamadı — ' . $e->getMessage() . "\n");
}

$yaz(sprintf('Kırık ana görsel: %d ürün', count($kirik)));
foreach (array_slice($kirik, 0, 50) as $satir) {
    $yaz(sprintf('  #%-6d %s', $satir['id'], $satir['url']));
}
if (count($kirik) > 50) {
    $yaz(sprintf('  … ve %d kayıt daha (tam liste rapor dosyasında değil; sorguyu yineleyin)', count($kirik) - 50));
}

// ── 3) ONARIM (yalnız --onar) ────────────────────────────────────────────────
if ($onar) {
    $yaz(str_repeat('-', 72));
    $kuyruk = new JobQueue($connection);
    $kuyrugaAlinan = 0;
    foreach ($kirik as $satir) {
        try {
            // D11a hattı: medya işi ürünün ana + galeri görsellerini yeniden indirir.
            $kuyruk->ekle(
                KuyrukIsleyicileri::TUR_MEDYA,
                'urun:' . $satir['id'],
                ['urun_id' => $satir['id']],
                $now,
            );
            $kuyrugaAlinan++;
        } catch (Throwable $e) {
            fwrite(STDERR, 'UYARI: #' . $satir['id'] . ' kuyruğa alınamadı — ' . $e->getMessage() . "\n");
        }
    }
    $yaz(sprintf('Kuyruğa alınan medya işi: %d', $kuyrugaAlinan));

    $esik = $now->getTimestamp() - $yasSaat * 3600;
    $silinen = 0;
    foreach ($tmpler as $tmp) {
        // YAŞ EŞİĞİ: şu anda yazılmakta olan bir dosyayı silmemek için.
        if ((int) @filemtime($tmp) > $esik) {
            continue;
        }
        if (@unlink($tmp)) {
            $silinen++;
        }
    }
    $yaz(sprintf('Silinen .tmp (yaş > %d saat): %d', $yasSaat, $silinen));
} else {
    $yaz(str_repeat('-', 72));
    $yaz('Onarım YAPILMADI. Kırık kayıtları kuyruğa almak ve eski .tmp dosyalarını');
    $yaz('silmek için: php bin/medya-envanter.php --onar');
}

// ── 4) Rapor dosyası ─────────────────────────────────────────────────────────
$raporDizini = $basePath . '/storage/raporlar';
if (!is_dir($raporDizini)) {
    @mkdir($raporDizini, 0775, true);
}
$raporYolu = $raporDizini . '/medya-envanter-' . $now->format('Ymd-His') . '.txt';
if (@file_put_contents($raporYolu, implode("\n", $satirlar) . "\n") !== false) {
    echo 'Rapor: ' . $raporYolu . "\n";
} else {
    fwrite(STDERR, "UYARI: rapor dosyası yazılamadı (storage/raporlar yazılabilir mi?).\n");
}

exit(count($tmpler) > 0 || count($kirik) > 0 ? 2 : 0);
