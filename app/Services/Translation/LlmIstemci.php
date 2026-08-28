<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Services\UrlGuard;
use RuntimeException;
use SensitiveParameter;

/**
 * ÇOK SAĞLAYICILI LLM İSTEMCİSİ (İE#20 C4) — yalnız cURL (K8).
 *
 * Sağlayıcılar arasındaki fark ADRES, BAŞLIK ve GÖVDE ŞEKLİDİR; istem (prompt) ve
 * beklenen çıktı AYNIDIR. Bu yüzden istemci "sohbet tamamlama" soyutlamasını sunar
 * ve sağlayıcıya özgü kısmı tek bir `switch`te tutar. Yeni sağlayıcı eklemek üç
 * satırdır; çağıran kod değişmez.
 *
 * GÜVENLİK VE SINIRLAR:
 *  • SSRF: her sağlayıcının adresi SABİTTİR ve UrlGuard beyaz listesinden geçer —
 *    kullanıcı "kendi uç noktamı gireyim" diyemez (o, sunucuyu içeri çeviren bir
 *    kapıdır). Yeni sağlayıcı eklemek KOD değişikliğidir, ayar değil.
 *  • Zaman aşımı kısa tutulur: çeviri KUYRUKTA koşar, kullanıcı beklemez.
 *  • Yanıt gövdesi tavanla okunur (devasa gövde bellek şişiremez).
 *  • API anahtarı hiçbir log satırına, hiçbir istisna mesajına GİRMEZ.
 */
final class LlmIstemci implements LlmIstemciInterface
{
    public const MAX_RESPONSE_BYTES = 512 * 1024;

    /** @var array<string, array{adres: string, host: string}> */
    private const UCLAR = [
        LlmTranslator::SAGLAYICI_OPENAI => [
            'adres' => 'https://api.openai.com/v1/chat/completions',
            'host' => 'api.openai.com',
        ],
        LlmTranslator::SAGLAYICI_ANTHROPIC => [
            'adres' => 'https://api.anthropic.com/v1/messages',
            'host' => 'api.anthropic.com',
        ],
        LlmTranslator::SAGLAYICI_DEEPSEEK => [
            'adres' => 'https://api.deepseek.com/chat/completions',
            'host' => 'api.deepseek.com',
        ],
    ];

    public function __construct(private readonly int $zamanAsimi = 45)
    {
    }

    /** Sağlayıcının izinli konağı — SSRF beyaz listesi buradan kurulur. */
    public static function host(string $saglayici): ?string
    {
        return self::UCLAR[$saglayici]['host'] ?? null;
    }

    /**
     * Tek "sohbet" isteği: sistem istemi + kullanıcı istemi → ham metin yanıt.
     *
     * @throws RuntimeException ağ/yetki/biçim hatalarında (mesajda ANAHTAR YOKTUR)
     */
    public function sor(
        string $saglayici,
        #[SensitiveParameter] string $apiAnahtari,
        string $model,
        string $sistemIstemi,
        string $kullaniciIstemi,
    ): string {
        $uc = self::UCLAR[$saglayici] ?? null;
        if ($uc === null) {
            throw new RuntimeException('Tanınmayan çeviri sağlayıcısı: ' . $saglayici);
        }

        // SSRF: adres sabit olsa da denetimden geçirilir (derinlik savunması).
        (new UrlGuard([$uc['host']]))->assertAllowed($uc['adres']);

        [$govde, $basliklar] = $this->istekGovdesi($saglayici, $apiAnahtari, $model, $sistemIstemi, $kullaniciIstemi);

        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('cURL başlatılamadı.');
        }

        $okunan = '';
        curl_setopt_array($ch, [
            CURLOPT_URL => $uc['adres'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $govde,
            CURLOPT_HTTPHEADER => $basliklar,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false, // yönlendirme yok: uç sabittir
            CURLOPT_TIMEOUT => $this->zamanAsimi,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->zamanAsimi),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $parca) use (&$okunan): int {
                $okunan .= $parca;
                if (strlen($okunan) > self::MAX_RESPONSE_BYTES) {
                    return 0; // tavanı aşan gövde: aktarımı kes
                }

                return strlen($parca);
            },
        ]);
        // PHP 8.1 uyumu (İE#9.7 dersi): CURLOPT_PROTOCOLS_STR 8.3+'tır.
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }

        $basarili = curl_exec($ch);
        $durum = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hata = curl_error($ch);
        curl_close($ch);

        if ($basarili === false && $okunan === '') {
            throw new RuntimeException('Çeviri sağlayıcısına ulaşılamadı: ' . ($hata !== '' ? $hata : 'bilinmeyen ağ hatası'));
        }
        if ($durum === 401 || $durum === 403) {
            throw new RuntimeException('Çeviri sağlayıcısı kimliği reddetti (HTTP ' . $durum . '). Ayarlar > Çeviri\'deki anahtarı denetleyin.');
        }
        if ($durum === 429) {
            throw new RuntimeException('Çeviri sağlayıcısı kota/hız sınırı bildirdi (HTTP 429). İş kuyrukta yeniden denenecek.');
        }
        if ($durum < 200 || $durum >= 300) {
            throw new RuntimeException('Çeviri sağlayıcısı HTTP ' . $durum . ' döndürdü.');
        }

        return $this->metniCikar($saglayici, $okunan);
    }

    /**
     * @return array{0: string, 1: list<string>} gövde JSON'u ve başlıklar
     */
    private function istekGovdesi(
        string $saglayici,
        #[SensitiveParameter] string $apiAnahtari,
        string $model,
        string $sistem,
        string $kullanici,
    ): array {
        if ($saglayici === LlmTranslator::SAGLAYICI_ANTHROPIC) {
            $govde = [
                'model' => $model,
                'max_tokens' => 4096,
                'temperature' => 0,
                'system' => $sistem,
                'messages' => [['role' => 'user', 'content' => $kullanici]],
            ];
            $basliklar = [
                'Content-Type: application/json',
                'x-api-key: ' . $apiAnahtari,
                'anthropic-version: 2023-06-01',
            ];
        } else {
            // OpenAI uyumlu gövde (DeepSeek de bu şekli kullanır).
            $govde = [
                'model' => $model,
                'temperature' => 0, // çeviri yaratıcılık istemez: belirlenimci olmalı
                'messages' => [
                    ['role' => 'system', 'content' => $sistem],
                    ['role' => 'user', 'content' => $kullanici],
                ],
                'response_format' => ['type' => 'json_object'],
            ];
            $basliklar = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiAnahtari,
            ];
        }

        return [
            (string) json_encode($govde, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $basliklar,
        ];
    }

    private function metniCikar(string $saglayici, string $yanit): string
    {
        $veri = json_decode($yanit, true);
        if (!is_array($veri)) {
            throw new RuntimeException('Çeviri sağlayıcısının yanıtı okunamadı (geçersiz JSON).');
        }

        if ($saglayici === LlmTranslator::SAGLAYICI_ANTHROPIC) {
            $parca = $veri['content'][0]['text'] ?? null;
        } else {
            $parca = $veri['choices'][0]['message']['content'] ?? null;
        }

        if (!is_string($parca) || trim($parca) === '') {
            throw new RuntimeException('Çeviri sağlayıcısı boş yanıt döndürdü.');
        }

        return $parca;
    }
}
