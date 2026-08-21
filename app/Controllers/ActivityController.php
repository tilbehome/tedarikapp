<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Connection;
use App\Core\Dates;
use App\Core\Response;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Aktivite günlüğü — docs/10 §7, E9 ekranının kaynağı.
 *
 * Salt okunur ve sayfalıdır. `detail` alanı hiçbir sır içermez (İE#4 §5 kuralı);
 * yine de yalnızca oturum açmış kullanıcıya gösterilir.
 */
final class ActivityController extends ApiController
{
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly Connection $connection,
        private readonly DateTimeZone $timezone,
    ) {
    }

    /** GET /api/activity */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $entityType = $this->query($request, 'entity_type');
        $entityId = $this->query($request, 'entity_id');

        $where = [];
        $params = [];
        if ($entityType !== '') {
            $where[] = 'entity_type = :entity_type';
            $params['entity_type'] = $entityType;
        }
        if ($entityId !== '') {
            if (preg_match('/^\d+$/', $entityId) !== 1) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'entity_id' => 'Kayıt kimliği tam sayı olmalı.',
                ]);
            }
            $where[] = 'entity_id = :entity_id';
            $params['entity_id'] = (int) $entityId;
        }
        // İE#11 EK-3 (5): hız sayacı satırları (capture_request) İNSAN aktivitesi değildir —
        // akıştan gizlenir. Sayaç LoginThrottle/ShareGate ile aynı disksiz desende
        // activity_log'da kalır (ayrı tablo = yeni migration + K49 haritası + ikinci
        // sayaç deseni; kazanç yok). Eskiler bin/bakim.php'de temizlenir.
        $where[] = "action <> 'capture_request'";
        // İE#17 G4: taze imza üretimi de SAYAÇ satırıdır (insan aktivitesi değil) —
        // her indirme tıklamasında bir satır düşer, akışı boğardı. Gerçek indirme
        // (share_download) akışta KALIR: o firmanın yaptığı bir iştir.
        $where[] = "action <> '" . \App\Services\Share\ShareGate::ACTION_LINK . "'";

        // $where hep dolu (capture_request filtresi eklendi) — koşul kaldırıldı.
        $clause = ' WHERE ' . implode(' AND ', $where);

        [$page, $perPage] = $this->pagination($request);

        $countStatement = $this->connection->pdo()->prepare('SELECT COUNT(*) AS total FROM activity_log' . $clause);
        $countStatement->execute($params);
        $countRow = $countStatement->fetch();
        $total = is_array($countRow) ? (int) $countRow['total'] : 0;

        // LIMIT/OFFSET doğrudan gömülür: ikisi de yukarıda tam sayıya zorlanmış
        // ve sınırlanmıştır; PDO bazı sürücülerde bu iki alanı parametre kabul etmez.
        $sql = 'SELECT id, entity_type, entity_id, action, detail, ip, actor_type, created_at
                FROM activity_log' . $clause . sprintf(
            ' ORDER BY id DESC LIMIT %d OFFSET %d',
            $perPage,
            ($page - 1) * $perPage,
        );

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = [
                'id' => (int) $row['id'],
                'entity_type' => (string) $row['entity_type'],
                'entity_id' => $row['entity_id'] === null ? null : (int) $row['entity_id'],
                'action' => (string) $row['action'],
                'detail' => $row['detail'] === null ? null : (string) $row['detail'],
                'ip' => $row['ip'] === null ? null : (string) $row['ip'],
                'actor_type' => (string) $row['actor_type'],
                'created_at' => Dates::toIso((string) $row['created_at'], $this->timezone),
            ];
        }

        return Response::success($response, $entries, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    /** @return array{int, int} sayfa, sayfa başına kayıt */
    private function pagination(ServerRequestInterface $request): array
    {
        $page = $this->query($request, 'page');
        $perPage = $this->query($request, 'per_page');

        $pageNumber = preg_match('/^\d+$/', $page) === 1 ? max(1, (int) $page) : 1;
        $size = preg_match('/^\d+$/', $perPage) === 1 ? (int) $perPage : self::DEFAULT_PER_PAGE;
        $size = max(1, min($size, self::MAX_PER_PAGE));

        return [$pageNumber, $size];
    }
}
