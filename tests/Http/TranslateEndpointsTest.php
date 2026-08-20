<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Translation\TranslationClient;
use Tests\Support\AuthTestCase;

/**
 * Çeviri önerisi uçları (İE#13 C4 — docs/10).
 *
 * İki yüzey aynı gövdeyi paylaşır: panel (oturum + CSRF) ve eklenti (Bearer).
 * Kimliksiz erişim YOK; öneri yoksa uç 200 + `suggestion: null` döner (akış bloklanmaz).
 */
final class TranslateEndpointsTest extends AuthTestCase
{
    private string $csrf = '';
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->translationClient = new class () implements TranslationClient {
            public function translate(string $text, string $sourceLang, string $targetLang): ?string
            {
                return $text === '便携式榨汁机' ? 'Taşınabilir meyve sıkacağı' : null;
            }

            public function name(): string
            {
                return 'sahte';
            }
        };

        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
        $this->token = (string) $this->json(
            $this->call('POST', '/api/settings/extension-token', [], [Csrf::HEADER => $this->csrf]),
        )['data']['token'];
    }

    public function testPanelUcuOneriDoner(): void
    {
        $response = $this->call('POST', '/api/panel/translate-suggest', ['text' => '便携式榨汁机'], [Csrf::HEADER => $this->csrf]);

        self::assertSame(200, $response->getStatusCode());
        $data = $this->json($response)['data'];
        self::assertSame('Taşınabilir meyve sıkacağı', $data['suggestion']);
        self::assertTrue($data['is_suggestion'], 'K54: yanıt öneri olduğunu açıkça söylemeli.');
        self::assertFalse($data['cached']);
    }

    public function testIkinciCagriONBELLEKTEN_doner(): void
    {
        $this->call('POST', '/api/panel/translate-suggest', ['text' => '便携式榨汁机'], [Csrf::HEADER => $this->csrf]);
        $data = $this->json(
            $this->call('POST', '/api/panel/translate-suggest', ['text' => '便携式榨汁机'], [Csrf::HEADER => $this->csrf]),
        )['data'];

        self::assertTrue($data['cached']);
    }

    public function testEklentiUcuBearerIleCalisir(): void
    {
        $response = $this->call('POST', '/api/extension/translate-suggest', ['text' => '便携式榨汁机'], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Taşınabilir meyve sıkacağı', $this->json($response)['data']['suggestion']);
    }

    public function testTokensizEklentiCagrisi401(): void
    {
        self::assertSame(
            401,
            $this->call('POST', '/api/extension/translate-suggest', ['text' => '便携式榨汁机'])->getStatusCode(),
        );
    }

    public function testCevrilemeyenMetinde200_ve_null_doner(): void
    {
        $response = $this->call('POST', '/api/panel/translate-suggest', ['text' => 'çevrilemez'], [Csrf::HEADER => $this->csrf]);

        self::assertSame(200, $response->getStatusCode(), 'Öneri yokluğu HATA DEĞİLDİR — akış bloklanmaz.');
        self::assertNull($this->json($response)['data']['suggestion']);
    }

    public function testBosVeCokUzunMetin422(): void
    {
        self::assertSame(
            422,
            $this->call('POST', '/api/panel/translate-suggest', ['text' => '  '], [Csrf::HEADER => $this->csrf])->getStatusCode(),
        );
        self::assertSame(
            422,
            $this->call('POST', '/api/panel/translate-suggest', ['text' => str_repeat('字', 501)], [Csrf::HEADER => $this->csrf])
                ->getStatusCode(),
        );
    }

    public function testCsrfsizPanelCagrisiReddedilir(): void
    {
        self::assertSame(
            403,
            $this->call('POST', '/api/panel/translate-suggest', ['text' => '便携式榨汁机'])->getStatusCode(),
        );
    }
}
