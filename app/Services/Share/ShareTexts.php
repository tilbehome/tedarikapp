<?php

declare(strict_types=1);

namespace App\Services\Share;

/**
 * Paylaşım metinleri — TR / 中文 / EN (İE#15 C2).
 *
 * Metinler KODA GÖMÜLMEZ, buradadır: yeni bir kanal eklemek ya da bir cümleyi
 * düzeltmek tek dosyayı değiştirir. Çince metin Çinli tedarikçiye gider; bu yüzden
 * "DDP teklif ver" çağrısı doğrudan ve kısa tutulmuştur — uzun nezaket cümleleri
 * WeChat'te kırpılır.
 *
 * Yer tutucular: {liste} {adet} {link} {tarih}. Değer verilmezse o satır DÜŞER
 * (örn. son geçerlilik tarihi yoksa "geçerlilik" cümlesi hiç yazılmaz — uydurma yok).
 */
final class ShareTexts
{
    public const DILLER = ['tr', 'zh', 'en'];

    /** @var array<string, array<string, string>> */
    private const METINLER = [
        'tr' => [
            'ozet' => "«{liste}» tedarik listesi · {adet} ürün\nDDP fiyat teklifinizi bu bağlantıdan iletebilirsiniz: {link}",
            'gecerlilik' => 'Teklif geçerlilik tarihi: {tarih}',
            'eposta_konu' => '{liste} — tedarik listesi ({adet} ürün)',
            'baslik' => '{liste} — tedarik listesi',
        ],
        'zh' => [
            'ozet' => "「{liste}」采购清单 · 共{adet}件商品\n请点击链接填写DDP报价：{link}",
            'gecerlilik' => '报价有效期至 {tarih}',
            'eposta_konu' => '{liste} — 采购清单（{adet}件商品）',
            'baslik' => '{liste} — 采购清单',
        ],
        'en' => [
            'ozet' => "«{liste}» supply list · {adet} items\nPlease submit your DDP quotation via this link: {link}",
            'gecerlilik' => 'Quotation valid until {tarih}',
            'eposta_konu' => '{liste} — supply list ({adet} items)',
            'baslik' => '{liste} — supply list',
        ],
    ];

    public static function dil(mixed $istenen): string
    {
        $dil = is_string($istenen) ? strtolower(trim($istenen)) : '';

        return in_array($dil, self::DILLER, true) ? $dil : 'tr';
    }

    /**
     * Tam paylaşım metni: özet + (varsa) geçerlilik satırı.
     *
     * @param array{liste: string, adet: int|string, link: string, tarih?: string|null} $degerler
     */
    public static function mesaj(string $dil, array $degerler): string
    {
        $dil = self::dil($dil);
        $metin = self::degistir(self::METINLER[$dil]['ozet'], $degerler);

        $tarih = $degerler['tarih'] ?? null;
        if (is_string($tarih) && trim($tarih) !== '') {
            $metin .= "\n" . self::degistir(self::METINLER[$dil]['gecerlilik'], $degerler);
        }

        return $metin;
    }

    /** @param array<string, mixed> $degerler */
    public static function metin(string $dil, string $anahtar, array $degerler = []): string
    {
        $dil = self::dil($dil);
        $sablon = self::METINLER[$dil][$anahtar] ?? (self::METINLER['tr'][$anahtar] ?? '');

        return self::degistir($sablon, $degerler);
    }

    /** @param array<string, mixed> $degerler */
    private static function degistir(string $sablon, array $degerler): string
    {
        foreach ($degerler as $anahtar => $deger) {
            if (is_scalar($deger)) {
                $sablon = str_replace('{' . $anahtar . '}', (string) $deger, $sablon);
            }
        }

        return $sablon;
    }

    /** Dil seçicide görünen adlar. */
    public static function dilAdi(string $dil): string
    {
        return match ($dil) {
            'zh' => '中文',
            'en' => 'English',
            default => 'Türkçe',
        };
    }
}
