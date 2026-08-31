<?php

declare(strict_types=1);

namespace App\Services;

/**
 * cURL tabanlı indirici (K8 — dış istekler YALNIZCA cURL ile; `allow_url_fopen` kapalı).
 *
 * Yönlendirmeleri cURL'e KÖRLEMESİNE takip ettirmez: her adım UrlGuard'dan geçmeli
 * (izinli alan adı + iç ağ değil). Bu yüzden `CURLOPT_FOLLOWLOCATION` kapalıdır ve
 * yönlendirme el ile, sınırlı sayıda izlenir.
 *
 * Boyut sınırı hem `Content-Length` başlığından hem de akış sırasında GERÇEK bayt
 * sayımıyla denetlenir — yalan başlık gönderen sunucu bellek şişiremez.
 */
final class CurlMediaFetcher implements MediaFetcher
{
    private const MAX_REDIRECTS = 3;

    /**
     * K47: alicdn CDN'i Referer ACL uygular — Referer'sız (boş dahil) istekler 403
     * "denied by Referer ACL" alır (canlı kanıtlı). Bu hostlara 1688 ürün sayfası
     * Referer'ı ve gerçekçi tarayıcı UA'sı gönderilir. Eşleme SADECE indirme
     * allowlist'indeki hostlar içindir; başka hiçbir hosta bu başlıklar EKLENMEZ
     * (Referer sızıntısı/kimlik karışması olmaz).
     *
     * @var array<string, array{referer: string, user_agent: string}> sonek → başlıklar
     */
    private const DEFAULT_HOST_HEADERS = [
        'alicdn.com' => [
            'referer' => 'https://detail.1688.com/',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ],
        '1688.com' => [
            'referer' => 'https://detail.1688.com/',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ],
    ];

    /** @param array<string, array{referer: string, user_agent: string}>|null $hostHeaders sonek → başlıklar (null = koddaki varsayılan) */
    public function __construct(
        private readonly UrlGuard $guard,
        private readonly int $timeoutSeconds = 25,
        private readonly ?array $hostHeaders = null,
    ) {
    }

    /**
     * Verilen adresin hostu için gönderilecek Referer/UA çifti — eşleşme yoksa null.
     *
     * UrlGuard ile AYNI sonek mantığı: `cbu01.alicdn.com` `alicdn.com` girdisiyle
     * eşleşir, `alicdn.com.evil.com` eşleşMEZ. Public: testler ağ olmadan doğrular.
     *
     * @return array{referer: string, user_agent: string}|null
     */
    public function headersFor(string $url): ?array
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return null;
        }

        foreach ($this->hostHeaders ?? self::DEFAULT_HOST_HEADERS as $suffix => $headers) {
            $suffix = strtolower(trim((string) $suffix));
            if ($suffix !== '' && ($host === $suffix || str_ends_with($host, '.' . $suffix))) {
                return $headers;
            }
        }

        return null;
    }

    /** @return array{body: string, content_type: string, final_url: string} */
    public function fetch(string $url, int $maxBytes): array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            // D3: HOP BAŞINA TEK ÇÖZÜMLEME. Kapı çözer, sonuç cURL'e pinlenir;
            // her yönlendirme sıçraması AYNI denetimden yeniden geçer.
            $adresler = $this->guard->assertAllowed($current);
            $result = $this->request($current, $maxBytes, $adresler);

            if ($result['redirect'] === null) {
                return [
                    'body' => $result['body'],
                    'content_type' => $result['content_type'],
                    'final_url' => $current,
                ];
            }

            $current = $this->resolveRedirect($current, $result['redirect']);
        }

        throw new MediaException('Çok fazla yönlendirme.');
    }

    /**
     * cURL seçenek dizisi — SAF kurulum, ağ yok (İE#9.7 bekçisi: gerçek PHP 8.1'de
     * CI bu metodu ÇALIŞTIRIR; tanımsız sabit lint'ten kaçsa da burada patlar).
     *
     * K45 taban 8.1 uyumu: `CURLOPT_PROTOCOLS_STR` PHP 8.3'te geldi (canlı vaka:
     * 8.1.34'te "Undefined constant" ile arşive taşıma düştü). Sabit tanımlıysa o
     * kullanılır; değilse eşdeğeri `CURLOPT_PROTOCOLS + CURLPROTO_HTTPS`. Kısıtın
     * etkisi her iki yolda AYNIDIR: yalnız https. Yönlendirmeler için ek kısıt
     * gerekmez — FOLLOWLOCATION kapalıdır, her sıçrama yeni bir istektir ve aynı
     * kısıttan (+UrlGuard'dan) geçer.
     *
     * @return array<int, mixed>
     */
    /**
     * @param  list<string>     $pinliAdresler D3 — `CURLOPT_RESOLVE` ile pinlenir
     * @return array<int, mixed>
     */
    public function requestOptions(string $url, array $pinliAdresler = []): array
    {
        $matched = $this->headersFor($url);

        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false, // yönlendirme el ile denetlenir
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => $matched['user_agent'] ?? 'tedarikapp/1.0',
            CURLOPT_HTTPHEADER => $matched === null ? [] : ['Referer: ' . $matched['referer']],
        ];

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[constant('CURLOPT_PROTOCOLS_STR')] = 'https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        // D3 — DNS PİNİ. Kapının doğruladığı adrese bağlanılır; cURL adı
        // YENİDEN ÇÖZMEZ ve rebinding penceresi kapanır. TLS doğrulaması yine
        // ALAN ADINA yapılır (SNI ve sertifika adı korunur), yani pinleme
        // güvenliği düşürmez — yalnız hangi IP'ye gidileceğini sabitler.
        $pin = $this->guard->pinSecenekleri($url, $pinliAdresler);
        if ($pin !== []) {
            $options[CURLOPT_RESOLVE] = $pin;
        }

        return $options;
    }

    /**
     * @param  list<string> $pinliAdresler D3 — cURL yeniden çözmesin diye
     * @return array{body: string, content_type: string, redirect: string|null}
     */
    private function request(string $url, int $maxBytes, array $pinliAdresler = []): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new MediaException('İndirme başlatılamadı.');
        }

        $body = '';
        curl_setopt_array($handle, $this->requestOptions($url, $pinliAdresler) + [
            CURLOPT_WRITEFUNCTION => static function ($_, string $chunk) use (&$body, $maxBytes): int {
                $body .= $chunk;
                if (strlen($body) > $maxBytes) {
                    return 0; // cURL'ü durdurur → boyut aşımı
                }

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($handle);
        // D3 — SON SAVUNMA: gerçekten bağlanılan adres, onayladığımız listede mi?
        // Pin bir şekilde uygulanmadıysa (eski cURL, araya giren proxy) istek
        // yine de kesilir. Boş değer GÜVENSİZ sayılır: doğrulanamayan bağlantı,
        // doğrulanmamış bağlantıdır.
        if ($pinliAdresler !== []) {
            $baglanilan = (string) curl_getinfo($handle, CURLINFO_PRIMARY_IP);
            if (!$this->guard->baglantiDogru($baglanilan, $pinliAdresler)) {
                curl_close($handle);

                throw new MediaDeniedException('Bağlanılan adres doğrulanamadı, indirme reddedildi.');
            }
        }

        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $redirect = curl_getinfo($handle, CURLINFO_REDIRECT_URL);
        $error = curl_error($handle);
        curl_close($handle);

        if ($ok === false && strlen($body) > $maxBytes) {
            throw new MediaException('Dosya izin verilen boyutu aşıyor.');
        }
        if ($ok === false && $error !== '') {
            throw new MediaException('İndirme başarısız oldu.');
        }
        if ($status >= 300 && $status < 400) {
            if (!is_string($redirect) || $redirect === '') {
                throw new MediaException('Yönlendirme hedefi okunamadı.');
            }

            return ['body' => '', 'content_type' => '', 'redirect' => $redirect];
        }
        if ($status !== 200) {
            throw new MediaException(sprintf('Kaynak sunucu %d döndü.', $status));
        }

        return ['body' => $body, 'content_type' => $contentType, 'redirect' => null];
    }

    /** Göreli yönlendirme hedefini mutlak adrese çevirir. */
    private function resolveRedirect(string $base, string $target): string
    {
        if (preg_match('#^https?://#i', $target) === 1) {
            return $target;
        }

        $parts = parse_url($base);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new MediaException('Yönlendirme çözümlenemedi.');
        }

        $root = $parts['scheme'] . '://' . $parts['host'];

        return str_starts_with($target, '/') ? $root . $target : $root . '/' . ltrim($target, '/');
    }
}
