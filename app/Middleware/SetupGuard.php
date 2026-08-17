<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Response;
use App\Setup\EnvWriter;
use App\Setup\SetupLock;
use App\Setup\SetupState;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Kurulum sihirbazının kapısı (K16, İE#5 §10, K37).
 *
 * Üç katmanlı denetim — hepsi 403 döner ve deneme loglanır:
 *  1. Kilit KESİN varsa (DB kaydı veya eski `storage/setup.lock`) → kapalı.
 *  2. Kilit OKUNAMIYORSA (DB yapılandırılmış ama erişilemiyor/tablo yok) → K37
 *     fail-closed: kilitli MUAMELESİ görür. Tek istisna, `.env`i BU oturumda üretmiş
 *     devam eden kurulumdur (migration'dan önce `settings` tablosu henüz yoktur).
 *  3. `.env` diskte varsa ve oturum onu üretmemişse → kapalı. Bu katman DB kilidinden
 *     BAĞIMSIZDIR: kurulmuş bir sistemde DB düşse bile sihirbaz açılamaz.
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
        private readonly EnvWriter $envWriter,
        private readonly SetupState $state,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $status = $this->lock->status();

        if ($status === SetupLock::STATE_LOCKED) {
            return $this->deny($request, 'kilit', 'Kurulum zaten tamamlanmış. Sihirbaz kalıcı olarak kapalıdır.');
        }

        $ownsEnvFile = $this->state->ownsEnvFile();

        if ($status === SetupLock::STATE_UNKNOWN && !$ownsEnvFile) {
            // K37: kilidin YOKLUĞU kanıtlanamadı → kilitli say.
            return $this->deny(
                $request,
                'kilit-okunamadi',
                'Kurulum durumu doğrulanamadı (veritabanına erişilemiyor). Güvenlik gereği sihirbaz kapalıdır.',
            );
        }

        if ($this->envWriter->exists() && !$ownsEnvFile) {
            return $this->deny(
                $request,
                'env-mevcut',
                'Sunucuda .env dosyası zaten var; kurulum sihirbazı mevcut kurulumun üzerine yazmaz. '
                . 'Yeniden kurulum gerekiyorsa .env dosyasını sunucudan elle silin.',
            );
        }

        return $handler->handle($request);
    }

    private function deny(ServerRequestInterface $request, string $reason, string $message): ResponseInterface
    {
        $this->logger->warning('Kilitli kuruluma erişim denemesi', [
            'neden' => $reason,
            'ip' => ClientIp::from($request),
            'yol' => $request->getUri()->getPath(),
            'metot' => $request->getMethod(),
            'zaman' => $this->clock->now()->format(DATE_ATOM),
        ]);

        return Response::error($this->responseFactory->createResponse(), 'FORBIDDEN', $message, 403);
    }
}
