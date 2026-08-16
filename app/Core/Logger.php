<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

/**
 * Günlükleyici fabrikası — storage/logs/app-{tarih}.log (CLAUDE.md §6:
 * hatalar loga, kullanıcıya teknik detay dökülmez).
 */
final class Logger
{
    public static function create(Config $config, string $basePath): MonologLogger
    {
        $logDir = $basePath . '/' . trim($config->get('LOG_PATH', 'storage/logs'), '/');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logger = new MonologLogger('tedarikapp');
        $logger->pushHandler(new StreamHandler(
            sprintf('%s/app-%s.log', $logDir, date('Y-m-d')),
            self::level($config->get('LOG_LEVEL', 'warning')),
        ));

        return $logger;
    }

    private static function level(string $name): Level
    {
        return match (strtolower($name)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'error' => Level::Error,
            default => Level::Warning,
        };
    }
}
