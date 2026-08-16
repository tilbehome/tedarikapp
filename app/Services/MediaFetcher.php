<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Dış kaynaktan bayt getiren port.
 *
 * Ayrı arayüz olmasının sebebi: MediaService'in SSRF ve yeniden kodlama kuralları
 * ağa çıkmadan test edilebilsin. Üretimde cURL uygulaması kullanılır (K8 — dış istekler
 * YALNIZCA cURL ile yapılır, `file_get_contents` ile URL açmak yasaktır).
 */
interface MediaFetcher
{
    /**
     * @return array{body: string, content_type: string, final_url: string}
     *
     * @throws MediaException ağ hatası, boyut aşımı veya yönlendirme sınırı
     */
    public function fetch(string $url, int $maxBytes): array;
}
