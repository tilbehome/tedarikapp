<?php

declare(strict_types=1);

namespace App\Services\Share;

use DateTimeImmutable;

/**
 * SÜRELİ İMZALI İNDİRME BAĞLANTISI (İE#15 A1 · havuzdaki F42).
 *
 * SORUN: paylaşım sayfasındaki Excel/PDF düğmeleri panel oturumu istiyordu; firma
 * (sayfanın asıl izleyicisi) onları hiç göremiyordu. Oturumsuz indirme gerekiyor,
 * ama uçuk bir "herkese açık export ucu" da olamaz.
 *
 * ÇÖZÜM: bağlantı SAYFA AÇILIRKEN sunucuda imzalanır. İmza APP_KEY ile üretilir;
 * kapsamı (token + biçim + dil + son kullanma) imzanın içindedir — biri biçimi ya
 * da dili elle değiştirirse imza tutmaz. Süre 15 dakikadır: sayfayı açan kişi
 * indirir, bağlantı bir yere kopyalanıp saatler sonra kullanılamaz.
 *
 * K51 SABİT YANIT: imza yanlış, süre dolmuş, token iptal — hepsi AYNI 404.
 * Hangi nedenle reddedildiği DIŞARI SIZMAZ; yoksa bu uç token doğrulama aracına
 * dönerdi.
 *
 * NOT: imzanın "gizli" olması gerekmez, TAHMİN EDİLEMEZ olması gerekir. Zaten
 * token'ı bilen kişi sayfayı da görebilir; imza, ucun token'sız/kapsamsız
 * kullanılmasını ve bağlantının süresiz yaşamasını engeller.
 */
final class ShareDownload
{
    /** Bağlantı ömrü: sayfayı açan kişinin indirmesine yeter, kopyalanıp saklanmaya yetmez. */
    public const OMUR_SANIYE = 900; // 15 dk

    public const BICIMLER = ['xlsx', 'pdf', 'csv'];
    public const DILLER = ['tr', 'zh', 'en'];

    public function __construct(private readonly string $appKey)
    {
    }

    public function yapilandirildi(): bool
    {
        return strlen(trim($this->appKey)) >= 16;
    }

    /** İmza: kapsamın tamamı üzerinden HMAC-SHA256 (base64url, 32 karaktere kırpılmış). */
    public function imza(string $token, string $format, string $dil, int $sonKullanma): string
    {
        $kapsam = implode("\n", ['tdk-share-download-v1', $token, $format, $dil, (string) $sonKullanma]);
        $ham = hash_hmac('sha256', $kapsam, $this->appKey, true);

        return substr(rtrim(strtr(base64_encode($ham), '+/', '-_'), '='), 0, 32);
    }

    /**
     * Sayfaya gömülecek göreli adres — sayfa her açıldığında yeniden üretilir.
     */
    public function adres(string $token, string $format, string $dil, DateTimeImmutable $now): string
    {
        $sonKullanma = $now->getTimestamp() + self::OMUR_SANIYE;

        return sprintf(
            '/p/%s/export?format=%s&lang=%s&exp=%d&sig=%s',
            $token,
            $format,
            $dil,
            $sonKullanma,
            $this->imza($token, $format, $dil, $sonKullanma),
        );
    }

    /**
     * Doğrulama. Tek bir bool döner — çağıran taraf NEDEN bilmez, bilmemelidir:
     * ayrım sabit 404 ilkesini deler (K51).
     */
    public function dogrula(
        string $token,
        string $format,
        string $dil,
        string $sonKullanma,
        string $imza,
        DateTimeImmutable $now,
    ): bool {
        if (!in_array($format, self::BICIMLER, true) || !in_array($dil, self::DILLER, true)) {
            return false;
        }
        if (preg_match('/^\d{10,11}$/', $sonKullanma) !== 1) {
            return false;
        }
        if ((int) $sonKullanma <= $now->getTimestamp()) {
            return false; // süresi dolmuş
        }
        // Aşırı uzun ömürlü imza kabul edilmez: birileri gelecekteki bir tarihi
        // deneyemesin diye üst sınır da denetlenir.
        if ((int) $sonKullanma > $now->getTimestamp() + self::OMUR_SANIYE + 60) {
            return false;
        }

        return hash_equals($this->imza($token, $format, $dil, (int) $sonKullanma), $imza);
    }

    /**
     * Erişim kaydına yazılacak KIRPILMIŞ IP (A1): son sekizli / son 80 bit atılır.
     * Kayıt "kim indirdi"yi değil "kaç yerden indirildi"yi anlamaya yarar.
     */
    public static function kirpilmisIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parcalar = explode('.', $ip);
            $parcalar[3] = '0';

            return implode('.', $parcalar);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $parcalar = explode(':', $ip);

            return implode(':', array_slice($parcalar, 0, 3)) . '::';
        }

        return 'bilinmiyor';
    }
}
