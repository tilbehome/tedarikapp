<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Response;
use App\Middleware\Auth;
use App\Models\FirmaRepository;
use App\Models\ShareRepository;
use App\Models\TeklifTuruRepository;
use App\Services\Tur\TeklifTuruServisi;
use App\Services\Tur\TurGecisiReddedildi;
use App\Services\Tur\TurIslemiReddedildi;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TEKLİF TURU + FİRMA UÇLARI (V3-C Aşama 2.1) — yalnız taşıma katmanı.
 *
 * İş kuralları `TeklifTuruServisi`nde; burada gövde okunur, sonuç zarfa
 * konur, iki istisna türü iki HTTP koduna çevrilir:
 *   · `TurGecisiReddedildi`  → 422 `TUR_GECIS`  (durum makinesi)
 *   · `TurIslemiReddedildi`  → 422 `<kod>`      (iş kuralı: TUR_ACIK, LISTE_BOS…)
 *
 * SAHİBİN ELLE DURUM YAZMA YOLU YOKTUR: her geçiş kendi eylem ucudur
 * (gonder / onayla / revizyon / vazgec). `PATCH state=VIEWED` diye bir şey
 * olsaydı "firma açtı" gözlemi sahibin ilan ettiği bir şeye dönerdi.
 */
final class TeklifTuruController
{
    public function __construct(
        private readonly TeklifTuruServisi $servis,
        private readonly TeklifTuruRepository $turlar,
        private readonly FirmaRepository $firmalar,
        private readonly ShareRepository $paylasimlar,
        private readonly Clock $clock,
        private readonly ?\App\Core\Config $appConfig = null,
    ) {
    }

    // ── Firmalar (çekirdek) ─────────────────────────────────────────────

    public function firmalar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->user($request);

        return Response::success($response, array_map(fn (array $f): array => $this->firmaSun($f), $this->firmalar->all()));
    }

    public function firmaOlustur(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->user($request);
        $body = $this->body($request);
        $ad = is_string($body['ad'] ?? null) ? trim($body['ad']) : '';
        if ($ad === '' || mb_strlen($ad) > 190) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['ad' => 'Firma adı zorunludur (en çok 190 karakter).']);
        }
        $dil = is_string($body['varsayilan_dil'] ?? null) ? $body['varsayilan_dil'] : 'zh';
        if (!in_array($dil, ['tr', 'en', 'zh'], true)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['varsayilan_dil' => 'Dil tr, en ya da zh olmalı.']);
        }

        $id = $this->firmalar->create([
            'ad' => $ad,
            'varsayilan_dil' => $dil,
            'ulke' => $this->nullableString($body['ulke'] ?? null, 64),
            'platform' => $this->nullableString($body['platform'] ?? null, 30),
            'whatsapp' => $this->nullableString($body['whatsapp'] ?? null, 32),
            'eposta' => $this->nullableString($body['eposta'] ?? null, 190),
            'notlar' => $this->nullableString($body['notlar'] ?? null, 2000),
            'varsayilan_gecerlilik_gun' => is_numeric($body['varsayilan_gecerlilik_gun'] ?? null) ? max(1, (int) $body['varsayilan_gecerlilik_gun']) : null,
        ], $this->clock->now());

        $firma = $this->firmalar->find($id);

        return Response::success($response, $firma === null ? null : $this->firmaSun($firma), [], 201);
    }

    // ── Turlar ──────────────────────────────────────────────────────────

    /**
     * GET /api/lists/{id}/turlar — listenin bütün turları (firma × tur).
     *
     * @param array<string, string> $args
     */
    public function listeninTurlari(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);
        $now = $this->clock->now();

        return Response::success($response, array_map(
            fn (array $t): array => $this->servis->sun($t, $now),
            $this->turlar->listeninTurlari((int) ($args['id'] ?? 0)),
        ));
    }

    /**
     * POST /api/lists/{id}/turlar — {firma_id, gecerlilik_gun?, portal_dili?} → 201 DRAFT tur.
     *
     * @param array<string, string> $args
     */
    public function ac(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        $body = $this->body($request);
        $firmaId = (int) ($body['firma_id'] ?? 0);
        if ($firmaId <= 0) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['firma_id' => 'Firma seçilmeli.']);
        }

        return $this->calistir($response, fn (): array => [
            $this->servis->ac((int) ($args['id'] ?? 0), $firmaId, $this->clock->now(), $user->id, ClientIp::from($request), [
                'gecerlilik_gun' => is_numeric($body['gecerlilik_gun'] ?? null) ? max(1, (int) $body['gecerlilik_gun']) : null,
                'portal_dili' => in_array($body['portal_dili'] ?? null, ['tr', 'en', 'zh'], true) ? (string) $body['portal_dili'] : null,
            ]),
            201,
        ]);
    }

    /**
     * GET /api/turlar/{id} — tur + (gönderildiyse) donmuş RFQ satırları.
     *
     * @param array<string, string> $args
     */
    public function goster(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);
        $tur = $this->turlar->find((int) ($args['id'] ?? 0));
        if ($tur === null) {
            return Response::error($response, 'NOT_FOUND', 'Tur bulunamadı.', 404);
        }
        $sunum = $this->servis->sun($tur, $this->clock->now());
        if ($tur['rfq_snapshot_id'] !== null) {
            $sunum['rfq_satirlari'] = array_map(static fn (array $s): array => [
                'rfq_satir_id' => (string) $s['rfq_satir_id'],
                'product_id' => $s['product_id'] === null ? null : (int) $s['product_id'],
                'sira' => (int) $s['sira'],
                'urun_kodu' => $s['urun_kodu'],
                'urun_adi' => json_decode((string) $s['urun_adi_json'], true),
                'kaynak_urun' => $s['kaynak_urun_json'] === null ? null : json_decode((string) $s['kaynak_urun_json'], true),
                'talep_miktar' => (string) $s['talep_miktar'],
                'talep_birim' => (string) $s['talep_birim'],
                'gorsel_url' => $s['gorsel_url'],
            ], $this->turlar->rfqSatirlari((int) $tur['rfq_snapshot_id']));
        }

        return Response::success($response, $sunum);
    }

    /**
     * POST /api/turlar/{id}/gonder — {gecerlilik_gun?, kanal?, alici?}.
     *
     * Tam token ve 6 haneli anahtar YALNIZ bu yanıtta döner (K51): panel
     * bağlantıyı ve anahtarı AYRI kanallardan iletir; sunucu düz token saklamaz.
     *
     * @param array<string, string> $args
     */
    public function gonder(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        $body = $this->body($request);

        return $this->calistir($response, function () use ($request, $args, $body, $user): array {
            $sonuc = $this->servis->gonder((int) ($args['id'] ?? 0), $this->clock->now(), $user->id, ClientIp::from($request), [
                'gecerlilik_gun' => is_numeric($body['gecerlilik_gun'] ?? null) ? max(1, (int) $body['gecerlilik_gun']) : null,
                'kanal' => in_array($body['kanal'] ?? null, ['whatsapp', 'eposta', 'panel', 'diger'], true) ? (string) $body['kanal'] : 'panel',
                'alici' => $this->nullableString($body['alici'] ?? null, 190),
            ]);
            $sunum = $this->servis->sun($sonuc['tur'], $this->clock->now());
            $sunum['satir_sayisi'] = $sonuc['satir_sayisi'];
            $sunum['share_token'] = $sonuc['share_token'];
            $sunum['erisim_anahtari'] = $sonuc['erisim_anahtari'];
            // İE#19 E5: adres APP_URL'den — Host başlığından değil.
            $sunum['share_url'] = \App\Core\AppUrl::to(
                $this->appConfig?->get('APP_URL'),
                $request,
                '/liste/' . $sonuc['share_token'],
                \App\Core\AppUrl::hostYedegiIzinli($this->appConfig?->get('APP_ENV')),
            );

            return [$sunum, 200];
        });
    }

    /**
     * POST /api/turlar/{id}/onayla
     *
     * @param array<string, string> $args
     */
    public function onayla(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);

        return $this->calistir($response, fn (): array => [
            $this->servis->onayla((int) ($args['id'] ?? 0), $this->clock->now(), $user->id, ClientIp::from($request)),
            200,
        ]);
    }

    /**
     * POST /api/turlar/{id}/vazgec — {sebep?}
     *
     * @param array<string, string> $args
     */
    public function vazgec(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        $body = $this->body($request);

        return $this->calistir($response, fn (): array => [
            $this->servis->vazgec((int) ($args['id'] ?? 0), $this->clock->now(), $this->nullableString($body['sebep'] ?? null, 500), $user->id, ClientIp::from($request)),
            200,
        ]);
    }

    /**
     * POST /api/turlar/{id}/revizyon — {sebep, rate_policy?: inherit|refresh} → 201 YENİ tur.
     *
     * @param array<string, string> $args
     */
    public function revizyon(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        $body = $this->body($request);
        $sebep = $this->nullableString($body['sebep'] ?? null, 500);
        if ($sebep === null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['sebep' => 'Revizyon gerekçesi zorunludur; firma bunu görür.']);
        }
        $politika = ($body['rate_policy'] ?? 'inherit') === 'refresh' ? 'refresh' : 'inherit';

        return $this->calistir($response, fn (): array => [
            $this->servis->revizyonIste((int) ($args['id'] ?? 0), $this->clock->now(), $sebep, $politika, $user->id, ClientIp::from($request)),
            201,
        ]);
    }

    /** GET /api/teklifler — {acik: [...], gecmis: [...]} (Teklifler modülü). */
    public function teklifler(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->user($request);
        $now = $this->clock->now();

        return Response::success($response, [
            'acik' => array_map(fn (array $t): array => $this->servis->sun($t, $now), $this->turlar->acikTurlar()),
            'gecmis' => array_map(fn (array $t): array => $this->servis->sun($t, $now), $this->turlar->gecmisTurlar()),
        ]);
    }

    /**
     * GET /api/lists/{id}/gonderim-gunlugu — hangi link kime ne zaman hangi kanaldan.
     *
     * @param array<string, string> $args
     */
    public function gonderimGunlugu(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);

        return Response::success($response, array_map(static fn (array $g): array => [
            'id' => (int) $g['id'],
            'supplier_round_id' => $g['supplier_round_id'] === null ? null : (int) $g['supplier_round_id'],
            'kanal' => (string) $g['kanal'],
            'alici' => $g['alici'],
            'dil' => $g['dil'],
            'not_metni' => $g['not_metni'],
            'token_prefix' => (string) $g['token_prefix'],
            'created_at' => (string) $g['created_at'],
        ], $this->paylasimlar->gonderimGecmisi((int) ($args['id'] ?? 0))));
    }

    // ── yardımcılar ─────────────────────────────────────────────────────

    /**
     * Servis çağrısını zarfa çevirir; iki istisna türü iki koda.
     *
     * @param callable(): array{0: array<string, mixed>, 1: int} $islem (tur satırı, HTTP durum)
     */
    private function calistir(ResponseInterface $response, callable $islem): ResponseInterface
    {
        try {
            [$tur, $durum] = $islem();
        } catch (TurGecisiReddedildi $ret) {
            return Response::error($response, 'TUR_GECIS', $ret->getMessage(), 422);
        } catch (TurIslemiReddedildi $ret) {
            $durumKodu = in_array($ret->kod, ['TUR_YOK', 'LISTE_YOK', 'FIRMA_YOK'], true) ? 404 : 422;

            return Response::error($response, $ret->kod, $ret->getMessage(), $durumKodu);
        }

        // Gönderim sunumu zaten hazırlanmış olabilir (share alanları ile).
        $sunum = isset($tur['etiket']) ? $tur : $this->servis->sun($tur, $this->clock->now());

        return Response::success($response, $sunum, [], $durum);
    }

    /**
     * @param  array<string, mixed> $f
     * @return array<string, mixed>
     */
    private function firmaSun(array $f): array
    {
        return [
            'id' => (int) $f['id'],
            'ad' => (string) $f['ad'],
            'tip' => (string) $f['tip'],
            'ulke' => $f['ulke'],
            'platform' => $f['platform'],
            'varsayilan_dil' => (string) $f['varsayilan_dil'],
            'varsayilan_gecerlilik_gun' => $f['varsayilan_gecerlilik_gun'] === null ? null : (int) $f['varsayilan_gecerlilik_gun'],
            'whatsapp' => $f['whatsapp'],
            'eposta' => $f['eposta'],
            'notlar' => $f['notlar'],
            'created_at' => (string) $f['created_at'],
        ];
    }

    private function nullableString(mixed $deger, int $enCok): ?string
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
