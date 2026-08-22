<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * API yalnızca JSON konuşur (docs/10 §1).
 *
 * GÖVDELİ yazma isteklerinde `Content-Type: application/json` zorunludur; değilse istek
 * ayrıştırılmadan 415 `UNSUPPORTED_MEDIA_TYPE` ile reddedilir.
 *
 * Neden gövde koşulu var: gövdesiz bir POST/DELETE (örn. `POST /api/auth/logout`) içerik
 * tipi bildirmek zorunda değildir — HTTP'de içerik tipi, içerik VARSA anlamlıdır.
 *
 * Neden önemli: form kodlamalı (`application/x-www-form-urlencoded`) bir istek tarayıcıdan
 * basit HTML formuyla, ön kontrol (preflight) olmadan gönderilebilir. JSON şartı bu yüzden
 * CSRF korumasını tamamlayan ikinci bir settir.
 */
final class JsonRequest implements MiddlewareInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const REQUIRED_TYPE = 'application/json';

    /** Sınır verilmezse kullanılan varsayılan gövde tavanı (KB). */
    public const VARSAYILAN_SINIR_KB = 512;

    /**
     * HTML FORM YÜZEYLERİ (İE#18 G6) — bu yollar API değildir.
     *
     * Erişim anahtarı kapısı gerçek bir `<form method="post">` ile çalışır:
     * JavaScript kapalı olsa da firma anahtarını girip listeyi görebilmelidir
     * (aşamalı geliştirme). Tarayıcı bu gönderimi daima
     * `application/x-www-form-urlencoded` ile yapar; JSON şartı burada geçerli
     * olamaz. İstisna DAR tutulur: yalnız paylaşım ön ekleri ve yalnız
     * `/anahtar` ucu. API uçlarında JSON zorunluluğu AYNEN sürer.
     */
    private const FORM_YOLLARI = ['#^/(liste|p)/[0-9a-f]{64}/anahtar$#'];

    /**
     * @param int $maxGovdeKb üst sınır (KB). Kaynağı `CAPTURE_MAX_PAYLOAD_KB` ayarıdır.
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly int $maxGovdeKb = self::VARSAYILAN_SINIR_KB,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array(strtoupper($request->getMethod()), self::WRITE_METHODS, true)) {
            return $handler->handle($request);
        }
        $asim = $this->govdeSiniriAsildi($request);
        if ($asim !== null) {
            return Response::error(
                $this->responseFactory->createResponse(),
                'PAYLOAD_TOO_LARGE',
                sprintf(
                    'İstek gövdesi çok büyük (%s). Üst sınır %d KB. '
                    . 'Yakalama yükünü küçültün (ör. daha az görsel adresi gönderin).',
                    $asim,
                    $this->maxGovdeKb,
                ),
                413,
            );
        }

        if (!$this->hasBody($request)) {
            return $handler->handle($request);
        }
        if ($this->formYuzeyi($request)) {
            return $handler->handle($request);
        }
        if ($this->mediaType($request) === self::REQUIRED_TYPE) {
            return $handler->handle($request);
        }

        return Response::error(
            $this->responseFactory->createResponse(),
            'UNSUPPORTED_MEDIA_TYPE',
            'Bu API yalnızca JSON kabul eder. İsteği "Content-Type: application/json" ile gönderin.',
            415,
        );
    }

    /**
     * GÖVDE BOYUTU KAPISI (İE#19 E7).
     *
     * `CAPTURE_MAX_PAYLOAD_KB` ayarı VARDI ama hiçbir yerde UYGULANMIYORDU: eklenti
     * ne kadar büyük bir JSON gönderirse göndersin, gövde önce belleğe alınıyor,
     * sonra ayrıştırılıyordu. 50 MB'lık tek bir istek, paylaşımlı hostingde PHP
     * sürecinin bellek sınırını aşıp isteği 500'e düşürmeye yetiyordu — ve bu,
     * kimlik doğrulamadan ÖNCE olabiliyordu.
     *
     * İki kademeli denetim:
     *  1. `Content-Length` başlığı — ucuz, ama istemci yalan söyleyebilir,
     *  2. akışın GERÇEK boyutu (biliniyorsa) — yalanı yakalar.
     * İkisi de bilinmiyorsa (chunked) istek geçer; gövde ayrıştırıcısı zaten
     * PHP'nin kendi sınırlarına tabidir.
     *
     * @return string|null aşım varsa okunabilir boyut, yoksa null
     */
    private function govdeSiniriAsildi(ServerRequestInterface $request): ?string
    {
        $tavan = max(1, $this->maxGovdeKb) * 1024;

        $bildirilen = (int) $request->getHeaderLine('Content-Length');
        if ($bildirilen > $tavan) {
            return sprintf('%d KB', intdiv($bildirilen, 1024));
        }

        $gercek = $request->getBody()->getSize();
        if ($gercek !== null && $gercek > $tavan) {
            return sprintf('%d KB', intdiv($gercek, 1024));
        }

        return null;
    }

    /** İstek bir HTML form yüzeyine mi gidiyor? (JSON şartından muaf) */
    private function formYuzeyi(ServerRequestInterface $request): bool
    {
        $yol = $request->getUri()->getPath();
        foreach (self::FORM_YOLLARI as $desen) {
            if (preg_match($desen, $yol) === 1) {
                return true;
            }
        }

        return false;
    }

    /** `application/json; charset=utf-8` → `application/json` */
    private function mediaType(ServerRequestInterface $request): string
    {
        $header = $request->getHeaderLine('Content-Type');
        $type = explode(';', $header, 2)[0];

        return strtolower(trim($type));
    }

    private function hasBody(ServerRequestInterface $request): bool
    {
        $size = $request->getBody()->getSize();
        if ($size !== null) {
            return $size > 0;
        }

        // Akış boyutu bilinmiyor (gerçek sunucuda `php://input` böyledir). HTTP'de gövdenin
        // varlığını Content-Length veya Transfer-Encoding belirler; ikisi de yoksa gövde YOKTUR.
        // Burada "var say" demek, gövdesiz bir POST'u (ör. /duplicate) 415'e düşürüyordu.
        if ($request->hasHeader('Transfer-Encoding')) {
            return true;
        }

        $length = $request->getHeaderLine('Content-Length');

        return $length !== '' && (int) $length > 0;
    }
}
