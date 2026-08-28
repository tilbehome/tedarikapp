<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Response;
use App\Setup\ReSetupTicket;
use App\Setup\SetupLock;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Kurulum sihirbazının kapısı (K45 + İE#19 G1/G2).
 *
 * ÜÇ DURUM, ÜÇ DAVRANIŞ — kilit okuması artık iki değerli değil:
 *
 *  • `unlocked` → sihirbaz AÇIK. Bu, kilit satırı HİÇ YAZILMAMIŞ demektir: gerçek
 *    ilk kurulum. K45'in "kurulum hiçbir koşulda bloklanmaz" sözü burada geçerlidir
 *    ve DEĞİŞMEDİ.
 *  • `locked` → sihirbaz KAPALI; tek istisna geçerli bir yeniden-kurulum biletidir
 *    (G2). Biletsiz istek 403 alır ve loglanır.
 *  • `unknown` → veritabanı YAPILANDIRILMIŞ ama kilit OKUNAMIYOR (DB düştü, tablo
 *    yok, kimlik hatalı). Eskiden bu durum sessizce "kilitli değil" muamelesi
 *    görüyordu: `status() !== STATE_LOCKED` koşulu `unknown`u da geçiriyordu. Yani
 *    kurulu bir sistemde veritabanını bir an düşürebilen biri, sihirbazı kimliksiz
 *    açabiliyordu — SetupLock'ın fail-closed niyeti (K37) kapıya hiç ulaşmıyordu.
 *    Artık 503: karar verilemiyorsa GEÇİRİLMEZ.
 */
final class SetupGuard implements MiddlewareInterface
{
    public function __construct(
        private readonly SetupLock $lock,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
        private readonly Clock $clock,
        private readonly ?ReSetupTicket $ticket = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $status = $this->lock->status();

        // FAIL-CLOSED (G1): kilit okunamıyorsa kimse geçmez — sihirbaz sayfası dahil.
        // Sayfayı da kapatmak bilinçlidir: DB okunamıyorken sihirbazın yapabileceği
        // hiçbir adım yoktur (unlock da, migrate de aynı veritabanına yazar), açık
        // bırakmak yalnız saldırı yüzeyi olurdu.
        if ($status === SetupLock::STATE_UNKNOWN) {
            // D2-REV DÜZELTMESİ: fail-closed KORUNUR ama TEŞHİS penceresi açılır.
            //
            // Eskisi tutarlıydı ama kullanıcıyı çıkmaza sokuyordu: veritabanı düşünce
            // sihirbaz komple 503 veriyordu ve ekranda "durum doğrulanamadı" dışında
            // hiçbir bilgi yoktu — oysa D2-REV'in 8. durumu tam da budur ve çözümü
            // sihirbazın İÇİNDE olmalıdır. Açılan pencere DAR ve zararsızdır:
            //   • /setup sayfası ve varlıkları (veri döndürmez),
            //   • GET /api/setup/situation — SALT OKUNUR teşhis, sır içermez,
            //   • POST /api/setup/config-repair — bağlantı bilgisini düzeltir ve
            //     SAHİPLİK KANITI ister (diskteki APP_KEY; DB'ye ihtiyaç duymaz).
            // Yazan/yıkan başka hiçbir uç geçmez.
            if ($this->diagnosisAllowed($request)) {
                return $handler->handle($request);
            }

            $this->logger->error('Kurulum kilidi OKUNAMADI — kapı fail-closed 503 verdi', [
                'ip' => ClientIp::from($request),
                'yol' => $request->getUri()->getPath(),
                'zaman' => $this->clock->now()->format(DATE_ATOM),
            ]);

            return Response::error(
                $this->responseFactory->createResponse(),
                'SETUP_STATE_UNKNOWN',
                'Kurulum durumu doğrulanamadı (veritabanına ulaşılamıyor). Güvenlik gereği '
                . 'sihirbaz bu durumda açılmaz. Veritabanı erişimini düzeltip tekrar deneyin.',
                503,
            );
        }

        if ($status === SetupLock::STATE_UNLOCKED) {
            return $handler->handle($request);
        }

        // ── Buradan aşağısı: sistem KİLİTLİ ──────────────────────────────────────
        // G2: geçerli yeniden-kurulum bileti taşıyan istek, kilitli sistemde de geçer.
        if ($this->ticketValid($request)) {
            return $handler->handle($request);
        }

        // K45: bilet ALMA yolu kilitliyken de açık kalmalı — kullanıcı "yeniden kur"
        // seçeneğini EKRANDA görür, çıkmaz sokakta kalmaz. Sihirbaz sayfası ve
        // varlıkları veri döndürmez; unlock ucu ise sahiplik kanıtı ister.
        $path = $request->getUri()->getPath();
        if ($this->diagnosisAllowed($request)
            || str_ends_with($path, '/api/setup/unlock')
            || str_ends_with($path, '/api/setup/verify-owner')
            || str_ends_with($path, '/api/setup/owner-check')) {
            return $handler->handle($request);
        }

        $this->logger->warning('Kilitli kuruluma erişim denemesi', [
            'ip' => ClientIp::from($request),
            'yol' => $path,
            'metot' => $request->getMethod(),
            'zaman' => $this->clock->now()->format(DATE_ATOM),
        ]);

        return Response::error(
            $this->responseFactory->createResponse(),
            'FORBIDDEN',
            'Kurulum zaten tamamlanmış. Sihirbazı yeniden açmak için "Yeniden kurulum" '
            . 'adımından config.php içindeki APP_KEY ile bilet alın.',
            403,
        );
    }

    /**
     * Kilitli ya da durumu bilinmeyen sistemde bile açık kalan DAR liste:
     * sihirbaz sayfası, varlıkları, salt-okunur teşhis ve kanıt isteyen config
     * onarımı. Hiçbiri veri okumaz/yazmaz (config onarımı hariç — o da kanıtlı).
     */
    private function diagnosisAllowed(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $method = strtoupper($request->getMethod());

        if ($method === 'GET') {
            return str_ends_with($path, '/setup')
                || str_ends_with($path, '/setup/wizard.js')
                || str_ends_with($path, '/setup/wizard.css')
                || str_ends_with($path, '/api/setup/situation');
        }

        return $method === 'POST'
            && (str_ends_with($path, '/api/setup/config-repair')
                || str_ends_with($path, '/api/setup/config-repair/verify'));
    }

    private function ticketValid(ServerRequestInterface $request): bool
    {
        if ($this->ticket === null) {
            return false;
        }

        $cookies = $request->getCookieParams();
        $raw = $cookies[ReSetupTicket::COOKIE_NAME] ?? null;

        return is_string($raw) && $this->ticket->validate($raw, $this->clock->now());
    }
}
