<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * Revizyon harfi LİSTE SÜRÜMÜNE bağlıdır (İE#14 B1 — canlı mantık hatası).
 *
 * ESKİ DAVRANIŞ: her indirme sayacı tüketiyordu; aynı listeden Excel "Rev D",
 * PDF "Rev E" çıkıyordu. PM'in şart koştuğu üç senaryo burada çivilenir:
 *   (a) aynı listeden arka arkaya Excel + PDF → AYNI Rev
 *   (b) bir ürün fiyatı değişir → Rev bir ARTAR
 *   (c) yalnız indirme tekrarı (geçmişten) → Rev SABİT
 */
final class RevisionSequenceTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listId = 0;
    private int $productId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Revizyon listesi']))['data']['id'];
        $this->productId = (int) $this->json($this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'Revizyon ürünü',
            'qty' => 10,
            'price_yuan' => '10.00',
        ]))['data']['id'];
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    /** Üretilen Excel'deki belge kodundan revizyon harfini okur. */
    private function revizyon(string $format = 'xlsx'): string
    {
        $response = $this->write('POST', '/api/lists/' . $this->listId . '/export?format=' . $format);
        self::assertSame(200, $response->getStatusCode());

        if ($format !== 'xlsx') {
            // PDF'te kodu okumak için snapshot'a bakılır (aynı kaynaktan üretilir).
            $snapshot = json_decode(
                (string) $this->pdo->query('SELECT snapshot_json FROM exports ORDER BY id DESC LIMIT 1')->fetchColumn(),
                true,
            );

            return (string) $snapshot['options']['revision_label'];
        }

        $temp = tempnam(sys_get_temp_dir(), 'rev') . '.xlsx';
        file_put_contents($temp, (string) $response->getBody());
        $kod = (string) IOFactory::load($temp)->getActiveSheet()->getCell('I3')->getValue();
        @unlink($temp);

        self::assertMatchesRegularExpression('/· Rev [A-Z]+$/', $kod);

        return substr($kod, strrpos($kod, ' ') + 1);
    }

    public function testAyniIcerikten_ExcelVePdf_AYNI_RevHarfiniTasir(): void
    {
        $excel = $this->revizyon('xlsx');
        $pdf = $this->revizyon('pdf');

        self::assertSame('A', $excel);
        self::assertSame($excel, $pdf, 'İndirme revizyon TÜKETMEZ — aynı içerik aynı harf.');
    }

    public function testUrunFiyatiDegisince_RevBirARTAR(): void
    {
        self::assertSame('A', $this->revizyon('xlsx'));

        $this->write('PATCH', '/api/products/' . $this->productId, ['price_yuan' => '11.00']);

        self::assertSame('B', $this->revizyon('xlsx'));
    }

    public function testYalnizIndirmeTekrari_RevSABIT(): void
    {
        self::assertSame('A', $this->revizyon('xlsx'));
        self::assertSame('A', $this->revizyon('xlsx'));
        self::assertSame('A', $this->revizyon('pdf'));

        // Geçmişten indirme yeni kayıt açmaz; revizyon etkilenmez.
        $gecmis = $this->json($this->call('GET', '/api/lists/' . $this->listId . '/exports'))['data'];
        $this->call('GET', '/api/exports/' . $gecmis[0]['id'] . '/file');

        self::assertSame('A', $this->revizyon('xlsx'));
    }

    /** Durum değişikliği de içerik değişikliğidir (belgede basılır). */
    public function testUrunDurumuDegisince_RevARTAR(): void
    {
        self::assertSame('A', $this->revizyon('xlsx'));

        $this->write('PATCH', '/api/products/' . $this->productId . '/status', ['status' => 'ordered']);

        self::assertSame('B', $this->revizyon('xlsx'));
    }
}
