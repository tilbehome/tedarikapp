<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\RequestContext;
use App\Core\Ulid;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Her isteğe tekil bir kimlik verir (K27).
 *
 * Kimlik SUNUCUDA üretilir; istemcinin gönderdiği `X-Request-Id` başlığına GÜVENİLMEZ —
 * aksi hâlde saldırgan başkasının kimliğini taklit ederek log kayıtlarını kirletebilir.
 * Değer yanıtta `X-Request-Id` olarak döner, tüm log satırlarına ve activity_log'a yazılır.
 */
final class RequestId implements MiddlewareInterface
{
    public const HEADER = 'X-Request-Id';
    public const ATTRIBUTE = 'request_id';

    public function __construct(private readonly RequestContext $context)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = Ulid::generate();
        $userAgent = $request->getHeaderLine('User-Agent');
        $this->context->fill($id, $userAgent === '' ? null : $userAgent);

        return $handler
            ->handle($request->withAttribute(self::ATTRIBUTE, $id))
            ->withHeader(self::HEADER, $id);
    }
}
