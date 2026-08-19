<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Connection;
use App\Core\Response;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Services\ActivityLog;
use App\Services\InputValidator;
use App\Services\ListMutationPolicy;
use App\Services\ListPresenter;
use App\Services\MediaDeniedException;
use App\Services\MediaException;
use App\Services\MediaService;
use App\Services\StateMachine;
use App\Services\StateTransitionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Ürün uçları — docs/10 §4.
 *
 * Listenin `revision` sayacı ürün ekleme/silme ve fiyat/adet/sıra değişiminde artar (K25):
 * "çıktı güncel değil" rozeti buna bakar, not düzenlemek çıktıyı eskitmez.
 *
 * K37 §B4: terminal (completed/cancelled) listenin ürünlerine HİÇBİR mutasyon yapılamaz.
 * K37 §B5: çok adımlı yazmalar tek transaction'da koşar — yarım kayıt kalmaz.
 */
final class ProductController extends ApiController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly ListPresenter $presenter,
        private readonly InputValidator $validator,
        private readonly StateMachine $stateMachine,
        private readonly ListMutationPolicy $mutationPolicy,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
        private readonly ?MediaService $media = null,
    ) {
    }

    /**
     * Terminal listeye mutasyon denemesinin standart yanıtı (K37 §B4).
     *
     * @param array<string, mixed> $list
     */
    private function listImmutable(ResponseInterface $response, array $list): ResponseInterface
    {
        return Response::error(
            $response,
            'LIST_IMMUTABLE',
            sprintf(
                'Liste "%s" durumunda ve artık değiştirilemez. Devam etmek için listeyi kopyalayın.',
                (string) $list['status'],
            ),
            422,
        );
    }

    /**
     * Verilen görsel URL'sini MediaService'e teslim eder (docs/10 §4, İE#8 §2).
     *
     * `download` modunda görsel indirilip yeniden kodlanır ve yerel yol saklanır;
     * `hotlink` modunda (K33 — üretimde diske yazılamıyor) URL beyaz liste denetiminden
     * geçirilip olduğu gibi bırakılır. Her iki modda da SSRF denetimi yapılır.
     *
     * @param array<string, mixed> $body istek gövdesi; `main_image` yerinde güncellenir
     *
     * @return string|null alan hatası (varsa)
     */
    private function ingestMainImage(array &$body): ?string
    {
        if ($this->media === null || !array_key_exists('main_image', $body)) {
            return null;
        }

        $value = $body['main_image'];
        if ($value === null || $value === '' || $this->isStoredMediaPath($value)) {
            return null;
        }
        if (!is_string($value)) {
            return 'Görsel adresi metin olmalı.';
        }

        try {
            $stored = $this->media->store($value);
        } catch (MediaDeniedException $e) {
            // Güvenlik reddi (beyaz liste dışı / iç ağ / http): kayıt REDDEDİLİR.
            return $e->getMessage();
        } catch (MediaException) {
            // K47 kırık-görsel dayanıklılığı: indirme hatası (403/404/zaman aşımı/bozuk
            // içerik) ürün kaydını BOZMAZ — URL uzak (remote) olarak saklanır, panel yer
            // tutucu + "yeniden dene" gösterir; arşive taşıma sonraki denemede yapılır.
            return null;
        }

        $body['main_image'] = $stored['url'];

        return null;
    }

    /**
     * Sistemin kendi ürettiği yerel medya yolu mu? (`/media/…`)
     *
     * Ürün yeniden kaydedilirken bu değer forma geri gelir; onu tekrar indirmeye
     * kalkmak da https doğrulamasından geçirmek de yanlış olur.
     */
    private function isStoredMediaPath(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, '/');
    }

    /**
     * GET /api/lists/{id}/products
     *
     * @param array<string, string> $args
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $listId = $this->intArg($args, 'id');
        $list = $listId === null ? null : $this->lists->find($listId);
        if ($list === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }

        $filters = [];
        $status = $this->query($request, 'status');
        if ($status !== '') {
            $error = $this->validator->enum($status, array_keys(StateMachine::PRODUCT_TRANSITIONS), 'Durum');
            if ($error !== null) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['status' => $error]);
            }
            $filters['status'] = $status;
        }
        $categoryId = $this->query($request, 'category_id');
        if ($categoryId !== '') {
            if (preg_match('/^\d+$/', $categoryId) !== 1) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'category_id' => 'Kategori kimliği tam sayı olmalı.',
                ]);
            }
            $filters['category_id'] = (int) $categoryId;
        }
        $q = $this->query($request, 'q');
        if ($q !== '') {
            $filters['q'] = $q;
        }

        return Response::success(
            $response,
            $this->presenter->productsOf($this->products->forList((int) $list['id'], $filters), $list),
        );
    }

    /**
     * POST /api/lists/{id}/products
     *
     * @param array<string, string> $args
     */
    public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $listId = $this->intArg($args, 'id');
        $list = $listId === null ? null : $this->lists->find($listId);
        if ($list === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }
        if ($this->mutationPolicy->isTerminal($list)) {
            return $this->listImmutable($response, $list);
        }

        $body = $this->body($request);
        $errors = $this->validateProduct($body, required: true);
        if ($errors !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $errors);
        }

        $mediaError = $this->ingestMainImage($body);
        if ($mediaError !== null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['main_image' => $mediaError]);
        }

        // Tekrar UYARISI (K25): engel değil, onay ister.
        $platform = $this->str($body, 'platform');
        $externalId = $this->str($body, 'external_id');
        if ($platform !== '' && $externalId !== '' && ($body['force'] ?? false) !== true) {
            $existing = $this->products->findDuplicate($platform, $externalId);
            if ($existing !== null) {
                return Response::error(
                    $response,
                    'DUPLICATE_WARNING',
                    'Bu ürün sisteme daha önce eklenmiş. Yine de eklemek için isteği {"force": true} ile tekrarlayın.',
                    409,
                    [],
                    ['existing' => [
                        'product_id' => (int) $existing['id'],
                        'list_id' => (int) $existing['list_id'],
                        'list_name' => (string) $existing['list_name'],
                        'name' => (string) $existing['name'],
                    ]],
                );
            }
        }

        $now = $this->clock->now();
        // K37 §B5: kayıt + tarihçe + revision tek transaction — tarihçesiz ürün kalamaz.
        $productId = $this->connection->transaction(function () use ($request, $list, $body, $now): int {
            $productId = $this->products->create((int) $list['id'], $this->productData($body), $now);
            $this->products->recordStatusChange(
                $productId,
                null,
                StateMachine::PRODUCT_TO_ORDER,
                $now,
                ActivityLog::ACTOR_ADMIN,
                $this->user($request)->id,
                $this->requestId($request),
            );
            $this->lists->bumpRevision((int) $list['id'], $now);
            $this->log($request, 'product_created', $productId, $this->str($body, 'name'));

            return $productId;
        });

        $created = $this->products->find($productId);
        $freshList = $this->lists->find((int) $list['id']);

        return Response::success(
            $response,
            $created === null || $freshList === null ? null : $this->presenter->product($created, $freshList),
            [],
            201,
        );
    }

    /**
     * PATCH /api/products/{id}
     *
     * @param array<string, string> $args
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->requireProduct($args);
        if ($product === null) {
            return Response::error($response, 'NOT_FOUND', 'Ürün bulunamadı.', 404);
        }
        $list = $this->lists->find((int) $product['list_id']);
        if ($list === null) {
            return Response::error($response, 'NOT_FOUND', 'Ürünün listesi bulunamadı.', 404);
        }
        if ($this->mutationPolicy->isTerminal($list)) {
            return $this->listImmutable($response, $list);
        }

        $body = $this->body($request);
        $errors = $this->validateProduct($body, required: false);
        if ($errors !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $errors);
        }

        $mediaError = $this->ingestMainImage($body);
        if ($mediaError !== null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['main_image' => $mediaError]);
        }

        $updates = [];
        foreach (ProductRepository::WRITABLE as $column) {
            if (array_key_exists($column, $body)) {
                $updates[$column] = $this->normalizeField($column, $body[$column]);
            }
        }
        if ($updates === []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'body' => 'Güncellenecek alan verilmedi.',
            ]);
        }

        $now = $this->clock->now();
        $this->connection->transaction(function () use ($request, $product, $list, $updates, $now): void {
            $this->products->update((int) $product['id'], $updates, $now);

            // Yalnızca çıktıyı etkileyen alanlar revision'ı artırır (K25).
            if (array_intersect(array_keys($updates), ProductRepository::REVISION_FIELDS) !== []) {
                $this->lists->bumpRevision((int) $list['id'], $now);
            }
            $this->log($request, 'product_updated', (int) $product['id'], implode(',', array_keys($updates)));
        });

        $fresh = $this->products->find((int) $product['id']);
        $freshList = $this->lists->find((int) $list['id']);

        return Response::success(
            $response,
            $fresh === null || $freshList === null ? null : $this->presenter->product($fresh, $freshList),
        );
    }

    /**
     * PATCH /api/products/{id}/status
     *
     * @param array<string, string> $args
     */
    public function updateStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->requireProduct($args);
        if ($product === null) {
            return Response::error($response, 'NOT_FOUND', 'Ürün bulunamadı.', 404);
        }
        $list = $this->lists->find((int) $product['list_id']);
        if ($list === null) {
            return Response::error($response, 'NOT_FOUND', 'Ürünün listesi bulunamadı.', 404);
        }

        if ($this->mutationPolicy->isTerminal($list)) {
            return $this->listImmutable($response, $list);
        }

        $to = $this->str($this->body($request), 'status');
        $from = (string) $product['status'];

        try {
            $this->stateMachine->assertProductTransition($from, $to);
        } catch (StateTransitionException $e) {
            return Response::error($response, 'STATE_TRANSITION', $e->getMessage(), 422, [], ['allowed' => $e->allowed]);
        }

        $now = $this->clock->now();
        $this->connection->transaction(function () use ($request, $product, $from, $to, $now): void {
            $this->applyStatus((int) $product['id'], $from, $to, $request, $now);
        });

        $fresh = $this->products->find((int) $product['id']);
        $freshList = $this->lists->find((int) $list['id']);

        return Response::success(
            $response,
            $fresh === null || $freshList === null ? null : $this->presenter->product($fresh, $freshList),
        );
    }

    /**
     * DELETE /api/products/{id}
     *
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->requireProduct($args);
        if ($product === null) {
            return Response::error($response, 'NOT_FOUND', 'Ürün bulunamadı.', 404);
        }
        $list = $this->lists->find((int) $product['list_id']);
        if ($list !== null && $this->mutationPolicy->isTerminal($list)) {
            return $this->listImmutable($response, $list);
        }

        $now = $this->clock->now();
        $this->connection->transaction(function () use ($request, $product, $now): void {
            $this->products->softDelete((int) $product['id'], $now);
            $this->lists->bumpRevision((int) $product['list_id'], $now);
            $this->log($request, 'product_deleted', (int) $product['id'], (string) $product['name']);
        });

        return $response->withStatus(204);
    }

    /** PATCH /api/products/bulk — kısmi başarı desteklenir (docs/10 §4). */
    public function bulk(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $ids = $body['ids'] ?? null;
        $action = $this->str($body, 'action');

        $errors = [];
        if (!is_array($ids) || $ids === []) {
            $errors['ids'] = 'En az bir ürün kimliği gönderilmeli.';
        }
        $actionError = $this->validator->enum($action, ['status', 'move', 'delete'], 'İşlem');
        if ($actionError !== null) {
            $errors['action'] = $actionError;
        }
        if ($action === 'status') {
            $statusError = $this->validator->enum(
                $body['status'] ?? null,
                array_keys(StateMachine::PRODUCT_TRANSITIONS),
                'Durum',
            );
            if ($statusError !== null) {
                $errors['status'] = $statusError;
            }
        }
        $targetList = null;
        if ($action === 'move') {
            $targetId = $body['target_list_id'] ?? null;
            $targetList = is_int($targetId) ? $this->lists->find($targetId) : null;
            if ($targetList === null) {
                $errors['target_list_id'] = 'Hedef liste bulunamadı.';
            }
        }
        if ($errors !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $errors);
        }
        // K37 §B4: terminal listeye taşıma da yasaktır (içine ürün eklemek demektir).
        if ($targetList !== null && $this->mutationPolicy->isTerminal($targetList)) {
            return $this->listImmutable($response, $targetList);
        }

        $now = $this->clock->now();

        // K37 §B5: tüm toplu işlem tek transaction'da koşar — SQL hatasında yarım
        // uygulanmış toplu değişiklik kalmaz. Kısmi başarı (failed listesi) İŞ kuralı
        // reddidir ve transaction'ı bozmaz.
        /** @var array{updated: int, failed: list<array<string, mixed>>} $result */
        $result = $this->connection->transaction(function () use ($request, $ids, $action, $body, $targetList, $now): array {
            $updated = 0;
            $failed = [];
            $touchedLists = [];
            /** @var array<int, array<string, mixed>|null> $listCache */
            $listCache = [];

            /** @var list<mixed> $ids */
            foreach ($ids as $rawId) {
                if (!is_int($rawId)) {
                    $failed[] = ['id' => $rawId, 'error' => 'Ürün kimliği tam sayı olmalı.'];

                    continue;
                }
                $product = $this->products->find($rawId);
                if ($product === null) {
                    $failed[] = ['id' => $rawId, 'error' => 'Ürün bulunamadı.'];

                    continue;
                }

                // K37 §B4: kaynağı terminal listede olan ürün hiçbir toplu işleme giremez.
                $sourceListId = (int) $product['list_id'];
                $sourceList = $listCache[$sourceListId] ??= $this->lists->find($sourceListId);
                if ($sourceList !== null && $this->mutationPolicy->isTerminal($sourceList)) {
                    $failed[] = ['id' => $rawId, 'error' => sprintf(
                        'Ürünün listesi "%s" durumunda ve değiştirilemez (LIST_IMMUTABLE).',
                        (string) $sourceList['status'],
                    )];

                    continue;
                }

                $touchedLists[$sourceListId] = true;

                if ($action === 'delete') {
                    $this->products->softDelete($rawId, $now);
                    $updated++;

                    continue;
                }

                if ($action === 'move') {
                    /** @var array<string, mixed> $targetList */
                    $this->products->update($rawId, [
                        'list_id' => (int) $targetList['id'],
                        'sort_no' => $this->products->maxSortNo((int) $targetList['id']) + 1,
                    ], $now);
                    $touchedLists[(int) $targetList['id']] = true;
                    $updated++;

                    continue;
                }

                $from = (string) $product['status'];
                $to = (string) $body['status'];
                try {
                    $this->stateMachine->assertProductTransition($from, $to);
                } catch (StateTransitionException $e) {
                    $failed[] = ['id' => $rawId, 'error' => $e->getMessage(), 'allowed' => $e->allowed];

                    continue;
                }
                $this->applyStatus($rawId, $from, $to, $request, $now);
                $updated++;
            }

            foreach (array_keys($touchedLists) as $listId) {
                $this->lists->bumpRevision($listId, $now);
            }
            $this->log($request, 'product_bulk_' . $action, null, sprintf('%d güncellendi, %d başarısız', $updated, count($failed)));

            return ['updated' => $updated, 'failed' => $failed];
        });

        return Response::success($response, $result);
    }

    /**
     * PATCH /api/lists/{id}/products/reorder
     *
     * @param array<string, string> $args
     */
    public function reorder(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $listId = $this->intArg($args, 'id');
        $list = $listId === null ? null : $this->lists->find($listId);
        if ($list === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }
        if ($this->mutationPolicy->isTerminal($list)) {
            return $this->listImmutable($response, $list);
        }

        $orderedIds = $this->body($request)['ordered_ids'] ?? null;
        if (!is_array($orderedIds) || $orderedIds === []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'ordered_ids' => 'Sıralanmış ürün kimlikleri dizisi gönderilmeli.',
            ]);
        }
        foreach ($orderedIds as $id) {
            if (!is_int($id)) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'ordered_ids' => 'Tüm ürün kimlikleri tam sayı olmalı.',
                ]);
            }
        }
        /** @var list<int> $orderedIds */

        // K37 §B6: gönderilen dizi listedeki ürünlerin TAM permütasyonu olmalı.
        // Eksik kimlik sessizce sıra dışı kalır, fazla/yinelenen kimlik başka listeyi
        // bozabilirdi — üçü de 422 ile reddedilir.
        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'ordered_ids' => 'Ürün kimlikleri yinelenemez.',
            ]);
        }
        $currentIds = array_map(
            static fn (array $product): int => (int) $product['id'],
            $this->products->forList((int) $list['id']),
        );
        $missing = array_diff($currentIds, $orderedIds);
        $unknown = array_diff($orderedIds, $currentIds);
        if ($missing !== [] || $unknown !== []) {
            $problems = [];
            if ($missing !== []) {
                $problems[] = sprintf('eksik: %s', implode(', ', $missing));
            }
            if ($unknown !== []) {
                $problems[] = sprintf('listede olmayan: %s', implode(', ', $unknown));
            }

            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'ordered_ids' => 'Dizi, listedeki ürünlerin tamamını birebir içermeli (' . implode(' · ', $problems) . ').',
            ]);
        }

        $now = $this->clock->now();
        $updated = $this->connection->transaction(function () use ($request, $list, $orderedIds, $now): int {
            $updated = $this->products->reorder((int) $list['id'], $orderedIds, $now);
            $this->lists->bumpRevision((int) $list['id'], $now);
            $this->log($request, 'product_reordered', (int) $list['id'], sprintf('%d ürün', $updated));

            return $updated;
        });

        return Response::success($response, ['updated' => $updated]);
    }

    // ─────────────── yardımcılar ───────────────

    private function applyStatus(
        int $productId,
        string $from,
        string $to,
        ServerRequestInterface $request,
        \DateTimeImmutable $now,
    ): void {
        $this->products->update($productId, ['status' => $to], $now);
        $this->products->recordStatusChange(
            $productId,
            $from,
            $to,
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
            $this->requestId($request),
        );
        $this->log($request, 'product_status_changed', $productId, sprintf('%s → %s', $from, $to));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, string>
     */
    private function validateProduct(array $body, bool $required): array
    {
        $errors = [];

        if ($required || array_key_exists('name', $body)) {
            $errors['name'] = $this->validator->productName($body['name'] ?? null);
        }
        if ($required || array_key_exists('qty', $body)) {
            $errors['qty'] = $this->validator->qty($body['qty'] ?? null);
        }
        if ($required || array_key_exists('price_yuan', $body)) {
            $errors['price_yuan'] = $this->validator->price($body['price_yuan'] ?? null, 'Yuan fiyatı');
        }
        if (array_key_exists('price_ddp_usd', $body)) {
            $errors['price_ddp_usd'] = $this->validator->price($body['price_ddp_usd'], 'DDP fiyatı');
        }
        if (array_key_exists('name_original', $body)) {
            $errors['name_original'] = $this->validator->nameOriginal($body['name_original']);
        }
        if (array_key_exists('detail', $body)) {
            $errors['detail'] = $this->validator->longText($body['detail'], 'Detay');
        }
        if (array_key_exists('note', $body)) {
            $errors['note'] = $this->validator->longText($body['note'], 'Not');
        }
        if (array_key_exists('tracking_no', $body)) {
            $errors['tracking_no'] = $this->validator->trackingNo($body['tracking_no']);
        }
        if (array_key_exists('units_per_carton', $body)) {
            $errors['units_per_carton'] = $this->validator->unitsPerCarton($body['units_per_carton']);
        }
        foreach (['url' => 'Ürün linki', 'vendor_url' => 'Satıcı linki', 'video_url' => 'Video linki'] as $key => $label) {
            if (array_key_exists($key, $body)) {
                $errors[$key] = $this->validator->url($body[$key], $label);
            }
        }
        // Görsel adresi diğer URL alanlarıyla AYNI kapıdan geçer (yalnız https, uzunluk,
        // biçim); sonra ayrıca MediaService'in beyaz liste/SSRF denetimine girer.
        // Sistemde zaten duran yerel yol (`/media/…`) yeniden doğrulanmaz.
        if (array_key_exists('main_image', $body) && !$this->isStoredMediaPath($body['main_image'])) {
            $errors['main_image'] = $this->validator->url($body['main_image'], 'Görsel adresi');
        }
        foreach (['sku_selection' => 'Varyasyon seçimi', 'sku_matrix' => 'Varyasyon matrisi'] as $key => $label) {
            if (array_key_exists($key, $body)) {
                $errors[$key] = $this->validator->jsonField($body[$key], $label);
            }
        }

        return array_filter($errors, static fn (?string $error): bool => $error !== null);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function productData(array $body): array
    {
        $data = [];
        foreach (ProductRepository::WRITABLE as $column) {
            if (array_key_exists($column, $body)) {
                $data[$column] = $this->normalizeField($column, $body[$column]);
            }
        }
        $data['name'] = trim((string) $body['name']);
        $data['qty'] = (int) $body['qty'];
        $data['price_yuan'] = $this->validator->toDecimalString($body['price_yuan'] ?? '0') ?? '0';
        $data['status'] = StateMachine::PRODUCT_TO_ORDER;

        return $data;
    }

    private function normalizeField(string $column, mixed $value): mixed
    {
        return match ($column) {
            'qty' => (int) $value,
            'category_id', 'units_per_carton' => $value === null || $value === '' ? null : (int) $value,
            'price_yuan', 'price_ddp_usd' => $this->validator->toDecimalString($value) ?? '0',
            'sku_selection', 'sku_matrix' => $value === null
                ? null
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => $value === null || $value === '' ? null : (is_string($value) ? trim($value) : $value),
        };
    }

    /**
     * @param array<string, string> $args
     *
     * @return array<string, mixed>|null
     */
    private function requireProduct(array $args): ?array
    {
        $id = $this->intArg($args, 'id');

        return $id === null ? null : $this->products->find($id);
    }

    private function log(ServerRequestInterface $request, string $action, ?int $entityId, string $detail): void
    {
        $this->activity->record(
            'product',
            $entityId,
            $action,
            $detail,
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
    }
}
