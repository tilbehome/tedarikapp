<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\SettingsRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * KAYDEDİLMİŞ GÖRÜNÜMLER — ekran başına (K105 §2.3: "mevcut `kesif.gorunumler`
 * deseni tek kaynaktır"). Keşif'in deseni burada ekran adıyla parametrelenir;
 * Keşif'in kendi ucu (`/api/kesif/gorunumler`) olduğu gibi durur — sözleşme
 * kırılmaz, yeni ekranlar bu genel ucu kullanır.
 *
 * Görünüm = ad + sorgu (URL durumu: sekme, sıralama, gruplama, sütunlar,
 * yoğunluk) + varsayılan bayrağı. Aynı adla ikinci kayıt ÜZERİNE YAZAR;
 * varsayılan TEK olabilir. Ayarlar tablosunda `gorunumler.<ekran>` anahtarı.
 */
final class GorunumController
{
    /** Yalnız tanımlı ekranlar; keyfi anahtar ayarlar tablosunu çöplüğe çevirirdi. */
    private const EKRANLAR = ['listeler'];
    private const AZAMI = 30;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * GET /api/gorunumler/{ekran}
     * @param array<string, string> $args
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ekran = $this->ekran($args);
        if ($ekran === null) {
            return Response::error($response, 'NOT_FOUND', 'Tanımsız ekran.', 404);
        }

        return Response::success($response, ['gorunumler' => $this->oku($ekran)]);
    }

    /**
     * POST /api/gorunumler/{ekran} — {ad, sorgu, varsayilan?}
     * @param array<string, string> $args
     */
    public function kaydet(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ekran = $this->ekran($args);
        if ($ekran === null) {
            return Response::error($response, 'NOT_FOUND', 'Tanımsız ekran.', 404);
        }
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $ad = trim((string) ($body['ad'] ?? ''));
        if ($ad === '') {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['ad' => 'Görünüm adı zorunludur.']);
        }
        $sorgu = [];
        foreach (is_array($body['sorgu'] ?? null) ? $body['sorgu'] : [] as $k => $v) {
            if (is_string($k) && (is_string($v) || is_numeric($v) || is_bool($v))) {
                $sorgu[mb_substr($k, 0, 40)] = mb_substr((string) $v, 0, 200);
            }
        }
        $varsayilan = ($body['varsayilan'] ?? false) === true;

        $gorunumler = $this->oku($ekran);
        if ($varsayilan) {
            foreach ($gorunumler as $i => $_) {
                $gorunumler[$i]['varsayilan'] = false;
            }
        }
        $yeni = ['ad' => mb_substr($ad, 0, 60), 'sorgu' => $sorgu, 'varsayilan' => $varsayilan];
        $bulundu = false;
        foreach ($gorunumler as $i => $mevcut) {
            if (mb_strtolower((string) $mevcut['ad']) === mb_strtolower($ad)) {
                $gorunumler[$i] = $yeni;
                $bulundu = true;
                break;
            }
        }
        if (!$bulundu) {
            if (count($gorunumler) >= self::AZAMI) {
                return Response::error($response, 'VALIDATION', sprintf('En fazla %d görünüm saklanabilir. Kullanmadığınız birini silin.', self::AZAMI), 422);
            }
            $gorunumler[] = $yeni;
        }
        $this->yaz($ekran, $gorunumler);

        return Response::success($response, ['gorunumler' => $gorunumler]);
    }

    /**
     * DELETE /api/gorunumler/{ekran}/{ad}
     * @param array<string, string> $args
     */
    public function sil(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ekran = $this->ekran($args);
        if ($ekran === null) {
            return Response::error($response, 'NOT_FOUND', 'Tanımsız ekran.', 404);
        }
        $ad = urldecode((string) ($args['ad'] ?? ''));
        $kalan = array_values(array_filter(
            $this->oku($ekran),
            static fn (array $g): bool => mb_strtolower((string) $g['ad']) !== mb_strtolower($ad),
        ));
        $this->yaz($ekran, $kalan);

        return Response::success($response, ['gorunumler' => $kalan]);
    }

    /** @param array<string, string> $args */
    private function ekran(array $args): ?string
    {
        $ekran = (string) ($args['ekran'] ?? '');

        return in_array($ekran, self::EKRANLAR, true) ? $ekran : null;
    }

    /** @return list<array{ad: string, sorgu: array<string, string>, varsayilan: bool}> */
    private function oku(string $ekran): array
    {
        $ham = $this->settings->get('gorunumler.' . $ekran);
        $veri = is_string($ham) && $ham !== '' ? json_decode($ham, true) : [];
        $out = [];
        foreach (is_array($veri) ? $veri : [] as $g) {
            if (is_array($g) && is_string($g['ad'] ?? null)) {
                $sorgu = [];
                foreach (is_array($g['sorgu'] ?? null) ? $g['sorgu'] : [] as $k => $v) {
                    $sorgu[(string) $k] = (string) $v;
                }
                $out[] = ['ad' => $g['ad'], 'sorgu' => $sorgu, 'varsayilan' => ($g['varsayilan'] ?? false) === true];
            }
        }

        return $out;
    }

    /** @param list<array{ad: string, sorgu: array<string, string>, varsayilan: bool}> $gorunumler */
    private function yaz(string $ekran, array $gorunumler): void
    {
        $this->settings->set('gorunumler.' . $ekran, json_encode($gorunumler, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }
}
