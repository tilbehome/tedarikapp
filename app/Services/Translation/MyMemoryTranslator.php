<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Services\UrlGuard;

/**
 * MyMemory çeviri ucu (İE#13 C1) — anahtarsız/ücretsiz, ZH→TR öneri üretir.
 *
 * Kurallar:
 *  • Dış istek YALNIZ cURL (K8) — `file_get_contents` üretim profilinde çalışmaz.
 *  • Kendi SSRF beyaz listesi vardır: yalnız `api.mymemory.translated.net`. Medya
 *    allowlist'i (alicdn/1688) GENİŞLETİLMEZ.
 *  • Zaman aşımı kısa (varsayılan 5 sn) — panel/eklenti akışı çeviriyi BEKLEMEZ.
 *  • Kota dolduğunda servis 200 ile "MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE
 *    TRANSLATIONS FOR TODAY" gibi bir METİN döndürür; bu bir çeviri değildir ve
 *    elenmelidir — yoksa uyarı metni ürün adı önerisi diye görünür.
 *  • Yanıt gövdesi tavanla okunur; devasa gövde bellek şişiremez.
 */
final class MyMemoryTranslator implements TranslationClient
{
    private const ENDPOINT = 'https://api.mymemory.translated.net/get';
    private const MAX_RESPONSE_BYTES = 64 * 1024;
    private const MAX_TEXT_LENGTH = 500;

    /** Sağlayıcının çeviri yerine geri yolladığı uyarı/hata kalıpları. */
    private const REJECT_PATTERNS = [
        'MYMEMORY WARNING',
        'YOU USED ALL AVAILABLE FREE TRANSLATIONS',
        'PLEASE SELECT TWO DISTINCT LANGUAGES',
        'INVALID LANGUAGE PAIR',
        'QUERY LENGTH LIMIT EXCEEDED',
        'AUTO DETECT FAILED',
    ];

    /** @var array<string, string> ISO kısaltması → sağlayıcının beklediği kod */
    private const LANG_MAP = ['zh' => 'zh-CN', 'tr' => 'tr-TR', 'en' => 'en-GB'];

    public function __construct(
        private readonly UrlGuard $guard,
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function name(): string
    {
        return 'mymemory';
    }

    /**
     * cURL seçenekleri — SAF kurulum, ağ yok (İE#9.7 bekçisiyle aynı desen:
     * PHP 8.1'de tanımsız sabit kullanılmaz, CI bu metodu gerçek 8.1'de çalıştırır).
     *
     * @return array<int, mixed>
     */
    public function requestOptions(): array
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'tedarikapp/1.0',
            CURLOPT_BUFFERSIZE => 8192,
        ];

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[constant('CURLOPT_PROTOCOLS_STR')] = 'https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        return $options;
    }

    /** İstek adresi — test edilebilir olsun diye ayrı (sır içermez, anahtarsız uç). */
    public function buildUrl(string $text, string $sourceLang, string $targetLang): string
    {
        $pair = (self::LANG_MAP[$sourceLang] ?? $sourceLang) . '|' . (self::LANG_MAP[$targetLang] ?? $targetLang);

        return self::ENDPOINT . '?' . http_build_query(['q' => $text, 'langpair' => $pair], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Sağlayıcı yanıtından öneriyi çıkarır: geçersiz/uyarı metinleri null döner.
     * Ağdan bağımsızdır — testler gerçek yanıt gövdeleriyle bu metodu doğrular.
     */
    public function extractSuggestion(string $body): ?string
    {
        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }

        $status = $decoded['responseStatus'] ?? null;
        if ($status !== null && (int) $status !== 200) {
            return null;
        }

        $data = $decoded['responseData'] ?? null;
        $text = is_array($data) ? ($data['translatedText'] ?? null) : null;
        if (!is_string($text)) {
            return null;
        }

        $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' || mb_strlen($text) > self::MAX_TEXT_LENGTH * 2) {
            return null;
        }

        $upper = mb_strtoupper($text, 'UTF-8');
        foreach (self::REJECT_PATTERNS as $pattern) {
            if (str_contains($upper, $pattern)) {
                return null;
            }
        }

        return $text;
    }

    public function translate(string $text, string $sourceLang, string $targetLang): ?string
    {
        if ($text === '' || mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return null;
        }

        $url = $this->buildUrl($text, $sourceLang, $targetLang);

        try {
            $this->guard->assertAllowed($url);
        } catch (\Throwable) {
            return null; // beyaz liste dışı/çözümlenemeyen adres — öneri yok
        }

        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, $this->requestOptions());
        /** @var string|false $body */
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if (!is_string($body) || $status !== 200 || strlen($body) > self::MAX_RESPONSE_BYTES) {
            return null;
        }

        return $this->extractSuggestion($body);
    }
}
