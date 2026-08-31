<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use RuntimeException;

/**
 * KİRA KAYBEDİLDİ — sonuç yazımı sahiplik denetimine takıldı (v1.2.1 A1/A8).
 *
 * Bir işleyici işini bitirir ve sonucu yazmaya çalışır; ama kirası bu arada
 * zaman aşımına uğramış ve iş BAŞKA bir işleyiciye geçmiştir. Yazım tek CAS
 * ifadesiyle reddedilir ve bu istisna atılır.
 *
 * NEDEN İSTİSNA, NEDEN SESSİZ `false` DEĞİL: işleyici sonucu yazamadığını
 * BİLMEK zorundadır. Sessiz dönüşte işleyici kendini başarılı sanar, yan
 * etkilerini (dosya yazımı, dış çağrı) yapmaya devam eder ve iki işleyici
 * aynı işi iki kez tamamlar — üstelik hiçbir yerde iz kalmaz.
 *
 * TİPLİ HİYERARŞİ (A8): sınıflandırma mesaj alt-string'ine bakmaz. Çağıran
 * `catch (KiraKaybedildi)` yazar; `str_contains($e->getMessage(), 'kira')`
 * gibi bir kontrol bir gün çeviri değişince sessizce bozulurdu.
 */
final class KiraKaybedildi extends RuntimeException
{
    public function __construct(
        /** Kirası kaybedilen işin kimliği — log ve ölçüm için. */
        public readonly int $isId,
        /** Yazılmaya çalışılan sonuç ('basarili', 'basarisiz', 'olu'). */
        public readonly string $yazilmakIstenen,
    ) {
        parent::__construct(sprintf(
            'İş #%d kirası kaybedildi; "%s" sonucu yazılamadı (kira devralınmış ya da iş artık çalışmıyor).',
            $isId,
            $yazilmakIstenen,
        ));
    }
}
