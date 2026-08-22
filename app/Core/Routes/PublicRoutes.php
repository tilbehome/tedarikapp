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
     * Paylaşım adresinin ön ekleri (İE#18 G5).
     *
     * `/liste` KANONİKTİR: üretilen her yeni bağlantı bunu kullanır. `/p`
     * ALIAS'tır ve KALDIRILMAZ — v0.11.3'e kadar gönderilmiş bağlantılar
     * yaşamaya devam etsin. İkisi de AYNI handler'a bağlıdır; yönlendirme
     * yoktur, dolayısıyla K51 sabit 404 disiplini iki yolda da birebir aynıdır.
     */
    public const ON_EKLER = ['/liste', '/p'];

    /** Üretilen bağlantılarda kullanılan kanonik ön ek. */
    public const KANONIK_ON_EK = '/liste';

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
        \Psr\Log\LoggerInterface $logger,
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
                new \App\Services\Translation\Glossary($basePath . '/config', $basePath . '/storage'),
            ),
            $shareDownload,
        );
        $shareGate = new ShareGate($connection);
        // İE#18 G6 (K62): erişim anahtarı kapısı — "linki bilen görür" dönemi bitti.
        $anahtar = new \App\Services\Share\ShareKeyService($lists, (string) $config->get('APP_KEY', ''));
        $kilitSayfasi = new \App\Services\Share\ShareLockPage();
        $surum = \App\Core\AppVersion::VALUE;
        // İE#18 G5 — ADRES ÖN EKİ: kanonik ön ek artık `/liste/`; `/p/` ALIAS
        // olarak AYNEN kalır (yönlendirme DEĞİL, aynı handler iki yola bağlı) —
        // daha önce gönderilmiş bağlantılar kırılmasın. K51 disiplini iki ön ekte
        // BİREBİR aynıdır: token denetimi, sabit 404, hız sınırı, X-Robots-Tag.
        $sayfaHandler = static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($lists, $products, $presenter, $connection, $sharePage, $shareGate, $services, $anahtar, $kilitSayfasi, $surum, $config): ResponseInterface {
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

            // İE#15 C4: paylaşım metinlerinin dili bağlantıdaki ?lang ile gelir.
            $dil = \App\Services\Share\ShareTexts::dil($request->getQueryParams()['lang'] ?? null);

            // ── İE#18 G6: ERİŞİM ANAHTARI KAPISI ──────────────────────────────
            // Kapı AÇIKSA ve geçerli çerez YOKSA: kilit ekranı döner. Bu yanıtta
            // LİSTE VERİSİ YOKTUR (ürün, fiyat, adet hiç render edilmez) — kapı
            // görsel bir katman değil, veri sınırıdır.
            $row = $anahtar->hazirla($row, $now);
            if ($anahtar->kapiAcik($row)) {
                $cerez = $request->getCookieParams()[\App\Services\Share\ShareKeyService::CEREZ_ADI] ?? null;
                if (!$anahtar->cerezGecerli($token, $row, is_string($cerez) ? $cerez : null, $now)) {
                    $response->getBody()->write(
                        $kilitSayfasi->render($presenter->list($row), $token, $surum, false, $dil),
                    );

                    return $response
                        ->withHeader('Content-Type', 'text/html; charset=utf-8')
                        ->withHeader('X-Robots-Tag', 'noindex, nofollow')
                        ->withHeader('Cache-Control', 'no-store');
                }
            }

            $categoryNames = array_column((new CategoryRepository($connection))->all(), 'name', 'id');
            $html = $sharePage->render(
                $presenter->list($row),
                $presenter->productsOf($products->forList((int) $row['id']), $row),
                $categoryNames,
                // Kanonik adres (İE#18 G5 · İE#19 E5): sayfanın kendi bağlantısı, QR ve
                // kanal metinleri /liste/ taşır — /p/ ile açılmış olsa bile — ve taban
                // adres AYARLARDAN gelir, isteğin Host başlığından DEĞİL.
                \App\Core\AppUrl::to($config->get('APP_URL'), $request, self::KANONIK_ON_EK . '/' . $token),
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
        };
        foreach (self::ON_EKLER as $onEk) {
            $app->get($onEk . '/{token}', $sayfaHandler);
        }

        // ── İE#18 G6-c: ANAHTAR DOĞRULAMA UCU ────────────────────────────────
        $anahtarHandler = static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($lists, $presenter, $shareGate, $services, $anahtar, $kilitSayfasi, $surum, $logger): ResponseInterface {
            $now = $services->clock->now();
            $ip = ClientIp::from($request);
            $token = (string) ($args['token'] ?? '');

            $notFound = static function () use ($response): ResponseInterface {
                $response->getBody()->write((new SharePage())->renderNotFound());

                return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            };

            if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1 || $shareGate->blocked($ip, $now)) {
                return $notFound();
            }
            $row = $lists->findByShareHash(hash('sha256', $token));
            if ($row === null) {
                $shareGate->recordInvalid($ip, $token, $now);

                return $notFound();
            }
            if ($row['share_expires_at'] !== null
                && Dates::fromStorage((string) $row['share_expires_at'], $services->timezone) <= $now) {
                return $notFound();
            }

            // HIZ SINIRI: token+IP başına dakikada 5 deneme. Aşımda K51 dili —
            // sabit 404; kaç deneme kaldığı SÖYLENMEZ.
            if ($shareGate->anahtarBlocked($token, $ip, $now)) {
                $logger->warning('Erişim anahtarı deneme sınırı', [
                    'token_onek' => substr($token, 0, 8),
                    'ip' => \App\Services\Share\ShareDownload::kirpilmisIp($ip),
                ]);

                return $notFound();
            }

            $govde = (array) ($request->getParsedBody() ?? []);
            $girilen = is_string($govde['anahtar'] ?? null) ? trim((string) $govde['anahtar']) : '';
            if ($girilen === '' && is_array($govde['anahtar_hane'] ?? null)) {
                // JS KAPALIYKEN: tarayıcı yalnız hane kutularını gönderir; gizli
                // alan boş gelir. Haneler birleştirilir — doğrulama kuralı AYNI.
                $girilen = '';
                foreach ($govde['anahtar_hane'] as $hane) {
                    if (is_scalar($hane)) {
                        $girilen .= trim((string) $hane);
                    }
                }
            }
            $shareGate->recordAnahtarDeneme($token, $ip, $now);

            $row = $anahtar->hazirla($row, $now);
            if (!$anahtar->dogru($row, $girilen)) {
                // İç teşhis (İE#17 G6 hattı) — istemciye yalnız "hatalı" döner.
                $logger->warning('Erişim anahtarı hatalı', [
                    'token_onek' => substr($token, 0, 8),
                    'ip' => \App\Services\Share\ShareDownload::kirpilmisIp($ip),
                ]);
                $response->getBody()->write(
                    $kilitSayfasi->render($presenter->list($row), $token, $surum, true),
                );

                return $response->withStatus(401)
                    ->withHeader('Content-Type', 'text/html; charset=utf-8')
                    ->withHeader('Cache-Control', 'no-store');
            }

            // Doğru: imzalı çerez + kanonik adrese dönüş (sayfa yeniden yüklenir).
            $cerez = sprintf(
                '%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax%s',
                \App\Services\Share\ShareKeyService::CEREZ_ADI,
                $anahtar->cerezDegeri($token, $row, $now),
                \App\Services\Share\ShareKeyService::CEREZ_OMRU_SANIYE,
                $request->getUri()->getScheme() === 'https' ? '; Secure' : '',
            );

            return $response->withStatus(303)
                ->withHeader('Set-Cookie', $cerez)
                ->withHeader('Location', self::KANONIK_ON_EK . '/' . $token . '?acildi=1')
                ->withHeader('Cache-Control', 'no-store');
        };
        foreach (self::ON_EKLER as $onEk) {
            $app->post($onEk . '/{token}/anahtar', $anahtarHandler);
        }

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
            $logger,
            $anahtar,
        );
        foreach (self::ON_EKLER as $onEk) {
            $app->get($onEk . '/{token}/export', [$publicExport, 'download']);
            // İE#17 G4: sayfa yenilenmeden TAZE imzalı bağlantı — 15 dakikadan uzun
            // açık kalan sayfada indirme düğmeleri ölmesin.
            $app->get($onEk . '/{token}/export-link', [$publicExport, 'link']);
        }

        // İE#15 C3 — PAYLAŞIM QR'ı: sunucuda üretilir (dış servis YOK, K45).
        // İçeriği YALNIZ paylaşım adresidir; imzalı indirme adresi QR'a KONMAZ.
        $qrHandler = static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($lists, $shareGate, $services, $anahtar, $config): ResponseInterface {
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

            // İE#18 G6-e: QR de kapıya tabidir — anahtarsız kare üretilmez.
            if ($anahtar->kapiAcik($row)) {
                $cerez = $request->getCookieParams()[\App\Services\Share\ShareKeyService::CEREZ_ADI] ?? null;
                if (!$anahtar->cerezGecerli($token, $row, is_string($cerez) ? $cerez : null, $now)) {
                    return $bos404();
                }
            }

            $dil = \App\Services\Share\ShareTexts::dil($request->getQueryParams()['lang'] ?? null);
            // E5: QR'ın taşıdığı adres ayarlardaki APP_URL'den üretilir.
            $adres = \App\Core\AppUrl::to($config->get('APP_URL'), $request, self::KANONIK_ON_EK . '/' . $token)
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
        };
        foreach (self::ON_EKLER as $onEk) {
            $app->get($onEk . '/{token}/qr.png', $qrHandler);
        }
    }
}
