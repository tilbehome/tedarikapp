<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * K49 — POST /api/system/migrate-baseline HTTP katmanı (İE#9.8).
 *
 * Çekirdek eşitleme kuralları MigratorBaselineTest'te; burada uç sözleşmesi sınanır:
 * Auth + CSRF zorunlu, yanıt şekli {recorded, skipped, pending_count}, idempotens ve
 * activity_log izi. Test şeması gerçek MySQL şemasının birebir kopyası olmadığı için
 * kayıt SAYISI değil davranış doğrulanır.
 */
final class MigrateBaselineEndpointTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
    }

    private function write(string $path): ResponseInterface
    {
        return $this->call('POST', $path, [], [Csrf::HEADER => $this->csrf]);
    }

    public function testOturumIster(): void
    {
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(401, $this->call('POST', '/api/system/migrate-baseline')->getStatusCode());
    }

    public function testCsrfIster(): void
    {
        self::assertSame(403, $this->call('POST', '/api/system/migrate-baseline')->getStatusCode());
    }

    public function testEsitlerVeAktiviteyeYazar(): void
    {
        $payload = $this->json($this->write('/api/system/migrate-baseline'));

        self::assertTrue($payload['success']);
        self::assertArrayHasKey('recorded', $payload['data']);
        self::assertArrayHasKey('skipped', $payload['data']);
        self::assertArrayHasKey('pending_count', $payload['data']);
        // Test şemasında ana tablolar (users, settings, lists, products…) var — en az
        // bir kayıt deftere işlenebilmiş olmalı; hiçbiri işlenemiyorsa harita bozuk demektir.
        self::assertNotEmpty($payload['data']['recorded'], 'Var olan tablolara ait kayıtlar deftere işlenmeliydi.');

        $activity = $this->json($this->call('GET', '/api/activity?entity_type=system'))['data'];
        self::assertContains('migrate_baseline', array_column($activity, 'action'));
    }

    public function testIdempotent_IkinciCagriYeniKayitIslemez(): void
    {
        $first = $this->json($this->write('/api/system/migrate-baseline'))['data'];
        $second = $this->json($this->write('/api/system/migrate-baseline'))['data'];

        self::assertNotEmpty($first['recorded']);
        self::assertSame([], $second['recorded'], 'İkinci çağrı deftere YENİ kayıt işlememeli.');
        self::assertSame($first['pending_count'], $second['pending_count']);
    }
}
