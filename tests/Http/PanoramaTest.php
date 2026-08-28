<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Services\Panorama\PanoramaServisi;
use Tests\Support\AuthTestCase;

/**
 * V3-B BLOK B — PANORAMA UCU.
 *
 * Sınanan üç şey emrin üç şartıdır:
 *   1. Koşullar SUNUCUDA değerlendirilir — yanıt hazır CÜMLE taşır, ham metrik
 *      ve koşul ifadesi TAŞIMAZ. Ham metrik dönseydi panel onu yorumlar ve
 *      aynı gerçeğin ikinci yolu açılırdı.
 *   2. Ölçülemeyen brifingler AYRI listede ve gerekçeli döner — "koşul
 *      sağlanmadı" ile karıştırılmaz.
 *   3. Hiç brifing yoksa boş gün cümlesi gelir ve AYNI GÜN İÇİNDE DEĞİŞMEZ.
 */
final class PanoramaTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
    }

    /** @return array<string, mixed> */
    private function panorama(): array
    {
        $yanit = $this->call('GET', '/api/panorama');
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());

        /** @var array{data: array<string, mixed>} $govde */
        $govde = json_decode((string) $yanit->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $govde['data'];
    }

    public function testBOSSISTEMDEBOSGUNCUMLESIDONER(): void
    {
        $veri = $this->panorama();

        self::assertSame([], $veri['brifingler'], 'Temiz sistemde brifing olmamalı.');
        self::assertIsString($veri['bos_gun']);
        self::assertNotSame('', $veri['bos_gun']);
    }

    public function testBOSGUNCUMLESIAYNIGUNDEDEGISMEZ(): void
    {
        // Belirlenimcilik: kullanıcı sayfayı yenilediğinde sistemin ne dediği
        // değişmemeli. Rastgele seçim, panelin tutarsız göründüğü bir tuzaktır.
        self::assertSame($this->panorama()['bos_gun'], $this->panorama()['bos_gun']);
    }

    public function testOLCULEMEYENBRIFINGLERGEREKCELIDONER(): void
    {
        $veri = $this->panorama();

        /** @var list<array{id: string, sebep: string}> $olculmeyen */
        $olculmeyen = $veri['olculmeyen'];
        self::assertCount(count(PanoramaServisi::OLCULEMEYEN), $olculmeyen);

        foreach ($olculmeyen as $satir) {
            self::assertNotSame('', trim($satir['sebep']), $satir['id'] . ': gerekçesiz "ölçülmüyor" satırı.');
        }
    }

    public function testOLCULEBILIRVEOLCULEMEYENCAKISMAZ(): void
    {
        $cakisan = array_intersect(PanoramaServisi::OLCULEBILIR, array_keys(PanoramaServisi::OLCULEMEYEN));

        self::assertSame([], array_values($cakisan), 'Bir brifing hem ölçülebilir hem ölçülemez olamaz.');
    }

    public function testKATALOGDAKIHERBRIFINGSINIFLANDIRILMIS(): void
    {
        $ham = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/v3/hazirlik/v3-b/panorama-brifing-katalogu.json');
        /** @var array{brifing_sablonlari: list<array{id: string}>} $katalog */
        $katalog = json_decode($ham, true, 512, JSON_THROW_ON_ERROR);
        $kodlar = array_map(static fn (array $b): string => $b['id'], $katalog['brifing_sablonlari']);

        $siniflanan = array_merge(PanoramaServisi::OLCULEBILIR, array_keys(PanoramaServisi::OLCULEMEYEN));
        $unutulan = array_values(array_diff($kodlar, $siniflanan));

        self::assertSame(
            [],
            $unutulan,
            "Bu brifingler ne ölçülebilir ne ölçülemez listesinde — sınıflandırılmamış brifing, "
            . "ekranda SESSİZCE kaybolan brifingtir:\n  " . implode("\n  ", $unutulan),
        );
    }

    public function testGELENKUTUSUBEKLEYENIBRIFINGURETIR(): void
    {
        $this->pdo->exec(
            "INSERT INTO inbox_items (capture_id, status, platform, payload_json, created_at)
             VALUES ('11111111-1111-4111-8111-111111111111', 'pending', '1688', '{}', '2026-08-28 10:00:00')",
        );

        $veri = $this->panorama();
        $idler = array_column($veri['brifingler'], 'id');

        self::assertContains('BRF-009', $idler);
        self::assertNull($veri['bos_gun'], 'Brifing varken boş gün cümlesi BASILMAZ.');
    }

    public function testCUMLEDEYERTUTUCUKALMAZ(): void
    {
        $this->pdo->exec(
            "INSERT INTO inbox_items (capture_id, status, platform, payload_json, created_at)
             VALUES ('22222222-2222-4222-8222-222222222222', 'pending', '1688', '{}', '2026-08-28 10:00:00')",
        );

        foreach ($this->panorama()['brifingler'] as $brifing) {
            self::assertStringNotContainsString(
                '{',
                (string) $brifing['cumle'],
                $brifing['id'] . ': doldurulmamış yer tutucu kullanıcıya gösterilemez.',
            );
        }
    }

    public function testYANITHAMMETRIKVEKOSULIFADESITASIMAZ(): void
    {
        // B1 şartı: koşul yorumu SUNUCUDA. Ham sayı ya da ifade dönerse panel
        // onu yeniden yorumlamaya başlar; iki gerçek kaynak doğar.
        $veri = $this->panorama();

        self::assertArrayNotHasKey('olcumler', $veri);
        foreach ($veri['brifingler'] as $brifing) {
            self::assertArrayNotHasKey('kosul', $brifing, 'Koşul ifadesi panele gönderilmez.');
            self::assertArrayHasKey('cumle', $brifing);
        }
    }

    public function testOLUISBRIFINGIENYUKSEKONCELIKTE(): void
    {
        $this->pdo->exec(
            "INSERT INTO jobs (tur, anahtar, yuk, durum, oncelik, deneme, max_deneme, calisacak_at, created_at, updated_at)
             VALUES ('ceviri', 'u-1', '{}', 'olu', 100, 3, 3, '2026-08-28 09:00:00', '2026-08-28 09:00:00', '2026-08-28 09:00:00')",
        );
        $this->pdo->exec(
            "INSERT INTO inbox_items (capture_id, status, platform, payload_json, created_at)
             VALUES ('33333333-3333-4333-8333-333333333333', 'pending', '1688', '{}', '2026-08-28 10:00:00')",
        );

        $brifingler = $this->panorama()['brifingler'];

        self::assertNotEmpty($brifingler);
        // BRF-011 (ölü iş) öncelik 1, BRF-009 (gelen kutusu) öncelik 3.
        self::assertSame('BRF-011', $brifingler[0]['id'], 'En acil brifing başa gelmeli.');
    }
}
