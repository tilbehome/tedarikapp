<?php

declare(strict_types=1);

namespace App\Controllers;

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
    ) {
    }

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
                foreach ($changes as $change) {
                    $this->settings->set($change['key'], $change['value']);
                    $this->recordRate($change['currency'], $change['value'], $now);
                }

                // İE#21 B5: kilitlenmemiş listeler güncel kuru İZLER. Aynı transaction
                // içindedir — kur değişip listeler eski kurda kalırsa panel ile belge
                // birbirini yalanlar ve hangisinin doğru olduğu anlaşılmaz.
                $tazelenen = (new ListRepository($this->connection))->kilitsizKurlariTazele(
                    $this->settings->yuanRate(),
                    $this->settings->usdRate(),
                );

                $this->activity->record(
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

        $sql = 'SELECT id, currency, rate, set_at FROM rate_history';
        $params = [];
        if ($currency !== '') {
            if (!in_array($currency, ['CNY', 'USD'], true)) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'currency' => 'Para birimi CNY veya USD olmalı.',
                ]);
            }
            $sql .= ' WHERE currency = :currency';
            $params['currency'] = $currency;
        }
        $sql .= ' ORDER BY set_at DESC, id DESC';

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'id' => (int) $row['id'],
                'currency' => (string) $row['currency'],
                'rate' => $this->money->formatRate((string) $row['rate']),
                'set_at' => Dates::toIso((string) $row['set_at'], $this->timezone),
            ];
        }

        return Response::success($response, $history);
    }

    private function recordRate(string $currency, string $rate, \DateTimeImmutable $now): void
    {
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
