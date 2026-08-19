<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Dates;
use App\Core\Response;
use App\Models\InboxRepository;
use App\Models\ListRepository;
use App\Services\ActivityLog;
use App\Services\CaptureException;
use App\Services\CaptureService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Gelen Kutusu uçları (İE#11 Görev D — docs/10). Panel Auth+CSRF arkasında.
 * Kuyruk: pending/error kayıtlar; "listeye taşı" CaptureService ile ürüne çevirir.
 */
final class InboxController extends ApiController
{
    public function __construct(
        private readonly InboxRepository $inbox,
        private readonly ListRepository $lists,
        private readonly CaptureService $capture,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
        private readonly \DateTimeZone $timezone,
    ) {
    }

    /** GET /api/inbox — bekleyen kuyruk (pending + error). */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = [];
        foreach ($this->inbox->queue() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'status' => (string) $row['status'],
                'platform' => (string) $row['platform'],
                'external_id' => $row['external_id'] === null ? null : (string) $row['external_id'],
                'name' => $row['name'] === null ? null : (string) $row['name'],
                'price_yuan' => $row['price_yuan'] === null ? null : (string) $row['price_yuan'],
                'image_url' => $row['image_url'] === null ? null : (string) $row['image_url'],
                'url' => $row['url'] === null ? null : (string) $row['url'],
                'error_note' => $row['error_note'] === null ? null : (string) $row['error_note'],
                'created_at' => Dates::toIso((string) $row['created_at'], $this->timezone),
            ];
        }

        return Response::success($response, $rows);
    }

    /** POST /api/inbox/assign — {ids:[], list_id}: seçilenleri listeye ürün olarak taşır. */
    public function assign(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $listId = $body['list_id'] ?? null;
        $ids = $body['ids'] ?? null;

        if (!is_int($listId) || $listId < 1 || !is_array($ids) || $ids === [] || count($ids) > 100) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'body' => 'ids (1–100 kimlik) ve list_id zorunlu.',
            ]);
        }
        $list = $this->lists->find($listId);
        if ($list === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }

        $now = $this->clock->now();
        $moved = 0;
        $failed = [];
        foreach ($ids as $id) {
            if (!is_int($id)) {
                continue;
            }
            $item = $this->inbox->find($id);
            if ($item === null || !in_array((string) $item['status'], ['pending', 'error'], true)) {
                $failed[] = ['id' => $id, 'error' => 'Kayıt bulunamadı veya zaten taşınmış.'];

                continue;
            }

            /** @var array<string, mixed>|null $payload */
            $payload = json_decode((string) $item['payload_json'], true);
            if (!is_array($payload)) {
                $failed[] = ['id' => $id, 'error' => 'Yakalama verisi okunamadı.'];

                continue;
            }

            // error kayıtları da taşınabilir OLMAYA ÇALIŞIR: zorunlu alanlar hâlâ eksikse net hata.
            $errors = $this->capture->validate($payload);
            if ($errors !== []) {
                $failed[] = ['id' => $id, 'error' => 'Eksik/bozuk alanlar: ' . implode(' · ', array_keys($errors))];

                continue;
            }

            try {
                $productId = $this->capture->createProduct($payload, (int) $list['id'], $now);
            } catch (CaptureException $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];

                continue;
            }
            $this->inbox->markAssigned($id, $productId, $now);
            $moved++;
        }

        $this->activity->record(
            'inbox',
            null,
            'inbox_assigned',
            sprintf('liste:%d → %d taşındı, %d başarısız', (int) $list['id'], $moved, count($failed)),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );

        return Response::success($response, ['moved' => $moved, 'failed' => $failed]);
    }

    /**
     * DELETE /api/inbox/{id} — kaydı siler (çöp kutusuna girmez; ham yakalama verisidir).
     *
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->intArg($args, 'id');
        $item = $id === null ? null : $this->inbox->find($id);
        if ($item === null) {
            return Response::error($response, 'NOT_FOUND', 'Kayıt bulunamadı.', 404);
        }

        $this->inbox->delete((int) $item['id']);

        $this->activity->record(
            'inbox',
            (int) $item['id'],
            'inbox_deleted',
            $item['name'] === null ? null : (string) $item['name'],
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );

        return $response->withStatus(204);
    }
}
