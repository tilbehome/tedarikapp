<?php

declare(strict_types=1);

use App\Core\AppBuilder;
use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RequestContext;
use App\Core\SetupAppBuilder;
use App\Setup\SetupLock;

$basePath = dirname(__DIR__);

// K40 ön kontrol kapısı: vendor YÜKLENMEDEN koşar. Eksik PHP sürümü/eklenti/vendor
// çıplak 500 yerine 503 + madde madde açıklama üretir; her şey yerindeyse sessizdir.
require $basePath . '/bootstrap/preflight.php';
tedarikapp_on_kontrol($basePath);

// K42: en erken evre hataları (autoload, Config, bootstrap) ASLA çıplak 500 olmaz.
// Kilit durumu bu evrede bilinemeyebilir; kural — .env YOKSA sistemde sır yoktur,
// tam teşhis gösterilir; .env VARSA (kurulu/kuruluyor) özet gösterilir, sır sızmaz.
try {
    require $basePath . '/vendor/autoload.php';

    // Logger ve uygulama AYNI bağlam nesnesini paylaşır: RequestId middleware
    // bağlamı doldurur, logger her satıra request_id'yi kendiliğinden ekler (K27).
    $requestContext = new RequestContext();

    /**
     * Kurulum mu, uygulama mı?
     *
     *  • `.env` yoksa hiçbir şey ayağa kalkamaz (Config zorunlu anahtar ister) → sihirbaz.
     *  • `/setup*` yolları HER ZAMAN sihirbaza gider; kurulum bittiyse SetupGuard 403 döner
     *    ve denemeyi loglar (İE#5 §10) — sessiz 404 yerine açık ret.
     *  • Kalan her şey normal uygulamaya gider.
     */
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($path) ? $path : '/';
    $isSetupPath = str_starts_with($path, '/setup') || str_starts_with($path, '/api/setup');
    // K44: birincil yapılandırma config.php (WordPress modeli); .env geriye dönük.
    $envExists = is_file($basePath . '/config.php') || is_file($basePath . '/.env');

    // K45: uygulama bir ALT KLASÖRE açılmışsa (örn. docroot/tedarikapp/) rotalar
    // '/tedarikapp/setup' önekiyle gelir. Önek SCRIPT_NAME'den otomatik bulunur
    // ve Slim'e taban yol olarak verilir — yerleşim nereye olursa olsun rota tutar.
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $urlBase = rtrim((string) preg_replace('#/public/index\.php$|/index\.php$#', '', $scriptName), '/');

    if (!$envExists || $isSetupPath || str_ends_with($path, '/setup') || str_contains($path, '/api/setup')) {
        $app = SetupAppBuilder::build(
            $basePath,
            Logger::createForSetup($basePath, $requestContext),
            requestContext: $requestContext,
        );
        if ($urlBase !== '') {
            $app->setBasePath($urlBase);
        }
        $app->run();

        return;
    }

    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

    // Tek tembel bağlantı: logger (LOG_DRIVER=db), kurulum kilidi ve uygulama aynı
    // bağlantıyı paylaşır — istek başına tek PDO açılır (K33).
    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    // K44 disksiz mod: dosyada yalnız DB+APP_KEY var; kalan ayarlar settings tablosundan.
    $config->attachSettings(static fn (): array => \App\Models\SettingsRepository::configOverrides($connection));
    $logger = Logger::create($config, $basePath, $requestContext, $connection);

    $app = AppBuilder::build(
        $config,
        static fn (): PDO => $connection->pdo(),
        $logger,
        setupLock: new SetupLock($connection, $basePath . '/storage'),
        requestContext: $requestContext,
        basePath: $basePath,
    );
    if ($urlBase !== '') {
        $app->setBasePath($urlBase);
    }

    $app->run();
} catch (Throwable $bootFailure) {
    // Framework'e hiç ulaşamamış hata: display_errors'a GÜVENİLMEZ (K42),
    // sayfayı preflight'ın saf-PHP yardımcıcısı üretir.
    $sinif = get_class($bootFailure);
    // 64 haneli hex (APP_KEY biçimi) mesaja sızdıysa maskele — sır kuralı.
    $mesaj = (string) preg_replace('/[0-9a-f]{64}/i', '[gizlendi]', $bootFailure->getMessage());
    $konum = $bootFailure->getFile() . ':' . $bootFailure->getLine();

    if (is_file($basePath . '/config.php') || is_file($basePath . '/.env')) {
        // Kurulu/kurulmakta olan sistem: sır vardır → özet göster, tam iz gösterme.
        tedarikapp_erken_hata_sayfasi(
            500,
            'Uygulama başlatılamadı',
            [
                'Beklenmeyen bir açılış hatası oluştu; ayrıntı aşağıdaki teknik detayda.',
                'Sorun sürerse bu ekranı destek talebinize aynen ekleyin.',
            ],
            $sinif . ': ' . $mesaj . "\n" . 'Konum: ' . $konum . "\n" . 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ') · ' . date('c'),
        );
    }

    // Kurulumsuz sistem: sır YOK → tam teşhis (kısa iz dahil) gösterilir (K42).
    $iz = array_slice(explode("\n", $bootFailure->getTraceAsString()), 0, 8);
    tedarikapp_erken_hata_sayfasi(
        500,
        'Kurulum başlatılamadı',
        [
            'Uygulama daha kurulum sihirbazına ulaşamadan durdu.',
            'Aşağıdaki teknik detay sorunun kaynağını gösterir.',
        ],
        $sinif . ': ' . $mesaj . "\n" . 'Konum: ' . $konum . "\n" . implode("\n", $iz)
        . "\n" . 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ') · ' . date('c'),
    );
}
