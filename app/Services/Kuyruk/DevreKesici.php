<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use App\Core\Clock;
use App\Models\SettingsRepository;
use DateTimeImmutable;
use Throwable;

/**
 * DEVRE KESİCİ — TÜR BAZLI, AYARLAR TABLOSUNDA (v1.2.2 D6).
 *
 * Aynı iş türünde N geçici hata ART ARDA geliyorsa sebep tek tek işlerde
 * değil, ORTAK bir kaynaktadır: kaynak site çökmüş, DNS bozulmuş, çıkış
 * bant genişliği bitmiştir. Bir sonraki işi denemek düzeltmez; yalnız hata
 * sayısını ve sağlayıcıya vurulan darbeyi büyütür, deneme haklarını yakar.
 *
 * KESİCİ AÇIKKEN O TÜRDE YENİ İŞ ALINMAZ. 15 dakika sonra kendiliğinden
 * kapanır — elle müdahale gerekmez; ilk başarı sayacı sıfırlar.
 *
 * TÜR BAZLI, çünkü medya kaynağı çöktü diye çeviri durmamalı: iki türün
 * dış bağımlılıkları ayrıdır, arızaları da ayrıdır.
 *
 * DURUM AYARLAR TABLOSUNDADIR, süreç belleğinde değil: işçi her cron turunda
 * yeniden doğar; süreç ömründeki bir sayaç her turda sıfırlanır ve kesici
 * hiç açılmazdı (A7'deki "süreç ömründe sayaç" dersinin aynısı).
 */
final class DevreKesici
{
    private const KEY_ONEK = 'kuyruk_devre_';

    public function __construct(
        private readonly SettingsRepository $ayarlar,
        private readonly Clock $saat,
        /** Kaç ART ARDA geçici hata kesiciyi açar. */
        private readonly int $esik = 5,
        /** Açık kalma süresi. */
        private readonly int $dakika = 15,
    ) {
    }

    /**
     * Geçici hata kaydeder; kesici BU ÇAĞRIYLA açıldıysa true döner.
     *
     * Dönüş, "yeni açıldı" anını çağırana verir: bildirim ve günlük satırı
     * yalnız o anda üretilmeli, her sonraki hatada değil.
     */
    public function geciciHata(string $tur): bool
    {
        if ($this->acikMi($tur)) {
            return false;
        }

        $sayac = $this->sayac($tur) + 1;
        $this->yaz($tur, 'sayac', (string) $sayac);

        if ($sayac < $this->esik) {
            return false;
        }

        $simdi = $this->saat->now();
        $this->yaz($tur, 'acik_at', $simdi->format(DATE_ATOM));
        $this->yaz($tur, 'sayac', '0');

        return true;
    }

    /** Başarı sayacı sıfırlar: hatalar ART ARDA değilse ortak sebep yoktur. */
    public function basari(string $tur): void
    {
        if ($this->sayac($tur) > 0) {
            $this->yaz($tur, 'sayac', '0');
        }
    }

    public function acikMi(string $tur): bool
    {
        $kapanma = $this->kapanmaAni($tur);

        return $kapanma !== null && $this->saat->now() < $kapanma;
    }

    /**
     * @return array{acik: bool, sayac: int, acilma_at: string|null, kapanma_at: string|null, esik: int, dakika: int}
     */
    public function durum(string $tur): array
    {
        $acik = $this->acikMi($tur);
        $acilma = $this->oku($tur, 'acik_at');
        $kapanma = $this->kapanmaAni($tur);

        return [
            'acik' => $acik,
            'sayac' => $this->sayac($tur),
            'acilma_at' => $acik ? $acilma : null,
            'kapanma_at' => $acik && $kapanma !== null ? $kapanma->format(DATE_ATOM) : null,
            'esik' => $this->esik,
            'dakika' => $this->dakika,
        ];
    }

    /**
     * Şu an AÇIK olan türler — kuyruk sahiplenmede bunlar atlanır.
     *
     * @param  list<string> $turler bilinen iş türleri
     * @return list<string>
     */
    public function acikTurler(array $turler): array
    {
        return array_values(array_filter($turler, fn (string $tur): bool => $this->acikMi($tur)));
    }

    private function kapanmaAni(string $tur): ?DateTimeImmutable
    {
        $acilma = $this->oku($tur, 'acik_at');
        if ($acilma === null || $acilma === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($acilma))->modify('+' . $this->dakika . ' minutes');
        } catch (Throwable) {
            return null;
        }
    }

    private function sayac(string $tur): int
    {
        return (int) ($this->oku($tur, 'sayac') ?? 0);
    }

    private function oku(string $tur, string $alan): ?string
    {
        return $this->ayarlar->get(self::KEY_ONEK . $tur . '_' . $alan);
    }

    private function yaz(string $tur, string $alan, string $deger): void
    {
        $this->ayarlar->set(self::KEY_ONEK . $tur . '_' . $alan, $deger);
    }
}
