<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\Translation\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Çeviri ÖNERİSİ ucu (İE#13 C4 — K54).
 *
 * Aynı gövde iki yüzeye hizmet eder:
 *   • POST /api/panel/translate-suggest      → oturum + CSRF (panel)
 *   • POST /api/extension/translate-suggest  → Bearer + mevcut hız sınırı (eklenti)
 *
 * Yanıt her zaman 200'dür: öneri yoksa `suggestion: null` döner. Hata kodu dönmemesi
 * bilinçlidir — çeviri akışın zorunlu parçası değildir, istemci sessizce geçer.
 */
final class TranslationController extends ApiController
{
    public function __construct(private readonly TranslationService $translation)
    {
    }

    public function suggest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $text = $this->str($this->body($request), 'text');
        if ($text === '') {
            return Response::error($response, 'VALIDATION', 'Çevrilecek metin boş olamaz.', 422);
        }
        if (mb_strlen($text) > TranslationService::MAX_LENGTH) {
            return Response::error(
                $response,
                'VALIDATION',
                sprintf('Metin en fazla %d karakter olabilir.', TranslationService::MAX_LENGTH),
                422,
            );
        }

        $result = $this->translation->suggest($text);

        return Response::success($response, [
            'suggestion' => $result['suggestion'],
            'cached' => $result['cached'],
            'provider' => $result['provider'],
            // K54: istemci bunu ürün adına KENDİLİĞİNDEN yazmaz — kullanıcı onayı şart.
            'is_suggestion' => true,
        ]);
    }
}
