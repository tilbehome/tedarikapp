<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Connection;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use DateTimeImmutable;

/**
 * Yakalama yükünü ürüne/kuyruğa çeviren çekirdek (İE#11 — docs/04 §2c v2).
 *
 * İki çağıran vardır: ExtensionController (hedef liste seçiliyse doğrudan ürün) ve
 * InboxController (kuyruktan "listeye taşı"). Kural tek yerde yaşar: normalized blok
 * ürüne eşlenir, ana görsel MediaService'ten geçer (arşiv modunda indirilir), kalan
 * görseller product_images'a REMOTE satır olarak yazılır — K47 arşive-taşıma hattı
 * onları sonra indirir. Para alanları string taşınır (K14).
 */
final class CaptureService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly MediaService $media,
        private readonly InputValidator $validator,
    ) {
    }

    /**
     * v2 yükünü doğrular; hatalıysa alan→mesaj listesi döner (boş = geçerli).
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    public function validate(array $payload): array
    {
        $errors = [];

        $captureId = $payload['capture_id'] ?? null;
        if (!is_string($captureId) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $captureId) !== 1) {
            $errors['capture_id'] = 'capture_id UUIDv4 olmalı.';
        }
        if ((int) ($payload['schema_version'] ?? 0) !== 2) {
            $errors['schema_version'] = 'schema_version 2 olmalı (docs/04 §2c v2).';
        }
        foreach (['extension_version', 'parser_version'] as $field) {
            if (!is_string($payload[$field] ?? null) || $payload[$field] === '') {
                $errors[$field] = $field . ' zorunlu.';
            }
        }

        $source = $payload['source'] ?? null;
        if (!is_array($source)) {
            $errors['source'] = 'source bloğu zorunlu.';
        } else {
            if (!is_string($source['platform'] ?? null) || $source['platform'] === '' || mb_strlen((string) $source['platform']) > 30) {
                $errors['source.platform'] = 'platform zorunlu (≤30).';
            }
            if (!is_string($source['url'] ?? null) || !str_starts_with((string) $source['url'], 'https://') || mb_strlen((string) $source['url']) > 1000) {
                $errors['source.url'] = 'url https ve ≤1000 olmalı.';
            }
        }

        $normalized = $payload['normalized'] ?? null;
        if (!is_array($normalized)) {
            $errors['normalized'] = 'normalized bloğu zorunlu.';
        } else {
            $name = $normalized['name'] ?? null;
            if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 300) {
                $errors['normalized.name'] = 'ad zorunlu (1–300).';
            }
            $price = $normalized['price_yuan'] ?? null;
            if (!is_string($price) || $this->validator->rate($price, 'Fiyat') !== null && !preg_match('/^\d{1,7}(\.\d{1,2})?$/', $price)) {
                $errors['normalized.price_yuan'] = 'price_yuan string decimal olmalı (0–9999999.99).';
            }
        }

        if (!is_array($payload['raw'] ?? null)) {
            $errors['raw'] = 'raw bloğu zorunlu (orijinal veri kaybolmaz).';
        }

        $qty = $payload['qty'] ?? 1;
        if (!is_int($qty) || $qty < 1 || $qty > 1000000) {
            $errors['qty'] = 'qty 1–1.000.000 tam sayı olmalı.';
        }

        return $errors;
    }

    /**
     * K25 mükerrer denetimi: platform+external_id mevcut bir üründe var mı?
     *
     * @param array<string, mixed> $payload
     *
     * @return array{product_id: int, list_id: int, list_name: string}|null
     */
    public function duplicateOf(array $payload): ?array
    {
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $platform = (string) ($source['platform'] ?? '');
        $externalId = (string) ($source['external_id'] ?? '');
        if ($platform === '' || $externalId === '') {
            return null;
        }

        $existing = $this->products->findDuplicate($platform, $externalId);
        if ($existing === null) {
            return null;
        }

        $list = $this->lists->find((int) $existing['list_id']);

        return [
            'product_id' => (int) $existing['id'],
            'list_id' => (int) $existing['list_id'],
            'list_name' => $list === null ? '' : (string) $list['name'],
        ];
    }

    /**
     * MEDYA HAZIRLIĞI — AĞ İŞİ, TRANSACTION DIŞINDA (İE#19 G6).
     *
     * Görsel indirme saniyeler sürebilir; bunu transaction içine almak satır
     * kilitlerini ağ süresince tutar ve paylaşımlı hostingde kilit zaman aşımı
     * üretir. Bu yüzden ağ ÖNCE koşar, DB yazımı sonra tek atomik blokta yapılır.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{main_image: string|null, main_source: string|null, gallery: list<string>, temp: string|null, path: string|null}
     */
    public function prepareMedia(array $payload): array
    {
        /** @var array<string, mixed> $normalized */
        $normalized = $payload['normalized'];
        $images = is_array($normalized['images'] ?? null) ? array_values(array_filter($normalized['images'], 'is_string')) : [];

        // Ana görsel MediaService'ten geçer: SSRF denetimi her modda; arşiv modunda indirilir.
        $mainImage = null;
        $mainSource = null;
        $temp = null;
        $path = null;
        if ($images !== []) {
            $mainSource = array_shift($images);
            try {
                // E7: ertelenmiş yazım — dosya `.tmp` adında bekler, kalıcı ada
                // ancak ürün satırı COMMIT olunca taşınır (yetim medya yok).
                $stored = $this->media->store($mainSource, ertele: true);
                $mainImage = $stored['url'];
                $temp = $stored['temp'] ?? null;
                $path = $stored['path'];
            } catch (MediaDeniedException $e) {
                throw new CaptureException('Görsel adresi reddedildi: ' . $e->getMessage());
            } catch (MediaException) {
                $mainImage = $mainSource; // K47 dayanıklılığı: indirme hatası kaydı bozmaz — remote kalır
            }
        }

        return [
            'main_image' => $mainImage,
            'main_source' => $mainSource,
            'gallery' => $images,
            'temp' => $temp,
            'path' => $path,
        ];
    }

    /**
     * Yükü verilen listeye ÜRÜN olarak açar (transaction çağıranda değil — burada).
     *
     * GERİYE UYUM: tek başına çağrılabilir; İE#19 G6 sonrası uygulama yolu
     * `CaptureApplier` üzerinden gider (kuyruk rezervasyonu + ürün + tarihçe
     * TEK transaction).
     *
     * @param array<string, mixed> $payload doğrulanmış v2 yükü
     */
    public function createProduct(array $payload, int $listId, DateTimeImmutable $now): int
    {
        $media = $this->prepareMedia($payload);

        try {
            $productId = (int) $this->connection->transaction(
                fn (): int => $this->insertProduct($payload, $listId, $media, $now),
            );
        } catch (\Throwable $e) {
            $this->media->discard($this->mediaHandle($media));

            throw $e;
        }

        // rc8-01 (F-13): taşıma başarısızsa kayıt `.tmp`ye işaret etmesin —
        // ürün adresi kaynak adrese düşer, görsel kaybolmaz.
        if (!$this->media->commit($this->mediaHandle($media))) {
            // rc8/E1: yetim `.tmp` diskte bırakılmaz — kayıt kaynak adrese
            // düştüğü için o dosyaya bir daha bakılmayacak, F-14 envanteri
            // ise her başarısız taşımada yeni kalıntı sayardı.
            $this->media->discard($this->mediaHandle($media));

            $kaynak = is_string($media['main_source'] ?? null) ? (string) $media['main_source'] : null;
            if ($kaynak !== null && $kaynak !== '') {
                $this->products->update(
                    $productId,
                    ['main_image' => $kaynak, 'main_image_source' => $kaynak],
                    $now,
                );
            }
        }

        return $productId;
    }

    /**
     * Galeri adreslerini SSRF beyaz listesinden geçirir (rc8-01 / F-16).
     *
     * Reddedilen adres SESSİZCE düşürülür ve yakalama SÜRER: tek bir kötü
     * galeri satırı yüzünden ürünü reddetmek, kullanıcıya "yakalama bozuk"
     * dedirtirdi. Ana görsel reddedilirse akış zaten `MediaDeniedException`
     * ile durur — orası veri kaybıdır, burası değil.
     *
     * @param list<string> $adresler
     *
     * @return list<string>
     */
    private function guvenliGaleri(array $adresler): array
    {
        $gecenler = [];
        foreach ($adresler as $adres) {
            try {
                $this->media->assertAllowed($adres);
                $gecenler[] = $adres;
            } catch (MediaDeniedException) {
                // Beyaz liste dışı / iç ağ adresi: kayda GİRMEZ.
                continue;
            }
        }

        return $gecenler;
    }

    /**
     * MediaService::commit/discard'ın beklediği tutamak.
     *
     * @param array{main_image: string|null, main_source: string|null, gallery: list<string>, temp?: string|null, path?: string|null} $media
     *
     * @return array{mode: string, path: string|null, url: string, temp: string|null}
     */
    public function mediaHandle(array $media): array
    {
        return [
            'mode' => 'download',
            'path' => $media['path'] ?? null,
            'url' => (string) ($media['main_image'] ?? ''),
            'temp' => $media['temp'] ?? null,
        ];
    }

    /**
     * SAF DB YAZIMI — kendi transaction'ını AÇMAZ (çağıran atomik bloğu yönetir).
     *
     * @param array<string, mixed> $payload
     * @param array{main_image: string|null, main_source: string|null, gallery: list<string>, temp?: string|null, path?: string|null} $media
     */
    public function insertProduct(array $payload, int $listId, array $media, DateTimeImmutable $now): int
    {
        /** @var array<string, mixed> $source */
        $source = $payload['source'];
        /** @var array<string, mixed> $normalized */
        $normalized = $payload['normalized'];
        $mainImage = $media['main_image'];
        $mainSource = $media['main_source'];
        $images = $media['gallery'];

        $productId = $this->products->create($listId, [
            'platform' => (string) $source['platform'],
            'external_id' => isset($source['external_id']) ? (string) $source['external_id'] : null,
            'name' => mb_substr(trim((string) $normalized['name']), 0, 300),
            'name_original' => isset($payload['raw']['title']) && is_string($payload['raw']['title'])
                ? mb_substr($payload['raw']['title'], 0, 500)
                : null,
            // D12: KAYNAK DİLİ YAKALAMA ANINDA İŞLENİR. Sonradan tahmin etmek,
            // her turda yeniden tahmin etmek demektir; kaynak dili bilinmeyen
            // ürün kendi diline "çevrilmeye" kalkışılabilir (TR kaynakta felaket).
            'source_lang' => \App\Services\Translation\DilSaptayici::sapta(
                isset($payload['raw']['title']) && is_string($payload['raw']['title'])
                    ? (string) $payload['raw']['title']
                    : (string) $normalized['name'],
            ),
            'url' => (string) $source['url'],
            'vendor_name' => isset($source['seller_name']) ? mb_substr((string) $source['seller_name'], 0, 200) : null,
            'vendor_url' => isset($source['seller_url']) && is_string($source['seller_url']) ? mb_substr($source['seller_url'], 0, 1000) : null,
            'sku_matrix' => isset($normalized['sku_matrix']) && is_array($normalized['sku_matrix'])
                ? json_encode($normalized['sku_matrix'], JSON_UNESCAPED_UNICODE)
                : null,
            'main_image' => $mainImage,
            'main_image_source' => $mainSource,
            'video_url' => isset($normalized['video_url']) && is_string($normalized['video_url']) ? $normalized['video_url'] : null,
            'qty' => (int) ($payload['qty'] ?? 1),
            'units_per_carton' => isset($payload['units_per_carton']) && is_int($payload['units_per_carton']) ? $payload['units_per_carton'] : null,
            // İE#11 EK-3 (2): RAW blok OLDUĞU GİBİ ürüne yazılır (v2 TechnicalProfile'ın
            // ham girdisi — docs/v2/02). Panel bunu göstermez; veri kaybolmaz.
            'raw_attributes' => json_encode($payload['raw'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'country_of_origin' => self::countryCode($normalized['country_of_origin'] ?? null),
            'country_of_dispatch' => self::countryCode($normalized['country_of_dispatch'] ?? null),
            'price_yuan' => (string) $normalized['price_yuan'],
            'note' => isset($payload['note']) && is_string($payload['note']) ? mb_substr($payload['note'], 0, 2000) : null,
        ], $now);

        // Kalan görseller REMOTE galeri satırları — K47 arşive-taşıma hattı sonra indirir.
        //
        // rc8-01 (F-16): GALERİ ADRESLERİ DE SSRF DENETİMİNDEN GEÇER. Eskiden
        // yalnız `https://` ön-eki ve uzunluk bakılıyordu; ana görsel `UrlGuard`
        // görüyor, galeri görmüyordu. Bu satırlar hem kullanıcıya servis edilir
        // hem de medya işi tarafından İNDİRİLİR — yani iç ağa çıkma yüzeyidir.
        $this->products->addRemoteImages($productId, $this->guvenliGaleri(array_slice($images, 0, 20)));
        $this->lists->bumpRevision($listId, $now);

        return $productId;
    }

    /** ISO 3166-1 alpha-2 doğrulaması — geçersizse null (uydurma menşe yazılmaz). */
    private static function countryCode(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $code = strtoupper(trim($value));

        return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;
    }

    /**
     * Kuyruk kaydı için liste kolonlarını yükten çıkarır.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function inboxFields(array $payload, string $status = 'pending', ?string $errorNote = null): array
    {
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $normalized = is_array($payload['normalized'] ?? null) ? $payload['normalized'] : [];
        $images = is_array($normalized['images'] ?? null) ? $normalized['images'] : [];

        return [
            'capture_id' => (string) ($payload['capture_id'] ?? ''),
            'status' => $status,
            'platform' => is_string($source['platform'] ?? null) && $source['platform'] !== '' ? $source['platform'] : 'bilinmiyor',
            'external_id' => isset($source['external_id']) ? mb_substr((string) $source['external_id'], 0, 100) : null,
            'name' => isset($normalized['name']) && is_string($normalized['name']) ? mb_substr($normalized['name'], 0, 300) : null,
            'price_yuan' => isset($normalized['price_yuan']) && is_string($normalized['price_yuan'])
                && preg_match('/^\d{1,7}(\.\d{1,4})?$/', $normalized['price_yuan']) === 1 ? $normalized['price_yuan'] : null,
            'image_url' => isset($images[0]) && is_string($images[0]) ? mb_substr($images[0], 0, 1000) : null,
            'url' => isset($source['url']) && is_string($source['url']) ? mb_substr($source['url'], 0, 1000) : null,
            'payload_json' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_note' => $errorNote === null ? null : mb_substr($errorNote, 0, 500),
        ];
    }
}
