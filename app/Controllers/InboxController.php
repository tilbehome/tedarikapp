<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Connection;
use App\Core\Dates;
use App\Core\Response;
use App\Models\InboxRepository;
use App\Models\ListRepository;
use App\Models\SettingsRepository;
use App\Services\ActivityLog;
use App\Services\CaptureApplier;
use App\Services\CaptureException;
use App\Services\CaptureService;
use App\Services\Inbox\DesteEylemi;
use App\Services\ListImmutableException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Gelen Kutusu uçları (İE#11 Görev D — docs/10). Panel Auth+CSRF arkasında.
 * Kuyruk: pending/error kayıtlar; "listeye taşı" CaptureService ile ürüne çevirir.
 */
final class InboxController extends ApiController
{
    private const PER_PAGE = 20;
    private const MAX_IDS = 100;

    public function __construct(
        private readonly InboxRepository $inbox,
        private readonly ListRepository $lists,
        private readonly CaptureService $capture,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
        private readonly \DateTimeZone $timezone,
        private readonly ?CaptureApplier $applier = null,
        // İE#21 B4 (deste modu): havuz listesi ve geri alma bunları ister.
        // Opsiyoneldir — eski test kurulumları kırılmaz, deste uçları yoklarsa
        // açık hata verir (sessizce yanlış çalışmaktansa).
        private readonly ?Connection $connection = null,
        private readonly ?\App\Models\ProductRepository $products = null,
        private readonly ?SettingsRepository $settingsDeposu = null,
    ) {
    }

    private function desteEylemi(): DesteEylemi
    {
        if ($this->connection === null || $this->settingsDeposu === null) {
            throw new \LogicException('Deste modu bağımlılıkları enjekte edilmedi (AppBuilder kompozisyonu).');
        }

        return new DesteEylemi($this->lists, $this->settingsDeposu);
    }

    /** Uygulayıcı zorunludur; opsiyonel parametre yalnız eski test kurulumları içindir. */
    private function applier(): CaptureApplier
    {
        if ($this->applier === null) {
            throw new \LogicException('CaptureApplier enjekte edilmedi (AppBuilder kompozisyonu).');
        }

        return $this->applier;
    }

    /**
     * GET /api/inbox — bekleyen kuyruk (pending + error), filtreli + sayfali (IE#13 B5).
     *
     * Sorgu: `q` (baslikta arama), `platform`, `from`/`to` (YYYY-AA-GG), `page`.
     * Yanit IE#13'te `data + meta` zarfina gecti (aktivite ucuyla ayni desen).
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $filters = [
            'q' => $this->query($request, 'q'),
            'platform' => $this->query($request, 'platform'),
            'from' => $this->gun($this->query($request, 'from')),
            'to' => $this->gun($this->query($request, 'to')),
        ];

        $perPage = self::PER_PAGE;
        $page = max(1, (int) (is_string($params['page'] ?? null) ? $params['page'] : 1));
        $total = $this->inbox->countQueue($filters);

        $rows = [];
        foreach ($this->inbox->queue($filters, $perPage, ($page - 1) * $perPage) as $row) {
            $rows[] = $this->ozet($row);
        }

        return Response::success($response, $rows, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'platforms' => $this->inbox->platforms(),
        ]);
    }

    /**
     * GET /api/inbox/{id} — detay cekmecesi (IE#13 B3): payload'dan gorseller, fiyat
     * kademeleri, varyasyonlar, yakalanan ozellikler ve kaynak linki.
     *
     * Ham payload OLDUGU GIBI degil, ayiklanmis haliyle doner: arayuzun ihtiyaci budur,
     * bilinmeyen alanlar disari sizdirilmaz.
     *
     * @param array<string, string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->intArg($args, 'id');
        $item = $id === null ? null : $this->inbox->find($id);
        if ($item === null) {
            return Response::error($response, 'NOT_FOUND', 'Kayit bulunamadi.', 404);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $item['payload_json'], true) ?: [];
        $normalized = is_array($payload['normalized'] ?? null) ? $payload['normalized'] : [];
        $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];

        $detay = $this->ozet($item);
        $detay['images'] = $this->metinListesi($normalized['images'] ?? null);
        $detay['price_tiers'] = $this->kademeler($normalized['price_tiers'] ?? null);
        $detay['sku_matrix'] = $this->varyasyonlar($normalized['sku_matrix'] ?? null);
        $detay['attributes'] = $this->ozellikler($raw['normalized_attributes'] ?? null);
        $detay['seller_name'] = is_string($source['seller_name'] ?? null) ? $source['seller_name'] : null;
        $detay['captured_at'] = is_string($source['captured_at'] ?? null) ? $source['captured_at'] : null;
        $detay['raw_title'] = is_string($raw['title'] ?? null) ? $raw['title'] : null;

        return Response::success($response, $detay);
    }

    /**
     * POST /api/inbox/delete — toplu silme (IE#13 B1).
     *
     * SOZLESME NOTU: Gelen Kutusu kaydi COP KUTUSUNA GIRMEZ (docs/10, IE#11) — ham
     * yakalama verisidir ve `inbox_items` tablosunda `deleted_at` yoktur. Toplu silme
     * de bu nedenle kalicidir; arayuz onay ister. (Is emrindeki "cop kutusuna" ifadesi
     * belgeyle celisiyor; CLAUDE.md 1 geregi BELGE uygulandi, celiski raporlandi.)
     */
    public function bulkDelete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $ids = $this->kimlikler($this->body($request)['ids'] ?? null);
        if ($ids === []) {
            return Response::error($response, 'VALIDATION', 'Dogrulama hatasi', 422, ['body' => 'ids (1-100 kimlik) zorunlu.']);
        }

        $silinen = $this->inbox->deleteMany($ids);

        $this->activity->record(
            'inbox',
            null,
            'inbox_deleted',
            sprintf('toplu silme: %d kayit', $silinen),
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );

        return Response::success($response, ['deleted' => $silinen]);
    }

    /** POST /api/inbox/assign — {ids:[], list_id}: seçilenleri listeye ürün olarak taşır. */
    public function assign(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $listId = $body['list_id'] ?? null;
        $ids = $body['ids'] ?? null;
        // İE#13 B6 (K54): kullanıcı çeviri önerisini "Kullan" dediyse ürün adı bu olur.
        // Yalnız KULLANICI ONAYLI adlar gelir; RAW başlık payload'da değişmeden kalır.
        $adlar = $this->adlar($body['names'] ?? null);

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

            if (isset($adlar[$id]) && is_array($payload['normalized'] ?? null)) {
                $payload['normalized']['name'] = $adlar[$id];
            }

            // error kayıtları da taşınabilir OLMAYA ÇALIŞIR: zorunlu alanlar hâlâ eksikse net hata.
            $errors = $this->capture->validate($payload);
            if ($errors !== []) {
                $failed[] = ['id' => $id, 'error' => 'Eksik/bozuk alanlar: ' . implode(' · ', array_keys($errors))];

                continue;
            }

            // İE#19 G6: sahiplenme + ürün + tarihçe TEK transaction; terminal liste
            // kuralı burada da geçerli (kural artık iki yolda ORTAK serviste yaşar).
            try {
                $sonuc = $this->applier()->applyInboxItem(
                    $id,
                    $payload,
                    $list,
                    $now,
                    ClientIp::from($request),
                    $this->user($request)->id,
                    $this->requestId($request),
                );
            } catch (ListImmutableException $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];

                continue;
            } catch (CaptureException $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];

                continue;
            }
            if ($sonuc['idempotent_replay']) {
                $failed[] = ['id' => $id, 'error' => 'Kayıt bu sırada başka bir istek tarafından taşındı.'];

                continue;
            }
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
    /**
     * POST /api/inbox/deste — DESTE MODU tek eylemi (İE#21 B4 · E2E-PNL-18).
     *
     * Üç hedef, üç tuş: sol ok çöpe · aşağı ok havuza · sağ ok listeye.
     * Her çağrı TEK ürünü taşır ve TEK veritabanı geçişi üretir; deste modunun
     * hızı buradan gelir — toplu uçları tek tek çağırmak her tuşta bir doğrulama
     * turu ve gereksiz gecikme demekti.
     *
     * Yanıt GERİ ALMA bilgisini taşır (E2E-PNL-19): panel "Geri al" düğmesini
     * bununla kurar.
     */
    public function deck(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $hedef = (string) ($body['hedef'] ?? '');
        $id = $body['id'] ?? null;

        if (!is_int($id) || $id < 1) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'id' => 'Geçerli bir yakalama kimliği gerekir.',
            ]);
        }
        if (!in_array($hedef, [DesteEylemi::HEDEF_COP, DesteEylemi::HEDEF_HAVUZ, DesteEylemi::HEDEF_LISTE], true)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'hedef' => 'Hedef "cop", "havuz" ya da "liste" olabilir.',
            ]);
        }

        $kayit = $this->inbox->find($id);
        if ($kayit === null) {
            return Response::error($response, 'NOT_FOUND', 'Yakalama bulunamadı.', 404);
        }

        $now = $this->clock->now();
        $deste = $this->desteEylemi();

        // ── ÇÖPE ────────────────────────────────────────────────────────────
        if ($hedef === DesteEylemi::HEDEF_COP) {
            $this->inbox->delete($id);

            return Response::success($response, [
                'hedef' => $hedef,
                'inbox_id' => $id,
                // Çöpe atılan yakalama GERİ ALINAMAZ (kayıt silinir); panel bu
                // yüzden geri al düğmesini göstermez. Yanlışlıkla silinen ürün
                // kaynak sayfadan yeniden yakalanır — sahte bir "geri al" vaadi
                // vermek, veriyi geri getirmediği anda güveni kırardı.
                'geri_alinabilir' => false,
            ]);
        }

        // ── HAVUZA / LİSTEYE ────────────────────────────────────────────────
        $listeId = $hedef === DesteEylemi::HEDEF_HAVUZ
            ? $deste->havuzListesi($now)
            : (is_int($body['list_id'] ?? null) ? (int) $body['list_id'] : 0);

        if ($listeId < 1) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'list_id' => 'Listeye taşımak için hedef liste seçin.',
            ]);
        }

        // Taşıma mevcut `assign` yolunu KULLANIR: doğrulama, çeviri onayı ve
        // mükerrer kontrolü orada yaşar; ikinci bir kopya iki ayrı davranış demekti.
        $istek = $request->withParsedBody([
            'list_id' => $listeId,
            'ids' => [$id],
            'names' => $body['names'] ?? null,
        ]);

        // TEMİZ GÖVDE: `assign()` kendi JSON'unu yazar. Aynı yanıt nesnesini hem
        // ona hem de bize verirsek iki JSON alt alta yazılır ve gövde bozulur
        // (testte "Syntax error" olarak görüldü). İç çağrı kendi akışını alır.
        $icYanit = $response->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStream());
        $sonuc = $this->assign($istek, $icYanit);

        if ($sonuc->getStatusCode() !== 200) {
            return $sonuc;
        }

        /** @var array<string, mixed> $govde */
        $govde = json_decode((string) $sonuc->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $tasinan = (int) ($govde['data']['moved'] ?? 0);

        // TAŞINAMADIYSA SESSİZ GEÇİLMEZ: deste modunda kullanıcı bir tuşa basıp
        // sıradaki karta geçer; başarısızlık görünmezse ürün "işlendi" sanılır
        // ve Gelen Kutusu'nda kalmaya devam eder.
        if ($tasinan < 1) {
            $hatalar = is_array($govde['data']['failed'] ?? null) ? $govde['data']['failed'] : [];
            $ilk = is_array($hatalar[0] ?? null) ? (string) ($hatalar[0]['error'] ?? '') : '';

            return Response::error(
                $response,
                'VALIDATION',
                $ilk !== '' ? $ilk : 'Yakalama taşınamadı.',
                422,
            );
        }

        // Ürün kimliği yakalama kaydından okunur: `assign` toplu bir uçtur ve
        // özet döner; deste modu ise TEK ürünle çalışır ve geri alma için o
        // ürünün kimliğini bilmek zorundadır.
        $guncel = $this->inbox->find($id);
        $urunId = is_array($guncel) && $guncel['assigned_product_id'] !== null
            ? (int) $guncel['assigned_product_id']
            : 0;

        return Response::success($response, [
            'hedef' => $hedef,
            'inbox_id' => $id,
            'liste_id' => $listeId,
            'urun_id' => $urunId > 0 ? $urunId : null,
            'geri_alinabilir' => $urunId > 0,
        ]);
    }

    /**
     * POST /api/inbox/deste/geri-al — son deste eylemini tersine çevirir.
     *
     * Ürün silinir ve yakalama Gelen Kutusu'na DÖNER. İkinci çağrı ETKİSİZDİR
     * (E2E-PNL-19): ürün zaten yoksa "geri alındı" demek, kullanıcıya olmayan bir
     * iş yapılmış gibi gösterir ve sayaçları bozar.
     */
    public function deckUndo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $urunId = $body['urun_id'] ?? null;
        $inboxId = $body['inbox_id'] ?? null;

        if (!is_int($urunId) || !is_int($inboxId)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'body' => 'urun_id ve inbox_id zorunlu.',
            ]);
        }

        if ($this->products === null || $this->connection === null) {
            throw new \LogicException('Deste modu bağımlılıkları enjekte edilmedi.');
        }

        $urun = $this->products->find($urunId);
        if ($urun === null) {
            // İkinci tetik: etkisiz ve AÇIKÇA söylenir.
            return Response::success($response, ['geri_alindi' => false, 'neden' => 'Zaten geri alınmış.']);
        }

        $now = $this->clock->now();
        $this->connection->transaction(function () use ($urunId, $inboxId, $now): void {
            $this->products->softDelete($urunId, $now);
            $this->inbox->markPending($inboxId);
        });

        return Response::success($response, ['geri_alindi' => true, 'inbox_id' => $inboxId]);
    }

    /** @param array<string, string> $args */
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

    /**
     * Liste/detay ortak ozet alanlari.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function ozet(array $row): array
    {
        return [
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

    /** YYYY-AA-GG disindaki her sey yok sayilir (uydurma tarihle sorgu yapilmaz). */
    private function gun(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    /**
     * `{ "12": "Türkçe ad" }` → [12 => 'Türkçe ad']. Geçersiz anahtar/boş ad elenir;
     * ad 300 karakterle sınırlıdır (ürün adı kolonunun sınırı).
     *
     * @return array<int, string>
     */
    private function adlar(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $key => $value) {
            $id = is_int($key) ? $key : (preg_match('/^\d+$/', (string) $key) === 1 ? (int) $key : 0);
            if ($id < 1 || !is_string($value)) {
                continue;
            }
            $ad = trim($value);
            if ($ad !== '') {
                $out[$id] = mb_substr($ad, 0, 300);
            }
        }

        return $out;
    }

    /** @return list<int> */
    private function kimlikler(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach (array_slice($raw, 0, self::MAX_IDS) as $id) {
            if (is_int($id) && $id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @return list<string> */
    private function metinListesi(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $value) {
            if (is_string($value) && $value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }

    /** @return list<array{min_qty: int, price_yuan: string}> */
    private function kademeler(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $tier) {
            if (!is_array($tier)) {
                continue;
            }
            $price = $tier['price_yuan'] ?? null;
            if (!is_string($price) && !is_numeric($price)) {
                continue;
            }
            $out[] = ['min_qty' => (int) ($tier['min_qty'] ?? 1), 'price_yuan' => (string) $price];
        }

        return $out;
    }

    /** @return list<array{label: string, price_yuan: string|null}> */
    private function varyasyonlar(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $props = is_array($entry['props'] ?? null) ? $entry['props'] : [];
            $parcalar = [];
            foreach ($props as $value) {
                if (is_string($value) || is_numeric($value)) {
                    $parcalar[] = (string) $value;
                }
            }
            $price = $entry['price_yuan'] ?? null;
            $out[] = [
                'label' => $parcalar === [] ? '—' : implode(' / ', $parcalar),
                'price_yuan' => is_string($price) || is_numeric($price) ? (string) $price : null,
            ];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function ozellikler(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }
}
