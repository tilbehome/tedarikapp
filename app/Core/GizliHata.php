<?php

declare(strict_types=1);

namespace App\Core;

use PDOException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * TEKNİK AYRINTIYI SAKLAR, TUTAMAK BIRAKIR (v1.2.1 C3/C4 · K51).
 *
 * İKİ YANLIŞI BİRDEN KAPATIR:
 *
 *  1. HAM İSTİSNA METNİNİ KULLANICIYA DÖKMEK. SQLSTATE metni tablo/kolon
 *     adlarını, kimi sürücülerde dosya yollarını taşır; saldırgana şema
 *     haritası verir. Üstelik bu genelde 200 OK gövdesinde gidiyordu, yani
 *     hiçbir hata izleyicisi de uyarmıyordu.
 *
 *  2. "BİR ŞEYLER TERS GİTTİ" DEYİP HİÇBİR TUTAMAK BIRAKMAMAK. Kullanıcı
 *     destek istediğinde günlükteki satırı bulmanın yolu kalmaz. Kısa bir
 *     HATA KİMLİĞİ hem yanıtta hem günlükte durur; ikisi eşleşir.
 *
 * AYRICA "TABLO YOK" AYRIMI: bir tablonun gerçekten olmaması (kurulum yarım,
 * migration bekliyor) NORMAL bir durumdur ve ekran çökmeden bunu söyleyebilir.
 * Ama bunu HER `Throwable` için varsaymak, gerçek arızayı "bekleyen migration"
 * gibi gösterir ve kimse doğru yere bakmaz. Ayrım SQLSTATE ile yapılır —
 * mesaj metnine bakmakla değil (sürücü ve dil değişince sessizce bozulur).
 */
final class GizliHata
{
    /** "Base table or view not found" — MySQL ve SQLite ortak SQLSTATE'i. */
    private const TABLO_YOK = '42S02';

    /**
     * Bu istisna DOĞRULANMIŞ bir "tablo yok" mu?
     *
     * SQLite bazı sürümlerde `HY000` ile "no such table" der; o yüzden
     * SQLSTATE eşleşmezse sürücü hata kodu da denetlenir. Metin eşleşmesi
     * SON ÇARE olarak yalnız SQLite için ve dar bir kalıpla yapılır —
     * genişletmek, gerçek arızaları yeniden görünmez kılardı.
     */
    public static function tabloYokMu(Throwable $hata): bool
    {
        if (!$hata instanceof PDOException) {
            return false;
        }

        if ($hata->getCode() === self::TABLO_YOK) {
            return true;
        }

        $bilgi = $hata->errorInfo ?? null;
        if (is_array($bilgi) && ($bilgi[0] ?? null) === self::TABLO_YOK) {
            return true;
        }

        // SQLite: SQLSTATE HY000 + sürücü kodu 1 + dar metin kalıbı.
        return is_array($bilgi)
            && ($bilgi[0] ?? null) === 'HY000'
            && (int) ($bilgi[1] ?? 0) === 1
            && str_contains(strtolower((string) ($bilgi[2] ?? '')), 'no such table');
    }

    /**
     * Ayrıntıyı günlüğe yazar, kullanıcıya verilecek KİMLİĞİ döndürür.
     *
     * @param array<string, scalar|null> $baglam
     */
    public static function kaydet(Throwable $hata, LoggerInterface $logger, string $nerede, array $baglam = []): string
    {
        $kimlik = bin2hex(random_bytes(6));

        $logger->error('Gizlenen hata: ' . $nerede, [
            'hata_kimligi' => $kimlik,
            'sinif' => $hata::class,
            'mesaj' => $hata->getMessage(),
            'dosya' => $hata->getFile() . ':' . $hata->getLine(),
        ] + $baglam);

        return $kimlik;
    }
}
