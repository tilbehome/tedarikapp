<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Kur\KurKaynagi;
use PHPUnit\Framework\TestCase;

/**
 * TCMB KUR KAYNAĞI — ayrıştırma sözleşmesi (İE#21 B5).
 *
 * DIŞ İSTEK YOK: testler ayrıştırıcıyı gerçek bülten BİÇİMİYLE besler. Ağ çağrısı
 * yapan bir test, TCMB bakımdayken CI'yı kırar ve kırılma bizim hatamız olmaz —
 * böyle bir test hata bulmaz, gürültü üretir.
 */
final class KurKaynagiTest extends TestCase
{
    /** TCMB bülteninin gerçek biçimi (kısaltılmış). */
    private const BULTEN = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <Tarih_Date Tarih="22.08.2026" Date="08/22/2026" Bulten_No="2026/162">
          <Currency CrossOrder="0" Kod="USD" CurrencyCode="USD">
            <Unit>1</Unit>
            <Isim>ABD DOLARI</Isim>
            <CurrencyName>US DOLLAR</CurrencyName>
            <ForexBuying>48.0100</ForexBuying>
            <ForexSelling>48.0500</ForexSelling>
            <BanknoteBuying>47.9800</BanknoteBuying>
            <BanknoteSelling>48.1200</BanknoteSelling>
          </Currency>
          <Currency CrossOrder="9" Kod="EUR" CurrencyCode="EUR">
            <Unit>1</Unit>
            <Isim>EURO</Isim>
            <ForexSelling>52.3300</ForexSelling>
          </Currency>
          <Currency CrossOrder="22" Kod="CNY" CurrencyCode="CNY">
            <Unit>1</Unit>
            <Isim>ÇİN YUANI</Isim>
            <CurrencyName>CHINESE RENMINBI</CurrencyName>
            <ForexBuying>7.1300</ForexBuying>
            <ForexSelling>7.1500</ForexSelling>
            <BanknoteBuying />
            <BanknoteSelling />
          </Currency>
        </Tarih_Date>
        XML;

    /**
     * @return array{USD: string, CNY: string, tarih: string}|null
     */
    private function ayristir(string $xml): ?array
    {
        $yontem = new \ReflectionMethod(KurKaynagi::class, 'ayristir');

        /** @var array{USD: string, CNY: string, tarih: string}|null $sonuc */
        $sonuc = $yontem->invoke(new KurKaynagi(), $xml);

        return $sonuc;
    }

    public function testDovizSatisKuruOkunur(): void
    {
        $kurlar = $this->ayristir(self::BULTEN);

        self::assertNotNull($kurlar);
        self::assertSame('48.0500', $kurlar['USD']);
        self::assertSame('7.1500', $kurlar['CNY']);
        self::assertSame('22.08.2026', $kurlar['tarih']);
    }

    public function testEfektifDegilDovizKuruAlinir(): void
    {
        // Ticari faturada döviz satış kuru kullanılır; efektif (nakit) kur DEĞİL.
        $kurlar = $this->ayristir(self::BULTEN);

        self::assertNotNull($kurlar);
        self::assertNotSame('48.1200', $kurlar['USD'], 'Efektif satış kuru alınmamalı');
    }

    public function testBirimBirdenBuyukseBOLUNUR(): void
    {
        // Bazı para birimleri "100 birim" olarak yayımlanır (JPY gibi). Birim
        // dikkate alınmazsa kur 100 kat yanlış olur — ve bu sessizce olur.
        // Yalnız CNY bloğundaki Unit değişir. Heredoc girintisi kırpıldığı için
        // düz metin araması kırılgandır; blok bazlı değiştiriyoruz.
        $xml = (string) preg_replace_callback(
            '#<Currency[^>]*CurrencyCode="CNY".*?</Currency>#s',
            static fn (array $m): string => str_replace('<Unit>1</Unit>', '<Unit>100</Unit>', $m[0]),
            self::BULTEN,
        );

        $kurlar = $this->ayristir($xml);

        self::assertNotNull($kurlar);
        self::assertSame('0.0715', $kurlar['CNY']);
    }

    public function testDovizSatisBossaEfektifeDUSULUR(): void
    {
        $xml = str_replace('<ForexSelling>7.1500</ForexSelling>', '<ForexSelling></ForexSelling>', self::BULTEN);
        $xml = str_replace('<BanknoteSelling />', '<BanknoteSelling>7.2000</BanknoteSelling>', $xml);

        $kurlar = $this->ayristir($xml);

        self::assertNotNull($kurlar);
        self::assertSame('7.2000', $kurlar['CNY']);
    }

    public function testIkiParaBirimindenBIRIEKSIKSENULL(): void
    {
        // Yarım veri kabul edilmez: yalnız USD dönerse panel CNY'yi eski değerle
        // bırakır ve kullanıcı ikisini de güncellediğini sanır.
        $xml = preg_replace('#<Currency[^>]*CurrencyCode="CNY".*?</Currency>#s', '', self::BULTEN) ?? '';

        self::assertNull($this->ayristir($xml));
    }

    public function testBozukXmlNullDoner(): void
    {
        self::assertNull($this->ayristir('<Tarih_Date><Currency>'));
        self::assertNull($this->ayristir(''));
    }

    public function testSayisalOlmayanDegerYokSayilir(): void
    {
        $xml = str_replace('<ForexSelling>48.0500</ForexSelling>', '<ForexSelling>—</ForexSelling>', self::BULTEN);
        $xml = str_replace('<BanknoteSelling>48.1200</BanknoteSelling>', '<BanknoteSelling>yok</BanknoteSelling>', $xml);

        self::assertNull($this->ayristir($xml), 'USD okunamadıysa yarım sonuç dönmemeli');
    }

    public function testAdresListesiBugunuVeGeriyeDONUKGunleriKapsar(): void
    {
        $yontem = new \ReflectionMethod(KurKaynagi::class, 'adresler');
        /** @var list<string> $adresler */
        $adresler = $yontem->invoke(new KurKaynagi(), new \DateTimeImmutable('2026-08-23 10:00:00'));

        self::assertSame('https://www.tcmb.gov.tr/kurlar/today.xml', $adresler[0]);
        // Hafta sonu/tatilde bülten yayımlanmaz; geriye dönük arşiv adresi denenir.
        self::assertContains('https://www.tcmb.gov.tr/kurlar/202608/21082026.xml', $adresler);
        foreach ($adresler as $adres) {
            self::assertStringStartsWith('https://www.tcmb.gov.tr/', $adres, 'Adres SABİT olmalı (SSRF)');
        }
    }
}
