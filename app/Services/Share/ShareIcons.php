<?php

declare(strict_types=1);

namespace App\Services\Share;

/**
 * Paylaşım sayfası SVG ikonları (İE#13 F4 — şartname: paylasim-v4-premium.html).
 *
 * Neden ayrı dosya: ikonlar satır içi SVG'dir (CSP `default-src 'self'` dış ikon
 * kütüphanesine izin vermez, K51) ve SharePage'i boğmasınlar diye burada durur.
 * Hepsi feather/lucide çizgi setinden, telifsiz geometrilerdir.
 */
final class ShareIcons
{
    private const CIZGI = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round"';

    public static function yazdir(): string
    {
        return '<svg ' . self::CIZGI . '><path d="M6 9V2h12v7"/>'
            . '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>'
            . '<rect x="6" y="14" width="12" height="8"/></svg>';
    }

    public static function indir(): string
    {
        return '<svg ' . self::CIZGI . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>'
            . '<polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    }

    public static function whatsapp(): string
    {
        return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.5 0 1.47 1.07 2.9 1.22 3.1.15.2 2.11 3.22 5.11 4.51.71.31 1.27.49 1.7.63.72.23 1.37.2 1.88.12.58-.09 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35zM12.05 21.8h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 1 1 8.38 4.63zM12.05.4C5.65.4.45 5.6.45 12c0 2.04.53 4.03 1.55 5.79L.36 23.64l6-1.57a11.55 11.55 0 0 0 5.68 1.49h.01c6.4 0 11.6-5.2 11.6-11.6 0-3.1-1.21-6.01-3.4-8.2A11.53 11.53 0 0 0 12.05.4z"/></svg>';
    }

    public static function link(): string
    {
        return '<svg ' . self::CIZGI . '><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>'
            . '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
    }

    public static function disLink(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
            . 'stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>'
            . '<polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
    }

    public static function asagiOk(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" '
            . 'stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
    }

    public static function oynat(): string
    {
        return '<svg viewBox="0 0 24 24"><polygon points="7 4 20 12 7 20"/></svg>';
    }
}
