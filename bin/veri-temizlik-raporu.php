<?php

declare(strict_types=1);

/**
 * VERİ TEMİZLİK RAPORU (İE#20 C1) — yalnızca CLI, SALT OKUNUR.
 *
 * Göçten (C2) ÖNCE koşar ve şu soruyu yanıtlar: "elimizde ne var, nesi bozuk?"
 * HİÇBİR ŞEY SİLMEZ, HİÇBİR ŞEY DEĞİŞTİRMEZ — yalnız okur ve rapor basar.
 * Silme kararı Ürün Sahibi'nindir ve göç kapısında birlikte onaylanır.
 *
 * Bu ayrım bilinçlidir: temizlik betiği ile rapor betiği aynı dosya olsaydı,
 * "bir bakayım" diye koşan biri veriyi silebilirdi. Rapor önce gelir, onay sonra.
 *
 * Kullanım:
 *   php bin/veri-temizlik-raporu.php               → ekrana özet + liste
 *   php bin/veri-temizlik-raporu.php --json        → makine okunur çıktı
 *   php bin/veri-temizlik-raporu.php --json > storage/temizlik-raporu.json
 *
 * Çıkış kodu: DAİMA 0 (bu bir denetim değil, envanterdir).
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$jsonMu = in_array('--json', array_slice($argv ?? [], 1), true);

$config = Config::load($basePath);
date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));
$connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
$pdo = $connection->pdo();

/**
 * @param array<string, mixed> $params
 *
 * @return list<array<string, mixed>>
 */
$sorgu = static function (string $sql, array $params = []) use ($pdo): array {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    /** @var list<array<string, mixed>> */
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
};

// ── 1) ÇÖP ADLAR ─────────────────────────────────────────────────────────────
// Elle girilmiş deneme kayıtları: "TT", "—", "test", tek karakter, yalnız noktalama.
// Ölçüt DAR tutulur: gerçek bir ürün adını yanlışlıkla işaretlemek, çöpü kaçırmaktan
// daha kötüdür (yanlış pozitif kullanıcıyı gerçek veriyi silmeye ikna edebilir).
$copAdlar = $sorgu(
    "SELECT p.id, p.list_id, l.name AS liste, p.name, p.price_yuan, p.qty, p.created_at
     FROM products p JOIN lists l ON l.id = p.list_id
     WHERE p.deleted_at IS NULL AND (
           CHAR_LENGTH(TRIM(p.name)) <= 2
        OR TRIM(p.name) IN ('—', '-', '--', '...', '.', 'test', 'TEST', 'deneme', 'DENEME', 'asd', 'aaa')
     )
     ORDER BY p.list_id, p.id",
);

// ── 2) KIRIK GÖRSELLER ───────────────────────────────────────────────────────
// Yerel (/media/…) işaret eden ama diskte OLMAYAN kayıtlar. Uzak (hotlink) kayıtlar
// buradan denetlenemez — onlar için ayrı bir ağ turu gerekir (kapsam dışı).
$medyaKok = $basePath . '/' . trim((string) $config->get('MEDIA_PATH', 'public/media'), '/');
$kirikGorseller = [];
foreach ($sorgu(
    "SELECT id, list_id, name, main_image FROM products
     WHERE deleted_at IS NULL AND main_image LIKE '/media/%'",
) as $satir) {
    $ad = basename((string) $satir['main_image']);
    if (!is_file($medyaKok . '/' . $ad)) {
        $kirikGorseller[] = $satir + ['neden' => 'ana görsel diskte yok'];
    }
}
foreach ($sorgu(
    "SELECT i.id, i.product_id, i.path, p.name FROM product_images i
     JOIN products p ON p.id = i.product_id
     WHERE i.storage_mode = 'local' AND p.deleted_at IS NULL",
) as $satir) {
    $ad = basename((string) $satir['path']);
    if (!is_file($medyaKok . '/' . $ad)) {
        $kirikGorseller[] = $satir + ['neden' => 'galeri görseli diskte yok'];
    }
}

// ── 3) GÖRSELSİZ ÜRÜNLER ─────────────────────────────────────────────────────
$gorselsiz = $sorgu(
    "SELECT id, list_id, name FROM products
     WHERE deleted_at IS NULL AND (main_image IS NULL OR TRIM(main_image) = '')
     ORDER BY list_id, id",
);

// ── 4) YİNELENEN ÜRÜNLER ─────────────────────────────────────────────────────
// AYNI LİSTEDE aynı platform+ilan no. Farklı listelerde aynı ürün olması NORMALDİR
// (tekrar sipariş); bu yüzden gruplama liste bazındadır.
$yinelenen = $sorgu(
    "SELECT list_id, platform, external_id, COUNT(*) AS adet,
            GROUP_CONCAT(id ORDER BY id) AS urun_idleri,
            MIN(name) AS ornek_ad
     FROM products
     WHERE deleted_at IS NULL AND external_id IS NOT NULL AND TRIM(external_id) <> ''
     GROUP BY list_id, platform, external_id
     HAVING COUNT(*) > 1
     ORDER BY adet DESC",
);

// Ad bazlı yinelenme (ilan no'su olmayan elle girilmiş kayıtlar için).
$yinelenenAd = $sorgu(
    "SELECT list_id, name, COUNT(*) AS adet, GROUP_CONCAT(id ORDER BY id) AS urun_idleri
     FROM products
     WHERE deleted_at IS NULL AND (external_id IS NULL OR TRIM(external_id) = '')
     GROUP BY list_id, name
     HAVING COUNT(*) > 1
     ORDER BY adet DESC",
);

// ── 5) DENEME LİSTELERİ ──────────────────────────────────────────────────────
$denemeListeler = $sorgu(
    "SELECT l.id, l.name, l.status, l.created_at,
            (SELECT COUNT(*) FROM products p WHERE p.list_id = l.id AND p.deleted_at IS NULL) AS urun
     FROM lists l
     WHERE l.deleted_at IS NULL AND (
           l.name REGEXP '(?i)(deneme|test|örnek|ornek)'
        OR CHAR_LENGTH(TRIM(l.name)) <= 3
     )
     ORDER BY l.id",
);

// ── 6) EKSİK ALANLI ÜRÜNLER (C8 "HAZIR" kapısının ön görünümü) ───────────────
$eksikAlan = $sorgu(
    "SELECT id, list_id, name,
            (url IS NULL OR TRIM(url) = '') AS link_yok,
            (main_image IS NULL OR TRIM(main_image) = '') AS gorsel_yok,
            (category_id IS NULL) AS kategori_yok,
            (price_yuan IS NULL OR price_yuan = 0) AS fiyat_yok,
            (qty IS NULL OR qty = 0) AS miktar_yok
     FROM products
     WHERE deleted_at IS NULL
     HAVING link_yok + gorsel_yok + kategori_yok + fiyat_yok + miktar_yok > 0
     ORDER BY list_id, id",
);

// ── 7) GENEL SAYIM (göç doğrulamasının ÖNCESİ tarafı) ────────────────────────
$sayim = [
    'liste' => (int) $pdo->query('SELECT COUNT(*) FROM lists WHERE deleted_at IS NULL')->fetchColumn(),
    'liste_copte' => (int) $pdo->query('SELECT COUNT(*) FROM lists WHERE deleted_at IS NOT NULL')->fetchColumn(),
    'urun' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn(),
    'urun_copte' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NOT NULL')->fetchColumn(),
    'galeri_gorseli' => (int) $pdo->query('SELECT COUNT(*) FROM product_images')->fetchColumn(),
    'kuyruk_bekleyen' => (int) $pdo->query("SELECT COUNT(*) FROM inbox_items WHERE status IN ('pending','error')")->fetchColumn(),
    'platformlar' => $sorgu('SELECT platform, COUNT(*) AS adet FROM products WHERE deleted_at IS NULL GROUP BY platform ORDER BY adet DESC'),
];

$rapor = [
    'uretim' => date(DATE_ATOM),
    'veritabani' => $config->get('DB_NAME', ''),
    'sayim' => $sayim,
    'cop_adlar' => $copAdlar,
    'kirik_gorseller' => $kirikGorseller,
    'gorselsiz_urunler' => $gorselsiz,
    'yinelenen_ilan' => $yinelenen,
    'yinelenen_ad' => $yinelenenAd,
    'deneme_listeler' => $denemeListeler,
    'eksik_alanli_urunler' => $eksikAlan,
];

if ($jsonMu) {
    echo json_encode($rapor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    exit(0);
}

echo "=== VERİ TEMİZLİK RAPORU (SALT OKUNUR — hiçbir şey silinmedi) ===\n";
echo 'Zaman        : ' . date('Y-m-d H:i') . "\n";
echo 'Veritabanı   : ' . $sayim['liste'] . ' liste · ' . $sayim['urun'] . ' ürün · '
    . $sayim['galeri_gorseli'] . " galeri görseli\n";
echo 'Çöp kutusu   : ' . $sayim['liste_copte'] . ' liste · ' . $sayim['urun_copte'] . " ürün\n";
echo 'Kuyruk       : ' . $sayim['kuyruk_bekleyen'] . " bekleyen yakalama\n\n";

echo "PLATFORM DAĞILIMI (C2 platform kaydının girdisi):\n";
foreach ($sayim['platformlar'] as $satir) {
    printf("  %-14s %5d ürün\n", (string) $satir['platform'], (int) $satir['adet']);
}

$bolum = static function (string $baslik, array $satirlar, callable $yaz): void {
    echo "\n" . $baslik . ' (' . count($satirlar) . ")\n";
    if ($satirlar === []) {
        echo "  — temiz —\n";

        return;
    }
    foreach (array_slice($satirlar, 0, 40) as $satir) {
        echo '  ' . $yaz($satir) . "\n";
    }
    if (count($satirlar) > 40) {
        echo '  … ve ' . (count($satirlar) - 40) . " kayıt daha (tam liste için --json)\n";
    }
};

$bolum('ÇÖP ADLAR — silme adayı', $copAdlar, static fn (array $r): string => sprintf(
    '#%d [liste %s] "%s" · ¥%s × %s',
    (int) $r['id'],
    (string) $r['liste'],
    (string) $r['name'],
    (string) $r['price_yuan'],
    (string) $r['qty'],
));

$bolum('KIRIK GÖRSELLER — onarım veya temizlik adayı', $kirikGorseller, static fn (array $r): string => sprintf(
    '#%d "%s" · %s',
    (int) ($r['id'] ?? 0),
    (string) ($r['name'] ?? ''),
    (string) $r['neden'],
));

$bolum('GÖRSELSİZ ÜRÜNLER', $gorselsiz, static fn (array $r): string => sprintf(
    '#%d [liste %d] "%s"',
    (int) $r['id'],
    (int) $r['list_id'],
    (string) $r['name'],
));

$bolum('YİNELENEN ÜRÜNLER (aynı listede aynı ilan)', $yinelenen, static fn (array $r): string => sprintf(
    'liste %d · %s/%s · %d kez · id: %s · "%s"',
    (int) $r['list_id'],
    (string) $r['platform'],
    (string) $r['external_id'],
    (int) $r['adet'],
    (string) $r['urun_idleri'],
    (string) $r['ornek_ad'],
));

$bolum('YİNELENEN ADLAR (ilan no yok)', $yinelenenAd, static fn (array $r): string => sprintf(
    'liste %d · "%s" · %d kez · id: %s',
    (int) $r['list_id'],
    (string) $r['name'],
    (int) $r['adet'],
    (string) $r['urun_idleri'],
));

$bolum('DENEME/TEST GÖRÜNÜMLÜ LİSTELER', $denemeListeler, static fn (array $r): string => sprintf(
    '#%d "%s" · %s · %d ürün · %s',
    (int) $r['id'],
    (string) $r['name'],
    (string) $r['status'],
    (int) $r['urun'],
    (string) $r['created_at'],
));

echo "\nEKSİK ALANLI ÜRÜNLER (C8 \"HAZIR\" kapısının ön görünümü): " . count($eksikAlan) . "\n";
$eksikSayac = ['link' => 0, 'görsel' => 0, 'kategori' => 0, 'fiyat' => 0, 'miktar' => 0];
foreach ($eksikAlan as $satir) {
    $eksikSayac['link'] += (int) $satir['link_yok'];
    $eksikSayac['görsel'] += (int) $satir['gorsel_yok'];
    $eksikSayac['kategori'] += (int) $satir['kategori_yok'];
    $eksikSayac['fiyat'] += (int) $satir['fiyat_yok'];
    $eksikSayac['miktar'] += (int) $satir['miktar_yok'];
}
foreach ($eksikSayac as $alan => $adet) {
    printf("  %-10s %d üründe eksik\n", $alan, $adet);
}

echo "\nSONRAKİ ADIM: bu rapor Ürün Sahibi ile birlikte gözden geçirilir; silme\n";
echo "kararı GÖÇ KAPISINDA verilir. Bu betik hiçbir kaydı değiştirmez.\n";
exit(0);
