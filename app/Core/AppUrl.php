<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface;

/**
 * DIŞA VERİLEN ADRESLERİN TEK KAYNAĞI (İE#19 E5).
 *
 * TEHDİT: paylaşım linki, QR kodu ve kanal (WhatsApp/WeChat) bağlantıları isteğin
 * `Host` başlığından üretiliyordu. `Host` istemcinin yazdığı bir değerdir. Saldırgan
 * `Host: kotu.site` başlığıyla panele bir istek attırabilirse (ör. kullanıcıya
 * tıklattığı bir bağlantı veya araya giren bir vekil), üretilen QR ve link KENDİ
 * alan adını taşır. O QR firmaya gider, firma tarayıcıda açar — biz de o sayfayı
 * kendi elimizle imzalamış oluruz. Belgeye basılan adres, tıklanacak bir adrestir;
 * dolayısıyla istemciden gelen hiçbir veriye dayanamaz.
 *
 * KURAL: taban adres `settings.APP_URL`den gelir (kurulumda yazılır, panelden
 * değiştirilir). İstek yalnızca APP_URL hiç yapılandırılmamışsa (veya kurulumun
 * bıraktığı `https://localhost` yer tutucusu hâlâ duruyorsa) YEDEK olarak kullanılır —
 * o durumda sistem zaten yapılandırılmamıştır ve link üretmek yerine çalışan bir
 * adres vermek daha az zararlıdır.
 */
final class AppUrl
{
    /** Kurulumun "zorunlu anahtar" denetimini geçirmek için koyduğu yer tutucu. */
    private const YER_TUTUCU = 'https://localhost';

    /**
     * @param string|null $configured `settings.APP_URL` (Config::get('APP_URL'))
     *
     * @return string sondaki eğik çizgi olmadan taban adres
     */
    public static function base(?string $configured, ServerRequestInterface $request): string
    {
        $aday = is_string($configured) ? trim($configured) : '';
        $aday = rtrim($aday, '/');

        if ($aday !== '' && $aday !== self::YER_TUTUCU && preg_match('#^https?://[^\s/]+#i', $aday) === 1) {
            return $aday;
        }

        $uri = $request->getUri();

        return rtrim($uri->getScheme() . '://' . $uri->getAuthority(), '/');
    }

    /** Taban + yol (yol başındaki eğik çizgi zorunlu değil). */
    public static function to(?string $configured, ServerRequestInterface $request, string $path): string
    {
        return self::base($configured, $request) . '/' . ltrim($path, '/');
    }
}
