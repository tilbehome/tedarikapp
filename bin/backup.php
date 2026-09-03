<?php

declare(strict_types=1);

/**
 * GECELİK KOŞU (İE#10.5 Blok 1b · İE#13 EK-A) — yalnızca CLI.
 *
 * TEK CRON İLKESİ: canlıda TEK zamanlanmış görev vardır; yedek ve bakım aynı süreçte
 * arka arkaya koşar (docs/07 "Zamanlanmış görevler"):
 *
 *   0 3 * * *  /usr/local/bin/php /home/<kullanıcı>/<alan-adı>/bin/backup.php
 *
 * 1) YEDEK: dump PHP içinden üretilir (exec YASAK), AES-256-GCM ile şifrelenir
 *    (anahtar APP_KEY'den türetilir), storage/backups/ altına yazılır (web'den
 *    erişilemez) ve yapılandırılmışsa off-site hedefe gönderilir (FTP/SMTP, K8: cURL).
 * 2) BAKIM: çöp kutusu + yetim medya, app_logs/hız sayacı saklama, yedek prune.
 *
 * İKİ İŞ BİRBİRİNİN HATASINI YUTMAZ: yedek düşse bile bakım koşar (NightlyRunner),
 * sonuç app_logs'a TEK birleşik özet satırı olarak yazılır. Çıkış kodu: 0 tamam,
 * 2 kısmi (bir adım hatalı), 1 koşu hiç başlayamadı.
 */

use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Logger;
use App\Core\SystemClock;
use App\Services\BackupOffsite;
use App\Services\BackupService;
use App\Services\CronLog;
use App\Services\MaintenanceTasks;
use App\Services\NightlyRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $now = SystemClock::fromConfig($config)->now();

    $yedek = static function () use ($config, $basePath, $connection): string {
        // v1.2.2 B1: yedek artık bir SET — tek dizin, tek manifest, atomik.
        // Migration defteri manifeste girer: geri yüklerken "bu yedek hangi
        // şemaya ait?" sorusunun tek cevabı odur.
        $defter = [];

        try {
            $statement = $connection->pdo()->query('SELECT name FROM migrations ORDER BY name');
            $defter = $statement === false ? [] : array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) {
            // Defter okunamıyorsa yedek yine alınır; manifest boş defterle
            // yazılır ve prova bunu "karşılaştırma yapılmadı" diye gösterir.
            // Yedeği defter yüzünden hiç almamak, orantısız olurdu.
        }

        $service = new BackupService($config, $basePath, migrationDefteri: $defter);
        $backup = $service->create();

        $setAdi = basename($backup['set_dizini']);
        $satir = sprintf('%s (%.1f KB, %d parça)', $setAdi, $backup['toplam_bayt'] / 1024, $backup['parca_sayisi']);
        printf("YEDEK SETI    %s\n", $satir);

        if ($backup['media_files'] > 0) {
            printf(
                "MEDYA         %d dosya · %.1f MB%s" . PHP_EOL,
                $backup['media_files'],
                $backup['media_bytes'] / 1048576,
                $backup['medya_atlandi'] ? ' · BAZI DOSYALAR ATLANDI (tek başına boyut sınırını aşıyor)' : '',
            );
        } else {
            echo "MEDYA         yedeklenecek gorsel yok." . PHP_EOL;
        }

        // GERİ YÜKLEME PROVASI HER GECE (B3): yedeği almak yetmez, geri
        // yüklenebilir olduğunu da bilmek gerekir. Prova yıkıcı değildir —
        // manifest ile diskin tutarlılığını okur.
        $prova = (new App\Services\Yedek\YedekProvasi())->dogrula($backup['set_dizini'], $defter);
        echo (new App\Services\Yedek\YedekProvasi())->rapor($prova) . PHP_EOL;
        if (!$prova['gecerli']) {
            throw new RuntimeException('Yedek seti provayı GEÇEMEDİ — set kullanılamaz.');
        }

        // Off-site gönderim e-posta ekiyle çalışır ve bir DİZİN gönderemez;
        // SQL parçası gönderilir (eski davranışın birebir karşılığı). Tam setin
        // uzak hedefe gönderimi B5'te TANIMLANDI, uygulaması V3-G.
        $sqlParcasi = $service->parcaYolu($setAdi, 'veritabani.sql.enc');
        $offsite = $sqlParcasi === null
            ? ['attempted' => false, 'sent' => false, 'via' => null, 'error' => null]
            : (new BackupOffsite($config))->send($sqlParcasi, $setAdi . '-veritabani.sql.enc');

        if (!$offsite['attempted']) {
            echo "OFF-SITE: yapılandırılmadı (BACKUP_FTP_* veya BACKUP_SMTP_* girilirse otomatik gönderilir).\n";

            return $satir . ', off-site yapılandırılmadı';
        }
        if (!$offsite['sent']) {
            throw new RuntimeException('off-site gönderim başarısız: ' . $offsite['error']);
        }
        echo 'OFF-SITE: gönderildi (' . $offsite['via'] . ")\n";

        return $satir . ', off-site ' . $offsite['via'];
    };

    $bakim = static function () use ($config, $connection, $basePath, $now): string {
        $result = (new MaintenanceTasks($config, $connection, $basePath))->run($now);
        foreach ($result['lines'] as $line) {
            echo $line . "\n";
        }
        foreach ($result['uyarilar'] as $uyari) {
            fwrite(STDERR, 'UYARI: ' . $uyari . "\n");
        }
        if ($result['uyarilar'] !== []) {
            throw new RuntimeException($result['ozet'] . ' — uyarılar: ' . implode(' · ', $result['uyarilar']));
        }

        return $result['ozet'];
    };

    $baslangic = microtime(true);
    $sonuc = (new NightlyRunner())->run($yedek, $bakim);

    // İE#14 D1: koşunun GÖRÜNÜR izi — cron hiç tetiklenmediyse bu dosya da
    // ilerlemez; panel "Son yedek: X saat önce" uyarısını buradan/yedek yaşından verir.
    (new CronLog($basePath))->write($now, $sonuc['ok'], $sonuc['summary'], microtime(true) - $baslangic);

    // TEK birleşik özet satırı — seviye Info'ya sabitlenmiş kayıtçı (LOG_LEVEL=warning
    // olsa bile gecelik koşunun izi app_logs'ta durmalı).
    Logger::createForMaintenance($config, $basePath, $connection)
        ->log($sonuc['ok'] ? 'info' : 'warning', $sonuc['summary'], [
            'yedek_ok' => $sonuc['backup']['ok'],
            'bakim_ok' => $sonuc['maintenance']['ok'],
        ]);

    echo $sonuc['summary'] . "\n";
    exit($sonuc['ok'] ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
