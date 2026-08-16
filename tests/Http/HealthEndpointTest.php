<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\AppBuilder;
use App\Core\Config;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;

final class HealthEndpointTest extends TestCase
{
    private function config(): Config
    {
        return new Config([
            'APP_ENV' => 'production',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('a1b2c3d4', 8),
            'EXTENSION_TOKEN_SALT' => str_repeat('s', 32),
        ]);
    }

    public function testSaglikUcuDocs10ZarfiylaDoner(): void
    {
        $app = AppBuilder::build(
            $this->config(),
            static fn (): PDO => new PDO('sqlite::memory:'),
            new NullLogger(),
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        self::assertTrue($body['success']);
        self::assertSame('tedarikapp', $body['data']['app']);
        self::assertNull($body['error']);
        // Zaman ISO 8601 ve +03:00 ofsetli olmalı (docs/10 §1).
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+03:00$/', $body['data']['time']);
    }

    public function testGuvenlikBasliklariYanittaMevcut(): void
    {
        $app = AppBuilder::build(
            $this->config(),
            static fn (): PDO => new PDO('sqlite::memory:'),
            new NullLogger(),
        );

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/health'));

        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertStringContainsString('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));
        self::assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
    }

    /**
     * PM eki (İE#8): sabit `img-src 'self' data:` politikası K33 hotlink modunda
     * görsellerin TAMAMINI bloklardı. Politika artık indirme beyaz listesinden üretilir.
     */
    public function testCspGorselKaynaklariMediaBeyazListesindenUretilir(): void
    {
        $config = new Config([
            'APP_ENV' => 'production',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('a1b2c3d4', 8),
            'EXTENSION_TOKEN_SALT' => str_repeat('s', 32),
            'MEDIA_ALLOWED_HOSTS' => 'alicdn.com, 1688.com',
        ]);

        $app = AppBuilder::build($config, static fn (): PDO => new PDO('sqlite::memory:'), new NullLogger());
        $csp = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/health'))
            ->getHeaderLine('Content-Security-Policy');

        self::assertStringContainsString("img-src 'self' data: https://alicdn.com https://*.alicdn.com", $csp);
        self::assertStringContainsString('https://1688.com https://*.1688.com', $csp);
        // Yalnız-HTTPS hükmü: http kaynağı politikaya girmez.
        self::assertStringNotContainsString('http://', $csp);
    }

    public function testVeritabaniKapaliysaHataZarfiDoner(): void
    {
        $app = AppBuilder::build(
            $this->config(),
            static fn (): PDO => throw new RuntimeException('bağlantı reddedildi'),
            new NullLogger(),
        );

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/health'));

        self::assertSame(500, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        self::assertFalse($body['success']);
        self::assertSame('SERVER_ERROR', $body['error']['code']);
    }

    public function testBilinmeyenUcNotFoundZarfiDoner(): void
    {
        $app = AppBuilder::build(
            $this->config(),
            static fn (): PDO => new PDO('sqlite::memory:'),
            new NullLogger(),
        );

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/olmayan-uc'));

        self::assertSame(404, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('NOT_FOUND', $body['error']['code']);
    }

    // ─────────────── K25: gerçek 405 ───────────────

    public function testDesteklenmeyenMetot405VeAllowBasligiDoner(): void
    {
        $app = AppBuilder::build(
            $this->config(),
            static fn (): PDO => new PDO('sqlite::memory:'),
            new NullLogger(),
        );

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('POST', '/api/health'));

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('GET', $response->getHeaderLine('Allow'));

        $body = json_decode((string) $response->getBody(), true);
        self::assertFalse($body['success']);
        self::assertSame('METHOD_NOT_ALLOWED', $body['error']['code']);
    }

    // ─────────────── K27: Request-ID ───────────────

    public function testHerYanitRequestIdBasligiTasir(): void
    {
        $app = AppBuilder::build(
            $this->config(),
            static fn (): PDO => new PDO('sqlite::memory:'),
            new NullLogger(),
        );

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/health'));

        // ULID: 26 karakter, Crockford Base32 (I, L, O, U yok) — activity_log.request_id CHAR(26).
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $response->getHeaderLine('X-Request-Id'));
    }

    public function testIstemcininGonderdigiRequestIdKullanilmaz(): void
    {
        $app = AppBuilder::build(
            $this->config(),
            static fn (): PDO => new PDO('sqlite::memory:'),
            new NullLogger(),
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/health')
            ->withHeader('X-Request-Id', 'SALDIRGANIN-UYDURDUGU-ID');
        $response = $app->handle($request);

        self::assertNotSame('SALDIRGANIN-UYDURDUGU-ID', $response->getHeaderLine('X-Request-Id'));
    }

    public function testHataYanitlariDaGuvenlikBasliklariniVeRequestIdAlir(): void
    {
        $app = AppBuilder::build(
            $this->config(),
            static fn (): PDO => new PDO('sqlite::memory:'),
            new NullLogger(),
        );

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/olmayan-uc'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertNotSame('', $response->getHeaderLine('X-Request-Id'));
    }
}
