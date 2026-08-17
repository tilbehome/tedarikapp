<?php

declare(strict_types=1);

namespace App\Setup;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use SensitiveParameter;

/**
 * `otpauth://` URI'sini SVG QR koduna çevirir (İE#5 §11e — Bacon SVG).
 *
 * PNG yerine SVG: sunucuda imagick gerekmez (docs/04 §7 — yalnızca GD var), çıktı
 * her ekran çözünürlüğünde net ve boyutu küçüktür.
 *
 * Sonuç `data:` URI olarak döner; sayfanın CSP'si `img-src 'self' data:` olduğundan
 * doğrudan `<img src>` içine konabilir — harici istek veya inline script gerekmez.
 */
final class QrCodeSvg
{
    private const SIZE = 260;
    private const MARGIN = 2;

    public static function dataUri(#[SensitiveParameter] string $text): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(self::SIZE, self::MARGIN),
            new SvgImageBackEnd(),
        ));

        return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($text));
    }
}
