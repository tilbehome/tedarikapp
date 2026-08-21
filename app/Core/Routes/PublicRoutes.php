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
     * @param array<string, \App\Services\Export\ExportRenderer> $exportRenderers biçim → render'cı
     */
    public static function register(
        App $app,
        MediaService $mediaService,
        ListRepository $lists,
        ProductRepository $products,
        ListPresenter $presenter,
        Connection $connection,
        AuthServices $services,
        // İE#15 A1: oturumsuz imzalı indirme — imza APP_KEY'den, render'cılar panelle AYNI.
        \App\Core\Config $config,
        \App\Services\Export\ExportSnapshot $snapshot,
        array $exportRenderers,
        string $basePath,
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
        // İE#14 A3: paylaşım sayfası da sözlükten geçen değerleri gösterir.
        // İE#15 A1: indirme bağlantılarını sayfa üretilirken İMZALAR.
        $shareDownload = new \App\Services\Share\ShareDownload((string) $config->get('APP_KEY', ''));
        $sharePage = new SharePage(
            new \App\Services\Translation\ValueSet(
                new \App\Services\Translation\Glossary($basePath . '/config'),
            ),
            $shareDownload,
        );
        $shareGate = new ShareGate($connection);
        $app->get('/p/{token}', static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($lists, $products, $presenter, $connection, $sharePage, $shareGate, $services): ResponseInterface {
            $now = $services->clock->now();
            $ip = ClientIp::from($request);
            $token = (string) ($args['token'] ?? '');

            // İE#10.5 ek: kurumsal 404 sayfası — sabit yanıt ilkesi aynen (tek sayfa, neden ayrımı yok).
            $notFound = static function () use ($response, $sharePage): ResponseInterface {
                $response->getBody()->write($sharePage->renderNotFound());

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
            $uri = $request->getUri();
            // İE#15 C4: paylaşım metinlerinin dili bağlantıdaki ?lang ile gelir.
            $dil = \App\Services\Share\ShareTexts::dil($request->getQueryParams()['lang'] ?? null);
            $html = $sharePage->render(
                $presenter->list($row),
                $presenter->productsOf($products->forList((int) $row['id']), $row),
                $categoryNames,
                $uri->getScheme() . '://' . $uri->getAuthority() . '/p/' . $token,
                // İE#13 F4: paylaşım sayfası da belge antedini taşır (aynı kurumsal dil).
                (new \App\Models\SettingsRepository($connection))->documentHeader(),
                false,
                $token,
                $dil,
                $now,
            );
            $response->getBody()->write($html);

            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withHeader('X-Robots-Tag', 'noindex, nofollow')
                ->withHeader('Cache-Control', 'no-store');
        });

        // İE#15 A1/A2/A3/A4 — OTURUMSUZ İNDİRME: /p/{token}/export?format&lang&exp&sig
        $publicExport = new \App\Controllers\PublicExportController(
            $lists,
            $products,
            new CategoryRepository($connection),
            new \App\Models\SettingsRepository($connection),
            $snapshot,
            $exportRenderers,
            $shareDownload,
            $shareGate,
            $sharePage,
            $services->clock,
            $services->timezone,
        );
        $app->get('/p/{token}/export', [$publicExport, 'download']);

        // İE#15 C3 — PAYLAŞIM QR'ı: sunucuda üretilir (dış servis YOK, K45).
        // İçeriği YALNIZ paylaşım adresidir; imzalı indirme adresi QR'a KONMAZ.
        $app->get('/p/{token}/qr.png', static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($lists, $shareGate, $services): ResponseInterface {
            $now = $services->clock->now();
            $token = (string) ($args['token'] ?? '');
            $bos404 = static fn (): ResponseInterface => $response->withStatus(404)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');

            if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1 || $shareGate->blocked(ClientIp::from($request), $now)) {
                return $bos404();
            }
            $row = $lists->findByShareHash(hash('sha256', $token));
            if ($row === null) {
                return $bos404();
            }
            if ($row['share_expires_at'] !== null
                && Dates::fromStorage((string) $row['share_expires_at'], $services->timezone) <= $now) {
                return $bos404();
            }

            $dil = \App\Services\Share\ShareTexts::dil($request->getQueryParams()['lang'] ?? null);
            $uri = $request->getUri();
            $adres = $uri->getScheme() . '://' . $uri->getAuthority() . '/p/' . $token
                . ($dil === 'tr' ? '' : '?lang=' . $dil);

            $png = \App\Services\Export\QrImage::png($adres);
            if ($png === null) {
                return $bos404();
            }
            $response->getBody()->write($png);

            return $response
                ->withHeader('Content-Type', 'image/png')
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('X-Robots-Tag', 'noindex, nofollow');
        });
    }
}
