<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\AppUrl;
use App\Core\AppUrlYokException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * rc8-04 / DIŞ DENETİM F-08 — DIŞA VERİLEN ADRES İSTEMCİYE BIRAKILMAZ.
 *
 * İKİ KUSUR VARDI:
 *  1. Doğrulama yalnız BAŞLANGICI kontrol ediyordu (`^https?://[^\s/]+`);
 *     `https://ornek.com/panel?x=1#y` gibi bir değer geçiyor ve belgeye
 *     olduğu gibi basılıyordu.
 *  2. Değer yoksa `Host` başlığına düşülüyordu — üretimde de. `Host`
 *     istemcinin yazdığı bir alandır; üretilen QR firmaya gider.
 *
 * Artık: tam kanonik doğrulama + üretimde AÇIK HATA. Host yedeği yalnız
 * `APP_ENV` geliştirme değerlerinde serbesttir (ortam TAHMİNİ yok — §
 * `hostYedegiIzinli`).
 */
final class AppUrlKanonikTest extends TestCase
{
    private function istek(string $host = 'kotu.site'): \Psr\Http\Message\ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', 'https://' . $host . '/panel');
    }

    /** @return list<array{0: string}> */
    public static function gecerliAdresler(): array
    {
        return [
            ['https://tedarik.ornek.com'],
            ['https://tedarik.ornek.com/'],
            ['http://192.168.1.50'],
            ['https://ornek.com:8443'],
        ];
    }

    #[DataProvider('gecerliAdresler')]
    public function testGECERLIADRESKABULEDILIR(string $adres): void
    {
        self::assertNotNull(AppUrl::kanonik($adres));
        self::assertStringStartsNotWith('kotu', AppUrl::base($adres, $this->istek()));
    }

    /** @return list<array{0: string, 1: string}> */
    public static function gecersizAdresler(): array
    {
        return [
            ['https://kullanici:parola@ornek.com', 'userinfo LİNKE GİRMEZ'],
            ['https://ornek.com/panel', 'yol taban adreste bulunmaz'],
            ['https://ornek.com?x=1', 'sorgu taban adreste bulunmaz'],
            ['https://ornek.com#parca', 'fragment taban adreste bulunmaz'],
            ['javascript:alert(1)', 'yalnız http/https'],
            ['ftp://ornek.com', 'yalnız http/https'],
            ["https://ornek.com\nHost: kotu.site", 'kontrol karakteri reddedilir'],
            ['https://localhost', 'kurulum yer tutucusu'],
            ['ornek.com', 'şema zorunlu'],
            ['', 'boş değer'],
        ];
    }

    #[DataProvider('gecersizAdresler')]
    public function testGECERSIZADRESREDDEDILIR(string $adres, string $gerekce): void
    {
        self::assertNull(AppUrl::kanonik($adres), $gerekce);
    }

    public function testURETIMDEAPPURLYOKSA_ACIKHATA(): void
    {
        // Sessiz Host yedeği yerine görünür arıza: kullanıcı ayarı girer.
        $this->expectException(AppUrlYokException::class);
        AppUrl::base(null, $this->istek());
    }

    public function testURETIMDEBOZUKAPPURL_DE_ACIKHATA(): void
    {
        $this->expectException(AppUrlYokException::class);
        AppUrl::base('https://ornek.com/panel?x=1', $this->istek());
    }

    public function testGELISTIRMEDE_HOSTYEDEGI_SERBEST(): void
    {
        self::assertTrue(AppUrl::hostYedegiIzinli('local'));
        self::assertTrue(AppUrl::hostYedegiIzinli('development'));
        self::assertFalse(AppUrl::hostYedegiIzinli('production'));
        self::assertFalse(AppUrl::hostYedegiIzinli(null));

        self::assertSame(
            'https://kotu.site',
            AppUrl::base(null, $this->istek(), true),
            'Geliştirmede yedek çalışır — ama yalnız açık izinle.',
        );
    }

    public function testTO_YOLUEKLER_VE_BAYRAGITASIR(): void
    {
        self::assertSame(
            'https://tedarik.ornek.com/liste/abc',
            AppUrl::to('https://tedarik.ornek.com/', $this->istek(), '/liste/abc'),
        );
    }
}
