<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

/**
 * İŞÇİ BAŞINA BELLEK BÜTÇESİ (v1.2.2 D6).
 *
 * PAYLAŞIMLI HOSTİNG GERÇEĞİ: PHP süreci `memory_limit`e çarpınca ÖLÜR —
 * istisna yok, `finally` yok, günlük satırı yok. Kirası dolana kadar iş
 * "çalışıyor" görünür, sonra devralınır ve aynı görselleri yeniden indirir;
 * üçüncü devralmada ölü rafına düşer ve operatör "işleyici sonuç yazmadan
 * düştü" satırından öteye hiçbir şey öğrenemez.
 *
 * BÜTÇE, sınıra çarpmadan DURMAKTIR: işleyici her pahalı adımdan önce sorar,
 * bütçe dolmuşsa kalan işi bir sonraki tura bırakır (`IsErtelendi`).
 *
 * Ölçüm enjekte edilebilir: gerçek `memory_get_usage()` testte kontrol
 * edilemez; sahte ölçüm "ikinci görselden sonra doldu" anını kesin üretir.
 */
final class BellekButcesi
{
    /** @var callable(): int */
    private $olcum;

    /**
     * @param int                  $butceBayt işçinin kendine ayırdığı üst sınır
     * @param (callable(): int)|null $olcum    anlık kullanım (bayt); verilmezse memory_get_usage()
     */
    public function __construct(
        private readonly int $butceBayt,
        ?callable $olcum = null,
    ) {
        $this->olcum = $olcum ?? static fn (): int => memory_get_usage(true);
    }

    public static function megabayttan(int $mb, ?callable $olcum = null): self
    {
        return new self(max(1, $mb) * 1024 * 1024, $olcum);
    }

    /** Bütçe DOLDU mu? Doldu ise pahalı adıma başlanmaz. */
    public function asildi(): bool
    {
        return ($this->olcum)() >= $this->butceBayt;
    }

    public function butceMb(): int
    {
        return intdiv($this->butceBayt, 1024 * 1024);
    }

    public function kullanimMb(): int
    {
        return intdiv(($this->olcum)(), 1024 * 1024);
    }

    /**
     * Sürecin ZİRVE bellek kullanımı (MB) — raporlanır.
     *
     * Bütçenin doğru ayarlanıp ayarlanmadığı ancak ölçülürse bilinir: zirve
     * bütçenin çok altındaysa bütçe gereksiz kısıtlıyor, çok yakınındaysa
     * bir sonraki büyük görsel süreci düşürecek demektir.
     */
    public function zirveMb(): float
    {
        return round(memory_get_peak_usage(true) / (1024 * 1024), 1);
    }
}
