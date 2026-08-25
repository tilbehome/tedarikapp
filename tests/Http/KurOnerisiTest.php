<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * KUR AKIŞI — GETİR ≠ KAYDET (E2E-PNL-49 · K4/K48).
 *
 * Kur TİCARİ BİR KARARDIR: arka planda kendiliğinden değişmez. Uç sözleşmesi bu
 * kararı taşır — `GET /settings/rates/suggest` yalnız ÖNERİR, kaydetme ayrı bir
 * istektir (`PUT /settings/rates`). Bu testler o sınırı sabitler.
 *
 * NOT: kaynak (TCMB) AĞ İSTEĞİ ister; testte ağ YOKTUR — uç, kaynak erişilemezse
 * hatayı GÖRÜNÜR biçimde döndürmeli, sessizce eski kurla devam etmemelidir. Test
 * her iki sonucu da kabul eder ama "sessizce kaydetme" ihtimalini kapatır.
 */
final class KurOnerisiTest extends AuthTestCase
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

    private function kayitliKurlar(): array
    {
        $veri = $this->json($this->call('GET', '/api/settings'))['data'];

        return [$veri['yuan_tl'], $veri['usd_tl']];
    }

    public function testONERI_UCU_KAYDETMEZ(): void
    {
        $oncesi = $this->kayitliKurlar();

        $yanit = $this->call('GET', '/api/settings/rates/suggest');

        // Ağ yoksa 422/502 döner; ağ varsa 200 + öneri. İkisinde de KAYIT DEĞİŞMEZ.
        self::assertContains($yanit->getStatusCode(), [200, 422, 502, 503]);
        self::assertSame($oncesi, $this->kayitliKurlar(), 'Öneri ucu kuru KAYDETMEMELİ (K4).');
    }

    public function testONERI_BASARILIYSA_KAYNAK_VE_TARIH_SOYLENIR(): void
    {
        $yanit = $this->call('GET', '/api/settings/rates/suggest');
        if ($yanit->getStatusCode() !== 200) {
            self::markTestSkipped('Kur kaynağına ağ erişimi yok; öneri gövdesi sınanamıyor.');
        }

        $veri = $this->json($yanit)['data'];

        // Kullanıcı "bu sayı nereden geldi?" sorusunu ekranda yanıtlayabilmeli.
        self::assertArrayHasKey('kaynak', $veri);
        self::assertArrayHasKey('tarih', $veri);
        self::assertNotSame('', trim((string) $veri['kaynak']));
    }

    public function testKAYDETME_AYRI_ISTEKTIR_VE_TARIHCEYE_YAZAR(): void
    {
        $yanit = $this->call('PUT', '/api/settings/rates', [
            'yuan_tl' => '7.1500',
            'usd_tl' => '48.0500',
        ], [Csrf::HEADER => $this->csrf]);

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        self::assertSame(['7.1500', '48.0500'], $this->kayitliKurlar());

        $gecmis = $this->json($this->call('GET', '/api/settings/rates/history'))['data'];
        self::assertNotSame([], $gecmis, 'Kur değişimi tarihçeye yazılmalı (K48).');
    }

    public function testAYNI_DEGERI_KAYDETMEK_TARIHCEYE_YAZMAZ(): void
    {
        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '7.1500', 'usd_tl' => '48.0500'], [Csrf::HEADER => $this->csrf]);
        $ilkGecmis = $this->json($this->call('GET', '/api/settings/rates/history'))['data'];

        $ikinci = $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '7.1500', 'usd_tl' => '48.0500'], [Csrf::HEADER => $this->csrf]);

        self::assertSame(200, $ikinci->getStatusCode());
        self::assertSame([], $this->json($ikinci)['data']['changes'], 'Değişmeyen kur "değişti" sayılmamalı.');
        self::assertCount(count($ilkGecmis), $this->json($this->call('GET', '/api/settings/rates/history'))['data']);
    }

    public function testGECERSIZ_KUR_REDDEDILIR(): void
    {
        $yanit = $this->call('PUT', '/api/settings/rates', [
            'yuan_tl' => '-1',
            'usd_tl' => 'abc',
        ], [Csrf::HEADER => $this->csrf]);

        self::assertSame(422, $yanit->getStatusCode());
    }
}
