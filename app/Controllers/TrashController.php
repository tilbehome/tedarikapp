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
use App\Services\ActivityLog;
use App\Services\ListMutationPolicy;
use App\Services\MediaJanitor;
use App\Services\TrashPolicy;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Çöp kutusu uçları — docs/10 §6, K15.
 *
 * Silme kaza korumasıdır: kayıt 30 gün geri alınabilir kalır, sonra `bin/purge-trash.php`
 * kalıcı siler. Listesi de silinmiş bir ürün tek başına geri alınamaz (409) — önce liste.
 *
 * K37 §C7: kalıcı silme fiziksel medya dosyalarını da temizler — DB silme
 * transaction'ı BAŞARIYLA bittikten sonra (dosya silme geri alınamaz).
 */
final class TrashController extends ApiController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly TrashPolicy $policy,
        private readonly ListMutationPolicy $mutationPolicy,
        private readonly MediaJanitor $mediaJanitor,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
        private readonly DateTimeZone $timezone,
    ) {
    }

    /** GET /api/trash */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $now = $this->clock->now();

        $lists = [];
        foreach ($this->lists->trashed() as $row) {
            $lists[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'deleted_at' => Dates::toIso((string) $row['deleted_at'], $this->timezone),
                'days_left' => $this->policy->daysLeft((string) $row['deleted_at'], $now, $this->timezone),
            ];
        }

        $products = [];
        foreach ($this->products->trashed() as $row) {
            $products[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'list_id' => (int) $row['list_id'],
                'list_name' => (string) $row['list_name'],
                'list_deleted' => $row['list_deleted_at'] !== null,
                'deleted_at' => Dates::toIso((string) $row['deleted_at'], $this->timezone),
                'days_left' => $this->policy->daysLeft((string) $row['deleted_at'], $now, $this->timezone),
            ];
        }

        return Response::success($response, [
            'retention_days' => $this->policy->retentionDays(),
            'lists' => $lists,
            'products' => $products,
        ]);
    }

    /**
     * POST /api/trash/{type}/{id}/restore
     *
     * @param array<string, string> $args
     */
    public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $type = $args['type'] ?? '';
        $id = $this->intArg($args, 'id');
        if ($id === null || !in_array($type, ['lists', 'products'], true)) {
            return Response::error($response, 'NOT_FOUND', 'Çöp kutusu kaydı bulunamadı.', 404);
        }

        $now = $this->clock->now();

        if ($type === 'lists') {
            $row = $this->lists->find($id, includeDeleted: true);
            if ($row === null || $row['deleted_at'] === null) {
                return Response::error($response, 'NOT_FOUND', 'Çöp kutusunda böyle bir liste yok.', 404);
            }
            $this->connection->transaction(function () use ($request, $id, $row, $now): void {
                $this->lists->restore($id, $now);
                $this->log($request, 'list', $id, 'list_restored', (string) $row['name']);
            });

            return Response::success($response, ['type' => 'lists', 'id' => $id]);
        }

        $row = $this->products->find($id, includeDeleted: true);
        if ($row === null || $row['deleted_at'] === null) {
            return Response::error($response, 'NOT_FOUND', 'Çöp kutusunda böyle bir ürün yok.', 404);
        }

        // docs/10 §6: listesi de silinmişse önce liste geri alınmalı.
        $list = $this->lists->find((int) $row['list_id'], includeDeleted: true);
        if ($list !== null && $list['deleted_at'] !== null) {
            return Response::error(
                $response,
                'STATE_TRANSITION',
                'Bu ürünün listesi de silinmiş. Önce listeyi geri alın.',
                409,
                [],
                ['list_id' => (int) $list['id'], 'list_name' => (string) $list['name']],
            );
        }

        // K37 §B4: terminal listeye ürün geri almak da listeyi değiştirmek demektir.
        if ($list !== null && $this->mutationPolicy->isTerminal($list)) {
            return Response::error(
                $response,
                'LIST_IMMUTABLE',
                sprintf(
                    'Ürünün listesi "%s" durumunda ve artık değiştirilemez; ürün bu listeye geri alınamaz.',
                    (string) $list['status'],
                ),
                422,
            );
        }

        $this->connection->transaction(function () use ($request, $id, $row, $now): void {
            $this->products->restore($id, $now);
            $this->lists->bumpRevision((int) $row['list_id'], $now);
            $this->log($request, 'product', $id, 'product_restored', (string) $row['name']);
        });

        return Response::success($response, ['type' => 'products', 'id' => $id]);
    }

    /**
     * DELETE /api/trash/{type}/{id} — kalıcı siler.
     *
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $type = $args['type'] ?? '';
        $id = $this->intArg($args, 'id');
        if ($id === null || !in_array($type, ['lists', 'products'], true)) {
            return Response::error($response, 'NOT_FOUND', 'Çöp kutusu kaydı bulunamadı.', 404);
        }

        if ($type === 'lists') {
            $row = $this->lists->find($id, includeDeleted: true);
            if ($row === null || $row['deleted_at'] === null) {
                return Response::error($response, 'NOT_FOUND', 'Çöp kutusunda böyle bir liste yok.', 404);
            }

            // K37 §C7: medya referansları DB kaydı silinmeden ÖNCE toplanır
            // (CASCADE sonrası hangi dosyaların sahipsiz kaldığı bilinemezdi).
            $mediaReferences = $this->products->mediaReferencesForList($id);
            $this->connection->transaction(function () use ($request, $id, $row): void {
                $this->lists->forceDelete($id);
                $this->log($request, 'list', $id, 'list_purged', (string) $row['name']);
            });
            $this->mediaJanitor->deleteUnreferenced($mediaReferences);

            return $response->withStatus(204);
        }

        $row = $this->products->find($id, includeDeleted: true);
        if ($row === null || $row['deleted_at'] === null) {
            return Response::error($response, 'NOT_FOUND', 'Çöp kutusunda böyle bir ürün yok.', 404);
        }

        $mediaReferences = $this->products->mediaReferencesForProduct($id);
        $this->connection->transaction(function () use ($request, $id, $row): void {
            $this->products->forceDelete($id);
            $this->log($request, 'product', $id, 'product_purged', (string) $row['name']);
        });
        $this->mediaJanitor->deleteUnreferenced($mediaReferences);

        return $response->withStatus(204);
    }

    private function log(ServerRequestInterface $request, string $entity, int $id, string $action, string $detail): void
    {
        $this->activity->record(
            $entity,
            $id,
            $action,
            $detail,
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
    }
}
