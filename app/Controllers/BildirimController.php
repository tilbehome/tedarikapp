<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Clock;
use App\Core\Response;
use App\Services\Bildirim\BildirimRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * BİLDİRİM MERKEZİ UÇLARI (V3-B A4).
 *
 * Dört uç: listele, sayaç, tek okundu, hepsi okundu. Bildirim ÜRETİMİ burada
 * DEĞİLDİR — olaylar kendi doğdukları yerde yayımlanır; bu denetleyici yalnız
 * okur ve okundu işaretler.
 *
 * A5 GÖRÜNÜM DESENİ: "anlık kart" kararı da sunucudan gelir. Panelin kendi
 * başına "bu kritik mi, gösterdim mi?" diye karar vermesi, aynı gerçeği iki
 * yoldan okumak olurdu — bu projenin tekrar eden hatası. Uç, gösterilecek
 * kartı `anlik` alanında AÇIKÇA söyler.
 */
final class BildirimController extends ApiController
{
    /** Anlık kart YALNIZ bu önem düzeyinde çıkar (A5). */
    private const ANLIK_ONEM = 'kritik';

    public function __construct(
        private readonly BildirimRepository $depo,
        private readonly Clock $clock,
    ) {
    }

    /**
     * GET /api/bildirimler?yalniz_okunmamis=1&limit=50
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sorgu = $request->getQueryParams();
        $limit = isset($sorgu['limit']) && is_numeric($sorgu['limit']) ? (int) $sorgu['limit'] : 50;
        $yalniz = ($sorgu['yalniz_okunmamis'] ?? '') === '1';

        $satirlar = $this->depo->listele($limit, $yalniz);

        return Response::success($response, [
            'bildirimler' => $satirlar,
            'okunmamis' => $this->depo->okunmamisSayisi(),
            // Anlık gösterilecek kart: en yeni OKUNMAMIŞ kritik bildirim.
            // Yoksa null — panel hiçbir şey açmaz.
            'anlik' => $this->anlikKart($satirlar),
        ]);
    }

    /** GET /api/bildirimler/sayac — üst çubuk rozeti (ucuz sorgu). */
    public function sayac(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Response::success($response, ['okunmamis' => $this->depo->okunmamisSayisi()]);
    }

    /**
     * POST /api/bildirimler/{id}/okundu
     *
     * @param array<string, string> $args
     */
    public function okundu(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return Response::error($response, 'VALIDATION', 'Geçersiz bildirim kimliği.', 422);
        }

        // Zaten okunmuş bildirimi yeniden işaretlemek HATA DEĞİLDİR: kullanıcı
        // iki sekmeden aynı satıra tıklamış olabilir. Yanıt her iki hâlde de
        // güncel sayacı döner — panel kendi durumunu ona göre tazeler.
        $degisti = $this->depo->okunduIsaretle($id, $this->clock->now());

        return Response::success($response, [
            'degisti' => $degisti,
            'okunmamis' => $this->depo->okunmamisSayisi(),
        ]);
    }

    /** POST /api/bildirimler/hepsi-okundu */
    public function hepsiOkundu(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sayi = $this->depo->hepsiniOkunduIsaretle($this->clock->now());

        return Response::success($response, ['isaretlenen' => $sayi, 'okunmamis' => 0]);
    }

    /**
     * @param  list<array<string, mixed>> $satirlar
     * @return array<string, mixed>|null
     */
    private function anlikKart(array $satirlar): ?array
    {
        foreach ($satirlar as $satir) {
            if ($satir['okundu'] === false && $satir['onem'] === self::ANLIK_ONEM) {
                return $satir;
            }
        }

        return null;
    }
}
