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
        // İE#21 B4 (PM şartı b): sistem listesi PAYLAŞILAMAZ.
        private readonly ?\App\Services\Inbox\SistemListesi $sistem = null,
        // V3-B A3: paylaşım olayları (oluşturma, yenileme, iptal) bildirim doğurur.
        private readonly ?\App\Services\Bildirim\BildirimYayinci $bildirim = null,
        // K103: paylaşım kaydı artık `shares` tablosunda.
        private readonly ?\App\Models\ShareRepository $shares = null,
    ) {
    }

    /**
     * GET /api/lists/{id}/share-text?lang=tr|en|zh — KANAL METNİ (İE#21 B6).
     *
     * Metin şablonu SUNUCUDAN gelir (`ShareTexts`): WhatsApp/e-posta metninin
     * Türkçe, İngilizce ve Çince karşılıkları tek yerde durur. Panelde ikinci bir
     * kopya yazmak, üç dilde iki ayrı gerçek demekti.
     *
     * BAĞLANTI SUNUCUYA GÖNDERİLMEZ: yanıt `{link}` yer tutucusunu OLDUĞU GİBİ
     * döner ve panel onu kendi belleğindeki adresle değiştirir. Tam token'ın
     * istek satırına (ve olası erişim günlüklerine) düşmemesi K51 disiplinidir.
     *
     * @param array<string, string> $args
     */
    public function text(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $listId = $this->intArg($args, 'id');
        $row = $listId === null ? null : $this->lists->find($listId);
        if ($row === null) {
            return Response::error($response, 'NOT_FOUND', 'Liste bulunamadı.', 404);
        }

        $dil = \App\Services\Share\ShareTexts::dil($request->getQueryParams()['lang'] ?? null);
        $adet = $this->lists->urunSayisi((int) $row['id']);
        $degerler = [
            'liste' => (string) $row['name'],
            'adet' => $adet,
            'link' => '{link}',
        ];
        $paylasim = $this->shares?->listeninAktifi((int) $row['id']);
        if (is_string($paylasim['expires_at'] ?? null) && $paylasim['expires_at'] !== '') {
            $degerler['tarih'] = (new \DateTimeImmutable((string) $paylasim['expires_at']))->format('d.m.Y');
        }

        return Response::success($response, [
            'dil' => $dil,
            'dil_adi' => \App\Services\Share\ShareTexts::dilAdi($dil),
            'mesaj' => \App\Services\Share\ShareTexts::mesaj($dil, $degerler),
            'konu' => \App\Services\Share\ShareTexts::metin($dil, 'eposta_konu', $degerler),
        ]);
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

        // K103: anahtar PAYLAŞIM kaydındadır. Paylaşım yoksa anahtar da yok —
        // "boş anahtar" göstermek yerine durumu açıkça söyleriz.
        $paylasim = $this->shares?->listeninAktifi((int) $row['id']);
        if ($paylasim === null) {
            return Response::error($response, 'NOT_FOUND', 'Bu listenin aktif paylaşımı yok.', 404);
        }
        $paylasim = $this->anahtar?->hazirla($paylasim, $this->clock->now()) ?? $paylasim;

        return Response::success($response, [
            'key' => (string) ($paylasim['key_plain'] ?? ''),
            'enabled' => (int) ($paylasim['key_enabled'] ?? 1) === 1,
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

        $auditId = $this->activity->record(
            'list',
            (int) $row['id'],
            'share_key_rotated',
            'erişim anahtarı yenilendi',
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
        $this->bildirim?->guvenliYayimla(
            'NTF-SHARE-KEY-RENEWED',
            ['liste_id' => (int) $row['id'], 'liste_adi' => (string) $row['name']],
            $auditId,
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
        $paylasim = $this->shares?->listeninAktifi((int) $row['id']);
        if ($paylasim === null) {
            return Response::error($response, 'NOT_FOUND', 'Bu listenin aktif paylaşımı yok.', 404);
        }
        $this->shares->anahtarKapisi((int) $paylasim['id'], $acik, $now);

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

        // Keşif Havuzu bir araştırma havuzudur; firmaya "sipariş listesi" diye
        // gitmesi sessiz bir felaket olurdu. Kapı sunucudadır (İE#21 B4).
        if ($this->sistem !== null && $this->sistem->sistemMi((int) $row['id'])) {
            return Response::error($response, 'SYSTEM_LIST', $this->sistem->redMesaji('paylaşılamaz'), 422);
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
        // K103: TEK YAZMA YOLU — paylaşım `shares` tablosuna açılır.
        // `lists` kolonlarına yazan yol kalmadı; iki kaynak arasında
        // sessizce ayrışma imkânsız.
        $oncekiPaylasim = $this->shares?->listeninAktifi((int) $row['id']);
        $isRenewal = $oncekiPaylasim !== null;
        $this->shares?->ac([
            'list_id' => (int) $row['id'],
            'token_hash' => hash('sha256', $token),
            'token_prefix' => substr($token, 0, 8),
            'expires_at' => $expiresAt,
        ], $now);
        $auditId = $this->activity->record(
            'list',
            (int) $row['id'],
            $isRenewal ? 'share_renewed' : 'share_created',
            'önek:' . substr($token, 0, 8) . ($expiresAt !== null ? ' son:' . $expiresAt : ''),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
        $this->bildirim?->guvenliYayimla(
            'NTF-SHARE-CREATED',
            ['liste_id' => (int) $row['id'], 'liste_adi' => (string) $row['name']],
            $auditId,
        );

        // İE#19 E5: adres AYARLARDAKİ APP_URL'den üretilir. Eskiden isteğin Host
        // başlığından türetiliyordu; Host istemcinin yazdığı bir değerdir ve sahte
        // bir Host, firmaya gidecek QR'a yabancı bir alan adı bastırabilirdi.
        $shareUrl = \App\Core\AppUrl::to(
            $this->appConfig?->get('APP_URL'),
            $request,
            '/liste/' . $token,
            \App\Core\AppUrl::hostYedegiIzinli($this->appConfig?->get('APP_ENV')),
        );

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
        $oncekiOnek = (string) ($this->shares?->listeninAktifi((int) $row['id'])['token_prefix'] ?? '—');
        $this->shares?->iptalEt((int) $row['id'], $now);

        $auditId = $this->activity->record(
            'list',
            (int) $row['id'],
            'share_revoked',
            'önek:' . $oncekiOnek,
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
        $this->bildirim?->guvenliYayimla(
            'NTF-SHARE-REVOKED',
            ['liste_id' => (int) $row['id'], 'liste_adi' => (string) $row['name']],
            $auditId,
        );

        return $response->withStatus(204);
    }
}
