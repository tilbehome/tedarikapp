<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Kurulum adım günlüğü (K42): sihirbazın HER API adımı — adı, sonucu, süresi —
 * sunucu tarafında da kayda geçer. Kullanıcı ekranda ilerleme listesini görür
 * (wizard.js); burada aynı bilgi destek/denetim için loga yazılır.
 *
 * Yalnızca `/api/setup/*` uçlarını ölçer; sihirbazın statik dosyaları gürültü olurdu.
 */
final class SetupAudit implements MiddlewareInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, '/api/setup')) {
            return $handler->handle($request);
        }

        $startedAt = microtime(true);
        $response = $handler->handle($request);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $status = $response->getStatusCode();
        $context = [
            'adim' => $path,
            'metot' => $request->getMethod(),
            'durum' => $status,
            'sure_ms' => $durationMs,
        ];

        if ($status >= 500) {
            $this->logger->error('Kurulum adımı BAŞARISIZ', $context);
        } elseif ($status >= 400) {
            $this->logger->warning('Kurulum adımı reddedildi', $context);
        } else {
            $this->logger->info('Kurulum adımı tamam', $context);
        }

        return $response;
    }
}
