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

    $yedek = static function () use ($config, $basePath): string {
        $service = new BackupService($config, $basePath);
        $backup = $service->create();
        $satir = sprintf('%s (%.1f KB)', $backup['name'], $backup['size'] / 1024);
        printf("YEDEK ALINDI  %s sha256 %s...\n", $satir, substr($backup['sha256'], 0, 12));

        $offsite = (new BackupOffsite($config))->send((string) $service->pathFor($backup['name']), $backup['name']);
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

    $sonuc = (new NightlyRunner())->run($yedek, $bakim);

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
