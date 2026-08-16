<?php

declare(strict_types=1);

namespace Tests\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AuthTestCase;

/**
 * docs/10 §1 · İE#5 §8: API yalnızca JSON konuşur.
 * Gövdeli yazma isteği JSON değilse 415 `UNSUPPORTED_MEDIA_TYPE` ile reddedilir.
 */
final class JsonRequestTest extends AuthTestCase
{
    /** Gerçek gövdeli istek üretir (withParsedBody akışı boş bırakır, burada gövde şart). */
    private function callWithBody(string $method, string $path, string $body, ?string $contentType): ResponseInterface
    {
        $request = $this->rawRequest($method, $path)
            ->withBody((new StreamFactory())->createStream($body));

        if ($contentType !== null) {
            $request = $request->withHeader('Content-Type', $contentType);
        }

        return $this->app()->handle($request);
    }

    public function testFormKodlamaliGovdeliPost415Doner(): void
    {
        $response = $this->callWithBody(
            'POST',
            '/api/auth/login',
            'email=admin@tedarikapp.test&password=cok-gizli-sifre',
            'application/x-www-form-urlencoded',
        );
        $body = $this->json($response);

        self::assertSame(415, $response->getStatusCode());
        self::assertFalse($body['success']);
        self::assertSame('UNSUPPORTED_MEDIA_TYPE', $body['error']['code']);
    }

    public function testIcerikTipsizGovdeliPost415Doner(): void
    {
        $response = $this->callWithBody('POST', '/api/auth/login', '{"email":"a@b.co"}', null);

        self::assertSame(415, $response->getStatusCode());
        self::assertSame('UNSUPPORTED_MEDIA_TYPE', $this->json($response)['error']['code']);
    }

    public function testDuzMetinGovdeliPatch415Doner(): void
    {
        $response = $this->callWithBody('PATCH', '/api/auth/me', 'deneme', 'text/plain');

        self::assertSame(415, $response->getStatusCode());
    }

    public function testJsonIcerikTipiKabulEdilir(): void
    {
        // 415'i geçer; sonrasında normal doğrulama akışı işler (422 doğrulama hatası).
        $response = $this->callWithBody('POST', '/api/auth/login', '{}', 'application/json');

        self::assertNotSame(415, $response->getStatusCode());
        self::assertSame(422, $response->getStatusCode());
    }

    public function testCharsetliJsonIcerikTipiKabulEdilir(): void
    {
        $response = $this->callWithBody('POST', '/api/auth/login', '{}', 'application/json; charset=utf-8');

        self::assertNotSame(415, $response->getStatusCode());
    }

    public function testBuyukHarfliIcerikTipiKabulEdilir(): void
    {
        $response = $this->callWithBody('POST', '/api/auth/login', '{}', 'Application/JSON');

        self::assertNotSame(415, $response->getStatusCode());
    }

    public function testGovdesizYazmaIstegiIcerikTipiIstemez(): void
    {
        // Gövdesiz POST (örn. logout) içerik tipi bildirmek zorunda değil — 415 dönmemeli.
        $response = $this->app()->handle($this->rawRequest('POST', '/api/auth/logout'));

        self::assertNotSame(415, $response->getStatusCode());
        self::assertSame(401, $response->getStatusCode(), 'Oturum yok: kimlik doğrulama hatası beklenir.');
    }

    public function testOkumaIstekleriEtkilenmez(): void
    {
        $response = $this->app()->handle($this->rawRequest('GET', '/api/health'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function test415YanitiGuvenlikBasliklariniVeRequestIdAlir(): void
    {
        $response = $this->callWithBody('POST', '/api/auth/login', 'x=1', 'text/plain');

        self::assertSame(415, $response->getStatusCode());
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertNotSame('', $response->getHeaderLine('X-Request-Id'));
    }
}
