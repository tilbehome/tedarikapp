<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Response;
use App\Models\CategoryRepository;
use App\Models\ExportRepository;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Services\ActivityLog;
use App\Services\Export\ExportException;
use App\Services\Export\ExportRenderer;
use App\Services\Export\ExportSnapshot;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Export uçları (İE#10 Blok 1 — docs/10 §3).
 *
 * DİSKSİZ tasarım (K33/K44): dosya hiçbir zaman diske yazılmaz. Üretimde snapshot
 * exports tablosuna kaydedilir, dosya bellekte üretilip akışla verilir; geçmişten
 * indirme AYNI snapshot'tan yeniden üretir — byte'ı saklamak yerine kaynağı saklarız
 * (sha256 kayıtta: yeniden üretim doğrulanabilir).
 */
final class ExportController extends ApiController
{
    /** @param array<string, ExportRenderer> $renderers biçim → render'cı */
    public function __construct(
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly ExportRepository $exports,
        private readonly ExportSnapshot $snapshot,
        private readonly array $renderers,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
        // İE#13 F1: belge antedi ayarları (firma adı/web/e-posta/hazırlayan).
        private readonly \App\Models\SettingsRepository $settings,
    ) {
    }

    /**
     * GET /api/lists/{id}/export?format=xlsx|pdf|csv — üretir, kaydeder, dosyayı akıtır.
     *
     * @param array<string, string> $args
     */
    public function export(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $listId = $this->intArg($args, 'id');
        $row = $listId === null ? null : $this->lists->find($listId);
        if ($row === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }

        $format = strtolower($this->query($request, 'format'));
        $renderer = $this->renderers[$format] ?? null;
        if ($renderer === null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'format' => 'Biçim ' . implode(', ', array_keys($this->renderers)) . ' olmalı.',
            ]);
        }

        $body = $this->body($request);

        // F5 — kopya türü: firma kopyası VARSAYILANDIR. İç kopya kâr sütunlarını
        // taşır ve dosya adında da işaretlenir; yanlışlıkla firmaya gitmesi zorlaşır.
        $copy = $this->str($body, 'copy') === 'ic' ? 'ic' : 'firma';

        // F2 — durum filtresi: verilmezse HEPSİ. Geçersiz durum kodu sessizce elenir;
        // hiç geçerli kod kalmazsa filtre uygulanmaz (boş çıktı sürprizi olmaz).
        $statuses = $this->statuses($body['statuses'] ?? null);

        $now = $this->clock->now();
        $productRows = $this->products->forList((int) $row['id']);
        if ($statuses !== []) {
            $productRows = array_values(array_filter(
                $productRows,
                static fn (array $product): bool => in_array((string) $product['status'], $statuses, true),
            ));
        }
        $categoryNames = array_column($this->categories->all(), 'name', 'id');

        // F7 + İE#14 B1 — revizyon LİSTE SÜRÜMÜNE bağlıdır: aynı içerikten alınan
        // Excel ve PDF AYNI harfi taşır; harf ancak liste değişince ilerler.
        $revision = \App\Services\Export\TemplateV2::revisionLabel(
            $this->exports->revisionSequence((int) $row['id'], (int) $row['revision']),
        );

        $snapshot = $this->snapshot->build($row, $productRows, $categoryNames, $now, [
            'copy' => $copy,
            'statuses' => $statuses,
            'revision_label' => $revision,
            'document_code' => \App\Services\Export\TemplateV2::documentCode(
                (int) $row['id'],
                (int) $now->format('Y'),
                $revision,
            ),
            // F6 — QR: paylaşım adresi YALNIZ istekle gelirse gömülür. Tam token
            // sunucuda SAKLANMAZ (K51: yalnız hash) — bu yüzden yeniden üretilemez.
            'share_url' => $this->shareUrl($body['share_url'] ?? null, $row),
            'document_header' => $this->settings->documentHeader(),
        ]);

        try {
            $bytes = $renderer->render($snapshot);
        } catch (ExportException $e) {
            return Response::error($response, 'SERVER_ERROR', $e->getMessage(), 500);
        }

        $exportId = $this->exports->record(
            (int) $row['id'],
            $format,
            $snapshot,
            hash('sha256', $bytes),
            strlen($bytes),
            (int) $row['revision'],
            $now,
        );

        $this->activity->record(
            'export',
            $exportId,
            'export_created',
            sprintf(
                'liste:%d %s (%d ürün) · kopya:%s · rev:%s%s',
                (int) $row['id'],
                $format,
                count($productRows),
                $copy,
                $revision,
                $statuses === [] ? '' : ' · filtre:' . implode('/', $statuses),
            ),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );

        return $this->stream(
            $response,
            $bytes,
            $renderer,
            (string) $row['name'] . ($copy === 'ic' ? ' (IC KOPYA)' : ''),
            $now,
        );
    }

    /**
     * Geçerli ürün durum kodları — uydurma kod sorguya girmez.
     *
     * @return list<string>
     */
    private function statuses(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $gecerli = array_keys(\App\Services\Export\TemplateV2::STATUS_BADGES);
        $out = [];
        foreach ($raw as $status) {
            if (is_string($status) && in_array($status, $gecerli, true) && !in_array($status, $out, true)) {
                $out[] = $status;
            }
        }

        return $out;
    }

    /**
     * F6 — QR adresi doğrulaması: yalnız KENDİ paylaşım adresimiz kabul edilir ve
     * listede AKTİF bir paylaşım linki olmalıdır. Böylece belgeye yabancı bir adres
     * bastırılamaz (QR, tıklanan bir link kadar tehlikelidir).
     *
     * @param array<string, mixed> $listRow
     */
    private function shareUrl(mixed $raw, array $listRow): ?string
    {
        if (!is_string($raw) || $raw === '' || ($listRow['share_token_hash'] ?? null) === null) {
            return null;
        }
        if (preg_match('#^https?://[^\s]+/p/([0-9a-f]{64})$#', $raw, $eslesme) !== 1) {
            return null;
        }
        // Verilen token GERÇEKTEN bu listenin linki mi? Hash'i karşılaştırılır.
        $hash = hash('sha256', $eslesme[1]);

        return hash_equals((string) $listRow['share_token_hash'], $hash) ? $raw : null;
    }

    /**
     * GET /api/lists/{id}/exports — export geçmişi (tarih + tür + indirme kimliği).
     *
     * @param array<string, string> $args
     */
    public function history(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $listId = $this->intArg($args, 'id');
        if ($listId === null || $this->lists->find($listId) === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }

        $rows = [];
        foreach ($this->exports->forList($listId) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'format' => (string) $row['format'],
                'file_size' => $row['file_size'] === null ? null : (int) $row['file_size'],
                'list_revision' => (int) $row['list_revision'],
                'created_at' => \App\Core\Dates::toIso((string) $row['created_at'], $this->timezone()),
            ];
        }

        return Response::success($response, $rows);
    }

    /**
     * GET /api/exports/{id}/file — geçmiş kaydı, SAKLANAN snapshot'tan yeniden üretir.
     *
     * Liste o günden beri değişmiş olsa bile içerik aynı kalır (K25: export = anlık
     * görüntü); yeni hali istemek = liste detayından yeni export üretmek.
     *
     * @param array<string, string> $args
     */
    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $exportId = $this->intArg($args, 'id');
        $row = $exportId === null ? null : $this->exports->find($exportId);
        if ($row === null || !is_string($row['snapshot_json'])) {
            return Response::error($response, 'NOT_FOUND', 'Export kaydı bulunamadı.', 404);
        }

        $renderer = $this->renderers[(string) $row['format']] ?? null;
        if ($renderer === null) {
            return Response::error($response, 'SERVER_ERROR', 'Bu biçim artık desteklenmiyor: ' . $row['format'], 500);
        }

        /** @var array<string, mixed>|null $snapshot */
        $snapshot = json_decode($row['snapshot_json'], true);
        if (!is_array($snapshot)) {
            return Response::error($response, 'SERVER_ERROR', 'Export kaydının anlık görüntüsü okunamadı.', 500);
        }

        try {
            $bytes = $renderer->render($snapshot);
        } catch (ExportException $e) {
            return Response::error($response, 'SERVER_ERROR', $e->getMessage(), 500);
        }

        $name = is_array($snapshot['list'] ?? null) ? (string) ($snapshot['list']['name'] ?? 'liste') : 'liste';

        return $this->stream($response, $bytes, $renderer, $name, $this->clock->now());
    }

    private function stream(
        ResponseInterface $response,
        string $bytes,
        ExportRenderer $renderer,
        string $listName,
        \DateTimeImmutable $now,
    ): ResponseInterface {
        // Dosya adı: Türkçe karakterler sadeleştirilir; RFC 5987 filename* ile tam ad da verilir.
        $slug = trim((string) preg_replace('/-+/', '-', (string) preg_replace(
            '/[^a-z0-9]+/',
            '-',
            strtolower(str_replace(
                ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'],
                ['c', 'g', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'o', 's', 'u'],
                $listName,
            )),
        )), '-');
        $fileName = sprintf(
            'tedarik-%s-%s.%s',
            $slug === '' ? 'liste' : $slug,
            $now->format('Ymd-Hi'),
            $renderer->extension(),
        );

        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', $renderer->mime())
            ->withHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'no-store');
    }

    private function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get());
    }
}
