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
 * Kurulum sihirbazının kapısı (K16, İE#5 §10).
 *
 * `storage/setup.lock` VARSA sihirbazın TÜM uçları 403 döner ve deneme kaydedilir.
 * Kilit, sihirbazı kalıcı olarak kapatan tek gerçektir: kurulmuş bir sistemde
 * sihirbazın yeniden çalıştırılabilmesi, .env'i ve admin kullanıcısını değiştirebilecek
 * kimliksiz bir kapı bırakırdı.
 *
 * Kayıt `activity_log`'a değil doğrudan log dosyasına yazılır: kilitliyken bile
 * çalışması gerekir ve bu aşamada veritabanının erişilebilir olduğu garanti değildir.
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
        if (!$this->lock->isLocked()) {
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
