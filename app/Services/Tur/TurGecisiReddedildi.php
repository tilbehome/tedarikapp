<?php

declare(strict_types=1);

namespace App\Services\Tur;

use RuntimeException;

/**
 * GEÇERSİZ TUR DURUMU GEÇİŞİ (V3-C Blok B · #15).
 *
 * NEDEN İSTİSNA, NEDEN SESSİZ `false` DEĞİL: `gecebilirMi()` bir sorudur ve
 * çağıran cevabı kontrol etmeyi unutabilir. `dogrula()` bir KAPIDIR; kapıdan
 * geçilemediğinde akış durmalıdır. Ürün ve liste durum makinelerinde de aynı
 * ayrım var (K37): sorulan yer ile zorlanan yer birbirinden ayrıdır.
 *
 * İki durumu da taşır: hata mesajını ayrıştırmadan "neydi, neye gitmek istedi"
 * sorusuna cevap verilebilsin — mesaj metni bir gün değişirse çağıranın
 * mantığı bozulmasın.
 */
final class TurGecisiReddedildi extends RuntimeException
{
    public function __construct(
        public readonly string $onceki,
        public readonly string $hedef,
        string $sebep = '',
    ) {
        parent::__construct(sprintf(
            'Teklif turu "%s" durumundan "%s" durumuna geçemez%s',
            $onceki,
            $hedef,
            $sebep === '' ? '.' : ': ' . $sebep,
        ));
    }
}
