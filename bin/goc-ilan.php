<?php

declare(strict_types=1);

/**
 * ÜRÜN → İLAN GÖÇÜ (İE#20 C2) — yalnızca CLI.
 *
 * `products` satırındaki İLAN bilgilerini (platform, ilan no, adres, satıcı, ham
 * veri, fiyat kademeleri) `listings` + `listing_price_tiers` tablolarına KOPYALAR.
 *
 * ÜÇ EMNİYET:
 *
 *  1. **VARSAYILAN PROVADIR.** Parametresiz koşum hiçbir şey yazmaz; ne olacağını
 *     rapor eder. Yazmak için açıkça `--uygula` gerekir. Yanlışlıkla koşulan bir
 *     göç, koşulmayan bir göçten çok daha pahalıdır.
 *  2. **TOPLAMALIDIR (additive).** `products` tablosundaki hiçbir kolon silinmez,
 *     hiçbir değer değiştirilmez. Kaynak veri yerinde kalır; bu yüzden geri dönüş
 *     planı "yeni tabloları boşalt" kadar basittir (`--geri-al`).
 *  3. **İDEMPOTENTTİR.** Aynı ürün için ikinci kez ilan açılmaz (platform+ilan no
 *     eşleşmesiyle denetlenir); yarıda kalan bir göç tekrar koşulabilir.
 *
 * Kullanım:
 *   php bin/goc-ilan.php                 → PROVA (yazmaz) + sayım raporu
 *   php bin/goc-ilan.php --json          → prova raporu makine okunur
 *   php bin/goc-ilan.php --uygula        → gerçekten yazar
 *   php bin/goc-ilan.php --geri-al       → listings + tiers TEMİZLENİR (products'a dokunmaz)
 *   php bin/goc-ilan.php --dogrula       → göç sonrası sayım/örneklem doğrulaması
 *
 * Çıkış kodu: 0 başarılı · 1 hata · 2 doğrulama TUTMADI.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Services\Ilan\FiyatKademeAyristirici;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$argumanlar = array_slice($argv ?? [], 1);
$uygula = in_array('--uygula', $argumanlar, true);
$geriAl = in_array('--geri-al', $argumanlar, true);
$dogrula = in_array('--dogrula', $argumanlar, true);
$jsonMu = in_array('--json', $argumanlar, true);

$cikisKodu = 0;

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $pdo = $connection->pdo();

    // ── GERİ DÖNÜŞ ───────────────────────────────────────────────────────────
    if ($geriAl) {
        if (!tabloVar($pdo, 'listings')) {
            echo "Hedef tablolar zaten yok — geri alınacak bir şey bulunmuyor.\n";
            exit(0);
        }
        if (!$uygula) {
            echo "GERİ DÖNÜŞ PROVASI: --geri-al ile birlikte --uygula verilmedi, hiçbir şey silinmedi.\n";
            echo 'Silinecek: ' . (int) $pdo->query('SELECT COUNT(*) FROM listings')->fetchColumn()
                . ' ilan · ' . (int) $pdo->query('SELECT COUNT(*) FROM listing_price_tiers')->fetchColumn()
                . " fiyat kademesi\n";
            echo "products tablosuna HİÇBİR koşulda dokunulmaz.\n";
            exit(0);
        }
        $pdo->exec('DELETE FROM listing_price_tiers');
        $pdo->exec('DELETE FROM listings');
        echo "GERİ ALINDI: listings ve listing_price_tiers boşaltıldı. products AYNEN duruyor.\n";
        exit(0);
    }

    // ── DOĞRULAMA ────────────────────────────────────────────────────────────
    if ($dogrula) {
        if (!tabloVar($pdo, 'listings')) {
            fwrite(STDERR, "Hedef tablolar yok — doğrulanacak göç yapılmamış.\n");
            exit(2);
        }
        $sonuc = dogrulamaKos($pdo);
        echo $jsonMu
            ? json_encode($sonuc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"
            : dogrulamaYaz($sonuc);
        exit($sonuc['tamam'] ? 0 : 2);
    }

    // ── GÖÇ (prova veya uygulama) ────────────────────────────────────────────
    //
    // PROVA ŞEMA İSTEMEZ (İE#20 göç kapısı dersi): hedef tablolar (`platforms`,
    // `listings`) henüz YOKKEN de prova raporu üretilebilmelidir. Aksi hâlde
    // "ne olacağını göster" adımı, kendisi için şema değişikliği talep ederdi —
    // yani onay ALINMADAN önce canlı şemaya dokunmak gerekirdi. Bu, kapının
    // amacını tersine çevirirdi.
    //
    // Tablolar yoksa: mevcut ilan sıfır sayılır, platform eşlemesi boş kalır ve
    // rapor "kaç ilan AÇILACAK" sorusunu yine tam yanıtlar. `--uygula` ise
    // tablolar olmadan çalışmaz ve bunu AÇIKÇA söyler.
    $hedefSemaVar = tabloVar($pdo, 'platforms') && tabloVar($pdo, 'listings');

    if ($uygula && !$hedefSemaVar) {
        throw new RuntimeException(
            'Hedef tablolar yok (platforms/listings). Önce şema güncellemesi koşulmalı: php bin/migrate.php',
        );
    }

    $platformlar = [];
    $mevcutIlanlar = [];
    if ($hedefSemaVar) {
        foreach ($pdo->query('SELECT id, kod FROM platforms')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $satir) {
            $platformlar[(string) $satir['kod']] = (int) $satir['id'];
        }
        foreach ($pdo->query('SELECT product_id FROM listings')->fetchAll(PDO::FETCH_COLUMN) ?: [] as $pid) {
            $mevcutIlanlar[(int) $pid] = true;
        }
    }

    $urunler = $pdo->query(
        'SELECT id, list_id, platform, external_id, url, name, name_original, vendor_name, vendor_url,
                sku_matrix, raw_attributes, price_yuan, units_per_carton, created_at
         FROM products
         ORDER BY id',
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $simdi = date('Y-m-d H:i:s');
    $acilacak = 0;
    $atlanan = 0;
    $platformsuz = 0;
    $kademeSayisi = 0;
    $ornekler = [];

    if ($uygula) {
        $pdo->beginTransaction();
    }

    $ilanEkle = $pdo->prepare(
        'INSERT INTO listings (product_id, platform_id, platform_kod, external_id, url, baslik_orijinal,
             satici_ad, satici_url, moq, birim_fiyat, para_birimi, ham_veri, yakalandi_at, created_at, updated_at)
         VALUES (:product_id, :platform_id, :platform_kod, :external_id, :url, :baslik_orijinal,
             :satici_ad, :satici_url, :moq, :birim_fiyat, :para_birimi, :ham_veri, :yakalandi_at, :created_at, :updated_at)',
    );
    $kademeEkle = $pdo->prepare(
        'INSERT INTO listing_price_tiers (listing_id, min_adet, birim_fiyat, para_birimi)
         VALUES (?, ?, ?, ?)',
    );

    foreach ($urunler as $urun) {
        $urunId = (int) $urun['id'];
        if (isset($mevcutIlanlar[$urunId])) {
            $atlanan++;

            continue;
        }

        $kod = trim((string) ($urun['platform'] ?? ''));
        if ($kod === '') {
            // Elle girilmiş ürün: ilan kaydı yine açılır ama platform "manuel"dir.
            // İlan açmamak, "bu ürünün kaynağı yok" bilgisini kaybettirirdi.
            $kod = 'manuel';
            $platformsuz++;
        }

        $ham = is_string($urun['raw_attributes'] ?? null) ? $urun['raw_attributes'] : null;
        $kademeler = FiyatKademeAyristirici::ayristir($ham);

        if (count($ornekler) < 5) {
            $ornekler[] = [
                'urun_id' => $urunId,
                'ad' => (string) $urun['name'],
                'platform' => $kod,
                'ilan_no' => (string) ($urun['external_id'] ?? ''),
                'birim_fiyat' => (string) $urun['price_yuan'],
                'kademe' => count($kademeler),
            ];
        }

        $acilacak++;
        $kademeSayisi += count($kademeler);

        if (!$uygula) {
            continue;
        }

        $ilanEkle->execute([
            'product_id' => $urunId,
            'platform_id' => $platformlar[$kod] ?? null,
            'platform_kod' => $kod,
            'external_id' => $urun['external_id'] === null ? null : mb_substr((string) $urun['external_id'], 0, 100),
            'url' => $urun['url'],
            'baslik_orijinal' => $urun['name_original'],
            'satici_ad' => $urun['vendor_name'],
            'satici_url' => $urun['vendor_url'],
            'moq' => null,
            'birim_fiyat' => $urun['price_yuan'],
            'para_birimi' => 'CNY',
            'ham_veri' => $ham,
            'yakalandi_at' => $urun['created_at'],
            'created_at' => $simdi,
            'updated_at' => $simdi,
        ]);
        $ilanId = (int) $pdo->lastInsertId();

        foreach ($kademeler as $kademe) {
            $kademeEkle->execute([$ilanId, $kademe['min_adet'], $kademe['birim_fiyat'], 'CNY']);
        }
    }

    if ($uygula) {
        $pdo->commit();
    }

    $rapor = [
        'mod' => $uygula ? 'UYGULANDI' : 'PROVA (hiçbir şey yazılmadı)',
        'hedef_sema_var' => $hedefSemaVar,
        'urun_toplam' => count($urunler),
        'ilan_acilacak' => $acilacak,
        'zaten_ilani_var' => $atlanan,
        'platformsuz_manuel_sayildi' => $platformsuz,
        'fiyat_kademesi' => $kademeSayisi,
        'ornekler' => $ornekler,
    ];

    if ($jsonMu) {
        echo json_encode($rapor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
    } else {
        echo "=== ÜRÜN → İLAN GÖÇÜ · " . $rapor['mod'] . " ===\n";
        if (!$hedefSemaVar) {
            echo "ŞEMA  : hedef tablolar HENÜZ YOK (platforms/listings) — bu rapor\n";
            echo "        yalnız NE OLACAĞINI gösterir; şemaya DOKUNULMADI.\n";
        }
        printf("Ürün toplam        : %d\n", $rapor['urun_toplam']);
        printf("Açılacak ilan      : %d\n", $rapor['ilan_acilacak']);
        printf("Zaten ilanı var    : %d (atlandı — idempotans)\n", $rapor['zaten_ilani_var']);
        printf("Platformsuz ürün   : %d ('manuel' olarak kaydedilir)\n", $rapor['platformsuz_manuel_sayildi']);
        printf("Fiyat kademesi     : %d\n", $rapor['fiyat_kademesi']);
        echo "\nÖRNEK EŞLEŞMELER (ilk 5):\n";
        foreach ($ornekler as $o) {
            printf(
                "  ürün #%-5d %-40s → %s/%s · ¥%s · %d kademe\n",
                $o['urun_id'],
                mb_substr($o['ad'], 0, 40),
                $o['platform'],
                $o['ilan_no'] === '' ? '(ilan no yok)' : $o['ilan_no'],
                $o['birim_fiyat'],
                $o['kademe'],
            );
        }
        if (!$uygula) {
            echo "\nBU BİR PROVADIR. Yazmak için: php bin/goc-ilan.php --uygula\n";
            echo "Geri dönüş: php bin/goc-ilan.php --geri-al --uygula (products'a DOKUNMAZ)\n";
        } else {
            echo "\nSONRAKİ ADIM: php bin/goc-ilan.php --dogrula\n";
        }
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    $cikisKodu = 1;
}

exit($cikisKodu);

/** Tablo var mı? (MySQL + SQLite ortak) */
function tabloVar(PDO $pdo, string $tablo): bool
{
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
            $statement->execute([$tablo]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        );
        $statement->execute([$tablo]);

        return (int) $statement->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Göç sonrası doğrulama: SAYIM + ÖRNEKLEM.
 *
 * @return array{tamam: bool, urun: int, ilan: int, eslesmeyen: int, ornek_farklari: list<string>, kademe: int}
 */
function dogrulamaKos(PDO $pdo): array
{
    $urun = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $ilan = (int) $pdo->query('SELECT COUNT(*) FROM listings')->fetchColumn();
    $eslesmeyen = (int) $pdo->query(
        'SELECT COUNT(*) FROM products p LEFT JOIN listings l ON l.product_id = p.id WHERE l.id IS NULL',
    )->fetchColumn();
    $kademe = (int) $pdo->query('SELECT COUNT(*) FROM listing_price_tiers')->fetchColumn();

    // ÖRNEKLEM: rastgele 20 ürünün ilan alanları kaynakla birebir mi?
    $farklar = [];
    $ornekler = $pdo->query(
        'SELECT p.id, p.platform, p.external_id, p.url, p.vendor_name,
                l.platform_kod, l.external_id AS l_external, l.url AS l_url, l.satici_ad
         FROM products p JOIN listings l ON l.product_id = p.id
         ORDER BY RANDOM() LIMIT 20',
    );
    if ($ornekler === false) {
        // MySQL'de RANDOM() yoktur.
        $ornekler = $pdo->query(
            'SELECT p.id, p.platform, p.external_id, p.url, p.vendor_name,
                    l.platform_kod, l.external_id AS l_external, l.url AS l_url, l.satici_ad
             FROM products p JOIN listings l ON l.product_id = p.id
             ORDER BY RAND() LIMIT 20',
        );
    }

    foreach ($ornekler === false ? [] : $ornekler->fetchAll(PDO::FETCH_ASSOC) as $satir) {
        $beklenenPlatform = trim((string) ($satir['platform'] ?? '')) === ''
            ? 'manuel'
            : trim((string) $satir['platform']);
        if ($beklenenPlatform !== (string) $satir['platform_kod']) {
            $farklar[] = sprintf('#%d platform: "%s" ≠ "%s"', (int) $satir['id'], $beklenenPlatform, (string) $satir['platform_kod']);
        }
        if ((string) ($satir['external_id'] ?? '') !== (string) ($satir['l_external'] ?? '')) {
            $farklar[] = sprintf('#%d ilan no ayrışıyor', (int) $satir['id']);
        }
        if ((string) ($satir['url'] ?? '') !== (string) ($satir['l_url'] ?? '')) {
            $farklar[] = sprintf('#%d adres ayrışıyor', (int) $satir['id']);
        }
        if ((string) ($satir['vendor_name'] ?? '') !== (string) ($satir['satici_ad'] ?? '')) {
            $farklar[] = sprintf('#%d satıcı adı ayrışıyor', (int) $satir['id']);
        }
    }

    return [
        'tamam' => $eslesmeyen === 0 && $farklar === [],
        'urun' => $urun,
        'ilan' => $ilan,
        'eslesmeyen' => $eslesmeyen,
        'kademe' => $kademe,
        'ornek_farklari' => $farklar,
    ];
}

/** @param array{tamam: bool, urun: int, ilan: int, eslesmeyen: int, ornek_farklari: list<string>, kademe: int} $sonuc */
function dogrulamaYaz(array $sonuc): string
{
    $metin = "=== GÖÇ DOĞRULAMASI ===\n";
    $metin .= sprintf("Ürün          : %d\n", $sonuc['urun']);
    $metin .= sprintf("İlan          : %d\n", $sonuc['ilan']);
    $metin .= sprintf("İlanı OLMAYAN : %d\n", $sonuc['eslesmeyen']);
    $metin .= sprintf("Fiyat kademesi: %d\n", $sonuc['kademe']);
    $metin .= "\nÖRNEKLEM (20 kayıt) FARKLARI:\n";
    if ($sonuc['ornek_farklari'] === []) {
        $metin .= "  — fark yok —\n";
    } else {
        foreach ($sonuc['ornek_farklari'] as $fark) {
            $metin .= '  ' . $fark . "\n";
        }
    }
    $metin .= "\nSONUÇ: " . ($sonuc['tamam'] ? "DOĞRULAMA GEÇTİ\n" : "DOĞRULAMA TUTMADI — göç eksik/bozuk\n");

    return $metin;
}
