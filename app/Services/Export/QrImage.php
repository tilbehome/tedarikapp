<?php

declare(strict_types=1);

namespace App\Services\Export;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Paylaşım QR'ı (İE#13 F6) — bacon/bacon-qr-code (K19 onaylı; TOTP için zaten var).
 *
 * SUNUCU GERÇEĞİ: imagick YOKTUR (docs/SUNUCU-PROFILI) — bacon'un hazır görüntü arka
 * uçları (Imagick/SVG→raster) kullanılamaz. Bu yüzden kütüphanenin ENCODER'ı ile QR
 * MATRİSİ alınır ve modüller GD ile doğrudan çizilir: ne imagick gerekir, ne de
 * SVG ayrıştırma kırılganlığı. Sonuç Excel'e gömülebilir bir PNG kaynağıdır.
 *
 * Adres doğrulaması ÇAĞIRANIN işidir (ExportController): buraya yalnız listenin
 * kendi paylaşım adresi gelir — belgeye yabancı QR bastırılamaz.
 */
final class QrImage
{
    private const BOYUT = 220;
    private const SESSIZ_ALAN = 2; // modül cinsinden kenar boşluğu (QR standardı ≥4, kare küçük olduğu için 2)

    /** @return \GdImage|null üretilemezse null — belge QR'sız yine üretilir */
    public static function olustur(string $url): ?\GdImage
    {
        try {
            $matris = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();
        } catch (\Throwable) {
            return null;
        }

        $modul = $matris->getWidth();
        if ($modul < 1) {
            return null;
        }

        $image = imagecreatetruecolor(self::BOYUT, self::BOYUT);
        if ($image === false) {
            return null;
        }
        $beyaz = (int) imagecolorallocate($image, 255, 255, 255);
        $lacivert = (int) imagecolorallocate($image, 15, 37, 87);
        imagefilledrectangle($image, 0, 0, self::BOYUT, self::BOYUT, $beyaz);

        $toplam = $modul + self::SESSIZ_ALAN * 2;
        $olcek = (int) floor(self::BOYUT / $toplam);
        if ($olcek < 1) {
            imagedestroy($image);

            return null;
        }
        $kayma = (int) floor((self::BOYUT - $olcek * $toplam) / 2) + $olcek * self::SESSIZ_ALAN;

        for ($y = 0; $y < $modul; $y++) {
            for ($x = 0; $x < $modul; $x++) {
                if ($matris->get($x, $y) !== 1) {
                    continue;
                }
                imagefilledrectangle(
                    $image,
                    $kayma + $x * $olcek,
                    $kayma + $y * $olcek,
                    $kayma + ($x + 1) * $olcek - 1,
                    $kayma + ($y + 1) * $olcek - 1,
                    $lacivert,
                );
            }
        }

        return $image;
    }
}
