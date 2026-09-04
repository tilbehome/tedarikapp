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
use App\Middleware\KuyrukSupurme;
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
        \App\Controllers\KesifController $kesifController,
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
        // D12: panel ziyaretinde kuyruk turu tetikler (cron'suz çalışma emniyeti).
        \App\Services\Kuyruk\KuyrukTetikleyici $kuyrukTetikleyici,
        // V3-B A4: bildirim merkezi uçları.
        \App\Controllers\BildirimController $bildirimController,
        // V3-B B1: panorama TEK uç.
        \App\Controllers\PanoramaController $panoramaController,
        // V3-B B4: "Yenilikler" balonu ve sürüm notları geçmişi.
        \App\Controllers\SurumNotuController $surumNotuController,
        // V3-B F2: panel içi günlük görüntüleyici (Ayarlar > 16).
        \App\Controllers\GunlukController $gunlukController,
        // V3-C Aşama 2.1: teklif turu + firma uçları (sahip tarafı).
        ?\App\Controllers\TeklifTuruController $teklifTuru = null,
        // V3-C Aşama 2.2: firma yanıtı (yapıştır-ayrıştır + Excel gel-git).
        ?\App\Controllers\TurYanitController $turYanit = null,
    ): void {
        // İE#19 G7 — AKTİVİTE DEFTERİ BİLEREK KAPI DIŞINDA.
        //
        // MigrationGuard'ın kapsamı bu iş emrinde genişledi; aktivite ucu ise
        // KASITLI olarak dışarıda bırakıldı. Gerekçe: bu uç salt okunurdur ve tam
        // olarak sistem BOZUKKEN gereklidir — "migrate koşuldu mu, kim koştu, ne
        // zaman?" sorusunun cevabı burada. Onu da 503'e kapatmak, kullanıcıyı
        // arızayı teşhis edecek tek ekrandan mahrum bırakırdı. Uç yalnız
        // `activity_log` tablosunu okur; şema kaymasından etkilenen kolonları yoktur.
        $app->group('/api', static function (RouteCollectorProxy $group) use ($activityController, $bildirimController, $panoramaController, $surumNotuController, $gunlukController): void {
            $group->get('/activity', [$activityController, 'index']);
            // BİLDİRİMLER DE KAPI DIŞINDA (aynı gerekçe): sistem bozukken
            // kullanıcının "ne oldu?" sorusuna cevap veren yüzey odur. Uç yalnız
            // `notifications` tablosunu okur; şema kaymasından etkilenmez.
            $group->get('/bildirimler', [$bildirimController, 'index']);
            $group->get('/bildirimler/sayac', [$bildirimController, 'sayac']);
            $group->post('/bildirimler/hepsi-okundu', [$bildirimController, 'hepsiOkundu']);
            $group->post('/bildirimler/{id}/okundu', [$bildirimController, 'okundu']);
            // Panorama da kapı dışında: "bugün ne var?" sorusu sistem yarım
            // kurulmuşken de sorulabilmeli; uç yalnız sayar, şema yazmaz.
            $group->get('/panorama', [$panoramaController, 'index']);
            $group->get('/surum-notu', [$surumNotuController, 'guncel']);
            $group->get('/surum-notu/gecmis', [$surumNotuController, 'gecmis']);
            $group->post('/surum-notu/goruldu', [$surumNotuController, 'gorulduIsaretle']);
            // Günlük de kapı dışında: tam olarak sistem BOZUKKEN gereklidir
            // (aktivite defteriyle aynı gerekçe, İE#19 G7).
            $group->get('/gunluk', [$gunlukController, 'index']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory));

        $app->group('/api', static function (RouteCollectorProxy $group) use ($settingsController, $categoryController, $translationController, $kesifController): void {
            $group->get('/settings', [$settingsController, 'show']);
            $group->put('/settings/rates', [$settingsController, 'updateRates']);
            $group->get('/settings/rates/history', [$settingsController, 'rateHistory']);
            // İE#21 B5: güncel kur ÖNERİSİ — okur, döner, KAYDETMEZ (K4).
            $group->get('/settings/rates/suggest', [$settingsController, 'suggestRates']);
            // İE#13 F1: belge antedi (çıktı üst bandı) — boş alan basılmaz.
            $group->put('/settings/document-header', [$settingsController, 'updateDocumentHeader']);
            // İE#14 A2 (K56 Katman 1): Ayarlar > Terminoloji — dosya tabanlı sözlük.
            // İE#21 EK-4 (B7): kilit ekranındaki anahtar talebi köprüsünün numarası.
            $group->put('/settings/share-contact', [$settingsController, 'updateShareContact']);
            // rc8/K4: paylaşım/QR tabanı — parola tekrarı ister (F-08).
            $group->put('/settings/app-url', [$settingsController, 'updateAppUrl']);
            $group->get('/settings/glossary', [$translationController, 'glossaryIndex']);
            $group->put('/settings/glossary', [$translationController, 'glossarySave']);
            // V3-B C3 (PNL-50/51): sözlük CSV dışa/içe aktarma.
            $group->get('/settings/glossary/disa-aktar', [$translationController, 'glossaryDisaAktar']);
            $group->post('/settings/glossary/ice-aktar', [$translationController, 'glossaryIceAktar']);
            // İE#20 C4: Ayarlar > Çeviri (sağlayıcı, anahtar, model, hedef diller).
            $group->get('/settings/translation', [$translationController, 'translationSettings']);
            $group->put('/settings/translation', [$translationController, 'translationSettingsSave']);
            // İE#20 D1: bağlantı testi — YEDEĞE DÜŞMEZ, sağlayıcının hatasını gösterir.
            $group->post('/settings/translation/test', [$translationController, 'translationTest']);

            // İE#11: eklenti token yönetimi (Faz 3 rozeti kalktı).
            $group->post('/settings/extension-token', [$settingsController, 'extensionTokenCreate']);
            $group->delete('/settings/extension-token', [$settingsController, 'extensionTokenRevoke']);

            // İE#21 B1: KEŞİF HAVUZU — listeye girmemiş ürünleri de kapsayan
            // istihbarat yüzeyi. Filtreler VE ile birleşir, sayfalama zorunludur.
            $group->get('/kesif', [$kesifController, 'index']);
            $group->get('/kesif/gorunumler', [$kesifController, 'views']);
            $group->post('/kesif/gorunumler', [$kesifController, 'saveView']);
            $group->delete('/kesif/gorunumler/{ad}', [$kesifController, 'deleteView']);
            $group->post('/kesif/karsilastir', [$kesifController, 'compare']);

            $group->get('/categories', [$categoryController, 'index']);
            $group->post('/categories', [$categoryController, 'store']);
            // İE#21 B10: toplu içe aktarım — idempotent (aynı ad iki kez eklenmez).
            $group->post('/categories/import', [$categoryController, 'import']);
            $group->patch('/categories/{id}', [$categoryController, 'update']);
            $group->delete('/categories/{id}', [$categoryController, 'destroy']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory))
            // İE#19 G7: kapsam GENİŞLEDİ — ayarlar/kategoriler/aktivite/terminoloji de
            // şema bağımlıdır. Eskiden bu grup korumasızdı: bekleyen migration varken
            // Ayarlar ekranı "Undefined column" ile çöküyor, kullanıcı sorunun
            // güncelleme olduğunu anlayamıyordu.
            ->add(new MigrationGuard($connection, $migrationsDir, $responseFactory));

        $app->group('/api', static function (RouteCollectorProxy $group) use ($listController, $productController, $trashController, $exportController, $shareController, $inboxController, $translationController, $teklifTuru, $turYanit): void {
            // V3-C Aşama 2.1 — TEKLİF TURU (liste × firma × tur, K103). Her geçiş
            // kendi eylem ucudur; sahibin elle durum yazma yolu YOKTUR (VIEWED
            // bir gözlemdir). Tam token + anahtar yalnız gönderim yanıtında.
            if ($teklifTuru !== null) {
                $group->get('/firmalar', [$teklifTuru, 'firmalar']);
                $group->post('/firmalar', [$teklifTuru, 'firmaOlustur']);
                $group->get('/teklifler', [$teklifTuru, 'teklifler']);
                $group->get('/lists/{id}/turlar', [$teklifTuru, 'listeninTurlari']);
                $group->post('/lists/{id}/turlar', [$teklifTuru, 'ac']);
                $group->get('/lists/{id}/gonderim-gunlugu', [$teklifTuru, 'gonderimGunlugu']);
                $group->get('/turlar/{id}', [$teklifTuru, 'goster']);
                $group->post('/turlar/{id}/gonder', [$teklifTuru, 'gonder']);
                $group->post('/turlar/{id}/onayla', [$teklifTuru, 'onayla']);
                $group->post('/turlar/{id}/vazgec', [$teklifTuru, 'vazgec']);
                $group->post('/turlar/{id}/revizyon', [$teklifTuru, 'revizyon']);
            }

            // V3-C Aşama 2.2 — FİRMA YANITI: yapıştır-ayrıştır + Excel gel-git.
            // Önizleme uçları YAZMAZ; yazım yalnız `yanit-uygula` ile, parmak izi
            // ile idempotent, tek transaction. Excel şablonu POST'tur (CSRF'li indirme,
            // İE#11 Görev E kalıbı).
            if ($turYanit !== null) {
                $group->get('/turlar/{id}/yanit', [$turYanit, 'yanit']);
                $group->post('/turlar/{id}/yapistir-ayristir', [$turYanit, 'yapistirAyristir']);
                $group->post('/turlar/{id}/yanit-uygula', [$turYanit, 'uygula']);
                $group->post('/turlar/{id}/excel-sablon', [$turYanit, 'excelSablon']);
                $group->post('/turlar/{id}/excel-ice-aktar', [$turYanit, 'excelIceAktar']);
                $group->post('/turlar/{id}/excel-sonuc', [$turYanit, 'excelSonuc']);
            }

            $group->get('/lists', [$listController, 'index']);
            $group->post('/lists', [$listController, 'store']);
            $group->get('/lists/{id}', [$listController, 'show']);
            $group->patch('/lists/{id}', [$listController, 'update']);
            $group->delete('/lists/{id}', [$listController, 'destroy']);
            $group->post('/lists/{id}/duplicate', [$listController, 'duplicate']);

            // İE#10 Blok 4: paylaşım linki üret/yenile + iptal (token yalnız yanıtın içinde bir kez).
            $group->post('/lists/{id}/share', [$shareController, 'create']);
            $group->delete('/lists/{id}/share', [$shareController, 'destroy']);
            // İE#18 G6 (K62): erişim anahtarı — göster · yenile · aç/kapat.
            // İE#21 B6: kanal metni (tr/en/zh) — şablon sunucudan, {link} panelde dolar.
            $group->get('/lists/{id}/share-text', [$shareController, 'text']);
            $group->get('/lists/{id}/share-key', [$shareController, 'keyShow']);
            $group->post('/lists/{id}/share-key', [$shareController, 'keyRotate']);
            $group->patch('/lists/{id}/share-key', [$shareController, 'keyToggle']);

            // İE#10: export üretimi + geçmiş + geçmişten indirme (snapshot'tan yeniden üretim).
            // İE#11 Görev E: ÜRETİM POST'a çevrildi (CSRF'li — durum değiştiren işlem);
            // geçmişten indirme GET kalır (salt okunur, kayıt açmaz).
            $group->post('/lists/{id}/export', [$exportController, 'export']);
            $group->get('/lists/{id}/exports', [$exportController, 'history']);
            $group->get('/exports/{id}/file', [$exportController, 'download']);

            $group->get('/lists/{id}/products', [$productController, 'index']);
            $group->post('/lists/{id}/products', [$productController, 'store']);
            $group->patch('/lists/{id}/products/reorder', [$productController, 'reorder']);

            // İE#19 E11: tekil ürün — düzenleme ekranı tüm listeyi çekmez.
            $group->get('/products/{id}', [$productController, 'show']);
            // İE#20 C8: "HAZIR" kalite kapısı — kural sunucuda zorlanır.
            $group->patch('/products/{id}/hazir', [$productController, 'setHazir']);
            // İE#21 B3: ürün çekmecesi — ürün + ilan + kademe + skor TEK istekte.
            $group->get('/products/{id}/cekmece', [$productController, 'cekmece']);
            $group->get('/lists/{id}/hazirlik', [$productController, 'listeHazirligi']);

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
            // İE#21 B4: DESTE MODU — tek tuş, tek geçiş, geri alınabilir.
            $group->post('/inbox/deste', [$inboxController, 'deck']);
            $group->post('/inbox/deste/geri-al', [$inboxController, 'deckUndo']);
            // İE#13 B1: toplu silme — sabit yol, {id} deseninden ÖNCE tanımlanır.
            $group->post('/inbox/delete', [$inboxController, 'bulkDelete']);
            $group->get('/inbox/{id}', [$inboxController, 'show']);
            $group->delete('/inbox/{id}', [$inboxController, 'destroy']);

            // İE#13 C4: ZH→TR başlık ÖNERİSİ (K54 — hiçbir alana kendiliğinden yazılmaz).
            $group->post('/panel/translate-suggest', [$translationController, 'suggest']);
            // İE#14 A2: ürünün TAMAMI tek çağrıda (K56 Katman 2 arayüzü üstünden).
            $group->post('/panel/translate-product', [$translationController, 'translateProduct']);
            // İE#20 C4: "çevrilmemiş N ürünü çevir" — kuyruğa alır, beklemez.
            $group->post('/panel/translate-backfill', [$translationController, 'translateBackfill']);
            // D12: toplu çevirinin ilerleme sorgusu (panel "N/M" göstergesi).
            $group->get('/panel/translate-progress', [$translationController, 'translateProgress']);
            // D12: ürün kartındaki "Çevir" düğmesi — SENKRON, kuyruğa yazmaz.
            $group->post('/products/{id}/translate', [$translationController, 'translateProductNow']);

            $group->get('/trash', [$trashController, 'index']);
            $group->post('/trash/{type}/{id}/restore', [$trashController, 'restore']);
            $group->delete('/trash/{type}/{id}', [$trashController, 'destroy']);
        })
            ->add(new Csrf($services->session, $responseFactory))
            ->add(new Auth($services, $responseFactory))
            // D12 madde 3: panel ziyareti kuyruk turu tetikler. Ara katman
            // Auth'un DIŞINDA değil İÇİNDE durur — yalnız oturumlu istekler
            // tur açar, dışarıdan gelen bir GET sunucuda iş koşturamaz.
            ->add(new KuyrukSupurme($kuyrukTetikleyici))
            // İE#10.5 Blok 2: bekleyen migration varken veri uçları 503 MIGRATION_PENDING
            // döner (canlı ders: 0018 bekleyenken panel çöküyordu).
            ->add(new MigrationGuard($connection, $migrationsDir, $responseFactory));
    }
}
