<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Services\LoginStats;
use Tests\Support\AuthTestCase;

/**
 * Giriş ekranı vitrini (İE#13 EK-B).
 *
 * KRİTİK kurallar: girişsiz bir API ucu AÇILMAZ (değerler panel HTML'ine gömülür),
 * rakamlar yuvarlanmış gösterilir, para bcmath ile hesaplanır (K14) ve veritabanı
 * düşse bile giriş ekranı yine açılır.
 */
final class LoginVitrinTest extends AuthTestCase
{
    public function testVitrinUrunSayisiVeHacmiHESAPLAR(): void
    {
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '5.00'], ['X-CSRF-Token' => $csrf]);
        $listId = (int) $this->json(
            $this->call('POST', '/api/lists', ['name' => 'Vitrin listesi'], ['X-CSRF-Token' => $csrf]),
        )['data']['id'];
        $this->call('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Vitrin ürünü',
            'qty' => 100,
            'price_yuan' => '10.00',
        ], ['X-CSRF-Token' => $csrf]);

        $ozet = (new LoginStats($this->connection))->summary();

        self::assertSame('1', $ozet['products']);
        // 100 × ¥10 × 5,00 = ₺5.000 (bcmath; float yok)
        self::assertSame('₺5.000', $ozet['volume']);
    }

    public function testUrunYokkenSifirDoner(): void
    {
        $ozet = (new LoginStats($this->connection))->summary();

        self::assertSame('0', $ozet['products']);
        self::assertSame('₺0', $ozet['volume']);
    }

    public function testIkiAdimliDogrulamaDurumuKullanicidanOkunur(): void
    {
        $stats = new LoginStats($this->connection);
        self::assertFalse($stats->twoFactorEnabled(), 'Kullanıcı yokken kart gösterilmemeli.');

        $this->createUser();

        self::assertTrue($stats->twoFactorEnabled());
    }

    /** Panel HTML'i vitrin meta etiketini taşır — GİRİŞSİZ UÇ AÇILMAZ (PM şartı). */
    public function testPanelHtmlMetaEtiketiTasir(): void
    {
        $response = $this->call('GET', '/panel');
        $html = (string) $response->getBody();

        // Panel derlenmemişse 503 döner; o durumda meta beklenmez (CI'da derleme var).
        if ($response->getStatusCode() !== 200) {
            self::markTestSkipped('Panel derlenmemiş — public/panel/index.html yok.');
        }

        self::assertStringContainsString('name="tedarikapp-giris"', $html);
        self::assertStringContainsString('&quot;products&quot;', $html, 'Meta içeriği kaçırılmış olmalı (K20).');
        self::assertStringNotContainsString('<script>window.', $html, 'K45: satır içi script eklenmemeli.');
    }
}
