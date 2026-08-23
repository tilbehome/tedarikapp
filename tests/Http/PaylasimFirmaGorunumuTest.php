<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * FİRMA GÖRÜNÜMÜ vs SAHİP GÖRÜNÜMÜ (İE#21 B8-4).
 *
 * Aynı adres iki farklı insana açılır: listeyi hazırlayan (panelde oturumu var) ve
 * firma (yalnız erişim anahtarı var). Sayfa ikisine de aynı VERİYİ gösterir ama
 * aynı EYLEMLERİ sunamaz.
 *
 * PAYLAŞ menüsü firmaya çıkmamalıdır. Gerekçe güvenliktir, estetik değil: erişim
 * anahtarı kapısı (K62) sayfayı "linki bilen herkes" olmaktan çıkarır; firmaya
 * "WhatsApp'ta paylaş" düğmesi vermek, o kapıyı ürünün kendi eliyle açmasıdır.
 */
final class PaylasimFirmaGorunumuTest extends AuthTestCase
{
    private string $csrf = '';
    private string $token = '';
    private int $listId = 0;
    /** @var array<string, string> */
    private array $kapiCerezi = [];

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listId = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Firma görünümü sınavı',
            'period' => 'Eylül 2026',
        ]))['data']['id'];

        $this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'Termos',
            'name_original' => '双层保温杯500ml',
            'qty' => 10,
            'price_yuan' => '12.00',
        ]);

        $paylasim = (string) $this->json(
            $this->write('POST', '/api/lists/' . $this->listId . '/share'),
        )['data']['share_url'];
        $this->token = substr($paylasim, strrpos($paylasim, '/') + 1);
        $this->kapiCerezi = $this->paylasimCerezi($this->token, $this->listId, $this->csrf);
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function sayfa(): string
    {
        $response = $this->call('GET', '/liste/' . $this->token, null, [], $this->kapiCerezi);
        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    public function testSahipGorunumundePaylasMenusuVAR(): void
    {
        $html = $this->sayfa();

        self::assertStringContainsString('data-paylas-menu', $html);
        self::assertStringContainsString('data-kanal="whatsapp"', $html);
    }

    public function testFirmaGorunumundePaylasMenusuYOK(): void
    {
        $this->write('POST', '/api/auth/logout');

        $html = $this->sayfa();

        self::assertStringNotContainsString('data-paylas-menu', $html, 'Firmaya paylaş menüsü çıkmamalı');
        self::assertStringNotContainsString('data-kanal=', $html);
        self::assertStringNotContainsString('data-kopyala', $html);
    }

    public function testFirmaGorunumundeCIKTILARCALISMAYADEVAMEDER(): void
    {
        // Kaldırılan yalnız PAYLAŞMA eylemidir. Firma belgeyi indirebilmelidir —
        // zaten kendisi için hazırlanmıştır (İE#15 A1: bağlantılar imzalı).
        $this->write('POST', '/api/auth/logout');

        $html = $this->sayfa();

        self::assertStringContainsString('data-yazdir', $html);
        self::assertStringContainsString('data-indir="Excel"', $html);
        self::assertStringContainsString('data-indir="PDF"', $html);
    }

    public function testFirmaGorunumundeVeriAYNIDIR(): void
    {
        // Görünüm ayrımı bir VERİ ayrımı değildir: firma listenin tamamını görür.
        $sahip = $this->sayfa();
        $this->write('POST', '/api/auth/logout');
        $firma = $this->sayfa();

        foreach (['Termos', 'class="kpis"', 'class="tot"'] as $parca) {
            self::assertStringContainsString($parca, $sahip);
            self::assertStringContainsString($parca, $firma);
        }
    }
}
