<?php

declare(strict_types=1);

/**
 * KUYRUK İŞLEYİCİSİ (İE#20 C3) — cron'dan koşar, yalnızca CLI.
 *
 * TEK CRON İLKESİ (İE#13 EK-A) korunur: canlıda zamanlanmış tek görev
 * `bin/backup.php`tir ve bakım onun sonunda AYNI süreçte koşar. Kuyruk ise
 * SIK koşmak zorundadır (çeviri işi 24 saat beklemez), bu yüzden İKİNCİ ve
 * TEK ek cron girdisi budur:
 *
 *   * / 5 * * * *   php /home/<kullanici>/tedarikapp/bin/kuyruk.php >/dev/null 2>&1
 *
 * Koşum kendi süresini kollar (varsayılan 50 sn) — cron aralığından kısadır,
 * yani iki koşum normalde üst üste binmez; binse bile kuyruk sahiplenmesi
 * (koşullu UPDATE) aynı işin iki kez çalışmasını engeller.
 *
 * Kullanım:
 *   php bin/kuyruk.php              → bir tur işle
 *   php bin/kuyruk.php --durum      → yalnız kuyruk sağlığını yazdır (iş almaz)
 *   php bin/kuyruk.php --sure=20    → koşum bütçesini daralt
 *
 * Çıkış kodu: 0 tamam · 1 hata · 2 tur içinde başarısız iş oldu.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Logger;
use App\Core\SystemClock;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\JobRunner;
use App\Services\Kuyruk\KuyrukIsleyicileri;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$argumanlar = array_slice($argv ?? [], 1);
$yalnizDurum = in_array('--durum', $argumanlar, true);
$sure = 50;
foreach ($argumanlar as $arguman) {
    if (preg_match('/^--sure=(\d+)$/', $arguman, $eslesme) === 1) {
        $sure = max(5, min(280, (int) $eslesme[1]));
    }
}

$cikisKodu = 0;

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $config->attachSettings(static fn (): array => \App\Models\SettingsRepository::configOverrides($connection));
    $now = SystemClock::fromConfig($config)->now();
    $logger = Logger::create($config, $basePath, new \App\Core\RequestContext(), $connection);

    $kuyruk = new JobQueue($connection);

    if ($yalnizDurum) {
        $saglik = $kuyruk->saglik($now);
        printf(
            "KUYRUK: %d bekleyen · %d çalışan · %d ölü · en eski bekleyen: %s\n",
            $saglik['bekleyen'],
            $saglik['calisan'],
            $saglik['olu'],
            $saglik['en_eski_bekleyen_dakika'] === null ? '—' : $saglik['en_eski_bekleyen_dakika'] . ' dk',
        );
        foreach ($saglik['turler'] as $tur => $adet) {
            printf("  %-16s %d\n", $tur, $adet);
        }
        exit(0);
    }

    $kosucu = new JobRunner($kuyruk, $logger, $sure);
    KuyrukIsleyicileri::kaydet($kosucu, $config, $connection, $logger, $basePath);

    $sonuc = $kosucu->kos($now);

    printf(
        "KUYRUK TURU: %d iş · %d başarılı · %d başarısız · %.1f sn · duruş: %s\n",
        $sonuc['islenen'],
        $sonuc['basarili'],
        $sonuc['basarisiz'],
        $sonuc['sure'],
        $sonuc['durma_nedeni'],
    );

    // Biten işler 7 günden sonra temizlenir; ölü işler DURUR (arıza kaydıdır).
    $temizlenen = $kuyruk->temizle($now);
    if ($temizlenen > 0) {
        printf("TEMİZLİK: %d biten iş kaydı silindi.\n", $temizlenen);
    }

    $cikisKodu = $sonuc['basarisiz'] > 0 ? 2 : 0;
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    $cikisKodu = 1;
}

exit($cikisKodu);
