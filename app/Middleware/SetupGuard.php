<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Response;
use App\Setup\SetupLock;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Kurulum sihirbazının kapısı (K45 — BASİTLEŞTİRİLDİ, Ürün Sahibi talimatı).
 *
 * TEK kural: veritabanında kurulum kilidi KESİN olarak varsa 403; diğer her
 * durumda sihirbaz AÇIKTIR. K37'nin ek katmanları (config/.env varlığı kilidi,
 * okunamayan kilit = kapalı, oturum sahiplik işareti) kurulumu üretimde defalarca
 * blokladığı için KALDIRILDI — kilit yazılana kadar sihirbaz her koşulda çalışır,
 * mevcut yapılandırma varsa üzerine YAZMADAN kullanır (SetupController).
 */
final class SetupGuard implements MiddlewareInterface
{
    public function __construct(
        private readonly SetupLock $lock,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
        private readonly Clock $clock,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->lock->status() !== SetupLock::STATE_LOCKED) {
            return $handler->handle($request);
        }

        $this->logger->warning('Kilitli kuruluma erişim denemesi', [
            'ip' => ClientIp::from($request),
            'yol' => $request->getUri()->getPath(),
            'metot' => $request->getMethod(),
            'zaman' => $this->clock->now()->format(DATE_ATOM),
        ]);

        return Response::error(
            $this->responseFactory->createResponse(),
            'FORBIDDEN',
            'Kurulum zaten tamamlanmış. Sihirbaz kalıcı olarak kapalıdır.',
            403,
        );
    }
}
