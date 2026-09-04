<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\MediaException;
use App\Services\MediaFetcher;

/**
 * Ağa çıkmadan indirme taklidi — MediaService'in SSRF ve yeniden kodlama kuralları
 * gerçek bir istek atmadan sınanabilsin diye.
 */
final class FakeMediaFetcher implements MediaFetcher
{
    /** @var array<string, array{body: string, content_type: string, final_url?: string}> */
    private array $responses = [];

    public ?string $lastUrl = null;

    public int $callCount = 0;

    /**
     * İndirme BAŞLAMADAN önce koşacak kanca (v1.2.1 A5 testleri).
     *
     * Gerçek dünyada indirme saniyeler sürer ve o aralıkta satır değişebilir.
     * Bu kanca o aralığı deterministik olarak taklit eder — `sleep` ile yarış
     * kovalamak yerine, "tam bu anda başkası yazdı" durumunu kesin üretir.
     *
     * @var (callable(string): void)|null
     */
    private $indirmedenOnce = null;

    /** @param callable(string): void $kanca */
    public function indirmedenOnce(callable $kanca): void
    {
        $this->indirmedenOnce = $kanca;
    }

    public function respondWith(string $url, string $body, string $contentType, ?string $finalUrl = null): void
    {
        $this->responses[$url] = [
            'body' => $body,
            'content_type' => $contentType,
            'final_url' => $finalUrl ?? $url,
        ];
    }

    public function fetch(string $url, int $maxBytes): array
    {
        $this->lastUrl = $url;
        $this->callCount++;

        if ($this->indirmedenOnce !== null) {
            ($this->indirmedenOnce)($url);
        }

        if (!isset($this->responses[$url])) {
            throw new MediaException('Sahte indirici bu adres için yanıt tanımlanmamış: ' . $url);
        }

        $response = $this->responses[$url];
        if (strlen($response['body']) > $maxBytes) {
            throw new MediaException('Dosya izin verilen boyutu aşıyor.');
        }

        return [
            'body' => $response['body'],
            'content_type' => $response['content_type'],
            'final_url' => $response['final_url'] ?? $url,
        ];
    }
}
