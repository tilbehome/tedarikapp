<?php

declare(strict_types=1);

use App\Core\AppBuilder;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

$config = Config::load($basePath);
date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

$logger = Logger::create($config, $basePath);

$app = AppBuilder::build(
    $config,
    static fn (): PDO => Database::connect($config),
    $logger,
);

$app->run();
