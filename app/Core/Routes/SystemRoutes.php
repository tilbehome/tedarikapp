<?php

declare(strict_types=1);

namespace App\Core\Routes;

use App\Auth\AuthServices;
use App\Controllers\SystemController;
use App\Middleware\Auth;
use App\Middleware\Csrf;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Sistem uçları (İE#10.5 Blok 6 — AppBuilder bölünmesi; davranış AYNEN taşındı).
 *
 * Güncelleme yolu (İE#5 §12) + bakım uçları. MigrationGuard bu gruba BİLEREK
 * eklenmez: migrate/baseline bu yolla koşulur (İE#10.5 Blok 2).
 */
final class SystemRoutes
{
    /**
     * @template T of \Psr\Container\ContainerInterface|null
     *
     * @param App<T> $app kompozisyon kökünden gelir
     */
    public static function register(
        App $app,
        SystemController $system,
        AuthServices $services,
        ResponseFactoryInterface $responseFactory,
    ): void {
        $app->group('/api/system', static function (RouteCollectorProxy $group) use ($system): void {
            $group->get('/status', [$system, 'status']);
            // İE#19 G4: bütünlük denetiminin İSİM İSİM listesi oturum arkasındadır.
            $group->get('/integrity/detay', [$system, 'integrityDetail']);
            // İE#20 C3: kuyruk sağlığı + ölü işi yeniden deneme.
            $group->get('/queue', [$system, 'queue']);
            $group->post('/queue/{id}/retry', [$system, 'queueRetry']);
            // İE#21 B11: ölü mektup eylemlerinin kalan ikisi — vazgeç ve düzelt.
            $group->post('/queue/{id}/discard', [$system, 'queueDiscard']);
            $group->post('/queue/{id}/fix', [$system, 'queueFix']);
            $group->get('/state-machine', [$system, 'stateMachine']);
            $group->post('/migrate', [$system, 'migrate']);
            // K46: kilit kaldırmanın admin-oturumu yolu (Auth + CSRF bu grupta).
            $group->post('/setup-unlock', [$system, 'setupUnlock']);
            // K47: uzak görselleri arşive taşıma (parti parti; Auth + CSRF bu grupta).
            $group->post('/media-migrate', [$system, 'mediaMigrate']);
            // A6-EK: boş sözlükle çevrilmiş ürünleri yeniden kuyruğa alır.
            // Yeni çeviri hattı yok — mevcut toplu çeviri yolunu kullanır.
            $group->post('/sozluksuz-ceviri-yenile', [$system, 'sozluksuzCeviriYenile']);
            // K49: migration defterini gerçeğe eşitleme (DDL koşmaz; Auth + CSRF bu grupta).
            $group->post('/migrate-baseline', [$system, 'migrateBaseline']);
            // İE#10 5d: medya bütünlük denetimi + kayıp dosya onarımı (parti parti).
            $group->post('/media-check', [$system, 'mediaCheck']);
            // İE#10.5: yedekleme — elle al (+off-site), listele, indir (ad deseni doğrulanır).
            $group->post('/backup', [$system, 'backupCreate']);
            $group->get('/backups', [$system, 'backupList']);
            $group->get('/backups/{name}/file', [$system, 'backupDownload']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory));
    }
}
