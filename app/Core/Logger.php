<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Günlükleyici fabrikası — storage/logs/app-{tarih}.log (CLAUDE.md §6:
 * hatalar loga, kullanıcıya teknik detay dökülmez).
 *
 * K27: satırlar JSON yazılır (grep yerine yapısal sorgu), her satıra `request_id`
 * eklenir ve hassas alanlar merkezî olarak gizlenir (LogRedactor).
 */
final class Logger
{
    /**
     * @param Connection|null $connection `LOG_DRIVER=db` için gerekir (K33).
     */
    public static function create(
        Config $config,
        string $basePath,
        ?RequestContext $context = null,
        ?Connection $connection = null,
    ): MonologLogger {
        $level = self::level($config->get('LOG_LEVEL', 'warning'));
        $logger = new MonologLogger('tedarikapp');

        // K33: üretimde uygulama diske yazamaz (PHP `nobody`, DSO) → loglar veritabanına.
        // K44 ZORLAMASI: production'da sürücü DAİMA db — dosya log üretimde devre dışı;
        // yanlış yapılandırma sessiz log kaybına dönüşemez. Geliştirmede .env seçer.
        $driver = strtolower($config->get('LOG_DRIVER', 'file'));
        if ($config->isProduction() && $connection !== null) {
            $driver = 'db';
        }

        if ($driver === 'db' && $connection !== null) {
            $logger->pushHandler(new DatabaseLogHandler($connection, $context, $level));
        } else {
            $logDir = $basePath . '/' . trim($config->get('LOG_PATH', 'storage/logs'), '/');
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            $handler = new StreamHandler(sprintf('%s/app-%s.log', $logDir, date('Y-m-d')), $level);
            $handler->setFormatter(new JsonFormatter());
            $logger->pushHandler($handler);
        }

        // Monolog processor'ları LIFO koşar: en son eklenen ilk çalışır.
        // Gizleme en son eklenir ki çağıranın verdiği bağlamı EN ÖNCE süzsün;
        // request_id ondan sonra eklenir (sır değildir, gizlenmemesi gerekir).
        if ($context !== null) {
            $logger->pushProcessor(static fn (LogRecord $record): LogRecord => $record->with(
                extra: $record->extra + ['request_id' => $context->id()],
            ));
        }
        $logger->pushProcessor(new LogRedactor());

        return $logger;
    }

    /**
     * Kurulum sihirbazı için günlükleyici: `.env` henüz yokken Config kurulamaz.
     *
     * `storage/` yazılabilir değilse (kurulumun düzeltmeye çalıştığı durumun ta kendisi)
     * sessizce NullLogger'a düşer — log yazamamak kurulumu durdurmamalı.
     */
    public static function createForSetup(string $basePath, ?RequestContext $context = null): LoggerInterface
    {
        $logDir = $basePath . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        if (!is_dir($logDir) || !is_writable($logDir)) {
            return new NullLogger();
        }

        try {
            $handler = new StreamHandler(sprintf('%s/setup-%s.log', $logDir, date('Y-m-d')), Level::Info);
            $handler->setFormatter(new JsonFormatter());
        } catch (Throwable) {
            return new NullLogger();
        }

        $logger = new MonologLogger('tedarikapp-setup');
        $logger->pushHandler($handler);
        if ($context !== null) {
            $logger->pushProcessor(static fn (LogRecord $record): LogRecord => $record->with(
                extra: $record->extra + ['request_id' => $context->id()],
            ));
        }
        $logger->pushProcessor(new LogRedactor());

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
