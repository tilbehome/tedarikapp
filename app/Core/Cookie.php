<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;

/**
 * `Set-Cookie` başlığı üretici.
 *
 * Çerezler `setcookie()` yerine PSR-7 yanıtına yazılır: böylece çıktı tamponundan
 * bağımsızdır ve sözleşme testlerinde başlık olarak doğrulanabilir.
 * Bayraklar K16'ya göre sabittir: HttpOnly + SameSite=Lax + (HTTPS'te) Secure.
 */
final class Cookie
{
    public static function write(
        ResponseInterface $response,
        string $name,
        string $value,
        DateTimeImmutable $expiresAt,
        bool $secure,
    ): ResponseInterface {
        $maxAge = max(0, $expiresAt->getTimestamp() - time());

        return $response->withAddedHeader('Set-Cookie', self::build($name, $value, $expiresAt, $maxAge, $secure));
    }

    /** Çerezi geçmişe tarihleyerek siler (iptal/çalıntı token durumunda). */
    public static function clear(ResponseInterface $response, string $name, bool $secure): ResponseInterface
    {
        $past = new DateTimeImmutable('@0', new DateTimeZone('UTC'));

        return $response->withAddedHeader('Set-Cookie', self::build($name, '', $past, 0, $secure));
    }

    private static function build(string $name, string $value, DateTimeImmutable $expiresAt, int $maxAge, bool $secure): string
    {
        $parts = [
            $name . '=' . rawurlencode($value),
            'Path=/',
            'Expires=' . $expiresAt->setTimezone(new DateTimeZone('UTC'))->format('D, d M Y H:i:s \G\M\T'),
            'Max-Age=' . $maxAge,
            'HttpOnly',
            'SameSite=Lax',
        ];
        if ($secure) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }
}
