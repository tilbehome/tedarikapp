<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Clock;
use App\Core\Response;
use App\Models\InboxRepository;
use App\Models\ListRepository;
use App\Services\CaptureApplier;
use App\Services\CaptureException;
use App\Services\CaptureService;
use App\Services\ListImmutableException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Eklenti uçları (İE#11 — docs/04 §2c v2, docs/10). ExtensionAuth arkasında koşar.
 */
final class ExtensionController extends ApiController
{
    public function __construct(
        private readonly CaptureService $capture,
        private readonly InboxRepository $inbox,
        private readonly ListRepository $lists,
        private readonly Clock $clock,
        private readonly string $basePath,
        private readonly ?CaptureApplier $applier = null,
        // D12 madde 4: yakalama yanıtı gittikten sonra fırsatçı kuyruk turu.
        private readonly ?\App\Services\Kuyruk\KuyrukTetikleyici $tetikleyici = null,
    ) {
    }

    /**
     * FIRSATÇI TETİK (D12 madde 4) — yakalama biter bitmez kuyruk denenir.
     *
     * Yeni yakalanan ürünün çevirisi ve galerisi kuyruğa yazılır; kullanıcı
     * ürünü panelde saniyeler içinde Türkçe görsün diye tur HEMEN denenir.
     * Yanıt önce gider (kapanış kancası), eklenti beklemez.
     *
     * GÜVENİLMEZ OLMASI KABULDÜR: bağlantı kapatılamayan bir sunucuda tur çok
     * kısa bütçeyle koşar ve belki hiçbir işi bitiremez. Emniyet madde 3'tür —
     * kullanıcı panele girdiğinde tur yeniden denenir.
     */
    private function firsatciTetik(): void
    {
        $this->tetikleyici?->yanittanSonraDene();
    }

    /**
     * POST /api/capture — v2 yükü: hedef liste seçiliyse doğrudan ürün, değilse kuyruk.
     * İdempotans: aynı capture_id ikinci kez gelirse İLK sonucun aynısı döner (K25).
     */
    public function capture(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) ($request->getParsedBody() ?? []);
        $now = $this->clock->now();

        $captureId = is_string($payload['capture_id'] ?? null) ? $payload['capture_id'] : '';
        if ($captureId !== '') {
            $existing = $this->inbox->findByCaptureId($captureId);
            if ($existing !== null) {
                // Tekrar deneme (eklenti kuyruğu): yeni kayıt AÇILMAZ.
                return Response::success($response, [
                    'inbox_id' => (int) $existing['id'],
                    'status' => (string) $existing['status'],
                    'product_id' => $existing['assigned_product_id'] === null ? null : (int) $existing['assigned_product_id'],
                    'duplicate' => null,
                    'idempotent_replay' => true,
                ], [], 201);
            }
        }

        $errors = $this->capture->validate($payload);
        if (isset($errors['capture_id'])) {
            // capture_id'siz istek idempotans garantisi veremez — kuyruk yerine 422.
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $errors);
        }

        $duplicate = $this->capture->duplicateOf($payload);

        if ($errors !== []) {
            // Doğrulanamayan gövde HAM haliyle kuyruğa düşer — veri kaybolmaz (docs/04 §2c).
            // rc8-03 (F-07): HATA YOLU DA ATOMİK. Ön kontrol bir okumadır; iki
            // eşzamanlı tekrar isteği onu geçebilir. UNIQUE ihlali burada bir
            // hata değil, "bu capture zaten kayıtlı" cevabıdır.
            $rezervasyon = $this->inbox->rezerveEtVeyaBul(
                $this->capture->inboxFields($payload, 'error', implode(' · ', $errors)),
                $now,
            );

            return Response::success($response, [
                'inbox_id' => $rezervasyon['id'],
                'status' => 'error',
                'product_id' => null,
                'duplicate' => $duplicate,
                'idempotent_replay' => !$rezervasyon['yeni'],
            ], [], 201);
        }

        $targetListId = $payload['target_list_id'] ?? null;
        if (is_int($targetListId) && $targetListId > 0) {
            $list = $this->lists->find($targetListId);
            if ($list === null) {
                return Response::error($response, 'NOT_FOUND', 'Hedef liste bulunamadı.', 404);
            }

            // İE#19 G6: rezervasyon + ürün + galeri + revizyon + kuyruk izi + ilk
            // durum/aktivite TEK transaction'da; terminal liste kuralı burada zorlanır.
            try {
                $sonuc = $this->applier()->applyToList(
                    $payload,
                    $list,
                    $now,
                    \App\Core\ClientIp::from($request),
                );
            } catch (ListImmutableException $e) {
                return Response::error($response, 'LIST_IMMUTABLE', $e->getMessage(), 422);
            } catch (CaptureException $e) {
                return Response::error($response, 'VALIDATION', $e->getMessage(), 422);
            }

            // D12 madde 4: ürün listeye düştü — çevirisi de hemen denensin.
            $this->firsatciTetik();

            return Response::success($response, [
                'inbox_id' => $sonuc['inbox_id'],
                'status' => $sonuc['status'],
                'product_id' => $sonuc['product_id'],
                'duplicate' => $duplicate,
                'idempotent_replay' => $sonuc['idempotent_replay'],
            ], [], 201);
        }

        // rc8-03 (F-07): VARSAYILAN GELEN KUTUSU YOLU DA ATOMİK.
        $rezervasyon = $this->inbox->rezerveEtVeyaBul($this->capture->inboxFields($payload), $now);

        $this->firsatciTetik();

        return Response::success($response, [
            'inbox_id' => $rezervasyon['id'],
            'status' => 'pending',
            'product_id' => null,
            'duplicate' => $duplicate,
            'idempotent_replay' => !$rezervasyon['yeni'],
        ], [], 201);
    }

    /**
     * Uygulayıcı zorunludur; opsiyonel parametre yalnız eski test kurulumları içindir.
     */
    private function applier(): CaptureApplier
    {
        if ($this->applier === null) {
            throw new \LogicException('CaptureApplier enjekte edilmedi (AppBuilder kompozisyonu).');
        }

        return $this->applier;
    }

    /**
     * GET /api/extension/selectors?platform=1688 — seçiciler VERİDİR (K53):
     * site yapısı değişince düzeltme eklenti güncellemesi olmadan buradan dağıtılır.
     */
    public function selectors(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $platform = strtolower($this->query($request, 'platform'));
        if ($platform === '') {
            $platform = '1688';
        }
        if (preg_match('/^[a-z0-9-]{1,30}$/', $platform) !== 1) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['platform' => 'Geçersiz platform adı.']);
        }

        $file = $this->basePath . '/app/Extension/selectors/' . $platform . '.json';
        if (!is_file($file)) {
            return Response::error($response, 'NOT_FOUND', 'Bu platform için seçici seti yok: ' . $platform, 404);
        }

        /** @var array<string, mixed>|null $selectors */
        $selectors = json_decode((string) file_get_contents($file), true);
        if (!is_array($selectors)) {
            return Response::error($response, 'SERVER_ERROR', 'Seçici seti okunamadı.', 500);
        }

        return Response::success($response, $selectors);
    }

    /** GET /api/extension/lists — eklentinin liste seçicisi (ad + id; taslak öncelikli sıra). */
    public function lists(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = [];
        foreach ($this->lists->all(['visibility' => 'active']) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'status' => (string) $row['status'],
            ];
        }

        return Response::success($response, $rows);
    }
}
