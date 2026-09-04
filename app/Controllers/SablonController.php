<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Connection;
use App\Core\Dates;
use App\Core\Response;
use App\Middleware\Auth;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\SablonRepository;
use App\Models\SettingsRepository;
use App\Services\ActivityLog;
use App\Services\ListPresenter;
use App\Services\StateMachine;
use DateTimeZone;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * LİSTE ŞABLONLARI + TEKRAR SİPARİŞ (V3-C Blok E · 0039 `list_templates`).
 *
 * "Her sezon aynı 40 ürün" işi: bir listeden şablon alınır, her seferinde
 * ondan TASLAK açılır. Şablondan doğan liste günün kurunu alır (K4: kilit
 * iletimde), ürünleri `to_order`da doğar, takip kodu taşınmaz — kopyalama
 * (İE#20 C9) ile aynı kural; fark, kaynağın liste değil dondurulmuş JSON olması.
 *
 * Silme GERİ ALINABİLİRDİR ama çöp kutusu yoktur (şema): panel 5 sn erteler
 * ve ancak süre dolunca bu ucu çağırır (`GeriAlToast` ertelenmiş kip, K105
 * §2.6 "önce geri alınabilir yol").
 */
final class SablonController
{
    private const EN_COK_URUN = 500;

    public function __construct(
        private readonly Connection $connection,
        private readonly SablonRepository $sablonlar,
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly SettingsRepository $settings,
        private readonly ListPresenter $presenter,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
        private readonly DateTimeZone $timezone,
    ) {
    }

    /** GET /api/sablonlar */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->user($request);

        return Response::success($response, array_map(fn (array $s): array => $this->sun($s), $this->sablonlar->all()));
    }

    /**
     * POST /api/lists/{id}/sablon — {ad, aciklama?} → 201 şablon (listenin ürünleri dondurulur).
     *
     * @param array<string, string> $args
     */
    public function listedenOlustur(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        $liste = $this->lists->find((int) ($args['id'] ?? 0));
        if ($liste === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }
        $body = $this->body($request);
        $ad = is_string($body['ad'] ?? null) ? trim($body['ad']) : '';
        if ($ad === '' || mb_strlen($ad) > 190) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['ad' => 'Şablon adı zorunludur (en çok 190 karakter).']);
        }
        $urunler = [];
        foreach ($this->products->forList((int) $liste['id']) as $urun) {
            $kopya = [];
            foreach (ProductRepository::WRITABLE as $kolon) {
                if (in_array($kolon, ['tracking_no'], true)) {
                    continue;
                }
                $kopya[$kolon] = $urun[$kolon] ?? null;
            }
            $kopya['sort_no'] = (int) $urun['sort_no'];
            $urunler[] = $kopya;
        }
        if ($urunler === []) {
            return Response::error($response, 'LISTE_BOS', 'Ürünsüz listeden şablon alınmaz; şablon ürün kümesidir.', 422);
        }
        if (count($urunler) > self::EN_COK_URUN) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['urunler' => 'Şablon en çok 500 ürün taşır.']);
        }

        $now = $this->clock->now();
        $id = $this->sablonlar->create($ad, $this->nullable($body['aciklama'] ?? null, 500), $urunler, (int) $liste['id'], $now);
        $this->activity->record('list_template', $id, 'template_created', sprintf('%s · %d ürün · kaynak liste #%d', $ad, count($urunler), (int) $liste['id']), ClientIp::from($request), $now, ActivityLog::ACTOR_ADMIN, $user->id);

        $sablon = $this->sablonlar->find($id);

        return Response::success($response, $sablon === null ? null : $this->sun($sablon), [], 201);
    }

    /**
     * POST /api/sablonlar/{id}/liste — {name?, period?, supplier_name?} → 201 TASLAK liste (günün kuru, ürünler to_order).
     *
     * @param array<string, string> $args
     */
    public function listeOlustur(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        $sablon = $this->sablonlar->find((int) ($args['id'] ?? 0));
        if ($sablon === null) {
            return Response::error($response, 'NOT_FOUND', 'Şablon bulunamadı.', 404);
        }
        $body = $this->body($request);
        $ad = is_string($body['name'] ?? null) && trim($body['name']) !== '' ? trim($body['name']) : (string) $sablon['ad'];
        if (mb_strlen($ad) > 190) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['name' => 'Liste adı en çok 190 karakter.']);
        }
        $now = $this->clock->now();

        // Liste + ürünler tek transaction (K37 §B5): yarım liste kalmaz.
        $listId = $this->connection->transaction(function () use ($sablon, $ad, $body, $now): int {
            $listId = $this->lists->create([
                'name' => $ad,
                'period' => $this->nullable($body['period'] ?? null, 60),
                'supplier_name' => $this->nullable($body['supplier_name'] ?? null, 190),
                'note' => null,
                'status' => StateMachine::LIST_DRAFT,
                'visibility' => 'active',
                'yuan_rate' => $this->settings->yuanRate(),
                'usd_rate' => $this->settings->usdRate(),
            ], $now);
            foreach (SablonRepository::urunler($sablon) as $urun) {
                $veri = [];
                foreach (ProductRepository::WRITABLE as $kolon) {
                    $veri[$kolon] = $urun[$kolon] ?? null;
                }
                $veri['sort_no'] = isset($urun['sort_no']) ? (int) $urun['sort_no'] : null;
                $veri['status'] = StateMachine::PRODUCT_TO_ORDER;
                $veri['tracking_no'] = null;
                $this->products->create($listId, $veri, $now);
            }
            $this->sablonlar->kullanildi((int) $sablon['id'], $now);

            return $listId;
        });

        $this->activity->record('list', $listId, 'template_used', sprintf('şablon #%d "%s" → liste "%s"', (int) $sablon['id'], (string) $sablon['ad'], $ad), ClientIp::from($request), $now, ActivityLog::ACTOR_ADMIN, $user->id);

        $liste = $this->lists->find($listId);

        return Response::success($response, $liste === null ? null : $this->presenter->list($liste), [], 201);
    }

    /**
     * PATCH /api/sablonlar/{id} — {ad?, aciklama?}.
     *
     * @param array<string, string> $args
     */
    public function guncelle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);
        $sablon = $this->sablonlar->find((int) ($args['id'] ?? 0));
        if ($sablon === null) {
            return Response::error($response, 'NOT_FOUND', 'Şablon bulunamadı.', 404);
        }
        $body = $this->body($request);
        $ad = is_string($body['ad'] ?? null) ? trim($body['ad']) : (string) $sablon['ad'];
        if ($ad === '' || mb_strlen($ad) > 190) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['ad' => 'Şablon adı zorunludur (en çok 190 karakter).']);
        }
        $aciklama = array_key_exists('aciklama', $body) ? $this->nullable($body['aciklama'], 500) : ($sablon['aciklama'] === null ? null : (string) $sablon['aciklama']);
        $this->sablonlar->update((int) $sablon['id'], $ad, $aciklama, $this->clock->now());
        $guncel = $this->sablonlar->find((int) $sablon['id']);

        return Response::success($response, $guncel === null ? null : $this->sun($guncel));
    }

    /**
     * DELETE /api/sablonlar/{id} → 204. Kalıcıdır; geri alma penceresi panelde (ertelenmiş çağrı).
     *
     * @param array<string, string> $args
     */
    public function sil(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        $sablon = $this->sablonlar->find((int) ($args['id'] ?? 0));
        if ($sablon === null) {
            return Response::error($response, 'NOT_FOUND', 'Şablon bulunamadı.', 404);
        }
        $this->sablonlar->delete((int) $sablon['id']);
        $this->activity->record('list_template', (int) $sablon['id'], 'template_deleted', (string) $sablon['ad'], ClientIp::from($request), $this->clock->now(), ActivityLog::ACTOR_ADMIN, $user->id);

        return $response->withStatus(204);
    }

    // ── yardımcılar ────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function sun(array $s): array
    {
        $urunler = SablonRepository::urunler($s);

        return [
            'id' => (int) $s['id'],
            'ad' => (string) $s['ad'],
            'aciklama' => $s['aciklama'] === null ? null : (string) $s['aciklama'],
            'urun_sayisi' => count($urunler),
            'ornek_urunler' => array_map(static fn (array $u): string => (string) ($u['name'] ?? ''), array_slice($urunler, 0, 3)),
            'kaynak_list_id' => $s['kaynak_list_id'] === null ? null : (int) $s['kaynak_list_id'],
            'kullanim_sayisi' => (int) $s['kullanim_sayisi'],
            'son_kullanim_at' => $s['son_kullanim_at'] === null ? null : Dates::toIso((string) $s['son_kullanim_at'], $this->timezone),
            'created_at' => Dates::toIso((string) $s['created_at'], $this->timezone),
            'updated_at' => Dates::toIso((string) $s['updated_at'], $this->timezone),
        ];
    }

    private function nullable(mixed $deger, int $enCok): ?string
    {
        if (!is_string($deger)) {
            return null;
        }
        $temiz = trim($deger);

        return $temiz === '' ? null : mb_substr($temiz, 0, $enCok);
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();

        return is_array($parsed) ? $parsed : [];
    }

    private function user(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(Auth::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new LogicException('Korumalı uç Auth middleware olmadan çağrıldı.');
        }

        return $user;
    }
}
