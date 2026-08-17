<?php

declare(strict_types=1);

namespace App\Core;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * QR kodunu terminalde okutulabilir biçimde basar (bacon/bacon-qr-code, K19).
 *
 * Modüller ANSI arka plan rengiyle çizilir: koyu modül siyah, açık modül beyaz zemin.
 * Böylece terminal teması koyu da olsa açık da olsa kod ters dönmez ve telefon okuyabilir.
 */
final class AsciiQrCode
{
    private const DARK = "\033[40m  \033[0m";
    private const LIGHT = "\033[47m  \033[0m";

    /** QR standardının gerektirdiği sessiz kenar (modül cinsinden). */
    private const QUIET_ZONE = 2;

    public static function render(string $text): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ENCODING)->getMatrix();
        $width = $matrix->getWidth();
        $height = $matrix->getHeight();
        $paddedWidth = $width + (self::QUIET_ZONE * 2);

        $lines = [];
        for ($i = 0; $i < self::QUIET_ZONE; $i++) {
            $lines[] = str_repeat(self::LIGHT, $paddedWidth);
        }

        for ($y = 0; $y < $height; $y++) {
            $line = str_repeat(self::LIGHT, self::QUIET_ZONE);
            for ($x = 0; $x < $width; $x++) {
                $line .= $matrix->get($x, $y) === 1 ? self::DARK : self::LIGHT;
            }
            $lines[] = $line . str_repeat(self::LIGHT, self::QUIET_ZONE);
        }

        for ($i = 0; $i < self::QUIET_ZONE; $i++) {
            $lines[] = str_repeat(self::LIGHT, $paddedWidth);
        }

        return implode(PHP_EOL, $lines);
    }
}
