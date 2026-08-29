<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\PasswordHasher;
use App\Core\AppUrl;
use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Connection;
use App\Core\Dates;
use App\Core\Response;
use App\Models\ListRepository;
use App\Models\SettingsRepository;
use App\Services\ActivityLog;
use App\Services\InputValidator;
use App\Services\MediaService;
use App\Services\MoneyService;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Ayarlar ve kur uçları — docs/10 §7.
 *
 * Kur değişikliği YALNIZCA yeni oluşturulan listeleri etkiler: mevcut listeler kendi
 * kilitli kurlarını taşır (K4). Her değişiklik `rate_history`'ye yazılır — geçmiş bir
 * listenin neden o kurla hesaplandığı sonradan açıklanabilsin diye.
 */
final class SettingsController extends ApiController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SettingsRepository $settings,
        private readonly MediaService $media,
        private readonly MoneyService $money,
        private readonly InputValidator $validator,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
        private readonly DateTimeZone $timezone,
        // rc8/K4: APP_URL değişikliği parola tekrarı ister — yapıcının SONUNA
        // eklenir, konumsal çağrılar bozulmaz.
        private readonly PasswordHasher $passwords,
        // V3-B A3: kur onayı ve ayar kaydı bildirim doğurur.
        private readonly ?\App\Services\Bildirim\BildirimYayinci $bildirim = null,
    ) {
    }

    /** `settings` tablosundaki taban adres anahtarı (kurulum sihirbazı da bunu yazar). */
    private const KEY_APP_URL = 'APP_URL';

    /** GET /api/settings */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Response::success($response, [
            'yuan_tl' => $this->money->formatRate($this->settings->yuanRate()),
            'usd_tl' => $this->money->formatRate($this->settings->usdRate()),
            'totp_enabled' => $this->totpEnabled(),
            // İE#11: token DB'de hash'lidir; panelde yalnız son 4 hane görünür (K34).
            'extension_token_preview' => $this->settings->get(SettingsRepository::KEY_EXTENSION_TOKEN_PREVIEW),
            // K33 çift modu: panel rozeti bunu okur (Faz 1D).
            'media_mode' => $this->media->mode(),
            'media_writable' => $this->media->isWritable(),
            // İE#13 F1: belge antedi — çıktı ve paylaşım sayfası bandında görünür.
            'document_header' => $this->settings->documentHeader(),
            // İE#21 EK-4 (B7): kilit ekranındaki "yeni anahtar iste" köprüsünün
            // hedefi. Boşsa düğme basılmaz.
            'share_contact_phone' => $this->settings->get(SettingsRepository::KEY_SHARE_CONTACT_PHONE),
            // rc8/K4 (dış denetim F-08): paylaşım bağlantısının ve QR'ın tabanı.
            // Ekran hem DEĞERİ hem de KANONİK olup olmadığını gösterir; eksikse
            // kırmızı şerit basar — çünkü bu ayar olmadan paylaşım üretilemez.
            'app_url' => $this->settings->get(self::KEY_APP_URL),
            'app_url_kanonik' => AppUrl::kanonik($this->settings->get(self::KEY_APP_URL)) !== null,
        ]);
    }

    /**
     * PUT /api/settings/app-url — panelin DIŞA VERİLEN adresi (rc8/K4, F-08).
     *
     * Bu değer paylaşım bağlantısının, QR kodunun ve e-postaya kopyalanan
     * adresin tabanıdır. Yanlışsa müşteriye çalışmayan bir link gider; boşsa
     * uygulama link üretmeyi tümden reddeder (`AppUrlYokException`) — yani
     * alan sessiz kalamaz.
     *
     * PAROLA TEKRARI İSTENİR: açık oturumu ele geçiren biri bu tek alanı
     * değiştirerek bundan sonraki tüm paylaşım linklerini kendi sunucusuna
     * yönlendirebilirdi. Doğrulama, oturumun değil KULLANICININ kararı
     * olduğunu garanti eder.
     */
    public function updateAppUrl(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $ham = is_string($body['app_url'] ?? null) ? trim((string) $body['app_url']) : '';
        $parola = is_string($body['password'] ?? null) ? (string) $body['password'] : '';

        $kullanici = $this->user($request);
        if ($parola === '' || !$this->passwords->verify($parola, $kullanici->passwordHash)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'password' => 'Parolanız doğrulanamadı; adres değiştirilmedi.',
            ]);
        }

        $kanonik = AppUrl::kanonik($ham);
        if ($kanonik === null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'app_url' => 'Panelin tam adresini şema ile girin; yol, sorgu ya da '
                    . 'kullanıcı adı içeremez (örnek: https://tedarik.firma.com).',
            ]);
        }

        $onceki = $this->settings->get(self::KEY_APP_URL);
        $this->settings->set(self::KEY_APP_URL, $kanonik);

        $this->activity->record(
            'settings',
            null,
            'app_url_updated',
            // Adresin kendisi kayda GİRER: bu bir sır değil, denetim izidir —
            // "linkler ne zaman nereye bakmaya başladı" sorusu cevaplanabilmeli.
            ($onceki === null || $onceki === '' ? '(boş)' : $onceki) . ' → ' . $kanonik,
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $kullanici->id,
        );
        $this->bildirim?->yayimla('NTF-SETTINGS-CHANGED', [
            'kullanici_id' => $this->user($request)->id,
            'sekme_kodu' => 'genel',
            'ayar_grubu' => 'genel',
        ]);

        return Response::success($response, [
            'app_url' => $kanonik,
            'app_url_kanonik' => true,
        ]);
    }

    /**
     * PUT /api/settings/share-contact — paylaşım iletişim numarası (İE#21 EK-4).
     *
     * İSTEĞE BAĞLIDIR ve boş bırakılabilir; boş değer düğmeyi kapatır. Numara
     * ülke koduyla beklenir ("+90 532 …"); kaydederken kullanıcının yazdığı biçim
     * korunur, wa.me bağlantısı için rakama indirgeme OKUMA anında yapılır —
     * kullanıcı ayarlar ekranında kendi yazdığını görmeli.
     */
    public function updateShareContact(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $ham = is_string($body['share_contact_phone'] ?? null) ? trim((string) $body['share_contact_phone']) : '';

        if ($ham !== '') {
            $rakam = preg_replace('/\D+/', '', $ham) ?? '';
            if (mb_strlen($ham) > 32) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'share_contact_phone' => 'En fazla 32 karakter olabilir.',
                ]);
            }
            // 8 hane altı bir numara ülke kodlu olamaz; wa.me böyle bir bağlantıyı
            // sessizce boş sayfaya götürür — kullanıcı hatayı burada görmeli.
            if (strlen($rakam) < 8) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'share_contact_phone' => 'Ülke koduyla birlikte girin (örn. +90 532 123 45 67).',
                ]);
            }
        }

        $this->settings->set(SettingsRepository::KEY_SHARE_CONTACT_PHONE, $ham);

        $this->activity->record(
            'settings',
            null,
            'share_contact_updated',
            $ham === '' ? 'temizlendi' : 'güncellendi',
            \App\Core\ClientIp::from($request),
            $this->clock->now(),
            \App\Services\ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
        $this->bildirim?->yayimla('NTF-SETTINGS-CHANGED', [
            'kullanici_id' => $this->user($request)->id,
            'sekme_kodu' => 'paylasim',
            'ayar_grubu' => 'paylasim',
        ]);

        return Response::success($response, [
            'share_contact_phone' => $this->settings->get(SettingsRepository::KEY_SHARE_CONTACT_PHONE),
        ]);
    }

    /**
     * PUT /api/settings/document-header — İE#13 F1 "Ayarlar > Belge Antedi".
     *
     * Dört alan da İSTEĞE BAĞLIDIR; boş gönderilen alan temizlenir ve çıktıda
     * basılmaz. Serbest metindir ama uzunluk sınırlıdır — antet tek satırdır.
     */
    public function updateDocumentHeader(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $alanlar = [
            'company' => SettingsRepository::KEY_DOC_COMPANY,
            'web' => SettingsRepository::KEY_DOC_WEB,
            'email' => SettingsRepository::KEY_DOC_EMAIL,
            'prepared_by' => SettingsRepository::KEY_DOC_PREPARED_BY,
        ];

        $errors = [];
        foreach ($alanlar as $alan => $key) {
            if (!array_key_exists($alan, $body)) {
                continue;
            }
            $deger = is_string($body[$alan]) ? trim($body[$alan]) : '';
            if (mb_strlen($deger) > 120) {
                $errors[$alan] = 'En fazla 120 karakter olabilir.';
            }
            if ($alan === 'email' && $deger !== '' && filter_var($deger, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$alan] = 'Geçerli bir e-posta adresi girin.';
            }
        }
        if ($errors !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $errors);
        }

        foreach ($alanlar as $alan => $key) {
            if (array_key_exists($alan, $body)) {
                $this->settings->set($key, is_string($body[$alan]) ? trim($body[$alan]) : '');
            }
        }

        $this->activity->record(
            'settings',
            null,
            'document_header_updated',
            null,
            \App\Core\ClientIp::from($request),
            $this->clock->now(),
            \App\Services\ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
        $this->bildirim?->yayimla('NTF-SETTINGS-CHANGED', [
            'kullanici_id' => $this->user($request)->id,
            'sekme_kodu' => 'ciktilar',
            'ayar_grubu' => 'ciktilar',
        ]);

        return Response::success($response, $this->settings->documentHeader());
    }

    /**
     * POST /api/settings/extension-token — İE#11: eklenti token'ı üretir/yeniler.
     * Tam token YALNIZ bu yanıtta bir kez görünür; DB'de SHA-256 hash durur (K34).
     * Tek kullanıcı ÇOK CİHAZ: aynı token birden çok tarayıcıya girilir; yenileme/iptal
     * hepsini birden düşürür.
     */
    public function extensionTokenCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = 'tdk_' . bin2hex(random_bytes(24)); // 192-bit + tanınabilir önek
        $now = $this->clock->now();
        $this->settings->set(SettingsRepository::KEY_EXTENSION_TOKEN_HASH, hash('sha256', $token));
        $this->settings->set(SettingsRepository::KEY_EXTENSION_TOKEN_PREVIEW, '…' . substr($token, -4));

        $this->activity->record(
            'settings',
            null,
            'extension_token_created',
            'önizleme:…' . substr($token, -4),
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
        $this->bildirim?->yayimla('NTF-SETTINGS-CHANGED', [
            'kullanici_id' => $this->user($request)->id,
            'sekme_kodu' => 'guvenlik',
            'ayar_grubu' => 'guvenlik',
        ]);

        return Response::success($response, [
            'token' => $token,
            'extension_token_preview' => '…' . substr($token, -4),
        ]);
    }

    /** DELETE /api/settings/extension-token — iptal: eklenti istekleri anında 401 alır. */
    public function extensionTokenRevoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $now = $this->clock->now();
        $this->settings->set(SettingsRepository::KEY_EXTENSION_TOKEN_HASH, '');
        $this->settings->set(SettingsRepository::KEY_EXTENSION_TOKEN_PREVIEW, '');

        $this->activity->record(
            'settings',
            null,
            'extension_token_revoked',
            null,
            ClientIp::from($request),
            $now,
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
        $this->bildirim?->yayimla('NTF-SETTINGS-CHANGED', [
            'kullanici_id' => $this->user($request)->id,
            'sekme_kodu' => 'guvenlik',
            'ayar_grubu' => 'guvenlik',
        ]);

        return $response->withStatus(204);
    }

    /** PUT /api/settings/rates — {yuan_tl?, usd_tl?} */
    public function updateRates(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);

        $map = [
            'yuan_tl' => [SettingsRepository::KEY_YUAN_RATE, 'Yuan kuru', 'CNY'],
            'usd_tl' => [SettingsRepository::KEY_USD_RATE, 'Dolar kuru', 'USD'],
        ];

        $current = [
            'yuan_tl' => $this->money->formatRate($this->settings->yuanRate()),
            'usd_tl' => $this->money->formatRate($this->settings->usdRate()),
        ];

        $errors = [];
        $changes = [];
        $provided = 0;
        foreach ($map as $field => [$key, $label, $currency]) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $provided++;
            $error = $this->validator->rate($body[$field], $label);
            if ($error !== null) {
                $errors[$field] = $error;

                continue;
            }
            $value = $this->money->formatRate((string) $this->validator->toDecimalString($body[$field]));
            // K48 ek (İE#9.8 3b): AYNI değer tarihçeye YAZILMAZ — canlı vaka: aynı
            // 7,0400/41,5000 en az 8 kez kayıtlıydı ve "çalışmıyor" algısı yaratmıştı.
            if ($value === $current[$field]) {
                continue;
            }
            $changes[$field] = [
                'key' => $key,
                'currency' => $currency,
                'from' => $current[$field],
                'value' => $value,
            ];
        }

        if ($errors !== []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, $errors);
        }
        if ($provided === 0) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'body' => 'Güncellenecek kur verilmedi (yuan_tl ve/veya usd_tl).',
            ]);
        }

        if ($changes !== []) {
            $now = $this->clock->now();
            // K37 §B5: ayar + rate_history + aktivite tek transaction — geçmişsiz kur kalmaz.
            $this->connection->transaction(function () use ($request, $changes, $now): void {
                // İE#22 A2: KAYNAK BİLGİSİ TAŞINIR. Kullanıcı TCMB önerisini
                // onayladıysa gövdede `kaynak=tcmb` gelir; K4 gereği otomatik
                // yazma YOKTUR — yazan yine kullanıcının onayıdır, yalnız
                // değerin nereden geldiği kayda geçer.
                $kaynak = ($this->body($request)['kaynak'] ?? null) === \App\Models\RateSnapshotRepository::KAYNAK_TCMB
                    ? \App\Models\RateSnapshotRepository::KAYNAK_TCMB
                    : \App\Models\RateSnapshotRepository::KAYNAK_ELLE;
                $kullaniciId = $this->user($request)->id;

                foreach ($changes as $change) {
                    // Ayar kopyası ve snapshot AYNI transaction'da yazılır —
                    // çift kaynak riski ancak böyle kapanır (Nöbet Raporu 4 §2d).
                    $this->settings->set($change['key'], $change['value']);
                    $this->recordRate($change['currency'], $change['value'], $now, $kaynak, $kullaniciId);
                }

                // İE#21 B5: kilitlenmemiş listeler güncel kuru İZLER. Aynı transaction
                // içindedir — kur değişip listeler eski kurda kalırsa panel ile belge
                // birbirini yalanlar ve hangisinin doğru olduğu anlaşılmaz.
                $tazelenen = (new ListRepository($this->connection))->kilitsizKurlariTazele(
                    $this->settings->yuanRate(),
                    $this->settings->usdRate(),
                );

                $auditId = $this->activity->record(
                    'settings',
                    null,
                    'rates_updated',
                    implode(', ', array_map(
                        static fn (array $c): string => $c['currency'] . '=' . $c['from'] . '→' . $c['value'],
                        $changes,
                    )) . ($tazelenen > 0 ? sprintf(' · %d kilitlenmemiş liste tazelendi', $tazelenen) : ''),
                    ClientIp::from($request),
                    $now,
                    ActivityLog::ACTOR_ADMIN,
                    $this->user($request)->id,
                );
                $this->bildirim?->yayimla('NTF-FX-UPDATED', [
                    'kullanici_id' => $this->user($request)->id,
                    'kur_ozeti' => implode(', ', array_map(
                        static fn (array $c): string => $c['currency'] . ' ' . $c['value'],
                        $changes,
                    )),
                    'liste_sayisi' => $tazelenen,
                ], $auditId);
            });
        }

        return Response::success($response, [
            'yuan_tl' => $this->money->formatRate($this->settings->yuanRate()),
            'usd_tl' => $this->money->formatRate($this->settings->usdRate()),
            // 3b sözleşmesi: panel bildirimi buradan kurar — boş liste = "zaten güncel".
            'changes' => array_values(array_map(
                static fn (array $c): array => ['currency' => $c['currency'], 'from' => $c['from'], 'to' => $c['value']],
                $changes,
            )),
        ]);
    }

    /**
     * GET /api/settings/rates/suggest — TCMB'den güncel kur ÖNERİSİ (İE#21 B5).
     *
     * KAYDETMEZ. Yanıt panele gider, panel FORMU doldurur, kullanıcı "Kaydet"
     * derse kur değişir (K4: kur bir ticari karardır, kendiliğinden değişmez).
     * Kaynağa ulaşılamazsa hata GÖRÜNÜR — sessizce eski değerle devam edilmez.
     */
    public function suggestRates(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sonuc = (new \App\Services\Kur\KurKaynagi())->getir($this->clock->now());

        if ($sonuc['ok'] !== true) {
            return Response::error(
                $response,
                'UPSTREAM_UNAVAILABLE',
                isset($sonuc['hata']) ? (string) $sonuc['hata'] : 'Kur kaynağına ulaşılamadı.',
                502,
            );
        }

        return Response::success($response, [
            'yuan_tl' => $this->money->formatRate((string) $sonuc['yuan_tl']),
            'usd_tl' => $this->money->formatRate((string) $sonuc['usd_tl']),
            'kaynak' => $sonuc['kaynak'] ?? 'TCMB',
            'tarih' => $sonuc['tarih'] ?? null,
            // Panel bunu "şu an kayıtlı olan" ile karşılaştırıp değişimi gösterir.
            'mevcut' => [
                'yuan_tl' => $this->money->formatRate($this->settings->yuanRate()),
                'usd_tl' => $this->money->formatRate($this->settings->usdRate()),
            ],
        ]);
    }

    /** GET /api/settings/rates/history */
    public function rateHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currency = strtoupper($this->query($request, 'currency'));
        if ($currency !== '' && !in_array($currency, \App\Models\RateSnapshotRepository::PARA_BIRIMLERI, true)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'currency' => 'Para birimi CNY veya USD olmalı.',
            ]);
        }

        // İE#22 A3: kaynak `rate_history` DEĞİL `rate_snapshots`.
        //
        // Ekran artık üç şeyi birden söyler: hangi satır GEÇERLİ (aktif),
        // değeri nereden geldi (elle/TCMB) ve ne zamandan beri geçerli.
        // Eski tablo yerinde duruyor ama okuma buradan yapılır; iki kaynağı
        // aynı ekranda tutmak, hangisinin doğru olduğunu belirsizleştirirdi.
        $satirlar = (new \App\Models\RateSnapshotRepository($this->connection))
            ->gecmis($currency === '' ? null : $currency, 100);

        $gecmis = [];
        foreach ($satirlar as $satir) {
            $gecmis[] = [
                'id' => $satir['id'],
                'currency' => $satir['currency'],
                'rate' => $this->money->formatRate($satir['rate']),
                // Eski sözleşmedeki alan adı KORUNUR: panelin kur tarihçesi
                // tablosu `set_at` okuyor ve bu emirde ekran sözleşmesini
                // kırmak yok. Anlamı da aynı: kaydın geçerlilik başlangıcı.
                'set_at' => Dates::toIso($satir['effective_from'], $this->timezone),
                // İE#22 A3 — YENİ ALANLAR: hangi satır geçerli, değeri nereden
                // geldi, ne zaman devre dışı kaldı.
                'aktif' => $satir['aktif'],
                'kaynak' => $satir['source'],
                'superseded_at' => $satir['superseded_at'] === null
                    ? null
                    : Dates::toIso($satir['superseded_at'], $this->timezone),
            ];
        }

        return Response::success($response, $gecmis);

    }

    /**
     * Kur değişikliğini KALICI hâle getirir (İE#22 A2).
     *
     * İki yere birden yazar ve bu bilinçlidir:
     *   · `rate_snapshots` — YENİ GERÇEK. Öncekine bitiş damgası basılır,
     *     yeni satır aktif olur. Kaynak (elle/TCMB) ve onaylayan kullanıcı
     *     burada durur.
     *   · `rate_history` — eski defter. Silinmedi: dışarıdan okuyan bir şey
     *     kalmış olabilir ve göç dosyası (0034) onu kaynak alıyor. Yeni satır
     *     yazmayı bırakırsak iki tablo ayrışır; K85 gereği ikisi de aynı
     *     transaction'da güncellenir.
     *
     * Çağıran transaction AÇMIŞ olmalıdır (K37 §B5).
     */
    private function recordRate(
        string $currency,
        string $rate,
        \DateTimeImmutable $now,
        string $kaynak = \App\Models\RateSnapshotRepository::KAYNAK_ELLE,
        ?int $kullaniciId = null,
    ): void {
        (new \App\Models\RateSnapshotRepository($this->connection))
            ->yeniSurum($currency, $rate, $now, $kaynak, $kullaniciId);

        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO rate_history (currency, rate, set_at) VALUES (:currency, :rate, :set_at)',
        );
        $statement->execute([
            'currency' => $currency,
            'rate' => $rate,
            'set_at' => Dates::toStorage($now),
        ]);
    }

    private function totpEnabled(): bool
    {
        $statement = $this->connection->pdo()->query(
            'SELECT COUNT(*) AS total FROM users WHERE totp_secret IS NOT NULL',
        );
        $row = $statement === false ? false : $statement->fetch();

        return is_array($row) && (int) $row['total'] > 0;
    }
}
