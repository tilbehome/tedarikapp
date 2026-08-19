<?php

declare(strict_types=1);

namespace App\Core\Routes;

use App\Auth\AuthServices;
use App\Controllers\ActivityController;
use App\Controllers\CategoryController;
use App\Controllers\ExportController;
use App\Controllers\ListController;
use App\Controllers\ProductController;
use App\Controllers\SettingsController;
use App\Controllers\ShareController;
use App\Controllers\TrashController;
use App\Core\Connection;
use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Middleware\MigrationGuard;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Veri uçları (İE#10.5 Blok 6 — AppBuilder bölünmesi; davranış AYNEN taşındı).
 *
 * İki grup: ayarlar/kategoriler/aktivite ve liste/ürün/çöp/export/paylaşım-yönetimi.
 * İkinci grup MigrationGuard taşır (İE#10.5 Blok 2): bekleyen migration varken veri
 * uçları 503 MIGRATION_PENDING döner — /api/system ve /api/auth bilerek dışarıda.
 */
final class DataRoutes
{
    /**
     * @template T of \Psr\Container\ContainerInterface|null
     *
     * @param App<T> $app kompozisyon kökünden gelir
     */
    public static function register(
        App $app,
        SettingsController $settingsController,
        \App\Controllers\InboxController $inboxController,
        CategoryController $categoryController,
        ActivityController $activityController,
        ListController $listController,
        ProductController $productController,
        TrashController $trashController,
        ExportController $exportController,
        ShareController $shareController,
        \App\Controllers\TranslationController $translationController,
        AuthServices $services,
        ResponseFactoryInterface $responseFactory,
        Connection $connection,
        string $migrationsDir,
    ): void {
        $app->group('/api', static function (RouteCollectorProxy $group) use ($settingsController, $categoryController, $activityController): void {
            $group->get('/settings', [$settingsController, 'show']);
            $group->put('/settings/rates', [$settingsController, 'updateRates']);
            $group->get('/settings/rates/history', [$settingsController, 'rateHistory']);

            $group->get('/activity', [$activityController, 'index']);

            // İE#11: eklenti token yönetimi (Faz 3 rozeti kalktı).
            $group->post('/settings/extension-token', [$settingsController, 'extensionTokenCreate']);
            $group->delete('/settings/extension-token', [$settingsController, 'extensionTokenRevoke']);

            $group->get('/categories', [$categoryController, 'index']);
            $group->post('/categories', [$categoryController, 'store']);
            $group->patch('/categories/{id}', [$categoryController, 'update']);
            $group->delete('/categories/{id}', [$categoryController, 'destroy']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory));

        $app->group('/api', static function (RouteCollectorProxy $group) use ($listController, $productController, $trashController, $exportController, $shareController, $inboxController, $translationController): void {
            $group->get('/lists', [$listController, 'index']);
            $group->post('/lists', [$listController, 'store']);
            $group->get('/lists/{id}', [$listController, 'show']);
            $group->patch('/lists/{id}', [$listController, 'update']);
            $group->delete('/lists/{id}', [$listController, 'destroy']);
            $group->post('/lists/{id}/duplicate', [$listController, 'duplicate']);

            // İE#10 Blok 4: paylaşım linki üret/yenile + iptal (token yalnız yanıtın içinde bir kez).
            $group->post('/lists/{id}/share', [$shareController, 'create']);
            $group->delete('/lists/{id}/share', [$shareController, 'destroy']);

            // İE#10: export üretimi + geçmiş + geçmişten indirme (snapshot'tan yeniden üretim).
            // İE#11 Görev E: ÜRETİM POST'a çevrildi (CSRF'li — durum değiştiren işlem);
            // geçmişten indirme GET kalır (salt okunur, kayıt açmaz).
            $group->post('/lists/{id}/export', [$exportController, 'export']);
            $group->get('/lists/{id}/exports', [$exportController, 'history']);
            $group->get('/exports/{id}/file', [$exportController, 'download']);

            $group->get('/lists/{id}/products', [$productController, 'index']);
            $group->post('/lists/{id}/products', [$productController, 'store']);
            $group->patch('/lists/{id}/products/reorder', [$productController, 'reorder']);

            // bulk, {id} deseninden ÖNCE tanımlanır; aksi hâlde "bulk" bir kimlik sanılır.
            $group->patch('/products/bulk', [$productController, 'bulk']);
            $group->patch('/products/{id}', [$productController, 'update']);
            $group->patch('/products/{id}/status', [$productController, 'updateStatus']);
            // İE#10 5d: kırık görsel onarımı — uzaksa arşive al, yerel+kayıpsa kaynaktan indir.
            $group->post('/products/{id}/media-repair', [$productController, 'mediaRepair']);
            $group->delete('/products/{id}', [$productController, 'destroy']);

            // İE#11 Görev D: Gelen Kutusu.
            $group->get('/inbox', [$inboxController, 'index']);
            $group->post('/inbox/assign', [$inboxController, 'assign']);
            $group->delete('/inbox/{id}', [$inboxController, 'destroy']);

            // İE#13 C4: ZH→TR başlık ÖNERİSİ (K54 — hiçbir alana kendiliğinden yazılmaz).
            $group->post('/panel/translate-suggest', [$translationController, 'suggest']);

            $group->get('/trash', [$trashController, 'index']);
            $group->post('/trash/{type}/{id}/restore', [$trashController, 'restore']);
            $group->delete('/trash/{type}/{id}', [$trashController, 'destroy']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory))
            // İE#10.5 Blok 2: bekleyen migration varken veri uçları 503 MIGRATION_PENDING
            // döner (canlı ders: 0018 bekleyenken panel çöküyordu).
            ->add(new MigrationGuard($connection, $migrationsDir, $responseFactory));
    }
}
