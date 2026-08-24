<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * PAYLAŞIM SAYFASI VE DİL (E2E-PNL-39 · PNL-37/38 ÇELİŞKİ KAYDI).
 *
 * ÖNEMLİ BULGU (İE#21, 24 Ağu 2026): E2E kataloğundaki PNL-37 ("paylaşım sayfası
 * TR/EN/ZH komple tek dil") ve PNL-38 ("karışık dil sızıntısı KIRMIZI"),
 * ONAYLI ŞABLONLA ÇELİŞİYOR. Şablon v2 rev7 (İE#13 F, PM onaylı) tablo
 * başlıklarını KASITLI OLARAK ÜÇ DİLDE basar: `MİKTAR / 数量 / QUANTITY`. Amaç
 * Çinli tedarikçinin ve Türk alıcının AYNI belgeye bakabilmesidir; "tek dil"
 * kuralı bu tasarımı ortadan kaldırırdı.
 *
 * O yüzden bu dosya şablonun GERÇEK sözleşmesini sınar:
 *  · tablo başlıkları her dilde üç dilli kalır (şablon sözü),
 *  · `?lang=` GÖNDERİM metinlerini ve arayüz etiketlerini değiştirir,
 *  · PNL-39 (K55): ürünün orijinal Çince satırı üç dilde de AYNEN durur.
 *
 * PNL-37/38 kapsam defterinde "çelişki — PM kararı" olarak işaretlidir; kararı
 * PM verir, kod tek taraflı çözmez (CLAUDE.md §1).
 */
final class PaylasimDiliTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listId = 0;
    private string $token = '';

    /** Şablonun ÜÇ DİLLİ başlık imzası — her sayfada birlikte bulunur. */
    private const UC_DILLI_BASLIK = ['MİKTAR', '数量'];

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listId = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Mutfak Ürünleri',
            'period' => 'Eylül 2026',
        ]))['data']['id'];

        $this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'Termos Yemek Kabı',
            'name_original' => '双层不锈钢保温饭盒500ml',
            'qty' => 240,
            'price_yuan' => '12.00',
        ]);

        $url = (string) $this->json($this->write('POST', '/api/lists/' . $this->listId . '/share'))['data']['share_url'];
        $this->token = substr($url, strrpos($url, '/') + 1);
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function sayfa(string $dil): string
    {
        $yanit = $this->call(
            'GET',
            '/liste/' . $this->token . '?lang=' . $dil,
            null,
            [],
            $this->paylasimCerezi($this->token, $this->listId, $this->csrf),
        );
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());

        return (string) $yanit->getBody();
    }

    public function testSABLONBASLIKLARIUCDILDEKALIR(): void
    {
        // Şablon sözü: hangi dil seçilse de tablo başlığı üç dilli kalır. Bu,
        // PNL-37'nin "tek dil" beklentisiyle çelişen ONAYLI davranıştır.
        foreach (['tr', 'en', 'zh'] as $dil) {
            $html = $this->sayfa($dil);

            foreach (self::UC_DILLI_BASLIK as $imza) {
                self::assertStringContainsString($imza, $html, $dil . ' sayfasında üç dilli başlık korunmalı.');
            }
        }
    }

    public function testGONDERIMDILISECILENDILEUYAR(): void
    {
        // `?lang=` GÖNDERİM metnini (WhatsApp/e-posta) ve indirme bağlantısının
        // dilini belirler: sayfanın değişen yüzü budur.
        $en = $this->sayfa('en');
        self::assertStringContainsString('data-dil="en"', $en);
        self::assertStringContainsString('supply list', mb_strtolower($en));

        $zh = $this->sayfa('zh');
        self::assertStringContainsString('data-dil="zh"', $zh);
        self::assertStringContainsString('采购清单', $zh);
    }

    public function testE2E_PNL_39_ORIJINALSATIRUCDILDEAYNEN(): void
    {
        foreach (['tr', 'en', 'zh'] as $dil) {
            $html = $this->sayfa($dil);

            // K55: orijinal başlık ÜRÜN VERİSİDİR, arayüz metni değildir —
            // hiçbir dilde çevrilmez, kırpılmaz, gizlenmez.
            self::assertStringContainsString(
                '双层不锈钢保温饭盒500ml',
                $html,
                $dil . ' sayfasında orijinal Çince satır aynen durmalı.',
            );
        }
    }

    public function testGECERSIZDILTURKCEYEDUSER(): void
    {
        $html = $this->sayfa('de');

        self::assertStringContainsString('data-dil="tr"', $html);
        self::assertStringContainsString('MİKTAR', $html);
    }
}
