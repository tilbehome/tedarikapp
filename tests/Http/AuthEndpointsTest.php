<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Auth\RememberTokenService;
use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\ArraySession;
use Tests\Support\AuthTestCase;

/**
 * docs/10 §2 sözleşme testleri — 7 auth ucunun her biri için en az bir senaryo (docs/10 §9).
 */
final class AuthEndpointsTest extends AuthTestCase
{
    private const string EMAIL = 'admin@tedarikapp.test';
    private const string PASSWORD = 'cok-gizli-sifre';

    /** @return array{id: int, secret: string, codes: list<string>} */
    private function girisYap(bool $remember = false): array
    {
        $user = $this->createUser(self::EMAIL, self::PASSWORD);
        $response = $this->call('POST', '/api/auth/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'remember' => $remember,
        ]);
        self::assertSame(200, $response->getStatusCode());

        return $user;
    }

    private function csrfToken(): string
    {
        $body = $this->json($this->call('GET', '/api/auth/me'));
        /** @var array{csrf_token: string} $data */
        $data = $body['data'];

        return $data['csrf_token'];
    }

    // ─────────────── POST /api/auth/login ───────────────

    public function testLoginEksikAlanlarla422DonerVeAlanBazliHataVerir(): void
    {
        $response = $this->call('POST', '/api/auth/login', []);
        $body = $this->json($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($body['success']);
        self::assertSame('VALIDATION', $body['error']['code']);
        self::assertArrayHasKey('email', $body['error']['fields']);
        self::assertArrayHasKey('password', $body['error']['fields']);
    }

    public function testLoginGecersizEpostaBicimiyle422Doner(): void
    {
        $response = $this->call('POST', '/api/auth/login', ['email' => 'eposta-degil', 'password' => 'bir-sifre-123']);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('email', $this->json($response)['error']['fields']);
    }

    public function testLoginYanlisSifreyle401Doner(): void
    {
        $this->createUser(self::EMAIL, self::PASSWORD);
        $response = $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => 'yanlis-sifre']);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('UNAUTHENTICATED', $this->json($response)['error']['code']);
    }

    public function testLoginOlmayanKullaniciylaAyniYanitiVerir(): void
    {
        // Kullanıcı sayımı (enumeration) sızmamalı: var olmayan e-posta da 401 döner.
        $response = $this->call('POST', '/api/auth/login', ['email' => 'yok@tedarikapp.test', 'password' => 'bir-sifre-123']);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('UNAUTHENTICATED', $this->json($response)['error']['code']);
    }

    public function testLoginBasariliOldugundaTotpAsamasiDoner(): void
    {
        $this->createUser(self::EMAIL, self::PASSWORD);
        $response = $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        $body = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($body['success']);
        self::assertSame(['stage' => 'totp'], $body['data']);
        self::assertNull($body['error']);
    }

    public function testTotpBeklerkenKorumaliUcTotpRequiredDoner(): void
    {
        $this->girisYap();
        $response = $this->call('GET', '/api/auth/me');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('TOTP_REQUIRED', $this->json($response)['error']['code']);
    }

    // ─────────────── POST /api/auth/totp ───────────────

    public function testTotpKodsuzIstek422Doner(): void
    {
        $this->girisYap();
        $response = $this->call('POST', '/api/auth/totp', []);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('code', $this->json($response)['error']['fields']);
    }

    public function testYanlisTotpKodu422Doner(): void
    {
        $this->girisYap();
        $response = $this->call('POST', '/api/auth/totp', ['code' => '000000']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('VALIDATION', $this->json($response)['error']['code']);
    }

    public function testGirisYapmadanTotpCagrisi401Doner(): void
    {
        $response = $this->call('POST', '/api/auth/totp', ['code' => '123456']);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('UNAUTHENTICATED', $this->json($response)['error']['code']);
    }

    public function testDogruTotpKoduOturumuAcarVeKullaniciDoner(): void
    {
        $user = $this->girisYap();
        $response = $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $body = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($user['id'], $body['data']['user']['id']);
        self::assertSame(self::EMAIL, $body['data']['user']['email']);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+03:00$/',
            $body['data']['user']['created_at'],
        );
    }

    public function testYanitlardaHashVeSecretSizmaz(): void
    {
        $user = $this->girisYap();
        $totpResponse = $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $meResponse = $this->call('GET', '/api/auth/me');

        foreach ([$totpResponse, $meResponse] as $response) {
            $raw = (string) $response->getBody();
            self::assertStringNotContainsString('password_hash', $raw);
            self::assertStringNotContainsString('totp_secret', $raw);
            self::assertStringNotContainsString('$argon2id$', $raw);
            self::assertStringNotContainsString($user['secret'], $raw);
        }

        self::assertSame(['id', 'email', 'created_at'], array_keys($this->json($meResponse)['data']['user']));
    }

    // ─────────────── POST /api/auth/recovery ───────────────

    public function testKurtarmaKoduOturumuAcarVeKalanSayiyiDoner(): void
    {
        $user = $this->girisYap();
        $response = $this->call('POST', '/api/auth/recovery', ['code' => $user['codes'][0]]);
        $body = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($user['id'], $body['data']['user']['id']);
        self::assertSame(9, $body['data']['remaining_codes']);
    }

    public function testKurtarmaKoduTekKullanimliktir(): void
    {
        $user = $this->girisYap();
        $kod = $user['codes'][0];

        self::assertSame(200, $this->call('POST', '/api/auth/recovery', ['code' => $kod])->getStatusCode());

        // Yeni bir oturumla aynı kodu tekrar kullanmayı dene.
        $this->session = new ArraySession();
        $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        $ikinci = $this->call('POST', '/api/auth/recovery', ['code' => $kod]);

        self::assertSame(422, $ikinci->getStatusCode());
        self::assertSame('VALIDATION', $this->json($ikinci)['error']['code']);
    }

    // ─────────────── GET /api/auth/me ───────────────

    public function testOturumsuzKorumaliUc401Unauthenticated(): void
    {
        $response = $this->call('GET', '/api/auth/me');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('UNAUTHENTICATED', $this->json($response)['error']['code']);
    }

    public function testMeKullaniciVeCsrfTokenDoner(): void
    {
        $user = $this->girisYap();
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);

        $body = $this->json($this->call('GET', '/api/auth/me'));

        self::assertSame(self::EMAIL, $body['data']['user']['email']);
        self::assertIsString($body['data']['csrf_token']);
        self::assertSame(64, strlen($body['data']['csrf_token']));
    }

    // ─────────────── CSRF ───────────────

    public function testCsrfTokensizYazmaIstegi403Doner(): void
    {
        $user = $this->girisYap();
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);

        $response = $this->call('POST', '/api/auth/logout', []);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('CSRF', $this->json($response)['error']['code']);
    }

    public function testYanlisCsrfTokeniyleYazmaIstegi403Doner(): void
    {
        $user = $this->girisYap();
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);

        $response = $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => str_repeat('0', 64)]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('CSRF', $this->json($response)['error']['code']);
    }

    // ─────────────── POST /api/auth/logout ───────────────

    public function testDogruCsrfTokeniyleCikis204DonerVeOturumDuser(): void
    {
        $user = $this->girisYap();
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);

        $response = $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrfToken()]);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame(401, $this->call('GET', '/api/auth/me')->getStatusCode());
    }

    public function testCikistaRememberTokenVeritabanindanSilinir(): void
    {
        $user = $this->girisYap(remember: true);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        self::assertSame(1, $this->rememberTokenSayisi());

        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrfToken()]);

        self::assertSame(0, $this->rememberTokenSayisi());
    }

    // ─────────────── Beni hatırla ───────────────

    public function testRememberIstenmedigindeCerezKurulmaz(): void
    {
        $user = $this->girisYap();
        $response = $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);

        self::assertNull($this->cookieValue($response, RememberTokenService::COOKIE_NAME));
        self::assertSame(0, $this->rememberTokenSayisi());
    }

    public function testRememberCerezindenSessizGirisYapilir(): void
    {
        $cerez = $this->rememberCerezi();

        // Tarayıcı oturumu kaybolur, yalnızca "beni hatırla" çerezi kalır.
        $this->session = new ArraySession();
        $response = $this->call('GET', '/api/auth/me', null, [], [RememberTokenService::COOKIE_NAME => $cerez]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::EMAIL, $this->json($response)['data']['user']['email']);
    }

    public function testCalintiRememberTokeniTumTokenlariSiler(): void
    {
        $cerez = $this->rememberCerezi();
        [$selector] = explode(':', $cerez, 2);

        $this->session = new ArraySession();
        $response = $this->call(
            'GET',
            '/api/auth/me',
            null,
            [],
            [RememberTokenService::COOKIE_NAME => $selector . ':' . str_repeat('0', 64)],
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('UNAUTHENTICATED', $this->json($response)['error']['code']);
        self::assertSame(0, $this->rememberTokenSayisi(), 'Çalıntı token işaretinde tüm token\'lar silinmeli.');

        // Çalınan çerez de artık işe yaramaz.
        $this->session = new ArraySession();
        self::assertSame(401, $this->call('GET', '/api/auth/me', null, [], [RememberTokenService::COOKIE_NAME => $cerez])->getStatusCode());
    }

    public function testSuresiDolmusRememberCereziKabulEdilmez(): void
    {
        $cerez = $this->rememberCerezi();

        $this->clock->advance('+31 days'); // REMEMBER_ME_LIFETIME = 43200 dakika = 30 gün
        $this->session = new ArraySession();

        self::assertSame(401, $this->call('GET', '/api/auth/me', null, [], [RememberTokenService::COOKIE_NAME => $cerez])->getStatusCode());
    }

    // ─────────────── Oturum listesi ───────────────

    public function testOturumListesiVeIptali(): void
    {
        $this->rememberCerezi();

        $liste = $this->json($this->call('GET', '/api/auth/sessions'));
        self::assertCount(1, $liste['data']);
        self::assertArrayHasKey('created_at', $liste['data'][0]);
        self::assertArrayHasKey('expires_at', $liste['data'][0]);
        self::assertTrue($liste['data'][0]['is_current']);

        $id = $liste['data'][0]['id'];
        $iptal = $this->call('DELETE', '/api/auth/sessions/' . $id, [], [Csrf::HEADER => $this->csrfToken()]);

        self::assertSame(204, $iptal->getStatusCode());
        self::assertCount(0, $this->json($this->call('GET', '/api/auth/sessions'))['data']);
    }

    public function testOlmayanOturumIptali404Doner(): void
    {
        $user = $this->girisYap();
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);

        $response = $this->call('DELETE', '/api/auth/sessions/9999', [], [Csrf::HEADER => $this->csrfToken()]);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('NOT_FOUND', $this->json($response)['error']['code']);
    }

    // ─────────────── Giriş kilidi ───────────────

    public function testArtArdaHataliGirisLockedDoner(): void
    {
        $this->createUser(self::EMAIL, self::PASSWORD);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => 'yanlis-sifre']);
            self::assertSame(401, $response->getStatusCode(), sprintf('%d. deneme henüz kilitlenmemeli.', $i + 1));
        }

        $kilitli = $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => 'yanlis-sifre']);
        $body = $this->json($kilitli);

        self::assertSame(403, $kilitli->getStatusCode());
        self::assertSame('LOCKED', $body['error']['code']);
        self::assertSame(15 * 60, $body['meta']['retry_after_seconds']);
    }

    public function testKilitliykenDogruSifreDeReddedilir(): void
    {
        $this->createUser(self::EMAIL, self::PASSWORD);
        for ($i = 0; $i < 5; $i++) {
            $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => 'yanlis-sifre']);
        }

        $response = $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('LOCKED', $this->json($response)['error']['code']);
    }

    public function testKilitSuresiDolduktanSonraGirisYapilabilir(): void
    {
        $this->createUser(self::EMAIL, self::PASSWORD);
        for ($i = 0; $i < 5; $i++) {
            $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => 'yanlis-sifre']);
        }

        $this->clock->advance('+16 minutes');
        $response = $this->call('POST', '/api/auth/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['stage' => 'totp'], $this->json($response)['data']);
    }

    public function testArtArdaYanlisTotpDaKilideIsler(): void
    {
        $this->girisYap();

        for ($i = 0; $i < 5; $i++) {
            self::assertSame(422, $this->call('POST', '/api/auth/totp', ['code' => '000000'])->getStatusCode());
        }

        $response = $this->call('POST', '/api/auth/totp', ['code' => '000000']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('LOCKED', $this->json($response)['error']['code']);
    }

    // ─────────────── Yardımcılar ───────────────

    /** Giriş + TOTP akışını "beni hatırla" ile tamamlar, kurulan çerezi döndürür. */
    private function rememberCerezi(): string
    {
        $user = $this->girisYap(remember: true);
        $response = $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);

        $cerez = $this->cookieValue($response, RememberTokenService::COOKIE_NAME);
        self::assertIsString($cerez);
        $this->cerezCookieBayraklariDogru($response);

        return $cerez;
    }

    private function cerezCookieBayraklariDogru(ResponseInterface $response): void
    {
        $header = '';
        foreach ($response->getHeader('Set-Cookie') as $candidate) {
            if (str_starts_with($candidate, RememberTokenService::COOKIE_NAME . '=')) {
                $header = $candidate;
            }
        }

        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Lax', $header);
        self::assertStringContainsString('Secure', $header, 'APP_URL https olduğunda Secure bayrağı zorunlu (K16).');
    }

    private function rememberTokenSayisi(): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) AS total FROM remember_tokens');
        $row = $statement === false ? [] : $statement->fetch();

        return is_array($row) ? (int) $row['total'] : 0;
    }
}
