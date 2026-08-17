<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Para hesaplarının TEK merkezi (K29).
 *
 * Kural: birim×adet, kur çevirimi, satır/genel toplam ve yuvarlama YALNIZCA buradan geçer.
 * Controller veya başka bir serviste `bc*` çağrısı YASAKTIR — aynı hesabın iki yerde
 * yazılması kuruş tutarsızlığı üretir ve test yüzeyini ikiye böler (R5).
 *
 * Politika (K24):
 *  • Değerler string taşınır, float ASLA kullanılmaz (K14).
 *  • Ara hesaplar scale ≥ 6; yuvarlama yalnızca satır sonunda, 2 hane HALF_UP.
 *  • Genel toplam = YUVARLANMIŞ satır toplamlarının toplamı ("önce yuvarla, sonra topla").
 */
final class MoneyService
{
    /** Ara hesap hassasiyeti — yuvarlama yalnızca sonda yapılır. */
    public const SCALE = 6;

    /** Para gösterim hassasiyeti. */
    public const MONEY_SCALE = 2;

    /** Kur hassasiyeti (docs/04 §2d). */
    public const RATE_SCALE = 4;

    /** docs/04 §2d: fiyat 0 – 9.999.999,99 */
    private const MAX_AMOUNT = '9999999.99';

    /** docs/04 §2d: kur 0,0001 – 1000 */
    private const MIN_RATE = '0.0001';
    private const MAX_RATE = '1000';

    /** Birim fiyatı hedef para birimine çevirir (ör. Yuan → TL). */
    public function convert(string $amount, string $rate): string
    {
        $this->assertAmount($amount);
        $this->assertRate($rate);

        return $this->round(bcmul($amount, $rate, self::SCALE));
    }

    /** Satır toplamı: birim fiyat × adet (2 hane HALF_UP). */
    public function lineTotal(string $unitPrice, int $qty): string
    {
        $this->assertAmount($unitPrice);
        $this->assertQty($qty);

        return $this->round(bcmul($unitPrice, (string) $qty, self::SCALE));
    }

    /** Satır toplamı TL: birim × adet × kur — tek yuvarlama, sonda (K24). */
    public function lineTotalInTl(string $unitPrice, int $qty, string $rate): string
    {
        $this->assertAmount($unitPrice);
        $this->assertQty($qty);
        $this->assertRate($rate);

        $raw = bcmul(bcmul($unitPrice, (string) $qty, self::SCALE), $rate, self::SCALE);

        return $this->round($raw);
    }

    /**
     * Yuvarlanmış satır toplamlarını toplar.
     *
     * @param list<string> $amounts
     */
    public function sum(array $amounts): string
    {
        $total = '0';
        foreach ($amounts as $amount) {
            $total = bcadd($total, $amount, self::MONEY_SCALE);
        }

        return $this->format($total);
    }

    /**
     * HALF_UP yuvarlama.
     *
     * PHP'nin `round()` fonksiyonu float üzerinden çalışır ve 2,675 gibi değerlerde
     * yanlış sonuç verir; bcmath'in kendisi ise yuvarlamaz, KESER. Bu yüzden yarım
     * birim eklenip kesme yapılır — matematiksel HALF_UP'ın bcmath karşılığı budur.
     */
    public function round(string $amount, int $scale = self::MONEY_SCALE): string
    {
        $negative = str_starts_with($amount, '-');
        $absolute = ltrim($amount, '-');

        $half = '0.' . str_repeat('0', $scale) . '5';
        $rounded = bcadd($absolute, $half, $scale);

        if ($negative && bccomp($rounded, '0', $scale) !== 0) {
            return '-' . $rounded;
        }

        return $rounded;
    }

    /** Para değerini 2 haneye sabitler (gösterim ve API taşıma biçimi). */
    public function format(string $amount): string
    {
        return bcadd($amount, '0', self::MONEY_SCALE);
    }

    /** Kuru 4 haneye sabitler. */
    public function formatRate(string $rate): string
    {
        return bcadd($rate, '0', self::RATE_SCALE);
    }

    /** docs/04 §2d: string decimal, 0 – 9.999.999,99, en çok 2 ondalık, negatif ASLA. */
    public function isValidAmount(string $value): bool
    {
        if (preg_match('/^\d{1,7}(\.\d{1,2})?$/', $value) !== 1) {
            return false;
        }

        return bccomp($value, self::MAX_AMOUNT, self::MONEY_SCALE) <= 0;
    }

    /** docs/04 §2d: string decimal, 0,0001 – 1000, en çok 4 ondalık. */
    public function isValidRate(string $value): bool
    {
        if (preg_match('/^\d{1,4}(\.\d{1,4})?$/', $value) !== 1) {
            return false;
        }

        return bccomp($value, self::MIN_RATE, self::RATE_SCALE) >= 0
            && bccomp($value, self::MAX_RATE, self::RATE_SCALE) <= 0;
    }

    /** docs/04 §2d: adet 1 – 1.000.000 (0 hesaplarda serbest, doğrulama uçta yapılır). */
    public function isValidQty(int $qty): bool
    {
        return $qty >= 1 && $qty <= 1000000;
    }

    private function assertAmount(string $amount): void
    {
        // Hesaba giren değer 4 ondalıklı da olabilir (DB DECIMAL(12,4)); sınır denetimi
        // giriş doğrulamasında yapılır, burada yalnızca "sayı ve negatif değil" aranır.
        if (preg_match('/^\d+(\.\d+)?$/', $amount) !== 1) {
            throw new InvalidArgumentException(sprintf('Geçersiz para değeri: "%s".', $amount));
        }
    }

    private function assertRate(string $rate): void
    {
        if (preg_match('/^\d+(\.\d+)?$/', $rate) !== 1 || bccomp($rate, '0', self::RATE_SCALE) === 0) {
            throw new InvalidArgumentException(sprintf('Geçersiz kur değeri: "%s".', $rate));
        }
    }

    private function assertQty(int $qty): void
    {
        if ($qty < 0) {
            throw new InvalidArgumentException(sprintf('Adet negatif olamaz: %d.', $qty));
        }
    }
}
