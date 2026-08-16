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

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

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
$envExists = is_file($basePath . '/.env');

if (!$envExists || $isSetupPath) {
    $app = SetupAppBuilder::build(
        $basePath,
        Logger::createForSetup($basePath, $requestContext),
        requestContext: $requestContext,
    );
    $app->run();

    return;
}

$config = Config::load($basePath);
date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

// Tek tembel bağlantı: logger (LOG_DRIVER=db), kurulum kilidi ve uygulama aynı
// bağlantıyı paylaşır — istek başına tek PDO açılır (K33).
$connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
$logger = Logger::create($config, $basePath, $requestContext, $connection);

$app = AppBuilder::build(
    $config,
    static fn (): PDO => $connection->pdo(),
    $logger,
    setupLock: new SetupLock($connection, $basePath . '/storage'),
    requestContext: $requestContext,
    basePath: $basePath,
);

$app->run();
