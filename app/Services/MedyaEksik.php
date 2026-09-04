<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * MEDYA EKSİK KALDI — iş BİTMEDİ (v1.2.1 A4 · TDR-004).
 *
 * ÖNCESİ: `MediaMigrator::urununMedyasi()` başarısız listesini DÖNDÜRÜYORDU
 * ama kuyruk işleyicisi dönüşü hiç okumuyordu. Beş görselden üçü inip ikisi
 * zaman aşımına uğradığında iş BAŞARILI bitiyordu: ürün "medyası indirildi"
 * sayılıyor, iki görsel sonsuza kadar uzak kalıyor ve kimse eksikliği fark
 * etmiyordu — çünkü ortada hata yoktu.
 *
 * TİPLİ SONUÇ (A8): sınıflandırma mesaja bakmaz.
 *   · `kalici = false` → geçici (ağ, zaman aşımı). Kuyruk geri çekilmeyle
 *     yeniden dener; ikinci tur YALNIZ eksikleri indirir, çünkü başarılı
 *     satırlar artık `local` ve sorguya girmiyor.
 *   · `kalici = true`  → güvenlik reddi / desteklenmeyen tür. Tekrar denemek
 *     düzeltmez; iş ölü rafına gider ve panelde görünür.
 *
 * İNEN GÖRSELLER KORUNUR: istisna atılması yapılmış işi geri almaz. Sayılar
 * (`indirilenSayisi`, `eksikSayisi`) bunu görünür kılar — "hiç inmedi" ile
 * "üçü indi ikisi kaldı" bir daha karışmasın.
 */
final class MedyaEksik extends RuntimeException
{
    /**
     * @param list<array{id: int, url: string, hata: string}> $eksikler
     */
    public function __construct(
        public readonly int $urunId,
        public readonly int $indirilenSayisi,
        public readonly int $eksikSayisi,
        public readonly bool $kalici,
        public readonly array $eksikler = [],
    ) {
        parent::__construct(sprintf(
            'Ürün #%d medyası eksik: %d indi, %d kaldı (%s).',
            $urunId,
            $indirilenSayisi,
            $eksikSayisi,
            $kalici ? 'kalıcı hata' : 'geçici hata',
        ));
    }
}
