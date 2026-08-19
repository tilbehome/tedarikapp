<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Translation\MyMemoryTranslator;
use App\Services\UrlGuard;
use PHPUnit\Framework\TestCase;

/**
 * MyMemory yanıt ayrıştırma — AĞA ÇIKMADAN gerçek gövdelerle sınanır.
 */
final class MyMemoryTranslatorTest extends TestCase
{
    private function translator(): MyMemoryTranslator
    {
        return new MyMemoryTranslator(new UrlGuard(['api.mymemory.translated.net']));
    }

    public function testBasariliYanittanCeviriCikar(): void
    {
        $body = '{"responseData":{"translatedText":"Ta&#351;&#305;nabilir s&#305;kaca&#287;&#305;","match":1},"responseStatus":200}';

        self::assertSame('Taşınabilir sıkacağı', $this->translator()->extractSuggestion($body));
    }

    public function testKotaUyarisiCeviriSAYILMAZ(): void
    {
        $body = '{"responseData":{"translatedText":"MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY."},"responseStatus":200}';

        self::assertNull($this->translator()->extractSuggestion($body));
    }

    public function testHataliDurumKoduVeBozukGovdeNullDoner(): void
    {
        self::assertNull($this->translator()->extractSuggestion('{"responseStatus":403,"responseData":{"translatedText":"x"}}'));
        self::assertNull($this->translator()->extractSuggestion('gövde json değil'));
        self::assertNull($this->translator()->extractSuggestion('{"responseData":{"translatedText":"   "},"responseStatus":200}'));
    }

    public function testAdresDogruKurulur_dilCiftiEslenir(): void
    {
        $url = $this->translator()->buildUrl('便携式', 'zh', 'tr');

        self::assertStringStartsWith('https://api.mymemory.translated.net/get?', $url);
        self::assertStringContainsString('langpair=zh-CN%7Ctr-TR', $url);
        self::assertStringContainsString('q=' . rawurlencode('便携式'), $url);
    }

    /** İE#9.7 dersi: PHP 8.1'de tanımsız sabit kullanılmaz — seçenekler ÇALIŞTIRILARAK sınanır. */
    public function testCurlSecenekleriYalnizHttpsVeKisaZamanAsimi(): void
    {
        $options = $this->translator()->requestOptions();

        self::assertSame(5, $options[CURLOPT_TIMEOUT]);
        self::assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        self::assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $protokol = defined('CURLOPT_PROTOCOLS_STR') ? $options[constant('CURLOPT_PROTOCOLS_STR')] : $options[CURLOPT_PROTOCOLS];
        self::assertSame(defined('CURLOPT_PROTOCOLS_STR') ? 'https' : CURLPROTO_HTTPS, $protokol);
    }
}
