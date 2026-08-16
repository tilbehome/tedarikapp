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
    private const int MAX_REDIRECTS = 3;

    public function __construct(
        private readonly UrlGuard $guard,
        private readonly int $timeoutSeconds = 25,
    ) {
    }

    /** @return array{body: string, content_type: string, final_url: string} */
    public function fetch(string $url, int $maxBytes): array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $this->guard->assertAllowed($current);
            $result = $this->request($current, $maxBytes);

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

    /** @return array{body: string, content_type: string, redirect: string|null} */
    private function request(string $url, int $maxBytes): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new MediaException('İndirme başlatılamadı.');
        }

        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false, // yönlendirme el ile denetlenir
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS_STR => 'https',
            CURLOPT_USERAGENT => 'tedarikapp/1.0',
            CURLOPT_WRITEFUNCTION => static function ($_, string $chunk) use (&$body, $maxBytes): int {
                $body .= $chunk;
                if (strlen($body) > $maxBytes) {
                    return 0; // cURL'ü durdurur → boyut aşımı
                }

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($handle);
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
