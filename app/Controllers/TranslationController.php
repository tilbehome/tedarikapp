<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\Translation\Glossary;
use App\Services\Translation\TranslationService;
use App\Services\Translation\TranslatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

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
    /**
     * Toplu çevirinin TEK İSTEKTEKİ süre bütçesi (sn).
     *
     * `set_time_limit`e YASLANILMAZ (PM koşulu): paylaşımlı hostingde bu çağrı
     * çoğu zaman devre dışıdır ve olsa bile web sunucusunun kendi zaman aşımını
     * uzatmaz. Bunun yerine iş PARÇALANIR: her istek bütçesi kadar çalışır,
     * kalanı söyler, panel bir sonraki isteği atar.
     */
    private const TOPLU_BUTCE_SANIYE = 12;

    /**
     * Tekil "Çevir" düğmesinin süre bütçesi (sn) — İE#22 B2.
     *
     * SAHA (28 Ağu): gerçek LLM'de üç dil 20-40 sn sürüyor; paylaşımlı hosting
     * isteği kesiyor, arayüz SONSUZ SPINNER'da kalıyordu ("sonsuz spinner
     * yasak" — D12 ilkesi). Uç artık bütçeye sığdırdığını bitirir, kalanı
     * kuyruğa yazar ve DURUMU SÖYLER. Spinner en geç bütçe + ağ payı kadar
     * sürer.
     */
    private const URUN_BUTCE_SANIYE = 12;

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
        // D12: "çevir" dendiğinde İSTEĞİN İÇİNDE çeviren yürütücü.
        private readonly ?\App\Services\Translation\CeviriYurutucu $yurutucu = null,
        // D12: yanıt gönderildikten sonra kuyruk turu tetikleyen yardımcı.
        private readonly ?\App\Services\Kuyruk\KuyrukTetikleyici $tetikleyici = null,
        // V3-B C3: sözlük içe aktarımı NTF-GLOSSARY-IMPORTED doğurur.
        private readonly ?\App\Services\Bildirim\BildirimYayinci $bildirim = null,
    ) {
    }

    /**
     * POST /api/products/{id}/translate — TEK ÜRÜNÜ ŞİMDİ ÇEVİRİR (D12 madde 1).
     *
     * Ürün kartındaki "Çevir" düğmesinin ucu. Kuyruğa yazıp "sırada" demez:
     * eksik dilleri bu isteğin içinde tamamlar ve sonucu döndürür. Kullanıcı
     * düğmeye bastığında olan bitenin tamamını görür — sahada en çok şikâyet
     * edilen şey, bastığı düğmenin görünürde hiçbir şey yapmamasıydı.
     *
     * K54: onaylı elle düzeltme EZİLMEZ — yürütücü yalnız EKSİK dilleri ister,
     * `elle` sağlayıcılı satır zaten "kalıcı" sayılır ve dokunulmaz.
     */
    /** @param array<string, string> $args */
    public function translateProductNow(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->yurutucu === null) {
            return Response::error($response, 'SERVER_ERROR', 'Çeviri yürütücüsü yapılandırılmamış.', 500);
        }

        $urunId = (int) ($args['id'] ?? 0);
        if ($urunId <= 0) {
            return Response::error($response, 'VALIDATION', 'Ürün kimliği geçersiz.', 422);
        }

        $sonuc = $this->yurutucu->urunuTamamla($urunId, self::URUN_BUTCE_SANIYE);
        if ($sonuc['hata'] !== null) {
            return Response::error($response, 'SERVER_ERROR', $sonuc['hata'], 500);
        }

        // KALAN DİLLER KUYRUĞA YAZILIR ve kullanıcıya DURUM döner.
        //
        // Kuyruğa yazmak "unutuldu" demek değildir: panel ziyareti süpürmesi
        // (D12 madde 3) turu açar ve kalanlar kendiliğinden tamamlanır. Arayüz
        // bunu "çeviri sürüyor — kalan: EN, ZH" diye gösterir; spinner döndürüp
        // beklemez.
        $durum = 'tamamlandi';
        if ($sonuc['kalan'] !== []) {
            $durum = $sonuc['cevrilen'] === [] ? 'kuyruga_alindi' : 'kismen';
            if ($this->kuyruk !== null && $this->clock !== null) {
                $this->kuyruk->ekle(
                    \App\Services\Kuyruk\KuyrukIsleyicileri::TUR_CEVIRI,
                    'urun:' . $urunId,
                    ['urun_id' => $urunId],
                    $this->clock->now(),
                );
            }
            $this->tetikleyici?->yanittanSonraDene(true);
        }

        // Çevrilemeyen dil kaldıysa SEBEBİ söylenir. En sık sebep sağlayıcının
        // yapılandırılmamış olmasıdır; sessiz kalmak "zaten tamamdı" izlenimi
        // verir ve kullanıcı çevirinin neden gelmediğini hiçbir yerde bulamaz.
        $saglayiciHazir = $this->ceviriAyarlari === null
            || ($this->ceviriAyarlari->acikMi() && $this->ceviriAyarlari->anahtarVarMi());
        $engel = ($sonuc['kalan'] !== [] && !$saglayiciHazir)
            ? 'Çeviri sağlayıcısı yapılandırılmamış: Ayarlar > Çeviri bölümünden sağlayıcı ve API anahtarı girin.'
            : ($sonuc['kalan'] !== [] ? 'Çeviri üretilemedi; sağlayıcı yanıt vermemiş olabilir.' : null);

        $this->izBirak($request, 'ceviri_urun', sprintf(
            '#%d — eksik: %s · çevrilen: %s',
            $urunId,
            $sonuc['eksikti'] === [] ? 'yok' : implode('+', $sonuc['eksikti']),
            $sonuc['cevrilen'] === [] ? 'yok' : implode('+', $sonuc['cevrilen']),
        ));

        return Response::success($response, $sonuc + [
            'durum' => $durum,
            'engel' => $engel,
            'is_suggestion' => true,
        ]);
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
        // D1: model alanı BOŞALTILABİLMELİDİR — boş değer "sağlayıcının varsayılanına
        // dön" demektir ve panel bunu gri yer tutucuyla gösterir. Eskiden boş değer
        // yok sayılıyordu; kullanıcı yanlış yazdığı bir model adını SİLEMİYORDU.
        // Ayrım anahtarın VARLIĞIDIR: gönderilmediyse dokunulmaz, gönderildiyse yazılır.
        if (array_key_exists('model', $body)) {
            $this->ceviriAyarlari->modelKaydet($this->str($body, 'model'));
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
     * POST /api/settings/translation/test — BAĞLANTIYI TEST ET (İE#20 D1).
     *
     * Bu uç, çeviri akışının aksine **YEDEK KATMANA DÜŞMEZ**. Gerekçe: normal
     * çeviride sağlayıcı hatası kullanıcının işini durdurmamalı, o yüzden sessizce
     * sözlük+makine katmanına düşülür. Ama TEST DÜĞMESİNİN İŞİ tam da o hatayı
     * göstermektir — yedeğe düşerse düğme "çalışıyor" der ve yanlış model adı,
     * süresi dolmuş anahtar ya da kota sorunu HİÇ GÖRÜNMEZ. Kullanıcı sonra
     * "çeviriler neden zayıf?" diye sorar ve cevabı hiçbir ekranda bulunmaz.
     *
     * Bu yüzden burada `LlmIstemci` DOĞRUDAN çağrılır ve sağlayıcının hata metni
     * (model_not_found, 401, 429 …) kullanıcıya AYNEN iletilir. Yanıt 200'dür ve
     * sonucu gövde taşır: test bir arıza değil, bir ÖLÇÜMDÜR.
     */
    public function translationTest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->ceviriAyarlari === null) {
            return Response::error($response, 'SERVER_ERROR', 'Çeviri ayarları yapılandırılmamış.', 500);
        }

        $ayarlar = $this->ceviriAyarlari;
        $saglayici = $ayarlar->saglayici();
        $model = $ayarlar->model();
        $anahtar = $ayarlar->anahtar();

        if ($anahtar === null) {
            return Response::success($response, [
                'basarili' => false,
                'saglayici' => $saglayici,
                'model' => $model,
                'hata' => 'API anahtarı tanımlı değil. Anahtarı girip kaydettikten sonra tekrar deneyin.',
            ]);
        }

        $baslangic = microtime(true);
        try {
            // Küçük ve belirlenimci bir istek: amaç çeviri kalitesi değil,
            // "kimlik + model + ağ" üçlüsünün çalıştığını görmek.
            $yanit = (new \App\Services\Translation\LlmIstemci(15))->sor(
                $saglayici,
                $anahtar,
                $model,
                'Yalnızca geçerli JSON döndür. Başka hiçbir şey yazma.',
                '{"görev":"bağlantı testi","yanıt_şeması":{"durum":"tamam"}}',
            );
        } catch (Throwable $hata) {
            $this->izBirak($request, 'ceviri_test', 'Bağlantı testi BAŞARISIZ: ' . $saglayici . '/' . $model);

            return Response::success($response, [
                'basarili' => false,
                'saglayici' => $saglayici,
                'model' => $model,
                // Sağlayıcının söylediği AYNEN aktarılır: "model_not_found" gibi bir
                // metin, kullanıcının model adını düzeltmesi için gereken tek ipucudur.
                'hata' => $hata->getMessage(),
            ]);
        }

        $this->izBirak($request, 'ceviri_test', 'Bağlantı testi başarılı: ' . $saglayici . '/' . $model);

        return Response::success($response, [
            'basarili' => true,
            'saglayici' => $saglayici,
            'model' => $model,
            'sure_ms' => (int) round((microtime(true) - $baslangic) * 1000),
            'ornek_yanit' => mb_substr(trim($yanit), 0, 200),
        ]);
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
        if ($this->urunler === null || $this->yurutucu === null) {
            return Response::error($response, 'SERVER_ERROR', 'Çeviri yürütücüsü yapılandırılmamış.', 500);
        }

        $listeId = isset($this->body($request)['list_id']) ? (int) $this->body($request)['list_id'] : null;

        // D12 — DÜĞME ARTIK FİİLEN ÇEVİRİR.
        //
        // Eski hâl yalnız kuyruğa yazıyordu ve kuyruğu işleyen yoktu: kullanıcı
        // "142 ürün kuyruğa alındı" yazısını okuyup bekliyor, işler 1432 dakika
        // duruyordu. Artık bu istek ZAMAN BÜTÇELİ bir parça işler ve KALANI
        // söyler; panel bitene dek ardışık istek atar. Sekme kapanırsa kalanlar
        // kaybolmaz — bir sonraki tetikte (panel ziyareti, yakalama, cron)
        // kaldığı yerden sürer, çünkü ölçüt kuyruk değil VERİNİN KENDİSİDİR.
        $baslangic = microtime(true);
        $butce = self::TOPLU_BUTCE_SANIYE;
        $cevrilen = 0;
        $hatali = 0;
        $adaylar = $this->urunler->cevrilmemisler($listeId, 2000);
        $toplam = count($adaylar);

        foreach ($adaylar as $urunId) {
            if ((microtime(true) - $baslangic) >= $butce) {
                break;
            }

            $sonuc = $this->yurutucu->urunuTamamla($urunId);
            if ($sonuc['hata'] !== null) {
                $hatali++;

                continue;
            }
            if ($sonuc['cevrilen'] !== []) {
                $cevrilen++;
            }
        }

        $kalanlar = $this->urunler->cevrilmemisler($listeId, 2000);
        $kalan = count($kalanlar);

        // SEKME KAPANIRSA İŞ KAYBOLMAZ: kalanlar kuyruğa da yazılır. Kuyruk
        // artık tek yol değil ama EMNİYET: panel kapansa bile bir sonraki
        // tetikleyici (panel ziyareti, yakalama, varsa cron) kaldığı yerden
        // sürdürür. Anahtar ürün kimliğidir; aynı ürün iki kez sıraya girmez.
        if ($this->kuyruk !== null && $this->clock !== null) {
            $simdi = $this->clock->now();
            foreach ($kalanlar as $bekleyenId) {
                $this->kuyruk->ekle(
                    \App\Services\Kuyruk\KuyrukIsleyicileri::TUR_CEVIRI,
                    'urun:' . $bekleyenId,
                    ['urun_id' => $bekleyenId],
                    $simdi,
                );
            }
        }
        // Yanıt gider gitmez arkada bir tur daha denenir; kullanıcı beklemez.
        $this->tetikleyici?->yanittanSonraDene(true);

        $this->izBirak($request, 'ceviri_toplu', sprintf('%d ürün çevrildi, %d kaldı', $cevrilen, $kalan));

        // SEBEBİ SÖYLE: hiç ilerleme olmadıysa kullanıcı NEDEN olmadığını
        // bilmeli. En sık sebep sağlayıcının yapılandırılmamış olmasıdır;
        // "0 çevrildi" deyip susmak, düğmenin bozuk olduğunu düşündürür.
        $saglayiciHazir = $this->ceviriAyarlari === null
            || ($this->ceviriAyarlari->acikMi() && $this->ceviriAyarlari->anahtarVarMi());
        $engel = (!$saglayiciHazir && $cevrilen === 0 && $toplam > 0)
            ? 'Çeviri sağlayıcısı yapılandırılmamış: Ayarlar > Çeviri bölümünden sağlayıcı ve API anahtarı girin.'
            : null;

        return Response::success($response, [
            'toplam' => $toplam,
            'cevrilen' => $cevrilen,
            'engel' => $engel,
            'hatali' => $hatali,
            'kalan' => $kalan,
            // Panel bu bayrağa bakar: true ise aynı ucu tekrar çağırır.
            'devam_var' => $kalan > 0 && $cevrilen > 0,
            'mesaj' => $engel ?? ($toplam === 0
                ? 'Tüm ürünler üç dilde tamam.'
                : sprintf('%d ürün çevrildi, %d ürün kaldı.', $cevrilen, $kalan)),
        ]);
    }

    /** İlerleme sorgusu: panel "N/M" göstergesini bununla tazeler. */
    public function translateProgress(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->urunler === null) {
            return Response::error($response, 'SERVER_ERROR', 'Ürün deposu yapılandırılmamış.', 500);
        }

        $listeId = $this->query($request, 'list_id') !== '' ? (int) $this->query($request, 'list_id') : null;

        return Response::success($response, [
            'kalan' => count($this->urunler->cevrilmemisler($listeId, 2000)),
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
     * GET /api/settings/glossary/disa-aktar?lang=zh — sözlüğü CSV olarak indirir
     * (V3-B C3 · PNL-50).
     *
     * CSV seçildi çünkü kullanıcının elindeki araç Excel'dir; JSON indirmek
     * "bunu neyle açacağım?" sorusunu doğururdu. BOM yazılır: Excel BOM'suz
     * UTF-8 CSV'yi Windows'ta bozuk gösterir ve Çince terimler soru işaretine
     * döner — sözlük tam olarak o karakterler için var.
     */
    public function glossaryDisaAktar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $dil = $this->dil(is_string($request->getQueryParams()['lang'] ?? null)
            ? (string) $request->getQueryParams()['lang']
            : '');

        $satirlar = "\xEF\xBB\xBFkaynak;turkce\r\n";
        foreach ($this->glossary->all($dil) as $kaynak => $karsilik) {
            $satirlar .= $this->csvHucre($kaynak) . ';' . $this->csvHucre($karsilik) . "\r\n";
        }

        $response->getBody()->write($satirlar);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="sozluk-%s-tr.csv"', $dil));
    }

    /**
     * POST /api/settings/glossary/ice-aktar — CSV içeriğini sözlüğe katar
     * (V3-B C3 · PNL-51).
     *
     * ÇAKIŞMADA KULLANICI TERİMİ KAZANIR ve bu emrin şartıdır: kullanıcı bir
     * terimi elle düzelttiyse, sonradan içe aktarılan bir dosya onu EZMEMELİDİR.
     * Dosyadan gelen satır YALNIZ o terim sözlükte yoksa yazılır.
     *
     * `storage/` üstyazımı korunur: `Glossary::save()` zaten oraya yazar,
     * `config/` altındaki temel sözlüğe DOKUNULMAZ.
     */
    public function glossaryIceAktar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $dil = $this->dil(is_string($body['lang'] ?? null) ? (string) $body['lang'] : '');
        $icerik = is_string($body['csv'] ?? null) ? (string) $body['csv'] : '';

        if (trim($icerik) === '') {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['csv' => 'Dosya boş.']);
        }
        if (!$this->glossary->writable($dil)) {
            return Response::error($response, 'SERVER_ERROR', 'Sözlük dosyası yazılabilir değil (storage/ izinleri).', 500);
        }

        $mevcut = $this->glossary->all($dil);
        $eklenen = 0;
        $atlanan = 0;
        $bozuk = 0;

        foreach ($this->csvSatirlari($icerik) as $sira => $satir) {
            if (count($satir) < 2) {
                $bozuk++;

                continue;
            }
            $kaynak = trim($satir[0]);
            $karsilik = trim($satir[1]);
            if ($kaynak === '' || $karsilik === '') {
                continue;
            }
            // BAŞLIK SATIRI TERİM DEĞİLDİR. Yalnız İLK satırda ve yalnız bilinen
            // başlık sözcükleriyle atlanır: "kaynak" sözcüğü bir terim olarak da
            // geçebilir, onu her yerde atlamak gerçek bir satırı yutardı.
            if ($sira === 0 && $this->baslikSatiriMi($kaynak, $karsilik)) {
                continue;
            }
            // ÇAKIŞMA: kullanıcının mevcut terimi KAZANIR.
            if (isset($mevcut[$kaynak])) {
                $atlanan++;

                continue;
            }
            $mevcut[$kaynak] = $karsilik;
            $eklenen++;
        }

        if (count($mevcut) > 5000) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['csv' => 'Sözlük en fazla 5000 terim taşıyabilir.']);
        }

        try {
            $this->glossary->save($mevcut, $dil);
        } catch (\Throwable $exception) {
            return Response::error($response, 'SERVER_ERROR', $exception->getMessage(), 500);
        }

        $auditId = null;
        if ($this->activity !== null && $this->clock !== null) {
            $auditId = $this->activity->record(
                'settings',
                null,
                'glossary_imported',
                sprintf('%s · %d eklendi, %d atlandı, %d bozuk satır', $dil, $eklenen, $atlanan, $bozuk),
                \App\Core\ClientIp::from($request),
                $this->clock->now(),
                \App\Services\ActivityLog::ACTOR_ADMIN,
                $this->user($request)->id,
            );
        }

        // V3-B A3/C3: sözlük içe aktarımı bildirim doğurur (NTF-GLOSSARY-IMPORTED).
        if ($auditId !== null) {
            $this->bildirim?->yayimla('NTF-GLOSSARY-IMPORTED', [
                'dil' => $dil,
                'terim_sayisi' => $eklenen,
                'surum' => $this->glossary->surum(),
            ], $auditId);
        }

        return Response::success($response, [
            'lang' => $dil,
            'eklenen' => $eklenen,
            'atlanan' => $atlanan,
            'bozuk' => $bozuk,
            'toplam' => count($mevcut),
        ]);
    }

    /**
     * İlk satır bir başlık satırı mı?
     *
     * Kendi dışa aktarımımız `kaynak;turkce` yazar ama kullanıcının dosyası
     * başka bir araçtan gelmiş olabilir. Bilinen sözcük çiftleriyle sınırlı
     * tutuluyor: "her ilk satırı atla" demek, başlıksız bir dosyanın ilk
     * terimini sessizce yutardı.
     */
    private function baslikSatiriMi(string $kaynak, string $karsilik): bool
    {
        $kaynakBasliklari = ['kaynak', 'source', 'terim', 'term', 'orijinal', 'original'];
        $hedefBasliklari = ['turkce', 'türkçe', 'turkish', 'karsilik', 'karşılık', 'ceviri', 'çeviri'];

        return in_array(mb_strtolower($kaynak), $kaynakBasliklari, true)
            && in_array(mb_strtolower($karsilik), $hedefBasliklari, true);
    }

    /** CSV hücresi — ayraç, tırnak ya da satır sonu varsa tırnaklanır. */
    private function csvHucre(string $deger): string
    {
        if (preg_match('/[";\r\n]/', $deger) !== 1) {
            return $deger;
        }

        return '"' . str_replace('"', '""', $deger) . '"';
    }

    /**
     * CSV'yi satırlara ayırır. Ayraç `;` VEYA `,` olabilir — Excel'in Türkçe
     * yereli noktalı virgül yazar, başka araçlar virgül. Kullanıcıya "hangi
     * ayracı kullanmalıyım?" diye sormak yerine ikisini de kabul ediyoruz.
     *
     * @return list<list<string>>
     */
    private function csvSatirlari(string $icerik): array
    {
        // Excel'in yazdığı BOM temizlenir; kalırsa ilk sütun adı görünmez bir
        // karakterle başlar ve başlık satırı tanınmaz.
        $icerik = preg_replace("/^\xEF\xBB\xBF/", '', $icerik) ?? $icerik;
        $ayrac = substr_count($icerik, ';') >= substr_count($icerik, ',') ? ';' : ',';

        $satirlar = [];
        foreach (preg_split('/\r\n|\r|\n/', $icerik) ?: [] as $ham) {
            if (trim($ham) === '') {
                continue;
            }
            // Kaçış karakteri BOŞ verilir: CSV standardında kaçış yoktur,
            // tırnak ikilenerek gösterilir. Ters bölü bırakmak, metindeki ters
            // bölüyü sessizce yutardı.
            $hucreler = str_getcsv($ham, $ayrac, '"', '');
            $satirlar[] = array_map(static fn (?string $h): string => (string) $h, $hucreler);
        }

        return $satirlar;
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
