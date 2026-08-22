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
        // İE#14 A7: sözlük değişikliği de iz bırakır — terim değişimi belgeyi değiştirir.
        private readonly ?\App\Services\ActivityLog $activity = null,
        private readonly ?\App\Core\Clock $clock = null,
        // İE#20 C4: Ayarlar > Çeviri (sağlayıcı/anahtar/model/diller) ve toplu çeviri.
        private readonly ?\App\Services\Translation\CeviriAyarlari $ceviriAyarlari = null,
        private readonly ?\App\Services\Kuyruk\JobQueue $kuyruk = null,
        private readonly ?\App\Models\ProductRepository $urunler = null,
    ) {
    }

    /**
     * GET /api/settings/translation — Ayarlar > Çeviri ekranının verisi.
     *
     * API ANAHTARI DÖNMEZ. Yalnız "tanımlı mı" ve maskeli önizleme döner: panel
     * ekranı omuz üstünden okunur, tarayıcı geçmişinde kalır, ekran görüntüsüne
     * girer. Bir sırrı göstermenin tek meşru anı, onu ÜRETTİĞİMİZ andır.
     */
    public function translationSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->ceviriAyarlari === null) {
            return Response::error($response, 'SERVER_ERROR', 'Çeviri ayarları yapılandırılmamış.', 500);
        }

        return Response::success($response, $this->ceviriAyarlari->ozet());
    }

    /**
     * PUT /api/settings/translation — sağlayıcı, anahtar, model, hedef diller.
     *
     * Anahtar BOŞ gönderilirse mevcut anahtar KORUNUR (panel maskeli değeri geri
     * gönderemez); anahtarı silmek için açıkça `"anahtar_sil": true` gerekir.
     */
    public function translationSettingsSave(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->ceviriAyarlari === null) {
            return Response::error($response, 'SERVER_ERROR', 'Çeviri ayarları yapılandırılmamış.', 500);
        }

        $body = $this->body($request);
        $hatalar = [];

        $saglayici = $this->str($body, 'saglayici');
        if ($saglayici !== '' && !in_array($saglayici, \App\Services\Translation\LlmTranslator::SAGLAYICILAR, true)) {
            $hatalar['saglayici'] = 'Tanınmayan sağlayıcı. Geçerli değerler: '
                . implode(', ', \App\Services\Translation\LlmTranslator::SAGLAYICILAR);
        }

        $diller = $body['hedef_diller'] ?? null;
        if ($diller !== null && !is_array($diller)) {
            $hatalar['hedef_diller'] = 'Hedef diller bir liste olmalı.';
        }

        if ($hatalar !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $hatalar);
        }

        if ($saglayici !== '') {
            $this->ceviriAyarlari->saglayiciKaydet($saglayici);
        }
        $model = $this->str($body, 'model');
        if ($model !== '') {
            $this->ceviriAyarlari->modelKaydet($model);
        }
        if (is_array($diller)) {
            /** @var list<string> $temizDiller */
            $temizDiller = array_values(array_filter(array_map(
                static fn (mixed $d): string => is_string($d) ? $d : '',
                $diller,
            )));
            $this->ceviriAyarlari->hedefDilleriKaydet($temizDiller);
        }
        if (array_key_exists('acik', $body)) {
            $this->ceviriAyarlari->acikKaydet(($body['acik'] ?? true) !== false);
        }

        if (($body['anahtar_sil'] ?? false) === true) {
            $this->ceviriAyarlari->anahtariKaydet('');
        } else {
            $anahtar = is_string($body['anahtar'] ?? null) ? trim($body['anahtar']) : '';
            if ($anahtar !== '') {
                $this->ceviriAyarlari->anahtariKaydet($anahtar);
            }
        }

        $this->izBirak($request, 'ceviri_ayari', 'Çeviri ayarları güncellendi');

        return Response::success($response, $this->ceviriAyarlari->ozet());
    }

    /**
     * POST /api/panel/translate-backfill — ÇEVRİLMEMİŞ ürünleri kuyruğa alır (C4).
     *
     * Neden kuyruk: 300 ürünü tek istekte çevirmek dakikalar sürer ve isteği
     * zaman aşımına uğratır. Kuyruk işi parça parça yapar; panel ilerlemeyi
     * `GET /api/system/queue` üzerinden okur.
     */
    public function translateBackfill(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->kuyruk === null || $this->urunler === null) {
            return Response::error($response, 'SERVER_ERROR', 'Kuyruk yapılandırılmamış.', 500);
        }

        $now = $this->clock?->now() ?? new \DateTimeImmutable();
        $listeId = isset($this->body($request)['list_id']) ? (int) $this->body($request)['list_id'] : null;

        $adaylar = $this->urunler->cevrilmemisler($listeId);
        $kuyruga = 0;
        foreach ($adaylar as $urunId) {
            $this->kuyruk->ekle(
                \App\Services\Kuyruk\KuyrukIsleyicileri::TUR_CEVIRI,
                'urun:' . $urunId,
                ['urun_id' => $urunId],
                $now,
            );
            $kuyruga++;
        }

        $this->izBirak($request, 'ceviri_toplu', $kuyruga . ' ürün çeviri kuyruğuna alındı');

        return Response::success($response, [
            'kuyruga_alinan' => $kuyruga,
            'mesaj' => $kuyruga === 0
                ? 'Çevrilmemiş ürün bulunamadı.'
                : $kuyruga . ' ürün çeviri kuyruğuna alındı. İlerlemeyi Sistem durumundan izleyebilirsiniz.',
        ]);
    }

    private function izBirak(ServerRequestInterface $request, string $eylem, string $detay): void
    {
        if ($this->activity === null || $this->clock === null) {
            return;
        }

        $this->activity->record(
            'settings',
            null,
            $eylem,
            $detay,
            \App\Core\ClientIp::from($request),
            $this->clock->now(),
            \App\Services\ActivityLog::ACTOR_ADMIN,
            null,
        );
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

        if ($this->activity !== null && $this->clock !== null) {
            $this->activity->record(
                'settings',
                null,
                'glossary_updated',
                $dil . ' · ' . count($temiz) . ' terim',
                \App\Core\ClientIp::from($request),
                $this->clock->now(),
                \App\Services\ActivityLog::ACTOR_ADMIN,
                $this->user($request)->id,
            );
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
