<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * İE#10 Blok 1-3 — export motoru (K25/K50): snapshot + akış + geçmiş + bayatlık.
 *
 * KRİTİK kurallar:
 *  • Export ANLIK GÖRÜNTÜDÜR: üretimden sonra liste değişse bile geçmişten indirme
 *    AYNI içeriği verir (snapshot'tan yeniden üretim).
 *  • Dosya diske yazılmaz — yanıt attachment akışıdır, kayıt yalnız snapshot+sha256 tutar.
 *  • K4/K48: snapshot'taki TL değerleri üretim ANINDAKİ etkin kurla hesaplanmıştır.
 */
final class ExportEndpointsTest extends AuthTestCase
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

    /** @return array{list: int} */
    private function seedList(): array
    {
        $listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Export listesi', 'period' => '2026 eylül']))['data']['id'];
        $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Termos', 'qty' => 24, 'price_yuan' => '12.00',
        ]);
        $this->write('POST', '/api/lists/' . $listId . '/products', [
            'name' => 'Hoparlör standı', 'qty' => 20, 'price_yuan' => '18.00',
        ]);

        return ['list' => $listId];
    }

    public function testCsvUretilirKaydedilirVeAkitilir(): void
    {
        ['list' => $listId] = $this->seedList();

        $response = $this->call('GET', '/api/lists/' . $listId . '/export?format=csv');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/csv', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('attachment; filename=', $response->getHeaderLine('Content-Disposition'));

        $body = (string) $response->getBody();
        self::assertStringContainsString('Termos', $body);
        // K4: TL satır değeri üretim anındaki kurla bcmath hesabıdır — 12,00 × 7,04 = 84,48.
        self::assertStringContainsString('84.48', $body);
        // TOPLAM (K15): 24×12 + 20×18 = ¥648; ₺ = 648×7,04 = 4561,92.
        self::assertStringContainsString('TOPLAM', $body);
        self::assertStringContainsString('648.00', $body);
        self::assertStringContainsString('4561.92', $body);

        // Kayıt: geçmişe düştü, sha256 + boyut dolu, liste artık "güncel" (stale değil).
        $history = $this->json($this->call('GET', '/api/lists/' . $listId . '/exports'))['data'];
        self::assertCount(1, $history);
        self::assertSame('csv', $history[0]['format']);

        $list = $this->json($this->call('GET', '/api/lists/' . $listId))['data'];
        self::assertFalse($list['is_export_stale']);
        self::assertSame('csv', $list['last_export']['format']);
    }

    public function testExportAnlikGoruntudur_ListeDegisinceEskiIndirmeAyniKalir(): void
    {
        ['list' => $listId] = $this->seedList();
        $this->call('GET', '/api/lists/' . $listId . '/export?format=csv');
        $exportId = (int) $this->json($this->call('GET', '/api/lists/' . $listId . '/exports'))['data'][0]['id'];

        // Liste DEĞİŞİR: yeni ürün + revizyon artar → rozet "güncel değil".
        $this->write('POST', '/api/lists/' . $listId . '/products', ['name' => 'Yeni ürün', 'qty' => 5, 'price_yuan' => '2.00']);

        $list = $this->json($this->call('GET', '/api/lists/' . $listId))['data'];
        self::assertTrue($list['is_export_stale'], 'Ürün eklenince çıktı BAYATLAMALI (K25).');

        // Geçmişten indirme: yeni ürün YOK — snapshot'tan üretim.
        $download = $this->call('GET', '/api/exports/' . $exportId . '/file');
        self::assertSame(200, $download->getStatusCode());
        $body = (string) $download->getBody();
        self::assertStringNotContainsString('Yeni ürün', $body, 'Anlık görüntü sonradan eklenen ürünü İÇERMEMELİ.');
        self::assertStringContainsString('Termos', $body);
    }

    public function testXlsxUretilirVeGecerliZipDoner(): void
    {
        ['list' => $listId] = $this->seedList();

        $response = $this->call('GET', '/api/lists/' . $listId . '/export?format=xlsx');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('spreadsheetml', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        self::assertStringStartsWith("PK", $body, 'xlsx bir zip konteyneridir.');

        // İçerik doğrulaması: dosyayı geri okuyup örnek düzenin hücrelerini denetle.
        $temp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($temp, $body);
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($temp)->getActiveSheet();
        self::assertStringContainsString('ÇİNDEN DDP', (string) $sheet->getCell('B2')->getValue());
        self::assertSame('NO', (string) $sheet->getCell('B6')->getValue());
        self::assertSame('YUAN', (string) $sheet->getCell('P8')->getValue());
        self::assertSame('Termos', (string) $sheet->getCell('H9')->getValue());
        self::assertSame(24, (int) $sheet->getCell('N9')->getValue());
        self::assertSame('TOPLAM', (string) $sheet->getCell('B11')->getValue());
        self::assertSame(44, (int) $sheet->getCell('N11')->getValue());
        unlink($temp);
    }

    public function testPdfUretilir(): void
    {
        ['list' => $listId] = $this->seedList();

        $response = $this->call('GET', '/api/lists/' . $listId . '/export?format=pdf');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('%PDF-', (string) $response->getBody());
    }

    public function testGecersizBicim422(): void
    {
        ['list' => $listId] = $this->seedList();

        self::assertSame(422, $this->call('GET', '/api/lists/' . $listId . '/export?format=doc')->getStatusCode());
    }

    public function testOturumsuzExport401(): void
    {
        ['list' => $listId] = $this->seedList();
        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(401, $this->call('GET', '/api/lists/' . $listId . '/export?format=csv')->getStatusCode());
    }
}
