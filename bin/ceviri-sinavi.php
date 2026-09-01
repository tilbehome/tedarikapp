<?php

declare(strict_types=1);

/**
 * ÇEVİRİ SINAVI — "ALTIN SET" ÖLÇÜMÜ (temiz kurulum sonrası kabul adımı).
 *
 * SALT OKUNUR: hiçbir satır yazmaz, hiçbir dış servise gitmez. Yalnız
 * `products` ile `translation_cache`i eşleştirip üç dili yan yana basar:
 * ZH (orijinal başlık) · TR · EN. Böylece "çeviri katmanı gerçekten üç dilli
 * çalışıyor mu" sorusu ekran görüntüsüne değil TABLOYA dayanır.
 *
 * NEDEN AYRI BETİK: çeviriyi ÜRETEN yol kuyruktur (`bin/kuyruk.php`). Üreten
 * ile ÖLÇEN aynı dosya olsaydı sınav kendi cevabını yazardı. Bu betik yalnız
 * ölçer; eksik çeviri görürse onu ÜRETMEZ, RAPOR EDER.
 *
 * Kullanım:
 *   php bin/ceviri-sinavi.php                → ilk 50 ürün, iki tablo (TR / EN)
 *   php bin/ceviri-sinavi.php --adet=100     → kapsamı değiştir
 *   php bin/ceviri-sinavi.php --liste=3      → yalnız 3 numaralı liste
 *   php bin/ceviri-sinavi.php --eksik        → yalnız çevirisi EKSİK olanlar
 *
 * SSH'sız sunucuda (cPanel) cron ile:
 *   php /home/<kullanici>/tedarikapp/bin/ceviri-sinavi.php > /home/<kullanici>/sinav.txt 2>&1
 *
 * Çıkış kodu: 0 sınav koştu · 1 çalıştırılamadı (bağlantı/yapılandırma).
 * Kapsama düşük çıksa bile 0 döner — bu bir ölçüm, bir kapı değil.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Encrypter;
use App\Models\SettingsRepository;
use App\Models\TranslationCacheRepository;
use App\Services\Translation\CeviriAyarlari;
use App\Services\Translation\CeviriSurumu;
use App\Services\Translation\SozlukFabrikasi;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$argumanlar = array_slice($argv ?? [], 1);

$adet = 50;
$listeId = null;
$yalnizEksik = in_array('--eksik', $argumanlar, true);
foreach ($argumanlar as $arg) {
    if (preg_match('/^--adet=(\d+)$/', $arg, $m) === 1) {
        $adet = max(1, min(500, (int) $m[1]));
    }
    if (preg_match('/^--liste=(\d+)$/', $arg, $m) === 1) {
        $listeId = (int) $m[1];
    }
}

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $pdo = $connection->pdo();
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: veritabanına bağlanılamadı — ' . $e->getMessage() . "\n");
    exit(1);
}

// ── Ürünler: orijinal (Çince) başlığı OLANLAR sınava girer ───────────────────
// Orijinali olmayan üründe çevrilecek bir şey yoktur; onu "eksik" saymak
// ölçümü yanıltırdı (elle girilen ürünlerin Çince başlığı hiç olmaz).
$sql = 'SELECT id, list_id, name, name_original FROM products
        WHERE deleted_at IS NULL AND name_original IS NOT NULL AND name_original <> \'\'';
$params = [];
if ($listeId !== null) {
    $sql .= ' AND list_id = :list_id';
    $params['list_id'] = $listeId;
}
$sql .= ' ORDER BY id ASC LIMIT ' . $adet;

$statement = $pdo->prepare($sql);
$statement->execute($params);
/** @var list<array<string, mixed>> $urunler */
$urunler = $statement->fetchAll(PDO::FETCH_ASSOC);

$cache = new TranslationCacheRepository($connection);

// ── D11 KÖK NEDENİ: SINAV İLE EKRAN AYNI SATIRA BAKMALI (v1.2.1 A6 eki) ──────
//
// Yazıcı (`LlmTranslator::onbellegeYaz`) İKİ satır yazar: SÜRÜMLÜ ve SÜRÜMSÜZ
// anahtar. Ekrandaki DEĞERLER (`ValueSet`) sürümlüyü okur; bu sınav ise yalnız
// SÜRÜMSÜZÜ okuyordu.
//
// Kuyruk yolu boş sözlükle koştuğu dönemde (A6) sürümlü satır YANLIŞ bir
// anahtara yazıldı, sürümsüz satır doğru yazıldı. Sonuç tam olarak 28 Ağustos
// bulgusuydu: sınav "4/4 llm:deepseek" diyor, ekran ham Çince gösteriyor.
//
// Sınav artık İKİSİNİ DE okur ve ayrışmayı RAPORLAR. Ayrışmayı gizleyen bir
// teşhis aracı teşhissizlikten kötüdür — yanlış yere güven verir.
$surumAnahtari = '';

try {
    $surumAnahtari = CeviriSurumu::kur(
        new CeviriAyarlari(new SettingsRepository($connection), new Encrypter($config)),
        SozlukFabrikasi::kur($basePath),
    )->anahtar();
} catch (Throwable $e) {
    fwrite(STDERR, 'UYARI: surum anahtari hesaplanamadi, yalniz surumsuz satir okunacak - ' . $e->getMessage() . "
");
}

/** @var list<array{id:int, zh:string, tr:?string, tr_saglayici:?string, en:?string, en_saglayici:?string}> $satirlar */
$satirlar = [];
foreach ($urunler as $urun) {
    $zh = (string) $urun['name_original'];
    $tr = $cache->find(TranslationCacheRepository::hash($zh, 'zh', 'tr'));
    $en = $cache->find(TranslationCacheRepository::hash($zh, 'zh', 'en'));

    // EKRANIN OKUDUĞU SATIR: sürümlü anahtar.
    $trSurumlu = $surumAnahtari === ''
        ? $tr
        : $cache->find(TranslationCacheRepository::hash($zh, 'zh', 'tr', $surumAnahtari));
    $enSurumlu = $surumAnahtari === ''
        ? $en
        : $cache->find(TranslationCacheRepository::hash($zh, 'zh', 'en', $surumAnahtari));

    $satir = [
        'id' => (int) $urun['id'],
        'zh' => $zh,
        'tr' => $tr === null ? null : (string) $tr['suggested_text'],
        'tr_saglayici' => $tr === null ? null : (string) $tr['provider'],
        'en' => $en === null ? null : (string) $en['suggested_text'],
        'en_saglayici' => $en === null ? null : (string) $en['provider'],
        // AYRIŞMA: sınavın gördüğü satır ile ekranın gördüğü satır aynı mı?
        'ayrisma' => ($tr === null) !== ($trSurumlu === null) || ($en === null) !== ($enSurumlu === null),
    ];

    if ($yalnizEksik && $satir['tr'] !== null && $satir['en'] !== null) {
        continue;
    }
    $satirlar[] = $satir;
}

// ── Basım ────────────────────────────────────────────────────────────────────

/** Uzun metni sütuna sığdır — kesme noktası çok baytlı karakteri BÖLMEZ. */
function kirp(?string $metin, int $uzunluk): string
{
    if ($metin === null || $metin === '') {
        return '—';
    }
    $metin = preg_replace('/\s+/u', ' ', $metin) ?? $metin;
    if (mb_strlen($metin) <= $uzunluk) {
        return $metin;
    }

    return mb_substr($metin, 0, $uzunluk - 1) . '…';
}

/**
 * Terminalde metnin KAPLADIĞI genişlik — karakter sayısı değil.
 *
 * Çince karakter tek karakterdir ama terminalde İKİ sütun kaplar; `printf`in
 * `%-38s`i ise BAYT sayar (Çince karakter 3 bayt). İkisi de yanlış hizalar ve
 * tablo Çince satırlarda dağılır. Sınav çıktısı okunacak bir belgedir; burada
 * hizalama süs değil işlevdir.
 */
function genislik(string $metin): int
{
    $toplam = 0;
    $uzunluk = mb_strlen($metin);
    for ($i = 0; $i < $uzunluk; $i++) {
        $kod = mb_ord(mb_substr($metin, $i, 1)) ?: 0;
        // CJK, Hangul, Kana ve tam genişlikli biçimler: iki sütun.
        $genisMi = ($kod >= 0x1100 && $kod <= 0x115F)
            || ($kod >= 0x2E80 && $kod <= 0xA4CF)
            || ($kod >= 0xAC00 && $kod <= 0xD7A3)
            || ($kod >= 0xF900 && $kod <= 0xFAFF)
            || ($kod >= 0xFF00 && $kod <= 0xFF60)
            || ($kod >= 0xFFE0 && $kod <= 0xFFE6);
        $toplam += $genisMi ? 2 : 1;
    }

    return $toplam;
}

/** Metni sola dayar, GÖRÜNEN genişliğe göre boşlukla tamamlar. */
function doldur(string $metin, int $sutun): string
{
    $eksik = $sutun - genislik($metin);

    return $metin . ($eksik > 0 ? str_repeat(' ', $eksik) : '');
}

/**
 * @param list<array{id:int, zh:string, tr:?string, tr_saglayici:?string, en:?string, en_saglayici:?string}> $satirlar
 */
function tabloBas(array $satirlar, string $dil, string $baslik): void
{
    $alan = $dil === 'tr' ? 'tr' : 'en';
    $saglayiciAlan = $alan . '_saglayici';

    echo "\n" . str_repeat('─', 100) . "\n";
    echo $baslik . "\n";
    echo str_repeat('─', 100) . "\n";
    echo doldur('ÜRÜN', 6) . '  ' . doldur('ORİJİNAL (ZH)', 34) . '  '
        . doldur(mb_strtoupper($dil), 38) . "  SAĞLAYICI\n";
    echo str_repeat('─', 100) . "\n";

    foreach ($satirlar as $satir) {
        /** @var ?string $ceviri */
        $ceviri = $satir[$alan];
        /** @var ?string $saglayici */
        $saglayici = $satir[$saglayiciAlan];
        echo doldur((string) $satir['id'], 6) . '  '
            . doldur(kirp($satir['zh'], 22), 34) . '  '
            . doldur(kirp($ceviri, 36), 38) . '  '
            . ($saglayici ?? 'YOK') . "\n";
    }
}

$toplam = count($satirlar);
$trVar = count(array_filter($satirlar, static fn (array $s): bool => $s['tr'] !== null));
$enVar = count(array_filter($satirlar, static fn (array $s): bool => $s['en'] !== null));

echo "════════════════════════════════════════════════════════════════════════\n";
echo " ÇEVİRİ SINAVI — üç dilli katmanın ölçümü (SALT OKUNUR)\n";
echo "════════════════════════════════════════════════════════════════════════\n";
echo 'Zaman        : ' . date('Y-m-d H:i:s') . "\n";
echo 'Kapsam       : ' . $toplam . ' ürün'
    . ($listeId !== null ? ' (liste #' . $listeId . ')' : '')
    . ($yalnizEksik ? ' — yalnız EKSİK olanlar' : '') . "\n";

if ($toplam === 0) {
    echo "\nSınava girecek ürün yok: orijinal (Çince) başlığı olan ürün bulunamadı.\n";
    echo "Önce eklentiyle ürün yakalayın, sonra kuyruğu koşturun (bin/kuyruk.php).\n";
    exit(0);
}

printf("TR kapsama   : %d/%d  (%%%.1f)\n", $trVar, $toplam, $trVar / $toplam * 100);
printf("EN kapsama   : %d/%d  (%%%.1f)\n", $enVar, $toplam, $enVar / $toplam * 100);


// D11: sınavın gördüğü ile EKRANIN gördüğü ayrışıyorsa bu, kapsamadan DAHA
// ÖNEMLİ bir bulgudur — kapsama yüksek görünürken ekran boş olabilir.
$ayrisan = count(array_filter($satirlar, static fn (array $s): bool => (bool) $s['ayrisma']));
if ($ayrisan > 0) {
    printf(
        "
AYRISMA      : %d/%d urun — sinav satiri BULUYOR ama ekranin okudugu
"
        . "               surumlu satir YOK (ya da tersi). Sozluk surumu degismis
"
        . "               ya da ceviri yanlis sozlukle uretilmis olabilir.
"
        . "               Onarim: panel > Ayarlar > Sistem durumu > Yeniden cevir.
",
        $ayrisan,
        $toplam,
    );
} else {
    echo "
AYRISMA      : yok — sinav ile ekran AYNI satiri okuyor.
";
}
tabloBas($satirlar, 'tr', 'TABLO 1 — TÜRKÇE');
tabloBas($satirlar, 'en', 'TABLO 2 — İNGİLİZCE');

echo "\n" . str_repeat('─', 100) . "\n";
if ($trVar < $toplam || $enVar < $toplam) {
    echo "EKSİK ÇEVİRİ VAR. Olağan nedeni: kuyruk henüz işlemedi.\n";
    echo "  Kuyruğun durumu   : php bin/kuyruk.php --durum\n";
    echo "  Bir tur işlet     : php bin/kuyruk.php\n";
    echo "  Sağlayıcı hatası ?: panel > Ayarlar > Çeviri > Bağlantıyı test et\n";
} else {
    echo "Kapsama TAM: sınava giren her üründe hem TR hem EN çevirisi var.\n";
}
echo str_repeat('─', 100) . "\n";

exit(0);
