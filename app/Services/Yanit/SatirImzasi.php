<?php

declare(strict_types=1);

namespace App\Services\Yanit;

/**
 * EXCEL SATIR / MANİFEST İMZASI (spec §6.5-6.6).
 *
 * Şablona yazılan imza GİZLİ SIR DEĞİLDİR; dosyadaki kimlik ve RFQ kaynak
 * alanlarının (miktar, varyant) değiştirilmediğini sunucunun DOĞRULAMASI
 * içindir. Anahtar `APP_KEY`dir ve dosyaya YAZILMAZ; imza kısa tutulur
 * (16 hex) — Excel hücresinde okunur, kopyalanır, kaybolmaz.
 *
 * Aynı satır iki ayrı turda farklı imza taşır (tur kimliği imzaya girer):
 * eski turun dosyası yeni tura sessizce yazılamaz (#28 numune 12).
 */
final class SatirImzasi
{
    public const SEMA_SURUMU = 1;

    public function __construct(private readonly string $appKey)
    {
    }

    public function satir(int $turId, int $snapshotId, string $rfqSatirId, string $talepMiktar, ?string $varyant): string
    {
        return $this->imza(implode('|', ['satir', self::SEMA_SURUMU, $turId, $snapshotId, $rfqSatirId, $talepMiktar, (string) $varyant]));
    }

    public function manifest(int $turId, int $snapshotId, int $satirSayisi, string $disaAktarimAt): string
    {
        return $this->imza(implode('|', ['manifest', self::SEMA_SURUMU, $turId, $snapshotId, $satirSayisi, $disaAktarimAt]));
    }

    public function dogru(string $beklenen, mixed $gelen): bool
    {
        return is_string($gelen) && hash_equals($beklenen, strtolower(trim($gelen)));
    }

    private function imza(string $kapsam): string
    {
        return substr(hash_hmac('sha256', $kapsam, $this->appKey), 0, 16);
    }
}
