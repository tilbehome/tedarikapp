<?php

declare(strict_types=1);

namespace App\Services;

/**
 * SSRF/beyaz liste REDDİ (K47 ayrımı).
 *
 * MediaException'ın iki farklı anlamı vardı: "bu adres GÜVENLİ DEĞİL" (guard reddi)
 * ve "indirme BAŞARISIZ" (403/404/zaman aşımı/bozuk dosya). K47 kırık-görsel
 * dayanıklılığı için ayrım şart: güvenlik reddi ürün kaydını da reddeder,
 * indirme hatası ise kaydı bozmaz (görsel remote kalır, sonra yeniden denenir).
 */
final class MediaDeniedException extends MediaException
{
}
