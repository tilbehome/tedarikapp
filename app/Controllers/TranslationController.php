<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\Translation\Glossary;
use App\Services\Translation\TranslationService;
use App\Services\Translation\TranslatorInterface;
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
    public function __construct(
        private readonly TranslationService $translation,
        // İE#14 A2: sözlük yönetimi (Ayarlar > Terminoloji) ve ürün bazlı çeviri
        // aynı controller'dan servis edilir — üçü de K56 hattının parçasıdır.
        private readonly Glossary $glossary,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * GET /api/settings/glossary?lang=zh|en — sözlük listesi (Ayarlar > Terminoloji).
     */
    public function glossaryIndex(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $dil = $this->dil($this->query($request, 'lang'));

        return Response::success($response, [
            'lang' => $dil,
            'writable' => $this->glossary->writable($dil),
            'terms' => $this->glossary->all($dil),
        ], ['languages' => Glossary::DILLER]);
    }

    /**
     * PUT /api/settings/glossary — {lang, terms:{kaynak: karşılık}} tüm sözlüğü yazar.
     *
     * Ekle/düzelt/sil tek uçtan yapılır: panel listeyi bütün olarak gönderir,
     * sunucu doğrulayıp DOSYAYA yazar (migration yok — K56 Katman 1 dosya tabanlı).
     */
    public function glossarySave(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $dil = $this->dil(is_string($body['lang'] ?? null) ? (string) $body['lang'] : '');
        $terms = $body['terms'] ?? null;

        if (!is_array($terms)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['terms' => 'Terim listesi zorunlu.']);
        }
        if (count($terms) > 5000) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['terms' => 'En fazla 5000 terim.']);
        }
        if (!$this->glossary->writable($dil)) {
            return Response::error($response, 'SERVER_ERROR', 'Sözlük dosyası yazılabilir değil (config/ izinleri).', 500);
        }

        /** @var array<string, string> $temiz */
        $temiz = [];
        foreach ($terms as $kaynak => $karsilik) {
            if (is_string($kaynak) && is_string($karsilik)) {
                $temiz[$kaynak] = $karsilik;
            }
        }

        try {
            $this->glossary->save($temiz, $dil);
        } catch (\Throwable $exception) {
            return Response::error($response, 'SERVER_ERROR', $exception->getMessage(), 500);
        }

        return Response::success($response, ['lang' => $dil, 'terms' => $this->glossary->all($dil)]);
    }

    /**
     * POST /api/panel/translate-product — ürünün TAMAMINI tek çağrıda çevirir (K56).
     * Yanıt yalnız ÖNERİDİR; hiçbir alan yazılmaz (K54).
     */
    public function translateProduct(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $urun = [
            'name' => $this->str($body, 'name'),
            'category' => $this->str($body, 'category'),
            'attributes' => is_array($body['attributes'] ?? null) ? $body['attributes'] : [],
            'variants' => is_array($body['variants'] ?? null) ? $body['variants'] : [],
        ];
        if ($urun['name'] === '' && $urun['attributes'] === [] && $urun['variants'] === []) {
            return Response::error($response, 'VALIDATION', 'Çevrilecek veri yok.', 422);
        }

        return Response::success($response, $this->translator->translateProduct($urun) + ['is_suggestion' => true]);
    }

    private function dil(string $deger): string
    {
        return in_array($deger, Glossary::DILLER, true) ? $deger : 'zh';
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
            // İE#14 A2: hangi katmandan geldi — 'sozluk' (belirlenimci) | 'makine'
            // (MyMemory). Arayüz makine çevirisini "makine çevirisi" etiketiyle gösterir.
            'source' => $result['source'] ?? null,
            // K54: istemci bunu ürün adına KENDİLİĞİNDEN yazmaz — kullanıcı onayı şart.
            'is_suggestion' => true,
        ]);
    }
}
