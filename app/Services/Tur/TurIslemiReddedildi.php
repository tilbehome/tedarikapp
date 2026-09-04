<?php

declare(strict_types=1);

namespace App\Services\Tur;

use RuntimeException;

/**
 * TUR İŞLEMİ REDDEDİLDİ — durum makinesi DIŞINDAKİ iş kuralları.
 *
 * `TurGecisiReddedildi` yalnız "bu geçiş izinli değil" der. Buradaki
 * sebepler başka: liste kapalı, firma yok, aynı firmaya açık tur var, liste
 * boş, CAS yarışı kaybedildi. Her biri KENDİ koduyla döner ki panel doğru
 * mesajı ve doğru eylemi göstersin ("listeyi kopyala" ile "sayfayı yenile"
 * aynı düğme değildir).
 */
final class TurIslemiReddedildi extends RuntimeException
{
    public function __construct(
        public readonly string $kod,
        string $mesaj,
    ) {
        parent::__construct($mesaj);
    }
}
