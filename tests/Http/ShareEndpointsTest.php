<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * İE#10 Blok 4 — paylaşım güvenlik modeli (K20/K34/K51).
 *
 * KRİTİK kurallar:
 *  • Tam token YALNIZ üretim yanıtında görünür; DB'de SHA-256 hash durur.
 *  • İptal/yenileme eski linki ANINDA öldürür.
 *  • Geçersiz token = sabit 404 (biçim hatası, bilinmeyen token, süresi dolmuş — ayrım sızmaz).
 *  • Enumeration hız sınırına takılır; sayfa noindex taşır; girişsiz erişilir.
 */
final class ShareEndpointsTest extends AuthTestCase
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

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    /** @return array{list: int, token: string} */
    private function seedShared(): array
    {
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Paylaşım listesi']))['data']['id'];
        $this->write('POST', '/api/lists/' . $listId . '/products', ['name' => 'Termos', 'qty' => 24, 'price_yuan' => '12.00']);
        $url = (string) $this->json($this->write('POST', '/api/lists/' . $listId . '/share'))['data']['share_url'];

        return ['list' => $listId, 'token' => substr($url, (int) strrpos($url, '/') + 1)];
    }

    public function testTokenUretilirVeDbDeYalnizHashDurur(): void
    {
        ['list' => $listId, 'token' => $token] = $this->seedShared();

        self::assertSame(64, strlen($token), 'Token 256-bit (64 hex) olmalı.');

        $row = $this->pdo->query('SELECT share_token_hash, share_token_prefix FROM lists WHERE id = ' . $listId)->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(hash('sha256', $token), $row['share_token_hash'], 'DB\'de HASH durmalı.');
        self::assertSame(substr($token, 0, 8), $row['share_token_prefix']);
        self::assertStringNotContainsString($token, json_encode($this->json($this->call('GET', '/api/lists/' . $listId))), 'Tam token liste nesnesinde görünMEMELİ.');
    }

    public function testPaylasimSayfasiGirissizAcilirVeNoindexTasir(): void
    {
        ['token' => $token] = $this->seedShared();
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        $response = $this->call('GET', '/p/' . $token);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('noindex', $response->getHeaderLine('X-Robots-Tag'));
        $html = (string) $response->getBody();
        self::assertStringContainsString('Paylaşım listesi', $html);
        self::assertStringContainsString('Termos', $html);
        // İE#13 F4: sayfa yeni düzende — birim TL hücresi "₺ 84.48" (ayraçlı) biçimindedir.
        self::assertStringContainsString('₺ 84.48', str_replace('&#8378;', '₺', $html));
    }

    public function testGecersizTokenSabit404(): void
    {
        $this->seedShared();

        // Biçimi bozuk, bilinmeyen ve süresi dolmuş: ÜÇÜ DE aynı 404 gövdesi.
        $bad = $this->call('GET', '/p/kisa-token');
        $unknown = $this->call('GET', '/p/' . str_repeat('a', 64));
        self::assertSame(404, $bad->getStatusCode());
        self::assertSame(404, $unknown->getStatusCode());
        self::assertSame((string) $bad->getBody(), (string) $unknown->getBody(), 'Sabit yanıt: ayrım sızdırılmaz.');
    }

    public function testIptalEdilenLinkAnindaOlur(): void
    {
        ['list' => $listId, 'token' => $token] = $this->seedShared();
        self::assertSame(200, $this->call('GET', '/p/' . $token)->getStatusCode());

        self::assertSame(204, $this->write('DELETE', '/api/lists/' . $listId . '/share')->getStatusCode());

        self::assertSame(404, $this->call('GET', '/p/' . $token)->getStatusCode(), 'İptal edilen link ölmeli.');
    }

    public function testYenilemeEskiLinkiOldururYenisiCalisir(): void
    {
        ['list' => $listId, 'token' => $old] = $this->seedShared();

        $newUrl = (string) $this->json($this->write('POST', '/api/lists/' . $listId . '/share'))['data']['share_url'];
        $new = substr($newUrl, (int) strrpos($newUrl, '/') + 1);

        self::assertSame(404, $this->call('GET', '/p/' . $old)->getStatusCode());
        self::assertSame(200, $this->call('GET', '/p/' . $new)->getStatusCode());
    }

    public function testSuresiDolmusLink404(): void
    {
        ['list' => $listId] = $this->seedShared();
        $url = (string) $this->json($this->write('POST', '/api/lists/' . $listId . '/share', ['expires_at' => '2030-01-01']))['data']['share_url'];
        $token = substr($url, (int) strrpos($url, '/') + 1);
        self::assertSame(200, $this->call('GET', '/p/' . $token)->getStatusCode());

        // Süreyi geçmişe çek — sayfa artık sabit 404.
        $this->pdo->exec("UPDATE lists SET share_expires_at = '2020-01-01 00:00:00' WHERE id = " . $listId);
        self::assertSame(404, $this->call('GET', '/p/' . $token)->getStatusCode());
    }

    public function testEnumerationHizSiniri(): void
    {
        ['token' => $token] = $this->seedShared();

        // 30 geçersiz deneme sınırı doldurur; sonrasında GEÇERLİ token bile 404 (sabit yanıt).
        for ($i = 0; $i < 30; $i++) {
            $this->call('GET', '/p/' . hash('sha256', 'deneme-' . $i));
        }

        self::assertSame(404, $this->call('GET', '/p/' . $token)->getStatusCode(), 'Sınır aşımında IP bloklanmalı.');
    }

    public function testIptalUcuOturumVeCsrfIster(): void
    {
        ['list' => $listId] = $this->seedShared();

        self::assertSame(403, $this->call('DELETE', '/api/lists/' . $listId . '/share')->getStatusCode(), 'CSRF şart.');

        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);
        self::assertSame(401, $this->call('POST', '/api/lists/' . $listId . '/share')->getStatusCode(), 'Oturum şart.');
    }

    public function testXssKaynakliVeriEscapeEdilir(): void
    {
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'XSS <script>alert(1)</script> listesi']))['data']['id'];
        $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Ürün <img src=x onerror=alert(2)>', 'qty' => 1, 'price_yuan' => '1.00',
        ]);
        $url = (string) $this->json($this->write('POST', '/api/lists/' . $listId . '/share'))['data']['share_url'];
        $token = substr($url, (int) strrpos($url, '/') + 1);

        $html = (string) $this->call('GET', '/p/' . $token)->getBody();

        self::assertStringNotContainsString('<script>alert(1)', $html, 'Liste adı escape edilmeli.');
        self::assertStringNotContainsString('<img src=x onerror', $html, 'Ürün adındaki HAM etiket sayfaya sızmamalı.');
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror', $html, 'Escape edilmiş hali metin olarak görünmeli.');
    }
}
