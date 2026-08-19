<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Dates;
use App\Core\Response;
use App\Models\ListRepository;
use App\Services\ActivityLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Paylaşım linki yönetimi (İE#10 Blok 4 — K20/K34/K51, docs/10 §3).
 *
 * Token 256-bit rastgeledir (64 hex) ve YALNIZ üretildiği yanıtta bir kez görünür;
 * DB'de SHA-256 hash'i durur (K34 — DB sızsa bile linkler kullanılamaz). Listede
 * yalnız tanıma için ilk 8 hane (`share_token_prefix`) saklanır. İptal edilen veya
 * yenilenen token'ın eski linki ANINDA ölür (hash değişir).
 */
final class ShareController extends ApiController
{
    public function __construct(
        private readonly ListRepository $lists,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
    ) {
    }

    /**
     * POST /api/lists/{id}/share — üretir/yeniler; {expires_at} opsiyonel (ISO tarih).
     *
     * @param array<string, string> $args
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $listId = $this->intArg($args, 'id');
        $row = $listId === null ? null : $this->lists->find($listId);
        if ($row === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }

        $body = $this->body($request);
        $now = $this->clock->now();

        $expiresAt = null;
        $expiresRaw = $body['expires_at'] ?? null;
        if ($expiresRaw !== null && $expiresRaw !== '') {
            if (!is_string($expiresRaw) || strtotime($expiresRaw) === false) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'expires_at' => 'Geçerli bir tarih girin (örn. 2026-09-30).',
                ]);
            }
            $candidate = new \DateTimeImmutable($expiresRaw, $now->getTimezone());
            if ($candidate <= $now) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'expires_at' => 'Bitiş tarihi gelecekte olmalı.',
                ]);
            }
            $expiresAt = Dates::toStorage($candidate);
        }

        // 256-bit rastgele token — üretimden sonra bir daha OKUNAMAZ (yalnız hash durur).
        $token = bin2hex(random_bytes(32));
        $this->lists->update((int) $row['id'], [
            'share_token_hash' => hash('sha256', $token),
            'share_token_prefix' => substr($token, 0, 8),
            'share_expires_at' => $expiresAt,
        ], $now);

        $isRenewal = $row['share_token_hash'] !== null;
        $this->activity->record(
            'list',
            (int) $row['id'],
            $isRenewal ? 'share_renewed' : 'share_created',
            'önek:' . substr($token, 0, 8) . ($expiresAt !== null ? ' son:' . $expiresAt : ''),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );

        // Adres istekten türetilir: alt alan adı/klasör yerleşimi ne olursa olsun doğru taban.
        $uri = $request->getUri();
        $shareUrl = $uri->getScheme() . '://' . $uri->getAuthority() . '/p/' . $token;

        return Response::success($response, [
            'share_url' => $shareUrl,
            'share_token_prefix' => substr($token, 0, 8),
            'share_expires_at' => $expiresAt === null ? null : Dates::toIso($expiresAt, $now->getTimezone()),
        ]);
    }

    /**
     * DELETE /api/lists/{id}/share — linki iptal eder; sayfa anında 404 döner.
     *
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $listId = $this->intArg($args, 'id');
        $row = $listId === null ? null : $this->lists->find($listId);
        if ($row === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }

        $now = $this->clock->now();
        $this->lists->update((int) $row['id'], [
            'share_token_hash' => null,
            'share_token_prefix' => null,
            'share_expires_at' => null,
        ], $now);

        $this->activity->record(
            'list',
            (int) $row['id'],
            'share_revoked',
            'önek:' . (string) ($row['share_token_prefix'] ?? '—'),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );

        return $response->withStatus(204);
    }
}
