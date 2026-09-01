<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\MediaDeniedException;
use App\Services\UrlGuard;
use PHPUnit\Framework\TestCase;

/**
 * SERTLEŞTİRME v1.2.1 BLOK D3 — SSRF: ÇÖZÜMLENEN IP PİNLENİR (TDR-013).
 *
 * KORUNAN FELAKET — DNS REBINDING:
 *
 * Kapı alan adını çözüp "herkese açık IP" diye onaylıyor, sonra cURL AYNI adı
 * KENDİ BAŞINA yeniden çözüyordu. İki çözümleme arasında saniyeler var ve
 * saldırganın DNS'i o aralıkta farklı cevap verebilir (TTL=0 ile bu bir
 * saniyeden kısa sürede yapılabilir): birinci cevap `93.184.216.34`, ikinci
 * cevap `169.254.169.254` (bulut metadata) ya da `127.0.0.1`. Denetim geçilir,
 * istek İÇ AĞA gider.
 *
 * ÇÖZÜM ÜÇ PARÇALI:
 *   1. HOP BAŞINA TEK ÇÖZÜMLEME — kapı çözer, sonuç TAŞINIR.
 *   2. `CURLOPT_RESOLVE` ile PİN — cURL yeniden çözmez, bizim doğruladığımız
 *      IP'ye bağlanır. TLS doğrulaması yine ALAN ADINA yapılır (SNI + sertifika
 *      adı korunur), yani pinleme güvenliği düşürmez.
 *   3. `CURLINFO_PRIMARY_IP` ile SONRADAN DOĞRULAMA — bağlanılan adres gerçekten
 *      onayladığımız IP mi? Pin bir şekilde uygulanmadıysa (eski cURL, proxy)
 *      istek yine de kesilir.
 *
 * KARIŞIK A/AAAA: bir ad hem açık hem özel adrese çözülüyorsa TAMAMI reddedilir.
 * "Açık olanı kullan" demek, saldırgana sıralamayı seçtirmek demektir.
 */
final class SsrfPinlemeTest extends TestCase
{
    public function testDOGRUDANIPCOZUMLEMEDENGECER(): void
    {
        $kapi = new UrlGuard(['example.com']);

        self::assertSame(['93.184.216.34'], $kapi->cozumle('93.184.216.34'));
    }

    public function testOZELIPREDDEDILIR(): void
    {
        $kapi = new UrlGuard(['example.com']);

        $this->expectException(MediaDeniedException::class);
        $kapi->guvenliAdresler('127.0.0.1');
    }

    public function testBULUTMETADATAADRESIREDDEDILIR(): void
    {
        // 169.254.169.254 — AWS/GCP/Azure metadata uç noktası. Buraya ulaşan
        // bir istek sunucunun kimlik bilgilerini okuyabilir.
        $kapi = new UrlGuard(['example.com']);

        $this->expectException(MediaDeniedException::class);
        $kapi->guvenliAdresler('169.254.169.254');
    }

    public function testKARISIKACIKVEOZELTAMAMENREDDEDILIR(): void
    {
        // Saldırgan alan adını İKİ kayıtla yayınlar: biri gerçek, biri iç ağ.
        // "Açık olanı kullan" demek, sıralamayı saldırgana seçtirmektir.
        $kapi = new UrlGuard(
            ['ornek.test'],
            static fn (string $host): array => ['93.184.216.34', '10.0.0.5'],
        );

        $this->expectException(MediaDeniedException::class);
        $kapi->guvenliAdresler('ornek.test');
    }

    public function testPINSECENEKLERIURETILIR(): void
    {
        // `CURLOPT_RESOLVE` biçimi: `host:port:ip`. Yanlış biçim sessizce
        // yok sayılır ve pin HİÇ uygulanmaz — bu yüzden biçim sınanır.
        $kapi = new UrlGuard(['example.com']);

        $secenekler = $kapi->pinSecenekleri('https://example.com/a.jpg', ['93.184.216.34']);

        self::assertSame(['example.com:443:93.184.216.34'], $secenekler);
    }

    public function testPINBIRDENCOKADRESITASIR(): void
    {
        // Tek IP'ye pinlemek, o IP geçici olarak düşünce indirmeyi imkânsız
        // kılardı. Hepsi doğrulandığı için hepsi pinlenebilir.
        $kapi = new UrlGuard(['example.com']);

        $secenekler = $kapi->pinSecenekleri('https://example.com/a.jpg', ['93.184.216.34', '93.184.216.35']);

        self::assertSame(['example.com:443:93.184.216.34,93.184.216.35'], $secenekler);
    }

    public function testBAGLANILANIPDOGRULANIR(): void
    {
        $kapi = new UrlGuard(['example.com']);

        self::assertTrue($kapi->baglantiDogru('93.184.216.34', ['93.184.216.34']));
        self::assertFalse(
            $kapi->baglantiDogru('169.254.169.254', ['93.184.216.34']),
            'Pin uygulanmamışsa istek KESİLMELİ — sonradan doğrulama son savunmadır.',
        );
    }

    public function testBOSPRIMARYIPGUVENSIZSAYILIR(): void
    {
        // cURL adresi bildiremiyorsa "herhalde doğrudur" DEMEYİZ: doğrulanamayan
        // bağlantı, doğrulanmamış bağlantıdır.
        $kapi = new UrlGuard(['example.com']);

        self::assertFalse($kapi->baglantiDogru('', ['93.184.216.34']));
    }

    public function testINDIRICIPINIKULLANIR(): void
    {
        // Kaynak taraması: pin üretilip cURL'e VERİLMEZSE bütün mekanizma
        // süstür. Bu bekçi, bağlantının koptuğu günü yakalar.
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/CurlMediaFetcher.php');

        self::assertStringContainsString('CURLOPT_RESOLVE', $kaynak, 'İndirici pin uygulamıyor.');
        self::assertStringContainsString('CURLINFO_PRIMARY_IP', $kaynak, 'Bağlanılan IP doğrulanmıyor.');
    }
}
