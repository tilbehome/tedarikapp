<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Core\AppVersion;
use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Connection;
use App\Core\Migrator;
use App\Core\Response;
use App\Middleware\Auth;
use App\Services\ActivityLog;
use App\Services\MediaService;
use App\Services\StateMachine;
use App\Setup\SetupLock;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Güncelleme yolu (İE#5 §12).
 *
 * Kurulum sihirbazı tek seferliktir ve bittikten sonra kilitlenir; ama uygulama
 * güncellendiğinde yeni migration'lar gelir. Bunları koşmak için sunucuya SSH ile
 * girmek gerekmesin diye panelden **kimlik doğrulamalı** bir yol bırakılır.
 *
 * `bin/migrate.php` (lokal geliştirme yolu) aynen durur — bu uçlar onun yerine geçmez.
 */
final class SystemController
{
    public function __construct(
        private readonly string $basePath,
        private readonly Connection $connection,
        private readonly SetupLock $lock,
        private readonly Clock $clock,
        private readonly ?MediaService $media = null,
        private readonly ?StateMachine $stateMachine = null,
        private readonly ?\App\Core\Config $appConfig = null,
        // K99: açılışta yapılan katalog denetiminin sonucu — "Sistem durumu"
        // ekranı bunu kırmızı madde olarak basar.
        private readonly ?\App\Core\KatalogDurumu $katalogDurumu = null,
        // A6-EK: "sözlüksüz çevrilmiş ürün" kartının düğmesi ürünleri yeniden
        // kuyruğa alır. Kuyruk yoksa kart yalnız SAYIYI gösterir — düğme
        // olmayan bir kuyruğa iş atmaz.
        private readonly ?\App\Services\Kuyruk\JobQueue $kuyruk = null,
        /** C4/F: gizlenen hataların ayrıntısı yalnız günlüğe. */
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
    }

    /**
     * A6-EK: sözlüksüz (boş sözlükle) çevrilmiş ürün sayacı.
     *
     * Ayarlar okunamıyorsa (kurulum yarım, sağlayıcı girilmemiş) sayaç
     * kurulamaz. Böyle bir durumda 0 döneriz — "bilinmiyor"u sıfır saymak
     * genelde yanlıştır ama burada kart bir EYLEM ÖNERİR: eylem üretemeyecek
     * bir kartı göstermek kullanıcıyı boşa uğraştırır.
     */
    private function sozluksuzSayac(): ?\App\Services\Translation\SozluksuzCeviriSayaci
    {
        if ($this->appConfig === null) {
            return null;
        }

        return new \App\Services\Translation\SozluksuzCeviriSayaci(
            $this->connection,
            $this->appConfig,
            $this->basePath,
        );
    }

    /**
     * POST /api/system/backup — İE#10.5: elle yedek al (+ yapılandırılmışsa off-site gönder).
     * Auth + CSRF arkasında. Sırlar/anahtar loglanmaz; sonuç activity_log'a yazılır.
     */
    public function backupCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        if ($this->appConfig === null) {
            return Response::error($response, 'SERVER_ERROR', 'Yapılandırma erişilemiyor.', 500);
        }

        $service = new \App\Services\BackupService($this->appConfig, $this->basePath);
        try {
            $backup = $service->create();
        } catch (Throwable $e) {
            return Response::error($response, 'BACKUP_FAILED', $e->getMessage(), 500);
        }

        $offsite = (new \App\Services\BackupOffsite($this->appConfig))
            ->send((string) $service->pathFor($backup['name']), $backup['name']);

        // İE#11 EK-2: saklama — eskiler silinir (en yeni 5 her koşulda kalır).
        $pruned = $service->prune($this->appConfig->getPositiveInt('BACKUP_RETENTION_DAYS', 14));

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'backup_created',
            sprintf(
                '%s: %s (%.1f KB) · off-site: %s',
                $user->email,
                $backup['name'],
                $backup['size'] / 1024,
                $offsite['attempted'] ? ($offsite['sent'] ? 'gönderildi (' . $offsite['via'] . ')' : 'BAŞARISIZ') : 'yapılandırılmadı',
            ) . ($pruned === [] ? '' : sprintf(' · %d eski yedek silindi', count($pruned))),
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, ['backup' => $backup, 'offsite' => $offsite, 'pruned' => count($pruned)]);
    }

    /** GET /api/system/backups — son yedekler + 24 saat uyarısı + off-site durumu. */
    public function backupList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticatedUser($request);
        if ($this->appConfig === null) {
            return Response::error($response, 'SERVER_ERROR', 'Yapılandırma erişilemiyor.', 500);
        }

        $service = new \App\Services\BackupService($this->appConfig, $this->basePath);
        $age = $service->lastBackupAgeSeconds();

        return Response::success($response, [
            'backups' => array_slice($service->list(), 0, 10),
            'writable' => $service->isWritable(),
            'last_age_seconds' => $age,
            'stale' => $age === null || $age > 86400,
            // İE#14 D1: 30 saat = "gecelik koşu bir kez atlandı" eşiği (24 saatlik
            // döngüye 6 saat pay bırakır; saat kayması yüzünden boş yere kızarmaz).
            'gecikti' => $age === null || $age > 108000,
            'cron' => (new \App\Services\CronLog($this->basePath))->last($this->clock->now()),
            'offsite_configured' => (new \App\Services\BackupOffsite($this->appConfig))->configured(),
        ]);
    }

    /**
     * GET /api/system/backups/{name}/file — şifreli yedeği indirir (Auth'lu; ad deseni doğrulanır).
     *
     * @param array<string, string> $args
     */
    public function backupDownload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->authenticatedUser($request);
        if ($this->appConfig === null) {
            return Response::error($response, 'SERVER_ERROR', 'Yapılandırma erişilemiyor.', 500);
        }

        $path = (new \App\Services\BackupService($this->appConfig, $this->basePath))->pathFor((string) ($args['name'] ?? ''));
        if ($path === null) {
            return Response::error($response, 'NOT_FOUND', 'Yedek bulunamadı.', 404);
        }

        // İE#11 EK-2 (3): akışla oku — büyük yedek indirmesi belleği şişirmez.
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            return Response::error($response, 'SERVER_ERROR', 'Yedek dosyası okunamadı.', 500);
        }

        return $response
            ->withBody(new \Slim\Psr7\Stream($stream))
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Length', (string) filesize($path))
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"')
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * GET /api/system/state-machine — izinli durum geçişlerinin haritası.
     *
     * Panel (İE#8 §2) durum menüsünü buradan kurar: geçersiz geçiş kullanıcıya
     * hiç SUNULMAZ. Kuralın tek kaynağı backend'deki StateMachine'dir; arayüz
     * kendi kopyasını tutmaz — tuttuğu an docs/04 §2b ile ayrışma riski doğar.
     */
    public function stateMachine(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticatedUser($request);

        $machine = $this->stateMachine ?? new StateMachine();

        $product = [];
        foreach (array_keys(StateMachine::PRODUCT_TRANSITIONS) as $status) {
            $product[$status] = $machine->allowedProductTransitions((string) $status);
        }

        $list = [];
        foreach (array_keys(StateMachine::LIST_TRANSITIONS) as $status) {
            $list[$status] = $machine->allowedListTransitions((string) $status);
        }

        return Response::success($response, [
            'product' => $product,
            'list' => $list,
        ]);
    }

    /** GET /api/system/status — sürüm bilgisi + bekleyen migration sayısı. */
    /**
     * POST /api/system/setup-unlock — K46: kilit kaldırmanın ADMİN OTURUMU yolu.
     * Auth + CSRF arkasındadır (rota grubu); sihirbazdaki APP_KEY yolunun paneldeki eşi.
     */
    public function setupUnlock(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $now = $this->clock->now();

        (new \App\Setup\UnlockGate($this->connection, $this->basePath, new \DateTimeZone(date_default_timezone_get())))
            ->recordSuccess(\App\Core\ClientIp::from($request), $now, 'admin:' . $user->email);

        // İE#19 G2: kilit SİLİNMEZ — yönetici oturumuna bağlı 15 dakikalık, tek
        // kullanımlık bir yeniden-kurulum bileti üretilir. Bilet HttpOnly çerezde
        // taşınır; sihirbaz yalnız bu tarayıcıya açılır ve süre dolunca kendiliğinden
        // kapanır. (Eskiden kilit siliniyor, sihirbaz herkese açık kalıyordu.)
        $ticket = (new \App\Setup\ReSetupTicket($this->connection))->issue($now, 'admin:' . $user->email);

        return \App\Core\Cookie::write(
            \App\Core\Response::success($response, [
                'unlocked' => true,
                'ticket' => true,
                'expires_in_seconds' => \App\Setup\ReSetupTicket::LIFETIME_SECONDS,
                'setup_url' => '/setup',
            ]),
            \App\Setup\ReSetupTicket::COOKIE_NAME,
            $ticket,
            $now->modify('+' . \App\Setup\ReSetupTicket::LIFETIME_SECONDS . ' seconds'),
            strtolower($request->getUri()->getScheme()) === 'https',
        );
    }

    /**
     * POST /api/system/media-migrate — K47: uzak görselleri arşive taşıma (bir parti).
     *
     * Auth + CSRF arkasındadır (rota grubu). Tek çağrı bir parti işler (zaman aşımı
     * yememek için); panel "kalan" sayısı sıfırlanana dek tekrar çağırır. Aynı işin
     * CLI eşi: `bin/media-migrate.php`.
     */
    public function mediaMigrate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);

        if ($this->media === null) {
            return Response::error($response, 'SERVER_ERROR', 'Medya servisi yapılandırılmamış.', 500);
        }
        if ($this->media->mode() !== MediaService::MODE_DOWNLOAD) {
            return Response::error(
                $response,
                'MEDIA_NOT_WRITABLE',
                'Medya klasörü yazılabilir değil — arşiv modu kapalı. public/media klasörüne yazma izni verin.',
                422,
            );
        }

        // İE#10 Blok 5b: panel önceki turların başarısız kimliklerini geçer — parti başı tıkanmaz.
        $body = (array) ($request->getParsedBody() ?? []);
        $excludeProducts = array_map('intval', is_array($body['exclude_products'] ?? null) ? $body['exclude_products'] : []);
        $excludeImages = array_map('intval', is_array($body['exclude_images'] ?? null) ? $body['exclude_images'] : []);

        try {
            $result = (new \App\Services\MediaMigrator($this->connection, $this->media))
                ->migrateBatch(20, $excludeProducts, $excludeImages);
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', 'Arşive taşıma çalıştırılamadı: ' . $e->getMessage(), 500);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'media_migrate',
            sprintf('%s: %d taşındı, %d başarısız, %d kaldı', $user->email, $result['migrated'], count($result['failed']), $result['remaining']),
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, $result);
    }

    /**
     * POST /api/system/migrate-baseline — K49: migration defterini gerçeğe eşitler.
     *
     * Auth + CSRF arkasındadır (rota grubu). PM kararı: APP_KEY kanıtı GEREKMEZ —
     * eylem yıkıcı değildir (HİÇBİR DDL çalıştırmaz; yalnız var olduğu doğrulanan
     * nesnelerin kayıtlarını deftere işler). İdempotent; CLI eşi bin/migrate-baseline.php.
     */
    public function migrateBaseline(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $now = $this->clock->now();

        try {
            $migrator = new Migrator($this->connection->pdo(), $this->basePath . '/migrations');
            $result = $migrator->baseline();
            $pending = $migrator->pending();
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', 'Defter eşitleme çalıştırılamadı: ' . $e->getMessage(), 500);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'migrate_baseline',
            sprintf(
                '%s: %d deftere işlendi, %d atlandı, kalan bekleyen %d',
                $user->email,
                count($result['recorded']),
                count($result['skipped']),
                count($pending),
            ),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, [
            'recorded' => $result['recorded'],
            'skipped' => $result['skipped'],
            'pending_count' => count($pending),
        ]);
    }

    /**
     * POST /api/system/media-check — İE#10 5d: medya bütünlük denetimi + onarım (bir parti).
     *
     * Yerel /media kayıtlarını diskle karşılaştırır; dosyası kayıpları saklanan orijinal
     * adresten yeniden indirir. İdempotent; kaynağı olmayan kayıt bozulmaz, raporlanır.
     */
    public function mediaCheck(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);

        if ($this->media === null) {
            return Response::error($response, 'SERVER_ERROR', 'Medya servisi yapılandırılmamış.', 500);
        }

        try {
            $result = (new \App\Services\MediaIntegrity($this->connection, $this->media))->repairBatch(20);
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', 'Bütünlük denetimi çalıştırılamadı: ' . $e->getMessage(), 500);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'media_check',
            sprintf(
                '%s: %d denetlendi, %d kayıp, %d onarıldı, %d kapalı listede atlandı, %d başarısız',
                $user->email,
                $result['checked'],
                $result['missing'],
                $result['repaired'],
                $result['skipped_terminal'],
                count($result['failed']),
            ),
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, $result);
    }

    /**
     * GET /api/system/integrity/detay — bütünlük denetiminin İSİM İSİM listesi (İE#19 G4).
     *
     * Kimliksiz uç (`/api/system/integrity`) yalnız sayı döner; eksik/bozuk dosya
     * ADLARI burada, oturum arkasında verilir.
     */
    public function integrityDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticatedUser($request);

        return Response::success($response, (new \App\Services\IntegrityChecker($this->basePath))->check());
    }

    /**
     * GET /api/system/queue — KUYRUK SAĞLIĞI (İE#20 C3).
     *
     * Panel "Sistem durumu" ekranının veri kaynağı. Üç sayı ve bir yaş: bekleyen,
     * çalışan, ölü ve en eski bekleyen işin yaşı. Son ikisi ASIL SİNYALDİR:
     * ölü iş varsa bir şey kalıcı olarak bozuk; en eski bekleyen yaşlanıyorsa
     * cron koşmuyor demektir. İkisi de sessizce sürerse kimse fark etmez.
     */
    public function queue(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticatedUser($request);

        $kuyruk = new \App\Services\Kuyruk\JobQueue($this->connection);
        $now = $this->clock->now();

        try {
            $saglik = $kuyruk->saglik($now);
            $olu = $kuyruk->oluIsler(20);
        } catch (Throwable $e) {
            // Kuyruk tablosu henüz yoksa (migration bekliyor) ekran ÇÖKMEZ.
            return Response::success($response, [
                'kurulu' => false,
                'mesaj' => 'Kuyruk tablosu yok — veritabanı güncellemesi bekliyor olabilir.',
            ]);
        }

        return Response::success($response, [
            'kurulu' => true,
            'bekleyen' => $saglik['bekleyen'],
            'calisan' => $saglik['calisan'],
            'olu' => $saglik['olu'],
            'en_eski_bekleyen_dakika' => $saglik['en_eski_bekleyen_dakika'],
            // D9: işçinin GERÇEKTEN alabileceği iş sayısı. `bekleyen` ile
            // ayrışması bir arızadır ve panelde sayı olarak görünür.
            'alinabilir' => $saglik['alinabilir'],
            'ileri_tarihli' => $saglik['ileri_tarihli'],
            'en_yakin_calisacak_dakika' => $saglik['en_yakin_calisacak_dakika'],
            'turler' => $saglik['turler'],
            'olu_isler' => $olu,
            // İE#21 B11 metrikleri: "kuyruk çalışıyor mu" sorusu sayılarla yanıtlanır.
            'saatlik_biten' => $saglik['saatlik_biten'],
            'saatlik_olen' => $saglik['saatlik_olen'],
            'hata_orani_yuzde' => $saglik['hata_orani_yuzde'],
            'yeniden_denenen' => $saglik['yeniden_denenen'],
            // Cron koşmuyorsa bekleyen iş yaşlanır; eşik geçilince panel uyarır.
            'uyari' => $this->kuyrukUyarisi($saglik),
        ]);
    }

    /**
     * @param array<string, mixed> $saglik
     */
    private function kuyrukUyarisi(array $saglik): ?string
    {
        if ((int) $saglik['olu'] > 0) {
            return $saglik['olu'] . ' iş kalıcı olarak başarısız oldu (ölü raf). Hatalarını inceleyip yeniden deneyin.';
        }
        // D12 — "CRON KOŞMUYOR OLABİLİR" UYARISI KALKTI.
        //
        // Cron artık ZORUNLU DEĞİL: panel ziyareti ve yakalama da tur açıyor
        // (KuyrukTetikleyici). Kullanıcıya kurmadığı bir cron'u hatırlatmak,
        // olmayan bir arızayı bildirmekti — üstelik kuyruk/cron kavramının
        // kullanıcıya görünmemesi Ürün Sahibi kararıdır.
        //
        // YERİNE TEK BİR GERÇEK ARIZA UYARISI: birikme VAR ve hiçbir tetikleyici
        // işleyemiyor. İkisi birlikte doğruysa sistem gerçekten tıkanmıştır.
        $bekleyen = (int) ($saglik['alinabilir'] ?? 0);
        $yas = (int) ($saglik['en_eski_bekleyen_dakika'] ?? 0);
        if ($bekleyen > 0 && $yas > 60 && !$this->tetikleyiciCalistiMi()) {
            return $bekleyen . ' iş ' . $yas . ' dakikadır bekliyor ve hiçbir tetikleyici onları işleyemiyor. '
                . 'Panel isteklerinin arkasında çalışan tur başarısız oluyor olabilir — sunucu günlüğüne bakın.';
        }
        // B11: ölü iş yokken de sağlıksız olabilir — sürekli yeniden denenen bir
        // sağlayıcı, üçüncü denemede tuttuğu için "ölü" sayısına hiç yansımaz.
        if ((int) ($saglik['hata_orani_yuzde'] ?? 0) >= 30) {
            return 'Son bir saatte işlerin %' . $saglik['hata_orani_yuzde']
                . '\'i başarısız oldu. Sağlayıcı ayarlarını (Ayarlar > Çeviri) kontrol edin.';
        }

        return null;
    }

    /**
     * Son bir saat içinde herhangi bir tetikleyici tur AÇABİLDİ Mİ? (D12)
     *
     * Tetikleyici her turda ayarlara bir zaman damgası yazar. Damga tazeyse
     * sistem çalışıyordur ve bekleyen iş yalnız sıradadır; damga yoksa ya da
     * eskiyse turlar gerçekten koşmuyordur — uyarı ancak o zaman anlamlıdır.
     */
    private function tetikleyiciCalistiMi(): bool
    {
        $son = (new \App\Models\SettingsRepository($this->connection))
            ->get(\App\Services\Kuyruk\KuyrukTetikleyici::KEY_SON_TUR);
        if (!is_string($son) || $son === '') {
            return false;
        }

        try {
            $sonZaman = new \DateTimeImmutable($son);
        } catch (Throwable) {
            return false;
        }

        return ($this->clock->now()->getTimestamp() - $sonZaman->getTimestamp()) <= 3600;
    }

    /**
     * POST /api/system/queue/{id}/discard — ÖLÜ işi kuyruktan siler (İE#21 B11).
     *
     * @param array<string, string> $args
     */
    public function queueDiscard(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return Response::error($response, 'VALIDATION', 'Geçersiz iş kimliği.', 422);
        }

        $silindi = (new \App\Services\Kuyruk\JobQueue($this->connection))->vazgec($id);
        if (!$silindi) {
            return Response::error(
                $response,
                'CONFLICT',
                'Yalnız ÖLÜ işler silinebilir. Bekleyen ya da çalışan bir işi silmek kuyruğu sessizce eksiltirdi.',
                409,
            );
        }

        (new ActivityLog($this->connection))->record(
            'system',
            $id,
            'queue_discard',
            $user->email . ': ölü işten vazgeçildi (silindi)',
            \App\Core\ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, ['silindi' => true]);
    }

    /**
     * POST /api/system/queue/{id}/fix — ölü işin YÜKÜNÜ düzeltip yeniden kuyruğa alır.
     *
     * @param array<string, string> $args
     */
    public function queueFix(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return Response::error($response, 'VALIDATION', 'Geçersiz iş kimliği.', 422);
        }

        $govde = $request->getParsedBody();
        $yuk = is_array($govde) && is_array($govde['yuk'] ?? null) ? $govde['yuk'] : null;
        if ($yuk === null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'yuk' => 'Düzeltilmiş yük bir nesne olmalı.',
            ]);
        }

        $now = $this->clock->now();
        $duzeltildi = (new \App\Services\Kuyruk\JobQueue($this->connection))->yukuDuzelt($id, $yuk, $now);
        if (!$duzeltildi) {
            return Response::error($response, 'CONFLICT', 'Yalnız ÖLÜ işlerin yükü düzeltilebilir.', 409);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            $id,
            'queue_fix',
            $user->email . ': ölü işin yükü düzeltildi ve yeniden kuyruğa alındı',
            \App\Core\ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, ['duzeltildi' => true]);
    }

    /**
     * POST /api/system/queue/{id}/retry — ölü rafındaki işi yeniden kuyruğa alır.
     *
     * @param array<string, string> $args
     */
    public function queueRetry(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return Response::error($response, 'VALIDATION', 'Geçersiz iş kimliği.', 422);
        }

        $now = $this->clock->now();
        (new \App\Services\Kuyruk\JobQueue($this->connection))->dirilt($id, $now);

        (new ActivityLog($this->connection))->record(
            'system',
            $id,
            'queue_retry',
            $user->email . ': ölü iş yeniden kuyruğa alındı',
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, ['queued' => true]);
    }

    /**
     * K102 — kayıt sonrası yazılamayan bildirim sayacı.
     *
     * @return array{sayi: int, son: string|null}
     */
    private function bildirimHatalari(): array
    {
        try {
            $ayarlar = new \App\Models\SettingsRepository($this->connection);
            $sayi = (int) ($ayarlar->get(\App\Services\Bildirim\BildirimYayinci::KEY_HATA_SAYISI, '0') ?? '0');
            $son = $ayarlar->get(\App\Services\Bildirim\BildirimYayinci::KEY_SON_HATA);
        } catch (Throwable) {
            // Ayar tablosu okunamıyorsa sistem durumu ekranı yine açılmalı.
            return ['sayi' => 0, 'son' => null];
        }

        return ['sayi' => $sayi, 'son' => $son];
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authenticatedUser($request);

        $pending = [];
        $applied = 0;
        $databaseVersion = null;

        try {
            $migrator = new Migrator($this->connection->pdo(), $this->basePath . '/migrations');
            $pending = $migrator->pending();

            $statement = $this->connection->pdo()->query('SELECT COUNT(*) AS total FROM migrations');
            $row = $statement === false ? false : $statement->fetch();
            $applied = is_array($row) ? (int) $row['total'] : 0;

            // v1.2.1 F: `VERSION()` MySQL'e ÖZGÜDÜR ve SQLite'ta "no such
            // function" ile patlıyordu — bütün sistem durumu ekranı 500
            // veriyordu. Üretimde MySQL koştuğu için canlıda görünmüyordu ama
            // testlerde ucun TAMAMI erişilemezdi: teşhis yüzeyi, teşhis
            // edilemez hâldeydi.
            //
            // Sürüm bir SÜS BİLGİSİDİR: okunamıyorsa null kalır, ekranın geri
            // kalanı çalışmaya devam eder. Tek bir alan yüzünden bütün ekranı
            // düşürmek orantısız.
            $databaseVersion = $this->veritabaniSurumu();
        } catch (Throwable $e) {
            $kimlik = $this->logger === null
                ? null
                : \App\Core\GizliHata::kaydet($e, $this->logger, 'system.status');

            // C4 hattı: ham istisna metni yanıta GİRMEZ.
            return Response::error(
                $response,
                'SERVER_ERROR',
                'Sistem durumu okunamadı. Sorun sürerse destek kaydında hata kimliğini belirtin.',
                500,
                [],
                [],
                $kimlik,
            );
        }

        $lockDetails = $this->lock->read();

        return Response::success($response, [
            'app_version' => AppVersion::VALUE,
            'php_version' => PHP_VERSION,
            // K99: çalışma zamanı katalogları. SAĞLIKLI olanlar da listelenir —
            // boş bir liste "denetim yapılmadı" ile "her şey yolunda" arasında
            // ayırt edilemezdi.
            'kataloglar' => $this->katalogDurumu?->dokum() ?? [],
            // K102: kayıt SONRASI yazılamayan bildirimler. Birincil eylem
            // düşmedi ama olay KAYBOLDU — sayı sıfırdan büyükse bu görünmeli.
            'bildirim_hatalari' => $this->bildirimHatalari(),
            'db_version' => $databaseVersion,
            'installed_at' => is_array($lockDetails) && isset($lockDetails['installed_at'])
                ? (string) $lockDetails['installed_at']
                : null,
            'migrations' => [
                'applied' => $applied,
                'pending' => $pending,
                'pending_count' => count($pending),
            ],
            // K33 çift modu — panel "görseller hotlink'te" rozetini buradan okur (Faz 1D).
            'media' => [
                'mode' => $this->media?->mode(),
                'writable' => $this->media?->isWritable(),
            ],
            'setup_lock_in_database' => $this->lock->storesInDatabase(),
            // A6-EK: boş sözlükle çevrilmiş ürün sayısı. SALT OKUNUR; 0 ise
            // panel kartı GİZLER — sıfır gösteren uyarı bir süre sonra okunmaz
            // hâle gelir ve gerçek uyarıyı da görünmez kılar.
            'sozluksuz_ceviri' => $this->sozluksuzSayisi(),
        ]);
    }

    /**
     * Veritabanı sürümü — okunamıyorsa null (süs bilgisidir).
     *
     * `VERSION()` MySQL'e özgüdür; SQLite'ta istisna atar. Sürücüye göre
     * dallanmak yerine denenip bırakılır: cevap alınamazsa ekran "—" gösterir.
     */
    private function veritabaniSurumu(): ?string
    {
        try {
            $statement = $this->connection->pdo()->query('SELECT VERSION() AS version');
            $satir = $statement === false ? false : $statement->fetch();

            return is_array($satir) ? (string) $satir['version'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Sözlüksüz çevrilmiş ürün sayısı — sistem durumu okunurken PATLAMAZ.
     *
     * Sayım bir teşhis alanıdır; hesaplanamıyorsa (çeviri ayarları yok, tablo
     * eksik) bütün sistem durumu ekranını düşürmemeli. Hata yutulmuyor: 0
     * dönerken kart gizlenir, yani yanlış bir "her şey yolunda" iddiası da
     * üretilmez — kart zaten yalnız pozitif sayıda bir şey iddia eder.
     */
    private function sozluksuzSayisi(): int
    {
        try {
            return $this->sozluksuzSayac()?->urunSayisi() ?? 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * POST /api/system/sozluksuz-ceviri-yenile — A6-EK.
     *
     * Etkilenen ürünleri MEVCUT toplu çeviri yoluna kuyruğa alır. Yeni bir
     * çeviri hattı YOKTUR: aynı iş türü, aynı idempotent anahtar (`urun:<id>`),
     * dolayısıyla düğmeye iki kez basmak iki iş açmaz.
     *
     * K54 KORUNUR: kuyruk işi ürün alanlarına yazmaz, önbelleği doldurur; elle
     * düzeltilmiş alanlar zaten ezilmez.
     *
     * VERİ SİLİNMEZ: eski (öksüz) önbellek satırları oldukları yerde kalır;
     * doğru anahtarla üretilen yeni satırlar onların yerine OKUNUR.
     */
    public function sozluksuzCeviriYenile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);

        $sayac = $this->sozluksuzSayac();
        if ($sayac === null || $this->kuyruk === null) {
            return Response::error($response, 'SERVER_ERROR', 'Çeviri kuyruğu yapılandırılmamış.', 500);
        }

        try {
            $kimlikler = $sayac->urunKimlikleri();
        } catch (Throwable $e) {
            return Response::error($response, 'SERVER_ERROR', 'Etkilenen ürünler okunamadı: ' . $e->getMessage(), 500);
        }

        $now = $this->clock->now();
        foreach ($kimlikler as $urunId) {
            $this->kuyruk->ekle(
                \App\Services\Kuyruk\KuyrukIsleyicileri::TUR_CEVIRI,
                'urun:' . $urunId,
                ['urun_id' => $urunId],
                $now,
            );
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'sozluksuz_ceviri_yenile',
            sprintf('%s: %d ürün yeniden çeviri kuyruğuna alındı', $user->email, count($kimlikler)),
            ClientIp::from($request),
            $now,
        );

        return Response::success($response, [
            'kuyruga_alinan' => count($kimlikler),
        ]);
    }

    /** POST /api/system/migrate — bekleyen migration'ları koşar (auth + CSRF). */
    public function migrate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->authenticatedUser($request);
        $now = $this->clock->now();

        try {
            $migrator = new Migrator($this->connection->pdo(), $this->basePath . '/migrations');
            $applied = $migrator->run();
        } catch (Throwable $e) {
            (new ActivityLog($this->connection))->record(
                'system',
                null,
                'migrate_failed',
                $user->email,
                ClientIp::from($request),
                $now,
                ActivityLog::ACTOR_ADMIN,
                $user->id,
            );

            return Response::error($response, 'SERVER_ERROR', 'Migration çalıştırılamadı: ' . $e->getMessage(), 500);
        }

        (new ActivityLog($this->connection))->record(
            'system',
            null,
            'migrate',
            sprintf('%s (%d migration)', $user->email, count($applied)),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );

        return Response::success($response, [
            'applied' => $applied,
            'applied_count' => count($applied),
        ]);
    }

    private function authenticatedUser(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(Auth::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new LogicException('Korumalı uç Auth middleware olmadan çağrıldı.');
        }

        return $user;
    }
}
