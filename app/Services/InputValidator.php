<?php

declare(strict_types=1);

namespace App\Services;

/**
 * docs/04 §2d doğrulama kuralları — sistem SINIRINDA zorlanır.
 *
 * Arayüz aynı kuralları önden gösterebilir ama gerçek denetim burasıdır: eklenti,
 * betik veya elle atılan bir istek de aynı kapıdan geçer.
 *
 * Her metot hata METNİ döndürür (alan bazlı `error.fields` için) veya null.
 */
final class InputValidator
{
    public function __construct(private readonly MoneyService $money)
    {
    }

    public function listName(mixed $value): ?string
    {
        return $this->text($value, 1, 200, 'Liste adı');
    }

    public function period(mixed $value): ?string
    {
        return $this->optionalText($value, 50, 'Dönem');
    }

    public function supplierName(mixed $value): ?string
    {
        return $this->optionalText($value, 200, 'Tedarikçi adı');
    }

    public function productName(mixed $value): ?string
    {
        return $this->text($value, 1, 300, 'Ürün adı');
    }

    public function nameOriginal(mixed $value): ?string
    {
        return $this->optionalText($value, 500, 'Orijinal başlık');
    }

    /** Detay ve not: ≤ 2000 karakter. */
    public function longText(mixed $value, string $label): ?string
    {
        return $this->optionalText($value, 2000, $label);
    }

    public function trackingNo(mixed $value): ?string
    {
        return $this->optionalText($value, 100, 'Takip kodu');
    }

    /** docs/04 §2d: tam sayı, 1 – 1.000.000. */
    public function qty(mixed $value): ?string
    {
        if (is_int($value)) {
            $qty = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $qty = (int) $value;
        } else {
            return 'Miktar tam sayı olmalı.';
        }

        return $this->money->isValidQty($qty) ? null : 'Miktar 1 ile 1.000.000 arasında olmalı.';
    }

    /** Koli içi adet: opsiyonel, 1 – 1.000.000 (1688'de alan yok, admin girer). */
    public function unitsPerCarton(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->qty($value) === null ? null : 'Koli içi adet 1 ile 1.000.000 arasında bir tam sayı olmalı.';
    }

    /** docs/04 §2d: string decimal, 0 – 9.999.999,99, en çok 2 ondalık, negatif ASLA. */
    public function price(mixed $value, string $label): ?string
    {
        $text = $this->toDecimalString($value);
        if ($text === null) {
            return $label . ' sayı olarak gönderilmeli (JSON string, örn. "9.00").';
        }

        return $this->money->isValidAmount($text)
            ? null
            : $label . ' 0 ile 9.999.999,99 arasında, en çok 2 ondalıklı ve negatif olmayan bir değer olmalı.';
    }

    /** docs/04 §2d: string decimal, 0,0001 – 1000, en çok 4 ondalık. */
    public function rate(mixed $value, string $label): ?string
    {
        $text = $this->toDecimalString($value);
        if ($text === null) {
            return $label . ' sayı olarak gönderilmeli (JSON string, örn. "7.0400").';
        }

        return $this->money->isValidRate($text)
            ? null
            : $label . ' 0,0001 ile 1000 arasında, en çok 4 ondalıklı olmalı.';
    }

    /** docs/04 §2d: https zorunlu, ≤ 1000 karakter. */
    public function url(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            return $label . ' metin olmalı.';
        }
        if (strlen($value) > 1000) {
            return $label . ' en çok 1000 karakter olabilir.';
        }
        if (!str_starts_with(strtolower($value), 'https://')) {
            return $label . ' https:// ile başlamalı (güvenli olmayan bağlantı kabul edilmez).';
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $label . ' geçerli bir adres değil.';
        }

        return null;
    }

    /** @param list<string> $allowed */
    public function enum(mixed $value, array $allowed, string $label): ?string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            return sprintf('%s şunlardan biri olmalı: %s.', $label, implode(', ', $allowed));
        }

        return null;
    }

    /** JSON alanları (sku_selection, sku_matrix) dizi/nesne olarak taşınır. */
    public function jsonField(mixed $value, string $label): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        return $label . ' JSON nesnesi veya dizisi olmalı.';
    }

    /** Sayısal değeri karşılaştırılabilir string'e çevirir; float ASLA kabul edilmez (K14). */
    public function toDecimalString(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return trim($value);
        }

        // float geldiyse reddedilir: JSON float hassasiyet kaybı taşır (docs/10 §1).
        return null;
    }

    private function text(mixed $value, int $min, int $max, string $label): ?string
    {
        if (!is_string($value)) {
            return $label . ' metin olmalı.';
        }
        $length = mb_strlen(trim($value));
        if ($length < $min) {
            return $label . ' zorunludur.';
        }
        if ($length > $max) {
            return sprintf('%s en çok %d karakter olabilir.', $label, $max);
        }

        return null;
    }

    private function optionalText(mixed $value, int $max, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            return $label . ' metin olmalı.';
        }
        if (mb_strlen($value) > $max) {
            return sprintf('%s en çok %d karakter olabilir.', $label, $max);
        }

        return null;
    }
}
