<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\Panorama\PanoramaServisi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/panorama — "Bugün ne var?" (V3-B B1).
 *
 * TEK UÇ. Sekiz brifing için sekiz istek atmak, panelin açılışını sekiz gidiş
 * dönüşe bağlardı; servis metrikleri bir kez toplar.
 */
final class PanoramaController extends ApiController
{
    public function __construct(private readonly PanoramaServisi $servis)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Response::success($response, $this->servis->panorama());
    }
}
