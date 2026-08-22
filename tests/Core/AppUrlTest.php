<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\AppUrl;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * İE#19 E5 — dışa verilen adresler AYARDAN üretilir, Host başlığından DEĞİL.
 *
 * SAHTE HOST TESTİ: isteğin Host'u saldırganın alan adı olsa bile üretilen
 * paylaşım/QR adresi kendi alan adımızı taşımalı. Aksi hâlde firmaya giden QR
 * yabancı bir siteye götürür ve bunu biz imzalamış oluruz.
 */
final class AppUrlTest extends TestCase
{
    private function istek(string $url): \Psr\Http\Message\ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', $url, ['REMOTE_ADDR' => '203.0.113.7']);
    }

    public function testSahteHostAdresiEtkilemez(): void
    {
        $adres = AppUrl::to(
            'https://tedarikapp.tilbehometoptan.com',
            $this->istek('https://kotu.site/liste/abc'),
            '/liste/deadbeef',
        );

        self::assertSame('https://tedarikapp.tilbehometoptan.com/liste/deadbeef', $adres);
        self::assertStringNotContainsString('kotu.site', $adres);
    }

    public function testSondakiEgikCizgiTekrarlanmaz(): void
    {
        self::assertSame(
            'https://ornek.test/liste/abc',
            AppUrl::to('https://ornek.test/', $this->istek('https://ornek.test/'), 'liste/abc'),
        );
    }

    public function testAyarYokkenIstegeDUSULUR(): void
    {
        // Yapılandırılmamış sistemde link üretmemek yerine çalışan bir adres vermek
        // daha az zararlıdır; bu bilinçli yedektir.
        self::assertSame(
            'https://kendi.test/liste/abc',
            AppUrl::to(null, $this->istek('https://kendi.test/x'), '/liste/abc'),
        );
    }

    public function testKurulumYerTutucusuKULLANILMAZ(): void
    {
        // Kurulum, zorunlu anahtar denetimini geçirmek için APP_URL'e
        // "https://localhost" yazar. Bu bir adres değil, bir yer tutucudur.
        self::assertSame(
            'https://gercek.test/liste/abc',
            AppUrl::to('https://localhost', $this->istek('https://gercek.test/y'), '/liste/abc'),
        );
    }

    public function testBozukAyarYokSayilir(): void
    {
        self::assertSame(
            'https://gercek.test/qr',
            AppUrl::to('bu-bir-adres-degil', $this->istek('https://gercek.test/z'), '/qr'),
        );
    }
}
