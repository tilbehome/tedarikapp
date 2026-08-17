<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sondaki eğik çizgi toleransı (K45 canlı düzeltmesi).
 *
 * Kullanıcı `/setup/` yazdığında Slim `/setup` rotasını EŞLEŞTİRMİYOR ve
 * anlamsız bir NOT_FOUND dönüyordu (canlıda yaşandı). Bu katman rota
 * eşleşmesinden önce sondaki `/`yi düşürür; kök `/` istisnadır.
 */
final class TrailingSlash implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if (strlen($path) > 1 && str_ends_with($path, '/')) {
            $request = $request->withUri($uri->withPath(rtrim($path, '/')));
        }

        return $handler->handle($request);
    }
}
