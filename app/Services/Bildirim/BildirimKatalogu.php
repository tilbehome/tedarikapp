<?php

declare(strict_types=1);

namespace App\Services\Bildirim;

use JsonException;
use RuntimeException;

/**
 * OLAY KATALOĞU OKUYUCUSU (V3-B A2).
 *
 * Katalog `docs/` altındadır ve ŞARTNAMEDİR: başlık, gövde, önem, grup, eylem
 * linki ve birleştirme kuralı orada tanımlıdır. Kod bu metinleri KOPYALAMAZ,
 * okur. Kopyalasaydı iki gerçek kaynak olurdu — bu projede tekrar eden hata
 * tam olarak budur (popup ile panel, sayaç ile işleyici, sınav ile ekran).
 *
 * Dosya okuması bir kez yapılır ve bellekte tutulur: bildirim yayını sıcak
 * yoldadır (her yakalama, her iş bitişi), her seferinde 22 KB JSON ayrıştırmak
 * gereksizdir.
 */
final class BildirimKatalogu
{
    private const GORELI_YOL = '/config/bildirim-olay-katalogu.json';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $olaylar = null;

    public function __construct(private readonly string $kokDizin)
    {
    }

    /**
     * Tek olayın katalog tanımı.
     *
     * @return array<string, mixed>|null bilinmeyen kodda null
     * @throws RuntimeException katalog okunamadı/bozuk (K99 — yutulmaz)
     */
    public function olay(string $olayKodu): ?array
    {
        return $this->tumu()[$olayKodu] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>> olay_kodu => tanım
     * @throws RuntimeException katalog okunamadı/bozuk (K99 — yutulmaz)
     */
    public function tumu(): array
    {
        if ($this->olaylar !== null) {
            return $this->olaylar;
        }

        $yol = $this->kokDizin . self::GORELI_YOL;
        $ham = @file_get_contents($yol);
        if ($ham === false) {
            throw new RuntimeException('Bildirim olay kataloğu okunamadı: ' . self::GORELI_YOL);
        }

        try {
            /** @var array{olaylar: list<array<string, mixed>>} $veri */
            $veri = json_decode($ham, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $hata) {
            throw new RuntimeException('Bildirim olay kataloğu bozuk JSON: ' . $hata->getMessage(), 0, $hata);
        }

        $harita = [];
        foreach ($veri['olaylar'] as $olay) {
            $harita[(string) $olay['olay_kodu']] = $olay;
        }

        return $this->olaylar = $harita;
    }

    /**
     * Şablondaki `{yer_tutucu}` alanlarını değerlerle doldurur.
     *
     * Karşılığı olmayan yer tutucu OLDUĞU GİBİ KALMAZ — okunabilir bir işarete
     * çevrilir. "{urun_adi} panele kabul edildi" cümlesinin kullanıcıya süslü
     * parantezle gösterilmesi, bildirimi çöpe çevirir.
     *
     * @param array<string, scalar|null> $degerler
     */
    public function doldur(string $sablon, array $degerler): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z_0-9]+)\}/',
            static function (array $eslesme) use ($degerler): string {
                $deger = $degerler[$eslesme[1]] ?? null;

                return $deger === null || $deger === '' ? '—' : (string) $deger;
            },
            $sablon,
        );
    }
}
