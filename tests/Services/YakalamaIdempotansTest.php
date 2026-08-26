<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\InboxRepository;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Services\ActivityLog;
use App\Services\CaptureApplier;
use App\Services\CaptureService;
use App\Services\InputValidator;
use App\Services\ListImmutableException;
use App\Services\ListMutationPolicy;
use App\Services\MediaService;
use App\Services\UrlGuard;
use Psr\Log\NullLogger;
use Tests\Support\AuthTestCase;
use Tests\Support\FakeMediaFetcher;

/**
 * İE#19 G6 — yakalamanın uygulanması: idempotans, terminal liste, ilk iz.
 *
 * Bu süit "kontrol edip yaz" ile "rezervasyona bağla" arasındaki farkı sınar:
 * ilkinde iki eşzamanlı istek iki ürün yazar, ikincisinde biri kaybeder ve
 * DİĞERİNİN kimliğini alır.
 */
final class YakalamaIdempotansTest extends AuthTestCase
{
    private CaptureApplier $applier;
    private ListRepository $lists;
    private ProductRepository $products;
    private InboxRepository $inbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lists = new ListRepository($this->connection);
        $this->products = new ProductRepository($this->connection);
        $this->inbox = new InboxRepository($this->connection);

        $config = $this->config();
        $guard = new UrlGuard(['alicdn.com']);
        $media = new MediaService(
            sys_get_temp_dir(),
            $guard,
            new FakeMediaFetcher(),
            new \App\Models\SettingsRepository($this->connection),
            8 * 1024 * 1024,
            'public/media',
        );
        $capture = new CaptureService($this->connection, $this->lists, $this->products, $media, new InputValidator(new \App\Services\MoneyService()));

        $this->applier = new CaptureApplier(
            $this->connection,
            $capture,
            $this->inbox,
            $this->products,
            new ListMutationPolicy(),
            new ActivityLog($this->connection),
            // rc8-01: MediaService ve kayitci ZORUNLU — eksik wiring artik
            // TEST ZAMANINDA patlar (F-01'in kalici korumasi).
            $media,
            new NullLogger(),
        );
    }

    public function testAyniCaptureIdIkinciKezUYGULANMAZ(): void
    {
        $list = $this->liste('draft');
        $yuk = $this->yuk('11111111-1111-4111-8111-111111111111');

        $ilk = $this->applier->applyToList($yuk, $list, $this->clock->now(), '203.0.113.7');
        $ikinci = $this->applier->applyToList($yuk, $list, $this->clock->now(), '203.0.113.7');

        self::assertFalse($ilk['idempotent_replay']);
        self::assertTrue($ikinci['idempotent_replay'], 'İkinci istek yeni kayıt açtı — idempotans yok.');
        self::assertSame($ilk['product_id'], $ikinci['product_id'], 'Yarışı kaybeden İLK sonucun kimliğini döndürmeli.');
        self::assertSame($ilk['inbox_id'], $ikinci['inbox_id']);

        $urunSayisi = (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        self::assertSame(1, $urunSayisi, 'Mükerrer ürün yazıldı.');
    }

    public function testTerminalListeyeEKLENTIDENDEURUNGIRMEZ(): void
    {
        $list = $this->liste('completed');

        $this->expectException(ListImmutableException::class);

        $this->applier->applyToList($this->yuk('22222222-2222-4222-8222-222222222222'), $list, $this->clock->now(), '203.0.113.7');
    }

    public function testTerminalListeDenemesiHICBIRSEYYAZMAZ(): void
    {
        $list = $this->liste('cancelled');

        try {
            $this->applier->applyToList($this->yuk('33333333-3333-4333-8333-333333333333'), $list, $this->clock->now(), '203.0.113.7');
        } catch (ListImmutableException) {
            // beklenen
        }

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM inbox_items')->fetchColumn());
    }

    public function testYakalananUrunIlkDURUMVEAKTIVITEIZIYLEDOGAR(): void
    {
        $list = $this->liste('draft');
        $sonuc = $this->applier->applyToList(
            $this->yuk('44444444-4444-4444-8444-444444444444'),
            $list,
            $this->clock->now(),
            '203.0.113.7',
        );

        $tarihce = $this->pdo->query(
            'SELECT from_status, to_status, actor_type FROM product_status_history WHERE product_id = ' . (int) $sonuc['product_id'],
        )->fetchAll();
        self::assertCount(1, $tarihce, 'Yakalanan ürün TARİHÇESİZ doğuyor.');
        self::assertNull($tarihce[0]['from_status']);
        self::assertSame('to_order', $tarihce[0]['to_status']);
        self::assertSame('extension', $tarihce[0]['actor_type']);

        $aktivite = $this->pdo->query(
            "SELECT action, detail FROM activity_log WHERE entity_type = 'product'",
        )->fetchAll();
        self::assertCount(1, $aktivite);
        self::assertSame('product_created', $aktivite[0]['action']);
        self::assertStringContainsString('Yakalamadan', (string) $aktivite[0]['detail']);
    }

    public function testKuyruktanTasimadaSAHIPLENMEYARISI(): void
    {
        $list = $this->liste('draft');
        $yuk = $this->yuk('55555555-5555-4555-8555-555555555555');
        $inboxId = $this->inbox->create((new CaptureService(
            $this->connection,
            $this->lists,
            $this->products,
            new MediaService(sys_get_temp_dir(), new UrlGuard(['alicdn.com']), new FakeMediaFetcher(), new \App\Models\SettingsRepository($this->connection), 1024, 'public/media'),
            new InputValidator(new \App\Services\MoneyService()),
        ))->inboxFields($yuk), $this->clock->now());

        $ilk = $this->applier->applyInboxItem($inboxId, $yuk, $list, $this->clock->now(), '203.0.113.7');
        $ikinci = $this->applier->applyInboxItem($inboxId, $yuk, $list, $this->clock->now(), '203.0.113.7');

        self::assertFalse($ilk['idempotent_replay']);
        self::assertTrue($ikinci['idempotent_replay'], 'İkinci taşıma da ürün yazdı — sahiplenme çalışmıyor.');
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
    }

    /** @return array<string, mixed> */
    private function liste(string $status): array
    {
        $id = $this->lists->create([
            'name' => 'Deneme',
            'status' => $status,
            'yuan_rate' => '4.7000',
            'usd_rate' => '35.0000',
        ], $this->clock->now());

        $this->pdo->prepare('UPDATE lists SET status = ? WHERE id = ?')->execute([$status, $id]);
        $row = $this->lists->find($id);
        self::assertNotNull($row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function yuk(string $captureId): array
    {
        return [
            'capture_id' => $captureId,
            'schema_version' => 2,
            'extension_version' => '1.2.1',
            'parser_version' => '1',
            'source' => [
                'platform' => '1688',
                'external_id' => '9001',
                'url' => 'https://detail.1688.com/offer/9001.html',
            ],
            'normalized' => [
                'name' => 'Deneme ürünü',
                'price_yuan' => '12.00',
                'images' => [],
            ],
            'raw' => ['title' => '测试'],
            'qty' => 5,
        ];
    }
}
