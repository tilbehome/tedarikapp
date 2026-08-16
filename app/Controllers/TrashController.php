<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Dates;
use App\Core\Response;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Services\ActivityLog;
use App\Services\TrashPolicy;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Çöp kutusu uçları — docs/10 §6, K15.
 *
 * Silme kaza korumasıdır: kayıt 30 gün geri alınabilir kalır, sonra `bin/purge-trash.php`
 * kalıcı siler. Listesi de silinmiş bir ürün tek başına geri alınamaz (409) — önce liste.
 */
final class TrashController extends ApiController
{
    public function __construct(
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly TrashPolicy $policy,
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
            $this->lists->restore($id, $now);
            $this->log($request, 'list', $id, 'list_restored', (string) $row['name']);

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

        $this->products->restore($id, $now);
        $this->lists->bumpRevision((int) $row['list_id'], $now);
        $this->log($request, 'product', $id, 'product_restored', (string) $row['name']);

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
            $this->lists->forceDelete($id);
            $this->log($request, 'list', $id, 'list_purged', (string) $row['name']);

            return $response->withStatus(204);
        }

        $row = $this->products->find($id, includeDeleted: true);
        if ($row === null || $row['deleted_at'] === null) {
            return Response::error($response, 'NOT_FOUND', 'Çöp kutusunda böyle bir ürün yok.', 404);
        }
        $this->products->forceDelete($id);
        $this->log($request, 'product', $id, 'product_purged', (string) $row['name']);

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
