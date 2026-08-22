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
        // İE#18 G6 (K62): erişim anahtarı — paylaşım linki artık tek başına yetmez.
        private readonly ?\App\Services\Share\ShareKeyService $anahtar = null,
        // İE#19 E5: dışa verilen adresler settings.APP_URL'den üretilir (Host'tan DEĞİL).
        private readonly ?\App\Core\Config $appConfig = null,
    ) {
    }

    /**
     * GET /api/lists/{id}/share-key — panelde gösterilecek anahtar ve kapı durumu.
     *
     * Anahtar 6 hanelidir ve firmaya ELDEN iletilir; hash'ten geri okunamayacağı
     * için düz metni de saklanır (bkz. migration 0021 gerekçesi). Bu uç oturum
     * arkasındadır — dışarıya hiçbir yoldan sızmaz.
     *
     * @param array<string, string> $args
     */
    public function keyShow(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$row, $hata] = $this->anahtarIcinListe($response, $args);
        if ($row === null) {
            return $hata;
        }

        $row = $this->anahtar?->hazirla($row, $this->clock->now()) ?? $row;

        return Response::success($response, [
            'key' => (string) ($row['share_key_plain'] ?? ''),
            'enabled' => (int) ($row['share_key_enabled'] ?? 1) === 1,
        ]);
    }

    /**
     * POST /api/lists/{id}/share-key — anahtarı YENİLER.
     *
     * Yenileme eskisini ANINDA öldürür (K51 iptal ruhu): hash değişir, eski
     * anahtarla alınmış tarayıcı çerezleri de geçersizleşir çünkü çerez imzası
     * hash'i kapsar. Firmaya yeni anahtar iletilmelidir.
     *
     * @param array<string, string> $args
     */
    public function keyRotate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$row, $hata] = $this->anahtarIcinListe($response, $args);
        if ($row === null) {
            return $hata;
        }

        $now = $this->clock->now();
        $yeni = $this->anahtar?->yenile((int) $row['id'], $now) ?? '';

        $this->activity->record(
            'list',
            (int) $row['id'],
            'share_key_rotated',
            'erişim anahtarı yenilendi',
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );

        return Response::success($response, ['key' => $yeni, 'enabled' => true]);
    }

    /**
     * PATCH /api/lists/{id}/share-key — kapıyı aç/kapat ({enabled: bool}).
     *
     * KAPALI listede davranış eski hâlidir: token bilen görür. Bu bilinçli bir
     * seçenektir (bazı listeler gerçekten herkese açık paylaşılmak istenebilir),
     * varsayılan AÇIK'tır.
     *
     * @param array<string, string> $args
     */
    public function keyToggle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$row, $hata] = $this->anahtarIcinListe($response, $args);
        if ($row === null) {
            return $hata;
        }

        $govde = $this->body($request);
        $acik = ($govde['enabled'] ?? null) === true;
        $now = $this->clock->now();
        $this->lists->update((int) $row['id'], ['share_key_enabled' => $acik ? 1 : 0], $now);

        $this->activity->record(
            'list',
            (int) $row['id'],
            $acik ? 'share_key_enabled' : 'share_key_disabled',
            $acik ? 'erişim anahtarı açıldı' : 'erişim anahtarı kapatıldı',
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );

        return Response::success($response, ['enabled' => $acik]);
    }

    /**
     * @param array<string, string> $args
     *
     * @return array{0: array<string, mixed>|null, 1: ResponseInterface}
     */
    private function anahtarIcinListe(ResponseInterface $response, array $args): array
    {
        $listId = $this->intArg($args, 'id');
        $row = $listId === null ? null : $this->lists->find($listId);
        if ($row === null) {
            return [null, Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404)];
        }
        if ($this->anahtar === null) {
            return [null, Response::error($response, 'SERVER_ERROR', 'Anahtar servisi kullanılamıyor.', 500)];
        }

        return [$row, $response];
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

        // İE#19 E5: adres AYARLARDAKİ APP_URL'den üretilir. Eskiden isteğin Host
        // başlığından türetiliyordu; Host istemcinin yazdığı bir değerdir ve sahte
        // bir Host, firmaya gidecek QR'a yabancı bir alan adı bastırabilirdi.
        $shareUrl = \App\Core\AppUrl::to($this->appConfig?->get('APP_URL'), $request, '/liste/' . $token);

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
