<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use RuntimeException;

/**
 * İŞ ERTELENDİ — hata DEĞİL, "koşullar uygun değil" (v1.2.2 D6).
 *
 * Bir işleyici bunu attığında kuyruk işi BAŞARISIZ saymaz: deneme hakkı
 * yakılmaz, ölü rafına gidilmez, yeniden deneme bildirimi doğmaz. İş
 * `saniye` sonra yeniden alınabilir olur.
 *
 * NEDEN AYRI BİR TİP: bellek bütçesi dolduğu için yarım bırakılan iş hiçbir
 * şeyi yanlış yapmamıştır. Onu "geçici hata" saymak üç deneme hakkının birini
 * yakar; üç tur üst üste bütçe dolarsa iş ölü rafına düşer — ve operatör,
 * doğru çalışan bir işi "kalıcı olarak başarısız" diye görür.
 *
 * İşleyici bunu atmadan önce KISMİ SONUCUNU KAYDETMİŞ olmalıdır (örneğin
 * inen görsellerin satırları `local`e çevrilmiş). Erteleme, kaldığı yerden
 * devam etme sözü verir; o sözü tutan, kısmi sonucu koruyan işleyicidir.
 */
final class IsErtelendi extends RuntimeException
{
    public function __construct(
        string $sebep,
        /** Yeniden alınabilir olana kadar geçecek süre. */
        public readonly int $saniye = 300,
    ) {
        parent::__construct('Ertelendi: ' . $sebep);
    }
}
