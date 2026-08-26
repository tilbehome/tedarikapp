<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Connection;
use App\Models\InboxRepository;
use App\Models\ProductRepository;
use DateTimeImmutable;
use PDOException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * YAKALAMANIN UYGULANMASI — TEK ATOMİK BLOK, TEK KURAL YERİ (İE#19 G6 · E8).
 *
 * NEDEN VAR: yakalamayı listeye açan iki yol vardı (eklenti doğrudan hedef listeye,
 * panel Gelen Kutusu'ndan taşıma) ve her ikisi de aynı işi KENDİ sırasıyla yapıyordu:
 *
 *   1. `capture_id` var mı diye BAK,
 *   2. ürünü yaz (kendi transaction'ı),
 *   3. kuyruk satırını AYRI bir insert ile aç,
 *   4. kuyruk satırını ürüne bağla.
 *
 * Aradaki her boşluk bir yarıştır. Eklenti ağ hatasında isteği tekrarlar; iki istek
 * aynı anda 1. adımı geçerse İKİ ÜRÜN yazılır ve 3. adımda `capture_id` UNIQUE
 * kısıtı patlar — kullanıcı 500 görür, listede mükerrer ürün kalır. İdempotans
 * "kontrol edip yazmak" ile değil, YAZMAYI REZERVASYONA BAĞLAMAKLA sağlanır.
 *
 * BURADAKİ SIRA: `capture_id` satırı (UNIQUE) transaction'ın İLK yazımıdır. Yarışı
 * kaybeden istek tam orada kısıt ihlali alır, transaction geri sarılır (ürün de
 * yazılmaz) ve İLK kaydın kimliği döndürülür. Ağ işi (görsel indirme) transaction'a
 * girmez — hazırlık önce koşar.
 *
 * TERMİNAL LİSTE KURALI (K37 §B4) BURADA ZORLANIR: `completed`/`cancelled` bir
 * listeye ne eklentiden ne kuyruktan ne de medya onarımından (E8) ürün girebilir.
 * Eskiden bu kural yalnız panel uçlarında denetleniyordu; eklenti yolu kuralın
 * dışındaydı — yani kapalı bir listenin belgesi sessizce değişebiliyordu.
 */
final class CaptureApplier
{
    /** MySQL/SQLite ortak: kısıt ihlali sınıfı. */
    private const SQLSTATE_CONSTRAINT = '23000';

    public function __construct(
        private readonly Connection $connection,
        private readonly CaptureService $capture,
        private readonly InboxRepository $inbox,
        private readonly ProductRepository $products,
        private readonly ListMutationPolicy $policy,
        private readonly ActivityLog $activity,
        /**
         * MEDYA SERVİSİ ZORUNLUDUR (rc8-01 / dış denetim F-01, 26 Ağu 2026).
         *
         * SAHA KANITI: bu parametre `?MediaService = null` idi ve `AppBuilder`
         * onu HİÇ geçmiyordu ("bilinçli boş bırakılır" yorumuyla). Sonuç: arşiv
         * modunda her yakalama diske `<ad>.jpg.tmp` bırakıyor, veritabanına ise
         * çözülemeyen `/media/<ad>.jpg` yazıyordu. Gerçek `AppBuilder` ile
         * koşulan kanıt: DB `/media/0404….jpg`, diskte `0404….jpg.tmp`, dosya YOK.
         *
         * Opsiyonel bırakmak, yanlış kompozisyonu SESSİZ bir çalışma zamanı
         * kusuruna çeviriyordu. Artık zorunlu: eksik wiring test zamanında patlar.
         */
        private readonly MediaService $media,
        private readonly LoggerInterface $logger,
        // İE#21 B3 (saha bulgusu): ürün≠ilan ayrımı veri akışında da yaşasın —
        // her yakalama ürünün yanında İLAN kaydını da açar.
        private readonly ?\App\Services\Ilan\IlanYazici $ilanlar = null,
        /**
         * D11a: galeri görselleri yakalamada indirilmez (ağ, yakalamayı
         * bekletirdi) — bir MEDYA İŞİ yazılır, kuyruk arka planda indirir.
         */
        private readonly ?\App\Services\Kuyruk\JobQueue $kuyruk = null,
    ) {
    }

    /**
     * E7: geçici görsel dosyası, veritabanı işleminin SONUCUNA göre kalıcılaşır.
     *
     * @param array<string, mixed> $media
     */
    private function medyayiSonlandir(
        array $media,
        bool $basarili,
        DateTimeImmutable $now,
        ?int $urunId = null,
    ): void {
        /** @var array{mode: string, path: string|null, url: string, temp: string|null} $tutamak */
        $tutamak = $this->capture->mediaHandle($media);

        if (!$basarili) {
            $this->media->discard($tutamak);

            return;
        }

        // rc8-01 (F-13): `commit()` DÖNÜŞÜ DENETLENİR.
        //
        // `rename()` başarısız olabilir (izin kaybı, disk dolu, aynı anda silinen
        // dosya). Eskiden dönüş yutuluyordu; ürün kaydı diskte olmayan bir
        // `/media/...` adresine işaret etmeye devam ediyor ve kimse fark
        // etmiyordu. Kırık bir görsel, olmayan bir görselden daha kötüdür:
        // kullanıcı boş kareye bakar ve nedenini bilmez.
        if ($this->media->commit($tutamak)) {
            return;
        }

        $this->logger->error('Medya kalıcı ada taşınamadı; kayıt kaynak adrese düşürüldü', [
            'urun_id' => $urunId,
            'gecici' => $tutamak['temp'],
            'hedef' => $tutamak['path'],
        ]);

        // ÜRÜN KAYDI `.tmp`YE İŞARET ETMEZ: kaynak adres (hotlink) yazılır.
        // Görsel uzak sunucudan gelir; D11a'nın medya işi bir sonraki turda
        // yeniden indirmeyi dener ve arayüz bu satırı "uzak" diye işaretler.
        $kaynak = is_string($media['main_source'] ?? null) ? (string) $media['main_source'] : null;
        if ($urunId !== null && $kaynak !== null && $kaynak !== '') {
            $this->products->update(
                $urunId,
                ['main_image' => $kaynak, 'main_image_source' => $kaynak],
                $now,
            );
        }
    }

    /**
     * Eklenti yolu: yükü doğrudan hedef listeye uygular.
     *
     * @param array<string, mixed> $payload doğrulanmış v2 yükü
     * @param array<string, mixed> $list    hedef liste satırı
     *
     * @return array{inbox_id: int, product_id: int|null, status: string, idempotent_replay: bool}
     *
     * @throws ListImmutableException terminal listeye ürün girmez
     * @throws CaptureException       görsel adresi reddedildi
     */
    public function applyToList(
        array $payload,
        array $list,
        DateTimeImmutable $now,
        string $ip,
        string $actorType = ActivityLog::ACTOR_EXTENSION,
        ?int $actorId = null,
        ?string $requestId = null,
    ): array {
        $this->policy->assertMutable($list);

        $listId = (int) $list['id'];
        $media = $this->capture->prepareMedia($payload); // AĞ: transaction dışında

        try {
            /** @var array{inbox_id: int, product_id: int|null, status: string, idempotent_replay: bool} $sonuc */
            $sonuc = $this->connection->transaction(function () use ($payload, $listId, $media, $now, $ip, $actorType, $actorId, $requestId): array {
                // (1) REZERVASYON: capture_id UNIQUE satırı ilk yazımdır.
                $inboxId = $this->inbox->create($this->capture->inboxFields($payload, 'assigned'), $now);

                // (2) ürün + galeri + liste revizyonu
                $productId = $this->capture->insertProduct($payload, $listId, $media, $now);

                // (3) kuyruk satırı ürüne bağlanır
                $this->inbox->markAssigned($inboxId, $productId, $now);

                // (3b) İLAN kaydı — aynı transaction içinde: ilansız ürün, Keşif'te
                // skorsuz ve kaynaksız görünür (İE#21 B3 bulgusu).
                $this->ilaniAc($productId, $payload, $now);

                // (4) İLK DURUM VE AKTİVİTE İZİ (G6): eklentiden gelen ürünler
                // tarihçesiz doğuyordu — panelde "bu ürün nereden geldi?" sorusunun
                // cevabı yoktu ve durum grafiği ilk adımı hiç görmüyordu.
                $this->products->recordStatusChange(
                    $productId,
                    null,
                    StateMachine::PRODUCT_TO_ORDER,
                    $now,
                    $actorType,
                    $actorId,
                    $requestId,
                );
                $this->activity->record(
                    'product',
                    $productId,
                    'product_created',
                    'Yakalamadan eklendi (' . (string) ($payload['source']['platform'] ?? 'bilinmiyor') . ')',
                    $ip,
                    $now,
                    $actorType,
                    $actorId,
                );

                return [
                    'inbox_id' => $inboxId,
                    'product_id' => $productId,
                    'status' => 'assigned',
                    'idempotent_replay' => false,
                ];
            });

            $this->medyayiSonlandir($media, true, $now, $sonuc['product_id']);
            $this->medyaIsiYaz($sonuc['product_id'], $now);

            return $sonuc;
        } catch (PDOException $exception) {
            $ilk = $this->kisitIhlaliyseIlkSonuc($exception, $payload);
            // Yarışı kaybettiysek de dosya yetim kalmaz: ürün yazılmadı, görsel silinir.
            $this->medyayiSonlandir($media, false, $now);
            if ($ilk === null) {
                throw $exception;
            }

            return $ilk;
        } catch (Throwable $exception) {
            $this->medyayiSonlandir($media, false, $now);

            throw $exception;
        }
    }

    /**
     * GALERİ İNDİRME İŞİ (D11a saha bulgusu, 25 Ağu 2026).
     *
     * Yakalamada yalnız ana görsel indiriliyordu; galeri satırları alicdn
     * adresiyle `remote` kalıyor ve tarayıcı onları çizemiyordu (alicdn Referer
     * ACL) — çekmecede "5 görsel" yazarken dördü boş kare görünüyordu.
     *
     * İş kuyruğa yazılır, yakalama BEKLEMEZ. Kuyruk yoksa (eski kurulum, test)
     * sessizce atlanır: yakalama medya yüzünden başarısız olmamalıdır.
     */
    private function medyaIsiYaz(?int $urunId, DateTimeImmutable $now): void
    {
        if ($this->kuyruk === null || $urunId === null || $urunId <= 0) {
            return;
        }

        try {
            $this->kuyruk->ekle(
                \App\Services\Kuyruk\KuyrukIsleyicileri::TUR_MEDYA,
                'urun:' . $urunId,
                ['urun_id' => $urunId],
                $now,
            );
        } catch (Throwable) {
            // Kuyruk yazılamadıysa görseller uzak kalır ve arayüz bunu işaretler;
            // yakalamayı geri almak orantısız olurdu.
        }
    }

    /**
     * Kuyruk yolu: mevcut bir `inbox_items` satırını listeye taşır.
     *
     * Rezervasyon burada INSERT değil, DURUM GEÇİŞİDİR: satır yalnızca hâlâ
     * `pending`/`error` iken sahiplenilir. İki eşzamanlı "taşı" isteğinden biri
     * 0 satır günceller ve ürün yazmadan döner.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $list
     *
     * @return array{inbox_id: int, product_id: int|null, status: string, idempotent_replay: bool}
     *
     * @throws ListImmutableException
     * @throws CaptureException
     */
    public function applyInboxItem(
        int $inboxId,
        array $payload,
        array $list,
        DateTimeImmutable $now,
        string $ip,
        ?int $actorId = null,
        ?string $requestId = null,
    ): array {
        $this->policy->assertMutable($list);

        $listId = (int) $list['id'];
        $media = $this->capture->prepareMedia($payload);

        try {
            /** @var array{inbox_id: int, product_id: int|null, status: string, idempotent_replay: bool} $sonuc */
            $sonuc = $this->connection->transaction(function () use ($inboxId, $payload, $listId, $media, $now, $ip, $actorId, $requestId): array {
                if (!$this->inbox->claim($inboxId, $now)) {
                    // Başka bir istek önce davrandı: ürün YAZILMAZ.
                    $mevcut = $this->inbox->find($inboxId);

                    return [
                        'inbox_id' => $inboxId,
                        'product_id' => is_array($mevcut) && $mevcut['assigned_product_id'] !== null
                            ? (int) $mevcut['assigned_product_id']
                            : null,
                        'status' => is_array($mevcut) ? (string) $mevcut['status'] : 'assigned',
                        'idempotent_replay' => true,
                    ];
                }

                $productId = $this->capture->insertProduct($payload, $listId, $media, $now);
                $this->inbox->markAssigned($inboxId, $productId, $now);
                $this->ilaniAc($productId, $payload, $now);
                $this->products->recordStatusChange(
                    $productId,
                    null,
                    StateMachine::PRODUCT_TO_ORDER,
                    $now,
                    ActivityLog::ACTOR_ADMIN,
                    $actorId,
                    $requestId,
                );
                $this->activity->record(
                    'product',
                    $productId,
                    'product_created',
                    'Gelen Kutusu\'ndan taşındı',
                    $ip,
                    $now,
                    ActivityLog::ACTOR_ADMIN,
                    $actorId,
                );

                return [
                    'inbox_id' => $inboxId,
                    'product_id' => $productId,
                    'status' => 'assigned',
                    'idempotent_replay' => false,
                ];
            });
        } catch (Throwable $exception) {
            $this->medyayiSonlandir($media, false, $now);

            throw $exception;
        }

        $this->medyayiSonlandir($media, !$sonuc['idempotent_replay'], $now, $sonuc['product_id']);

        return $sonuc;
    }

    /**
     * Kısıt ihlali (yarışı kaybeden istek) mi? Öyleyse İLK kaydın kimliğini döndürür.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{inbox_id: int, product_id: int|null, status: string, idempotent_replay: bool}|null
     */
    private function kisitIhlaliyseIlkSonuc(PDOException $exception, array $payload): ?array
    {
        if (($exception->getCode() !== self::SQLSTATE_CONSTRAINT)
            && !str_contains(strtolower($exception->getMessage()), 'unique')) {
            return null;
        }

        $captureId = is_string($payload['capture_id'] ?? null) ? $payload['capture_id'] : '';
        if ($captureId === '') {
            return null;
        }

        try {
            $ilk = $this->inbox->findByCaptureId($captureId);
        } catch (Throwable) {
            return null;
        }
        if ($ilk === null) {
            return null; // kısıt başka bir sebepten patlamış — hatayı yut ma
        }

        return [
            'inbox_id' => (int) $ilk['id'],
            'product_id' => $ilk['assigned_product_id'] === null ? null : (int) $ilk['assigned_product_id'],
            'status' => (string) $ilk['status'],
            'idempotent_replay' => true,
        ];
    }

    /**
     * İlan kaydını açar. Yazıcı bağlanmamışsa (eski testler, dar bağlam) sessizce
     * atlanır — yakalamanın kendisi ilan kaydına BAĞIMLI DEĞİLDİR; ilan bir
     * zenginleştirmedir ve onun eksikliği ürünün kaydını engellememelidir.
     *
     * @param array<string, mixed> $payload
     */
    private function ilaniAc(int $productId, array $payload, DateTimeImmutable $now): void
    {
        if ($this->ilanlar === null) {
            return;
        }

        $kademeler = $payload['normalized']['price_tiers'] ?? null;
        /** @var list<array<string, mixed>>|null $kademeler */
        $kademeler = is_array($kademeler) ? array_values(array_filter($kademeler, 'is_array')) : null;

        $this->ilanlar->yaz($productId, $now, $kademeler);
    }
}
