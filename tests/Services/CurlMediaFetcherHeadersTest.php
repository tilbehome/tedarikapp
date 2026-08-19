<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\CurlMediaFetcher;
use App\Services\UrlGuard;
use PHPUnit\Framework\TestCase;

/**
 * K47 Görev 1 — Referer/UA başlık eşlemesi (ağ YOK; karar mantığı saf sınanır).
 *
 * alicdn Referer ACL uyguladığı için (canlı kanıt: boş Referer bile 403) allowlist
 * hostlarına 1688 Referer'ı + tarayıcı UA gönderilir. KRİTİK ters yön: eşleme dışı
 * hiçbir hosta bu başlıklar EKLENMEZ (Referer sızıntısı) ve sonek taklidi
 * (`alicdn.com.evil.com`) eşleşmez.
 */
final class CurlMediaFetcherHeadersTest extends TestCase
{
    private function fetcher(?array $hostHeaders = null): CurlMediaFetcher
    {
        return new CurlMediaFetcher(new UrlGuard(['alicdn.com', '1688.com']), 25, $hostHeaders);
    }

    public function testAlicdnAltAlanAdinaRefererVeTarayiciUaEklenir(): void
    {
        $headers = $this->fetcher()->headersFor('https://cbu01.alicdn.com/img/ibank/ornek.jpg');

        self::assertNotNull($headers);
        self::assertSame('https://detail.1688.com/', $headers['referer']);
        self::assertStringContainsString('Mozilla/5.0', $headers['user_agent']);
    }

    public function test1688HostunaDaEklenir(): void
    {
        self::assertNotNull($this->fetcher()->headersFor('https://detail.1688.com/pic/ornek.jpg'));
    }

    public function testEslemeDisiHostaBaslikEklenmez(): void
    {
        self::assertNull($this->fetcher()->headersFor('https://ornek-cdn.com/gorsel.jpg'));
    }

    public function testSonekTaklidiEslesmez(): void
    {
        // `alicdn.com.evil.com` gerçek alicdn DEĞİLDİR — UrlGuard ile aynı sonek kuralı.
        self::assertNull($this->fetcher()->headersFor('https://alicdn.com.evil.com/gorsel.jpg'));
    }

    /**
     * İE#9.7 regresyonu: CURLOPT_PROTOCOLS_STR PHP 8.3'te geldi; 8.1.34 canlıda
     * "Undefined constant" ile arşive taşımayı düşürdü. Seçenek kurulumu artık saf
     * metottur ve protokol kısıtı sürüme göre kurulur — etki her yolda: yalnız https.
     */
    public function testProtokolKisitiSurumeGoreKurulur(): void
    {
        $options = $this->fetcher()->requestOptions('https://cbu01.alicdn.com/img/ibank/ornek.jpg');

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            self::assertSame('https', $options[constant('CURLOPT_PROTOCOLS_STR')] ?? null);
            self::assertArrayNotHasKey(CURLOPT_PROTOCOLS, $options);
        } else {
            self::assertSame(CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS] ?? null);
        }
    }

    public function testSecenekKurulumuBasliklariIcerir(): void
    {
        $fetcher = $this->fetcher();

        $withHeaders = $fetcher->requestOptions('https://cbu01.alicdn.com/img/ibank/ornek.jpg');
        self::assertSame(['Referer: https://detail.1688.com/'], $withHeaders[CURLOPT_HTTPHEADER]);
        self::assertStringContainsString('Mozilla/5.0', (string) $withHeaders[CURLOPT_USERAGENT]);

        $without = $fetcher->requestOptions('https://ornek-cdn.com/gorsel.jpg');
        self::assertSame([], $without[CURLOPT_HTTPHEADER]);
        self::assertSame('tedarikapp/1.0', $without[CURLOPT_USERAGENT]);
    }

    public function testYapilandirilabilirEslemeVarsayilaniEzer(): void
    {
        $custom = ['ornek-cdn.com' => ['referer' => 'https://ornek.com/', 'user_agent' => 'OzelUA/1.0']];

        $fetcher = $this->fetcher($custom);

        self::assertSame('https://ornek.com/', $fetcher->headersFor('https://img.ornek-cdn.com/a.jpg')['referer'] ?? null);
        // Özel eşleme verildiğinde koddaki varsayılan alicdn eşlemesi devre dışıdır.
        self::assertNull($fetcher->headersFor('https://cbu01.alicdn.com/img/ibank/ornek.jpg'));
    }
}
