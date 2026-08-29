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
    public function __construct(
        private readonly PanoramaServisi $servis,
        // K99: açılışta yapılan katalog denetiminin sonucu. Uç, kataloğu
        // OKUMAYA ÇALIŞIP 500 vermek yerine ne olduğunu SÖYLER.
        private readonly ?\App\Core\KatalogDurumu $katalogDurumu = null,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $hata = $this->katalogDurumu?->hata('panorama');
        if ($hata !== null) {
            // Çıplak 500 "bir şeyler ters gitti" der; bu mesaj NE OLDUĞUNU ve
            // NEREYE bakılacağını söyler. Panel bunu ekranda gösterir.
            return Response::error(
                $response,
                'KATALOG_EKSIK',
                $hata . ' Ayarlar > Sistem & Yedekler bölümünde ayrıntısı var.',
                503,
            );
        }

        return Response::success($response, $this->servis->panorama());
    }
}
