<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\AppBuilder;
use App\Middleware\Csrf;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use App\Services\MediaJanitor;
use App\Services\MediaService;
use App\Services\UrlGuard;
use Psr\Log\NullLogger;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;
use Tests\Support\TempDirectory;

/**
 * K37 §C7 KRİTİK: kalıcı silme fiziksel medya dosyasını da siler; yetim dosya GC'si
 * DB'de referansı kalmamış sunucu-üretimi dosyaları temizler.
 *
 * Kurallar:
 *  • Kopyalanan listeler aynı dosyayı paylaşır → dosya SON referans gidince silinir.
 *  • Soft-delete (çöp kutusu) görseli KORUR — kayıt geri alınabilir (K15).
 *  • `.htaccess` ve desen dışı adlara ASLA dokunulmaz.
 */
final class MediaJanitorTest extends AuthTestCase
{
    use TempDirectory;

    private function mediaService(): MediaService
    {
        mkdir($this->tempPath('public/media'), 0775, true);

        return new MediaService(
            $this->tempRoot(),
            new UrlGuard(['alicdn.com']),
            new FakeMediaFetcher(),
            new SettingsRepository($this->connection),
            8 * 1024 * 1024,
        );
    }

    private function janitor(MediaService $media): MediaJanitor
    {
        return new MediaJanitor($media, new ProductRepository($this->connection));
    }

    /** Diske sahte medya dosyası koyar, adını döndürür. */
    private function putMediaFile(MediaService $media): string
    {
        $name = bin2hex(random_bytes(16)) . '.jpg';
        file_put_contents($media->directory() . '/' . $name, 'sahte-gorsel-baytlari');

        return $name;
    }

    /** @return int ürün kimliği */
    private function insertProduct(?string $mainImage, ?string $deletedAt = null): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (list_id, name, qty, price_yuan, price_ddp_usd, status, main_image, created_at, updated_at, deleted_at)
             VALUES (1, :name, 1, \'1\', \'0\', \'to_order\', :main_image, :ts, :ts, :deleted_at)',
        );
        $statement->execute([
            'name' => 'Ürün',
            'main_image' => $mainImage,
            'ts' => '2026-08-17 10:00:00',
            'deleted_at' => $deletedAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testSonReferansSilininceDosyaDiskteKalmaz(): void
    {
        $media = $this->mediaService();
        $name = $this->putMediaFile($media);
        $reference = '/media/' . $name;

        $productId = $this->insertProduct($reference);
        self::assertFileExists($media->directory() . '/' . $name);

        // Kalıcı silme akışının yaptığı sıra: önce DB kaydı, sonra dosya.
        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);
        $deleted = $this->janitor($media)->deleteUnreferenced([$reference]);

        self::assertSame([$name], $deleted);
        self::assertFileDoesNotExist($media->directory() . '/' . $name, 'KRİTİK: dosya diskte KALMAMALI.');
    }

    public function testPaylasilanDosyaSonReferansaKadarKorunur(): void
    {
        $media = $this->mediaService();
        $name = $this->putMediaFile($media);
        $reference = '/media/' . $name;

        $first = $this->insertProduct($reference);
        $this->insertProduct($reference); // kopyalanmış listedeki ikiz kayıt

        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $first]);
        $this->janitor($media)->deleteUnreferenced([$reference]);

        self::assertFileExists($media->directory() . '/' . $name, 'İkinci kayıt hâlâ kullanıyor — dosya KALMALI.');
    }

    public function testHotlinkUrlleriVeDesenDisiAdlarDokunulmaz(): void
    {
        $media = $this->mediaService();
        file_put_contents($media->directory() . '/.htaccess', 'Deny from all');
        file_put_contents($media->directory() . '/logo.png', 'el-ile-konmus');

        $deleted = $this->janitor($media)->deleteUnreferenced([
            'https://cdn.alicdn.com/img/123.jpg', // hotlink — bize ait değil
            '/media/../../.env',                  // traversal denemesi
            '/media/logo.png',                    // desen dışı ad
        ]);

        self::assertSame([], $deleted);
        self::assertFileExists($media->directory() . '/.htaccess');
        self::assertFileExists($media->directory() . '/logo.png');
    }

    public function testYetimGcReferanssizDosyalariSilerKorunanlariBirakir(): void
    {
        $media = $this->mediaService();

        $referenced = $this->putMediaFile($media);
        $softDeleted = $this->putMediaFile($media);
        $orphan = $this->putMediaFile($media);
        file_put_contents($media->directory() . '/.htaccess', 'Deny from all');

        $this->insertProduct('/media/' . $referenced);
        // Çöp kutusundaki ürünün görseli korunmalı — kayıt geri alınabilir (K15).
        $this->insertProduct('/media/' . $softDeleted, deletedAt: '2026-08-17 09:00:00');

        $deleted = $this->janitor($media)->purgeOrphans();

        self::assertSame([$orphan], $deleted);
        self::assertFileExists($media->directory() . '/' . $referenced);
        self::assertFileExists($media->directory() . '/' . $softDeleted);
        self::assertFileExists($media->directory() . '/.htaccess');
    }

    // ─────────────── Uçtan uca: çöp kutusu kalıcı silme ───────────────

    public function testKaliciSilmeUcuFizikselDosyayiDaSiler(): void
    {
        // Uygulama, medya klasörü geçici dizinde olacak şekilde kurulur.
        mkdir($this->tempPath('public/media'), 0775, true);
        $name = bin2hex(random_bytes(16)) . '.jpg';
        file_put_contents($this->tempPath('public/media/' . $name), 'sahte-gorsel');

        $app = AppBuilder::build(
            $this->config(),
            fn (): \PDO => $this->pdo,
            new NullLogger(),
            $this->session,
            $this->clock,
            basePath: $this->tempRoot(),
        );

        $user = $this->createUser('janitor@tedarikapp.test');
        $login = fn (string $method, string $path, ?array $body, array $headers = []) => $app->handle(
            (function () use ($method, $path, $body, $headers) {
                $request = $this->rawRequest($method, $path);
                if ($body !== null) {
                    $request = $request->withParsedBody($body)->withHeader('Content-Type', 'application/json');
                }
                foreach ($headers as $header => $value) {
                    $request = $request->withHeader($header, $value);
                }

                return $request;
            })(),
        );

        $login('POST', '/api/auth/login', ['email' => 'janitor@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $login('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $me = json_decode((string) $login('GET', '/api/auth/me', null)->getBody(), true);
        $csrf = (string) $me['data']['csrf_token'];

        // Liste + görselli ürün doğrudan DB'ye kurulur; ürün çöp kutusuna alınmış hâlde.
        $this->pdo->exec(
            "INSERT INTO lists (name, status, visibility, yuan_rate, usd_rate, created_at, updated_at)
             VALUES ('Liste', 'draft', 'active', '7.04', '41.5', '2026-08-17 10:00:00', '2026-08-17 10:00:00')",
        );
        $productId = $this->insertProduct('/media/' . $name, deletedAt: '2026-08-17 09:00:00');

        $response = $login('DELETE', '/api/trash/products/' . $productId, [], [Csrf::HEADER => $csrf]);

        self::assertSame(204, $response->getStatusCode(), (string) $response->getBody());
        self::assertFileDoesNotExist($this->tempPath('public/media/' . $name), 'KRİTİK (K37 §C7): dosya diskte KALMAMALI.');
    }
}
