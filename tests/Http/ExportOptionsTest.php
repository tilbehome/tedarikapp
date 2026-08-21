<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\AuthTestCase;

/**
 * Çıktı seçenekleri (İE#13 F2/F5/F7) — uçtan uca: istek → snapshot → dosya.
 *
 * KRİTİK kurallar: durum filtresi snapshot'a KAYDEDİLİR; firma kopyasında kâr
 * sütunları dosyada HİÇ YOKTUR; revizyon harfi her yeni çıktıda ilerler.
 */
final class ExportOptionsTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listId = (int) $this->json(
            $this->write('POST', '/api/lists', ['name' => 'Seçenek listesi', 'period' => 'Eylül 2026']),
        )['data']['id'];

        $this->urun('Verilecek ürün', 10, 'to_order');
        $this->urun('Sipariş edilen ürün', 20, 'ordered');
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function urun(string $ad, int $adet, string $status): int
    {
        $id = (int) $this->json($this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => $ad,
            'qty' => $adet,
            'price_yuan' => '10.00',
            'price_target_try' => '250.00',
        ]))['data']['id'];

        if ($status !== 'to_order') {
            $this->write('PATCH', '/api/products/' . $id . '/status', ['status' => $status]);
        }

        return $id;
    }

    /** @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet */
    private function xlsx(\Psr\Http\Message\ResponseInterface $response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $temp = tempnam(sys_get_temp_dir(), 'tdk') . '.xlsx';
        file_put_contents($temp, (string) $response->getBody());
        $sheet = IOFactory::load($temp)->getActiveSheet();
        @unlink($temp);

        return $sheet;
    }

    public function testDurumFiltresiYalnizSecilenleriBasarVeSnapshotaYazilir(): void
    {
        $response = $this->write('POST', '/api/lists/' . $this->listId . '/export?format=xlsx', [
            'statuses' => ['ordered'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $sheet = $this->xlsx($response);
        self::assertStringContainsString('Sipariş edilen ürün', (string) $sheet->getCell('D11')->getValue());
        self::assertSame('', (string) $sheet->getCell('D12')->getValue(), 'Filtre dışı ürün basılmamalı.');

        $snapshot = json_decode(
            (string) $this->pdo->query('SELECT snapshot_json FROM exports ORDER BY id DESC LIMIT 1')->fetchColumn(),
            true,
        );
        self::assertSame(['ordered'], $snapshot['options']['statuses']);
    }

    public function testGecersizDurumKoduSessizceElenir(): void
    {
        $response = $this->write('POST', '/api/lists/' . $this->listId . '/export?format=xlsx', [
            'statuses' => ['uydurma_durum'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        // Geçerli kod kalmadı → filtre uygulanmaz, iki ürün de basılır.
        $sheet = $this->xlsx($response);
        self::assertNotSame('', (string) $sheet->getCell('D12')->getValue());
    }

    public function testFirmaKopyasiVarsayilandirVeKarSutunuICERMEZ(): void
    {
        $sheet = $this->xlsx($this->write('POST', '/api/lists/' . $this->listId . '/export?format=xlsx'));

        self::assertSame('O', $sheet->getHighestColumn());
        self::assertSame('DDP ₺', (string) $sheet->getCell('O8')->getValue());
    }

    public function testIcKopyaKarSutunlariniEkler(): void
    {
        $sheet = $this->xlsx($this->write('POST', '/api/lists/' . $this->listId . '/export?format=xlsx', ['copy' => 'ic']));

        self::assertSame('R', $sheet->getHighestColumn());
        self::assertSame('Hedef Satış (₺)', (string) $sheet->getCell('P8')->getValue());
        // 250,00 hedef − (10,00 ¥ × kur) maliyet: kâr HESAPLANMIŞ gelir, boş değil.
        self::assertIsFloat($sheet->getCell('Q11')->getValue());
    }

    /**
     * İE#14 B1 — REVİZYON İÇERİKLE İLERLER, indirmeyle DEĞİL.
     *
     * ESKİ (yanlış) davranış bu testin kendisiyle sabitlenmişti: her çıktı harfi
     * bir ilerletiyordu; aynı listeden Excel ve PDF almak "Rev D / Rev E" üretiyor,
     * firma iki farklı revizyon sanıyordu. Yeni kural: harf `lists.revision`e bağlı.
     */
    public function testRevizyonHarfiICERIKdegisinceIlerler(): void
    {
        $ilk = $this->xlsx($this->write('POST', '/api/lists/' . $this->listId . '/export?format=xlsx'));
        self::assertStringEndsWith('Rev A', (string) $ilk->getCell('I3')->getValue());

        // Aynı içerikten ikinci indirme: harf DEĞİŞMEZ, "geçersiz kılar" notu da çıkmaz.
        $ikinci = $this->xlsx($this->write('POST', '/api/lists/' . $this->listId . '/export?format=xlsx'));
        self::assertStringEndsWith('Rev A', (string) $ikinci->getCell('I3')->getValue());

        // İçerik değişti (ürün durumu ilerledi) → sıradaki harf ve "geçersiz kılar" notu.
        // Satır SAYISI bilerek değiştirilmiyor: belge yerleşimi (B16) sabit kalsın.
        $this->write('PATCH', '/api/lists/' . $this->listId, ['yuan_rate' => '4.9000']);
        $ucuncu = $this->xlsx($this->write('POST', '/api/lists/' . $this->listId . '/export?format=xlsx'));
        self::assertStringEndsWith('Rev B', (string) $ucuncu->getCell('I3')->getValue());
        self::assertStringContainsString('GEÇERSİZ KILAR', (string) $ucuncu->getCell('B16')->getValue());
    }

    public function testBelgeAntediCiktiyaGirer_bosAlanBASILMAZ(): void
    {
        $this->write('PUT', '/api/settings/document-header', [
            'company' => 'Tilbe Home',
            'web' => 'tilbehome.com',
            'email' => '',
            'prepared_by' => 'Bünyamin TİLBE',
        ]);

        $sheet = $this->xlsx($this->write('POST', '/api/lists/' . $this->listId . '/export?format=xlsx'));
        $antet = (string) $sheet->getCell('D3')->getValue();

        self::assertStringContainsString('Tilbe Home · tilbehome.com', $antet);
        self::assertStringNotContainsString('··', $antet, 'Boş alan ayraç bırakmamalı.');
        self::assertStringContainsString('Hazırlayan: Bünyamin TİLBE', (string) $sheet->getCell('J16')->getValue());
    }

    public function testGecersizEpostaAntetiReddedilir(): void
    {
        $response = $this->write('PUT', '/api/settings/document-header', ['email' => 'bu-eposta-degil']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPdfUretilirVeGecerliBaslikTasir(): void
    {
        $response = $this->write('POST', '/api/lists/' . $this->listId . '/export?format=pdf');

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringStartsWith('%PDF', $body);
        self::assertGreaterThan(2000, strlen($body));
    }
}
