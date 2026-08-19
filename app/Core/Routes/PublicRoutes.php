<?php

declare(strict_types=1);

namespace App\Core\Routes;

use App\Auth\AuthServices;
use App\Core\ClientIp;
use App\Core\Connection;
use App\Core\Dates;
use App\Models\CategoryRepository;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Services\ListPresenter;
use App\Services\MediaService;
use App\Services\Share\ShareGate;
use App\Services\Share\SharePage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

/**
 * Kimliksiz uçlar (İE#10.5 Blok 6 — AppBuilder bölünmesi; davranış AYNEN taşındı):
 * /media yedek hattı (İE#10 5c) ve /p/{token} paylaşım sayfası (K51).
 */
final class PublicRoutes
{
    /**
     * @template T of \Psr\Container\ContainerInterface|null
     *
     * @param App<T> $app kompozisyon kökünden gelir
     */
    public static function register(
        App $app,
        MediaService $mediaService,
        ListRepository $lists,
        ProductRepository $products,
        ListPresenter $presenter,
        Connection $connection,
        AuthServices $services,
    ): void {
        // İE#10 5c YEDEK HAT: /media normalde Apache'nin statik işidir (.htaccess [END]
        // kuralları); rewrite zinciri hangi yerleşimde şaşarsa şaşsın görsel yine açılsın
        // diye uygulama da AYNI adresi sunabilir. Yalnız sunucu-üretimi ad deseni kabul
        // edilir (fileNameFor path-traversal kalkanı); dosya yoksa sade 404 (SPA yönlendirmesi YOK).
        $app->get('/media/{name}', static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($mediaService): ResponseInterface {
            $fileName = $mediaService->fileNameFor('/media/' . (string) ($args['name'] ?? ''));
            $path = $fileName === null ? null : $mediaService->directory() . '/' . $fileName;
            if ($path === null || !is_file($path)) {
                return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }

            $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'avif' => 'image/avif',
                default => 'image/jpeg',
            };
            $response->getBody()->write((string) file_get_contents($path));

            return $response
                ->withHeader('Content-Type', $mime)
                ->withHeader('Cache-Control', 'public, max-age=86400')
                ->withHeader('X-Robots-Tag', 'noindex');
        });

        // İE#10 Blok 4: GİRİŞSİZ paylaşım sayfası — /p/{token}. Sabit yanıt ilkesi:
        // geçersiz/iptal/süresi dolmuş token ve hız sınırı aşımı AYNI 404'ü döndürür.
        $sharePage = new SharePage();
        $shareGate = new ShareGate($connection);
        $app->get('/p/{token}', static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($lists, $products, $presenter, $connection, $sharePage, $shareGate, $services): ResponseInterface {
            $now = $services->clock->now();
            $ip = ClientIp::from($request);
            $token = (string) ($args['token'] ?? '');

            $notFound = static function () use ($response): ResponseInterface {
                $response->getBody()->write(
                    '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
                    . '<title>Bulunamadı</title></head><body><p>Bu paylaşım linki geçersiz veya kaldırılmış.</p></body></html>',
                );

                return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            };

            // 64 hex dışındaki her şey ve hız sınırı aşımı: sorgusuz sabit 404.
            if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1 || $shareGate->blocked($ip, $now)) {
                return $notFound();
            }

            $row = $lists->findByShareHash(hash('sha256', $token));
            if ($row === null) {
                $shareGate->recordInvalid($ip, $token, $now);

                return $notFound();
            }
            if ($row['share_expires_at'] !== null && Dates::fromStorage((string) $row['share_expires_at'], $services->timezone) <= $now) {
                return $notFound();
            }

            $categoryNames = array_column((new CategoryRepository($connection))->all(), 'name', 'id');
            $html = $sharePage->render(
                $presenter->list($row),
                $presenter->productsOf($products->forList((int) $row['id']), $row),
                $categoryNames,
            );
            $response->getBody()->write($html);

            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withHeader('X-Robots-Tag', 'noindex, nofollow')
                ->withHeader('Cache-Control', 'no-store');
        });
    }
}
