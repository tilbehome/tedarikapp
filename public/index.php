<?php

declare(strict_types=1);

use App\Core\AppBuilder;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RequestContext;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

$config = Config::load($basePath);
date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

// Logger ve uygulama AYNI bağlam nesnesini paylaşır: RequestId middleware
// bağlamı doldurur, logger her satıra request_id'yi kendiliğinden ekler (K27).
$requestContext = new RequestContext();
$logger = Logger::create($config, $basePath, $requestContext);

$app = AppBuilder::build(
    $config,
    static fn (): PDO => Database::connect($config),
    $logger,
    requestContext: $requestContext,
);

$app->run();
