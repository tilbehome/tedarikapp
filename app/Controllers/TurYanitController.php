<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Response;
use App\Middleware\Auth;
use App\Models\TeklifTuruRepository;
use App\Services\Tur\TurGecisiReddedildi;
use App\Services\Tur\TurIslemiReddedildi;
use App\Services\Yanit\ExcelIceAktarici;
use App\Services\Yanit\ExcelSablonu;
use App\Services\Yanit\ExcelSonucDosyasi;
use App\Services\Yanit\YanitDonusturucu;
use App\Services\Yanit\YanitUygulayici;
use App\Services\Yanit\YapistirAyristirici;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * FİRMA YANITI UÇLARI — YAPIŞTIR-AYRIŞTIR + EXCEL GEL-GİT (V3-C Aşama 2.2).
 *
 * Akış her iki kanalda aynıdır: ÖNİZLE (hiçbir şey yazılmaz) → sahip satır
 * seçer → UYGULA (tek transaction, parmak izi ile idempotent). Önizleme ile
 * uygulama ayrı uçlardır ki "yapıştırdım, yanlış ürüne yazıldı" olmasın:
 * yazım yalnız sahibin gördüğü ve seçtiği satırlar için olur.
 *
 * Yalnız taşıma katmanı; kurallar `YapistirAyristirici`, `ExcelIceAktarici`,
 * `YanitAlanKurallari`, `YanitUygulayici`de.
 */
final class TurYanitController
{
    private const EN_COK_METIN = 200_000;
    private const EN_COK_BASE64 = 4 * 1024 * 1024;

    public function __construct(
        private readonly TeklifTuruRepository $turlar,
        private readonly YanitUygulayici $uygulayici,
        private readonly YapistirAyristirici $ayristirici,
        private readonly ExcelSablonu $sablon,
        private readonly ExcelIceAktarici $iceAktarici,
        private readonly ExcelSonucDosyasi $sonucDosyasi,
        private readonly Clock $clock,
    ) {
    }

    /**
     * GET /api/turlar/{id}/yanit — mevcut taslak yanıt (rfq_satir_id → kanonik satır).
     *
     * @param array<string, string> $args
     */
    public function yanit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);
        $tur = $this->tur($args);
        if ($tur === null) {
            return Response::error($response, 'NOT_FOUND', 'Tur bulunamadı.', 404);
        }

        return Response::success($response, [
            'tur_id' => (int) $tur['id'],
            'state' => (string) $tur['state'],
            'satirlar' => $this->uygulayici->mevcutSatirlar((int) $tur['id']),
        ]);
    }

    /**
     * POST /api/turlar/{id}/yapistir-ayristir — {metin} → önizleme (yazmaz).
     *
     * @param array<string, string> $args
     */
    public function yapistirAyristir(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);
        $tur = $this->tur($args);
        if ($tur === null) {
            return Response::error($response, 'NOT_FOUND', 'Tur bulunamadı.', 404);
        }
        if ($tur['rfq_snapshot_id'] === null) {
            return Response::error($response, 'TUR_GONDERILMEMIS', 'Tur henüz gönderilmedi; RFQ satırları donmadan yanıt eşleştirilemez.', 422);
        }
        $body = $this->body($request);
        $metin = is_string($body['metin'] ?? null) ? $body['metin'] : '';
        if (trim($metin) === '') {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['metin' => 'Yapıştırılacak metin boş.']);
        }
        if (strlen($metin) > self::EN_COK_METIN) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['metin' => 'Metin 200 KB sınırını aşıyor.']);
        }

        $rfq = $this->turlar->rfqSatirlari((int) $tur['rfq_snapshot_id']);
        $baglam = array_map(static function (array $r): array {
            $adlar = json_decode((string) $r['urun_adi_json'], true);

            return [
                'satir_id' => (string) $r['rfq_satir_id'],
                'kod' => (string) ($r['urun_kodu'] ?? ''),
                'adlar' => is_array($adlar) ? array_values(array_filter(array_map('strval', $adlar), static fn (string $a): bool => $a !== '')) : [],
            ];
        }, $rfq);

        $sonuc = $this->ayristirici->ayristir($metin, $baglam);
        $mevcut = $this->uygulayici->mevcutSatirlar((int) $tur['id']);
        $rfqIndeks = [];
        foreach ($rfq as $r) {
            $rfqIndeks[(string) $r['rfq_satir_id']] = $r;
        }
        $satirlar = [];
        foreach ($sonuc['eslesmeler'] as $e) {
            $id = (string) $e['satir_id'];
            $adlar = json_decode((string) $rfqIndeks[$id]['urun_adi_json'], true);
            $hatalar = array_values(array_filter($sonuc['dogrulama_hatalari'], static fn (array $h): bool => $h['satir_id'] === $id));
            $satirlar[] = [
                'rfq_satir_id' => $id,
                'urun_kodu' => $rfqIndeks[$id]['urun_kodu'],
                'urun_adi' => is_array($adlar) ? $adlar : null,
                'talep_miktar' => YanitDonusturucu::sade((string) $rfqIndeks[$id]['talep_miktar']),
                'eslesme' => $e,
                'yeni' => YanitDonusturucu::yapistirdan($e),
                'eski' => $mevcut[$id] ?? null,
                'hatalar' => $hatalar,
                'eksik_zorunlu' => $e['eksik_zorunlu'],
                'secilebilir' => $hatalar === [],
                'varsayilan_secili' => $hatalar === [] && $e['eksik_zorunlu'] === [],
            ];
        }

        return Response::success($response, [
            'parmak_izi' => hash('sha256', $metin),
            'satirlar' => $satirlar,
            'belirsiz' => $sonuc['belirsiz'],
            'dogrulama_hatalari' => $sonuc['dogrulama_hatalari'],
            'eslesmeyen_satirlar' => array_values(array_diff(array_keys($rfqIndeks), array_column($satirlar, 'rfq_satir_id'))),
        ]);
    }

    /**
     * POST /api/turlar/{id}/yanit-uygula — {kaynak: yapistir|excel, parmak_izi, etiket?, satirlar: [kanonik + temizle?]}.
     *
     * @param array<string, string> $args
     */
    public function uygula(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        $tur = $this->tur($args);
        if ($tur === null) {
            return Response::error($response, 'NOT_FOUND', 'Tur bulunamadı.', 404);
        }
        $body = $this->body($request);
        $kaynak = ($body['kaynak'] ?? '') === 'excel' ? YanitUygulayici::KANAL_EXCEL : YanitUygulayici::KANAL_YAPISTIR;
        $parmakIzi = is_string($body['parmak_izi'] ?? null) ? trim($body['parmak_izi']) : '';
        if (preg_match('/^[a-f0-9]{64}$/', $parmakIzi) !== 1) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['parmak_izi' => 'Önizlemenin parmak izi (64 hex) zorunlu; tek kullanımlık uygulama anahtarıdır.']);
        }
        $satirlar = is_array($body['satirlar'] ?? null) ? array_values($body['satirlar']) : [];

        try {
            $sonuc = $this->uygulayici->uygula($tur, $satirlar, [
                'kanal' => $kaynak,
                'parmak_izi' => $parmakIzi,
                'etiket' => is_string($body['etiket'] ?? null) ? mb_substr($body['etiket'], 0, 190) : null,
            ], $user->id, ClientIp::from($request), $this->clock->now());
        } catch (TurGecisiReddedildi $ret) {
            return Response::error($response, 'TUR_GECIS', $ret->getMessage(), 422);
        } catch (TurIslemiReddedildi $ret) {
            return Response::error($response, $ret->kod, $ret->getMessage(), 422, [], ['hatalar' => $ret->ayrintilar]);
        }

        return Response::success($response, [
            'tekrar' => $sonuc['tekrar'],
            'yazilan' => $sonuc['yazilan'],
            'satirlar' => $sonuc['satirlar'],
            'state' => (string) $sonuc['tur']['state'],
            'yanit' => $this->uygulayici->mevcutSatirlar((int) $tur['id']),
        ]);
    }

    /**
     * POST /api/turlar/{id}/excel-sablon — {dil?} → .xlsx (firmaya gidecek şablon; mevcut taslak önceden dolu).
     *
     * @param array<string, string> $args
     */
    public function excelSablon(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);
        $tur = $this->tur($args);
        if ($tur === null) {
            return Response::error($response, 'NOT_FOUND', 'Tur bulunamadı.', 404);
        }
        if ($tur['rfq_snapshot_id'] === null) {
            return Response::error($response, 'TUR_GONDERILMEMIS', 'Tur gönderilmeden şablon üretilmez; RFQ önce donmalı.', 422);
        }
        $body = $this->body($request);
        $dil = in_array($body['dil'] ?? null, ['tr', 'en', 'zh'], true) ? (string) $body['dil'] : (string) ($tur['portal_dili'] ?? 'en');

        $bytes = $this->sablon->uret(
            $tur,
            $this->turlar->rfqSatirlari((int) $tur['rfq_snapshot_id']),
            $this->uygulayici->mevcutSatirlar((int) $tur['id']),
            $this->clock->now(),
            $dil,
        );

        return $this->dosya($response, $bytes, sprintf('RFQ-%d-R%d.xlsx', (int) $tur['list_id'], (int) $tur['tur_no']));
    }

    /**
     * POST /api/turlar/{id}/excel-ice-aktar — {dosya_base64} → önizleme grupları (yazmaz).
     *
     * @param array<string, string> $args
     */
    public function excelIceAktar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);
        $tur = $this->tur($args);
        if ($tur === null) {
            return Response::error($response, 'NOT_FOUND', 'Tur bulunamadı.', 404);
        }
        if ($tur['rfq_snapshot_id'] === null) {
            return Response::error($response, 'TUR_GONDERILMEMIS', 'Tur henüz gönderilmedi.', 422);
        }
        $body = $this->body($request);
        $b64 = is_string($body['dosya_base64'] ?? null) ? $body['dosya_base64'] : '';
        if ($b64 === '' || strlen($b64) > self::EN_COK_BASE64) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['dosya_base64' => 'Dosya boş ya da 3 MB sınırını aşıyor.']);
        }
        $bytes = base64_decode($b64, true);
        if ($bytes === false) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['dosya_base64' => 'Base64 çözülemedi.']);
        }

        try {
            $onizleme = $this->iceAktarici->onizle(
                $bytes,
                $tur,
                $this->turlar->rfqSatirlari((int) $tur['rfq_snapshot_id']),
                $this->uygulayici->mevcutSatirlar((int) $tur['id']),
            );
        } catch (TurIslemiReddedildi $ret) {
            return Response::error($response, $ret->kod, $ret->getMessage(), 422);
        }

        return Response::success($response, $onizleme);
    }

    /**
     * POST /api/turlar/{id}/excel-sonuc — {onizleme} → …-IMPORT-RESULT.xlsx (durumsuz: önizleme JSON'u geri verilir).
     *
     * @param array<string, string> $args
     */
    public function excelSonuc(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->user($request);
        $tur = $this->tur($args);
        if ($tur === null) {
            return Response::error($response, 'NOT_FOUND', 'Tur bulunamadı.', 404);
        }
        $body = $this->body($request);
        $onizleme = is_array($body['onizleme'] ?? null) ? $body['onizleme'] : [];
        if (!is_array($onizleme['satirlar'] ?? null)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['onizleme' => 'Önizleme satırları eksik.']);
        }
        $ad = sprintf('RFQ-%d-R%d-IMPORT-RESULT.xlsx', (int) $tur['list_id'], (int) $tur['tur_no']);

        return $this->dosya($response, $this->sonucDosyasi->uret($onizleme, $ad), $ad);
    }

    // ── yardımcılar ────────────────────────────────────────────────────

    private function dosya(ResponseInterface $response, string $bytes, string $ad): ResponseInterface
    {
        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $ad . '"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string, string> $args
     * @return ?array<string, mixed>
     */
    private function tur(array $args): ?array
    {
        return $this->turlar->find((int) ($args['id'] ?? 0));
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
