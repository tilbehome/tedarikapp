<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Response;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response as Psr7Response;

final class ResponseTest extends TestCase
{
    public function testBasariZarfiDocs10BicimindeUretilir(): void
    {
        $response = Response::success(new Psr7Response(), ['app' => 'tedarikapp'], ['page' => 1]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        self::assertSame(
            ['success' => true, 'data' => ['app' => 'tedarikapp'], 'error' => null, 'meta' => ['page' => 1]],
            $body,
        );
    }

    public function testBosMetaJsonNesnesiOlarakYazilir(): void
    {
        $response = Response::success(new Psr7Response(), []);

        // meta boşken [] değil {} yazılmalı — sözleşme nesne bekler (docs/10 §1).
        self::assertStringContainsString('"meta":{}', (string) $response->getBody());
    }

    public function testHataZarfiKodMesajVeAlanlariTasir(): void
    {
        $response = Response::error(
            new Psr7Response(),
            'VALIDATION',
            'Doğrulama hatası',
            422,
            ['qty' => '1–1.000.000 arası tam sayı olmalı'],
        );

        self::assertSame(422, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        self::assertFalse($body['success']);
        self::assertNull($body['data']);
        self::assertSame('VALIDATION', $body['error']['code']);
        self::assertSame('Doğrulama hatası', $body['error']['message']);
        self::assertArrayHasKey('qty', $body['error']['fields']);
    }

    public function testAlanlarBosSaFieldsAnahtariEklenmez(): void
    {
        $response = Response::error(new Psr7Response(), 'NOT_FOUND', 'İstenen kaynak bulunamadı.', 404);

        $body = json_decode((string) $response->getBody(), true);
        self::assertArrayNotHasKey('fields', $body['error']);
    }
}
