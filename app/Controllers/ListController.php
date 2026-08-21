<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Connection;
use App\Core\Dates;
use App\Core\Response;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use App\Services\ActivityLog;
use App\Services\InputValidator;
use App\Services\ListMutationPolicy;
use App\Services\ListPresenter;
use App\Services\StateMachine;
use App\Services\StateTransitionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Liste uçları — docs/10 §3.
 *
 * Kur kuralı (K4, tek ifade): liste oluşturulurken o anki ayar kuru kopyalanır;
 * `draft` iken güncel kuru izleyebilir, `sent` olduğunda KİLİTLENİR ve
 * `rate_locked_at` yazılır. Kilitten sonra kur alanları değiştirilemez (422).
 *
 * K37 §B4: `completed`/`cancelled` liste DONMUŞTUR — içerik alanları ve durum
 * değiştirilemez (`LIST_IMMUTABLE`), yeniden açma ucu yoktur; çözüm kopyalamaktır.
 * Yalnızca `visibility` (arşivleme) yaşam döngüsü işlemi olarak serbesttir.
 */
final class ListController extends ApiController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly SettingsRepository $settings,
        private readonly ListPresenter $presenter,
        private readonly InputValidator $validator,
        private readonly StateMachine $stateMachine,
        private readonly ListMutationPolicy $mutationPolicy,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
    ) {
    }

    /** GET /api/lists */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filters = [];

        $visibility = $this->query($request, 'visibility');
        if ($visibility !== '') {
            $error = $this->validator->enum($visibility, ['active', 'passive', 'archived'], 'Görünürlük');
            if ($error !== null) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['visibility' => $error]);
            }
            $filters['visibility'] = $visibility;
        }

        $status = $this->query($request, 'status');
        if ($status !== '') {
            $error = $this->validator->enum($status, array_keys(StateMachine::LIST_TRANSITIONS), 'Durum');
            if ($error !== null) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['status' => $error]);
            }
            $filters['status'] = $status;
        }

        $q = $this->query($request, 'q');
        if ($q !== '') {
            $filters['q'] = $q;
        }

        return Response::success($response, $this->presenter->lists($this->lists->all($filters)));
    }

    /**
     * GET /api/lists/{id}
     *
     * @param array<string, string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $row = $this->requireList($args);
        if ($row === null) {
            return $this->notFound($response);
        }

        return Response::success($response, $this->presenter->list($row));
    }

    /** POST /api/lists */
    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);

        $fields = array_filter([
            'name' => $this->validator->listName($body['name'] ?? null),
            'period' => $this->validator->period($body['period'] ?? null),
            'supplier_name' => $this->validator->supplierName($body['supplier_name'] ?? null),
            'note' => $this->validator->longText($body['note'] ?? null, 'Not'),
        ], static fn (?string $error): bool => $error !== null);

        if ($fields !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $fields);
        }

        $now = $this->clock->now();
        $listId = $this->lists->create([
            'name' => trim((string) $body['name']),
            'period' => $this->nullable($body['period'] ?? null),
            'supplier_name' => $this->nullable($body['supplier_name'] ?? null),
            'note' => $this->nullable($body['note'] ?? null),
            'status' => StateMachine::LIST_DRAFT,
            'visibility' => 'active',
            'yuan_rate' => $this->settings->yuanRate(),
            'usd_rate' => $this->settings->usdRate(),
        ], $now);

        $this->log($request, 'list_created', $listId, (string) $body['name']);

        $created = $this->lists->find($listId);

        return Response::success($response, $created === null ? null : $this->presenter->list($created), [], 201);
    }

    /**
     * PATCH /api/lists/{id}
     *
     * @param array<string, string> $args
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $row = $this->requireList($args);
        if ($row === null) {
            return $this->notFound($response);
        }

        $body = $this->body($request);

        // K37 §B4: terminal listede içerik alanları ve durum dokunulmazdır;
        // yalnız arşivleme (visibility) serbesttir.
        if ($this->mutationPolicy->isTerminal($row)) {
            $blocked = array_diff(array_keys($body), ['visibility']);
            if ($blocked !== []) {
                return Response::error(
                    $response,
                    'LIST_IMMUTABLE',
                    sprintf(
                        'Liste "%s" durumunda ve artık değiştirilemez (yalnızca arşivlenebilir). '
                        . 'Devam etmek için listeyi kopyalayın.',
                        (string) $row['status'],
                    ),
                    422,
                );
            }
        }

        $errors = [];
        $updates = [];
        $now = $this->clock->now();

        if (array_key_exists('name', $body)) {
            $errors['name'] = $this->validator->listName($body['name']);
            $updates['name'] = is_string($body['name']) ? trim($body['name']) : '';
        }
        if (array_key_exists('period', $body)) {
            $errors['period'] = $this->validator->period($body['period']);
            $updates['period'] = $this->nullable($body['period']);
        }
        if (array_key_exists('supplier_name', $body)) {
            $errors['supplier_name'] = $this->validator->supplierName($body['supplier_name']);
            $updates['supplier_name'] = $this->nullable($body['supplier_name']);
        }
        if (array_key_exists('note', $body)) {
            $errors['note'] = $this->validator->longText($body['note'], 'Not');
            $updates['note'] = $this->nullable($body['note']);
        }
        if (array_key_exists('visibility', $body)) {
            $errors['visibility'] = $this->validator->enum($body['visibility'], ['active', 'passive', 'archived'], 'Görünürlük');
            $updates['visibility'] = $body['visibility'];
            $updates['archived_at'] = $body['visibility'] === 'archived' ? Dates::toStorage($now) : null;
        }

        // Kur: yalnızca kilitlenmeden önce (draft) değiştirilebilir (K4).
        foreach (['yuan_rate' => 'Yuan kuru', 'usd_rate' => 'Dolar kuru'] as $key => $label) {
            if (!array_key_exists($key, $body)) {
                continue;
            }
            if ($row['rate_locked_at'] !== null) {
                return Response::error(
                    $response,
                    'STATE_TRANSITION',
                    'Liste iletildikten sonra kur değiştirilemez (K4). Kur ' . Dates::toIso((string) $row['rate_locked_at'], $this->presenterTimezone()) . ' tarihinde kilitlendi.',
                    422,
                );
            }
            $errors[$key] = $this->validator->rate($body[$key], $label);
            $updates[$key] = $this->validator->toDecimalString($body[$key]);
        }

        $errors = array_filter($errors, static fn (?string $error): bool => $error !== null);
        if ($errors !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $errors);
        }

        // Durum geçişi ayrı ele alınır: makine kuralları ve kur kilidi buna bağlı.
        if (array_key_exists('status', $body)) {
            $from = (string) $row['status'];
            $to = is_string($body['status']) ? $body['status'] : '';

            try {
                $this->stateMachine->assertListTransition($from, $to, $this->products->statusesForList((int) $row['id']));
            } catch (StateTransitionException $e) {
                return Response::error($response, 'STATE_TRANSITION', $e->getMessage(), 422, [], [
                    'allowed' => $e->allowed,
                ]);
            }

            $updates['status'] = $to;
            // sent'e geçişte kur kilitlenir — ve KİLİTLENEN değer O ANKİ ayar kurudur
            // (K4 düzeltmesi, canlı vaka): liste oluşturulduktan sonra kur güncellendiyse
            // bayat kopya değil güncel kur kilitlenmeli. Elle kur verilmişse o üstündür.
            if ($to === StateMachine::LIST_SENT && $row['rate_locked_at'] === null) {
                $updates['rate_locked_at'] = Dates::toStorage($now);
                if (!array_key_exists('yuan_rate', $updates)) {
                    $updates['yuan_rate'] = $this->settings->yuanRate();
                }
                if (!array_key_exists('usd_rate', $updates)) {
                    $updates['usd_rate'] = $this->settings->usdRate();
                }
            }
            // K45: Taslağa dönüş kilidi AÇAR — liste yeniden güncel kuru izler;
            // yanlış kurla iletilmiş liste böylece kurtarılır (Taslak → tekrar İlet).
            if ($to === StateMachine::LIST_DRAFT && $row['rate_locked_at'] !== null) {
                $updates['rate_locked_at'] = null;
                $updates['yuan_rate'] = $this->settings->yuanRate();
                $updates['usd_rate'] = $this->settings->usdRate();
            }
        }

        $this->lists->update((int) $row['id'], $updates, $now);
        // İE#14 B1: kur kilidi ve liste durumu belgeye BASILIR — değiştiklerinde
        // liste sürümü ilerler (revizyon harfi ve K25 rozeti bunu izler).
        if (array_key_exists('rate_locked_at', $updates) || array_key_exists('status', $updates)
            || array_key_exists('yuan_rate', $updates) || array_key_exists('usd_rate', $updates)) {
            $this->lists->bumpRevision((int) $row['id'], $now);
        }
        $this->log($request, 'list_updated', (int) $row['id'], implode(',', array_keys($updates)));

        $fresh = $this->lists->find((int) $row['id']);

        return Response::success($response, $fresh === null ? null : $this->presenter->list($fresh));
    }

    /**
     * DELETE /api/lists/{id} — çöp kutusuna taşır (30 gün, K15).
     *
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $row = $this->requireList($args);
        if ($row === null) {
            return $this->notFound($response);
        }

        $this->lists->softDelete((int) $row['id'], $this->clock->now());
        $this->log($request, 'list_deleted', (int) $row['id'], (string) $row['name']);

        return $response->withStatus(204);
    }

    /**
     * POST /api/lists/{id}/duplicate — yeni liste GÜNCEL kuru alır ve yeniden kilitlenir.
     *
     * @param array<string, string> $args
     */
    public function duplicate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $row = $this->requireList($args);
        if ($row === null) {
            return $this->notFound($response);
        }

        $now = $this->clock->now();
        $body = $this->body($request);
        $name = $this->str($body, 'name');
        if ($name === '') {
            $name = $this->copyName((string) $row['name']);
        }
        $nameError = $this->validator->listName($name);
        if ($nameError !== null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['name' => $nameError]);
        }

        // K37 §B5: liste + tüm ürün kopyaları tek transaction — yarım kopya kalmaz.
        $newId = $this->connection->transaction(function () use ($request, $row, $name, $now): int {
            // Kopya taslak olarak açılır ve GÜNCEL ayar kurunu alır (K4) — eski kilitli kur taşınmaz.
            $newId = $this->lists->create([
                'name' => $name,
                'period' => $row['period'],
                'supplier_name' => $row['supplier_name'],
                'note' => $row['note'],
                'status' => StateMachine::LIST_DRAFT,
                'visibility' => 'active',
                'yuan_rate' => $this->settings->yuanRate(),
                'usd_rate' => $this->settings->usdRate(),
            ], $now);

            foreach ($this->products->forList((int) $row['id']) as $product) {
                $copy = [];
                foreach (ProductRepository::WRITABLE as $column) {
                    $copy[$column] = $product[$column];
                }
                $copy['sort_no'] = (int) $product['sort_no'];
                // Ürünler başa döner; takip kodu taşınmaz (yeni sipariş, yeni sevkiyat).
                $copy['status'] = StateMachine::PRODUCT_TO_ORDER;
                $copy['tracking_no'] = null;

                $this->products->create($newId, $copy, $now);
            }

            $this->log($request, 'list_duplicated', $newId, sprintf('kaynak:%d', (int) $row['id']));

            return $newId;
        });

        $created = $this->lists->find($newId);

        return Response::success($response, $created === null ? null : $this->presenter->list($created), [], 201);
    }

    /**
     * Kopya adı üretimi (İE#10 Blok 5a): "ad (kopya)" → "ad (kopya 2)" → "ad (kopya 3)".
     *
     * Canlı vaka: kopyanın kopyası "test (kopya) (kopya)" diye yığılıyordu ve listeler
     * ayırt edilemiyordu. Taban ad, sondaki "(kopya)"/"(kopya N)" eklerinden arındırılır;
     * mevcut listelerdeki EN BÜYÜK kopya numarasının bir fazlası verilir. Eski kayıtlara
     * dokunulmaz — kural yalnız YENİ kopya adlarını üretir.
     */
    private function copyName(string $sourceName): string
    {
        $base = trim((string) preg_replace('/\s*\(kopya(?:\s+\d+)?\)\s*$/u', '', $sourceName));
        if ($base === '') {
            $base = $sourceName;
        }

        $highest = 1; // "ad (kopya)" 1 sayılır → ilk yeni kopya "(kopya 2)"... taban hiç kopyalanmadıysa 0.
        $anyCopyExists = false;
        foreach ($this->lists->namesLike($base . ' (kopya%') as $existing) {
            if (preg_match('/\(kopya(?:\s+(\d+))?\)\s*$/u', $existing, $match) === 1) {
                $anyCopyExists = true;
                $highest = max($highest, isset($match[1]) ? (int) $match[1] : 1);
            }
        }

        $suffix = $anyCopyExists ? ' (kopya ' . ($highest + 1) . ')' : ' (kopya)';

        return mb_substr($base, 0, 200 - mb_strlen($suffix)) . $suffix;
    }

    // ─────────────── yardımcılar ───────────────

    /**
     * @param array<string, string> $args
     *
     * @return array<string, mixed>|null
     */
    private function requireList(array $args): ?array
    {
        $id = $this->intArg($args, 'id');

        return $id === null ? null : $this->lists->find($id);
    }

    private function notFound(ResponseInterface $response): ResponseInterface
    {
        return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = is_string($value) ? trim($value) : (string) $value;

        return $text === '' ? null : $text;
    }

    private function presenterTimezone(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get());
    }

    private function log(ServerRequestInterface $request, string $action, int $listId, string $detail): void
    {
        $user = $this->user($request);
        $this->activity->record(
            'list',
            $listId,
            $action,
            $detail,
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $user->id,
        );
    }
}
