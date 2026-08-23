<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DURUM ETİKETLERİ — TEK KAYNAK (İE#21 B13).
 *
 * SORUN: aynı durumun Türkçesi ÜÇ yerde ayrı ayrı yazılıydı — `TemplateV2`
 * (Excel/PDF), `SharePage` (paylaşım sayfası) ve panelin `locales/tr.ts`'i.
 * Dördüncüsü marka kitindeki `status-map.json` idi ve o BAŞKA bir sözlük
 * kullanıyordu (inbox/review/approved… gibi bu üründe olmayan durumlar).
 * Dört listenin dördü de aynı anda güncellenmediği sürece belge ile ekran
 * birbirini yalanlar; kimse de hangisinin doğru olduğunu bilemez.
 *
 * KURAL: kod adları docs/04 §5B durum makinesinden gelir ve 5B KAZANIR.
 * Etiketler `config/durumlar.json` dosyasındadır; PHP burayı, panel de aynı
 * dosyayı okur. Marka kiti dosyası bu kaynağa eşitlenmiştir.
 *
 * Dosya PAKETLE gelir (config/), yani sunucuda ayrıca kurulum istemez.
 */
final class DurumSozlugu
{
    public const URUN = 'urun';
    public const LISTE = 'liste';

    /** @var array<string, array<string, array<string, string>>>|null */
    private static ?array $onbellek = null;

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Bir durumun etiketi. Bilinmeyen kod OLDUĞU GİBİ döner — ham enum ekranda
     * görünmesi hatadır ama uydurma bir Türkçe basmak daha büyük hatadır: kod
     * göründüğünde eksik eşleme fark edilir, uydurma metin gizlenir.
     */
    public function etiket(string $kume, string $kod, string $dil = 'tr'): string
    {
        $kayit = $this->kayit($kume, $kod);
        if ($kayit === null) {
            return $kod;
        }

        $metin = $kayit[$dil] ?? $kayit['tr'] ?? $kod;

        return $metin !== '' ? $metin : $kod;
    }

    /**
     * Rozet renkleri (ön plan/arka plan) — Excel ve PDF bunları HEX olarak ister.
     *
     * @return array{fg: string, bg: string}
     */
    public function renk(string $kume, string $kod): array
    {
        $kayit = $this->kayit($kume, $kod);

        return [
            'fg' => $kayit['fg'] ?? '#4B5563',
            'bg' => $kayit['bg'] ?? '#F3F4F6',
        ];
    }

    /**
     * Bir kümenin tüm kodları — testler ve panelin sözlüğü bununla denetlenir.
     *
     * @return list<string>
     */
    public function kodlar(string $kume): array
    {
        return array_keys($this->tumu()[$kume] ?? []);
    }

    /** @return array<string, array<string, string>> */
    public function kume(string $kume): array
    {
        return $this->tumu()[$kume] ?? [];
    }

    /** @return array<string, string>|null */
    private function kayit(string $kume, string $kod): ?array
    {
        return $this->tumu()[$kume][$kod] ?? null;
    }

    /** @return array<string, array<string, array<string, string>>> */
    private function tumu(): array
    {
        if (self::$onbellek !== null) {
            return self::$onbellek;
        }

        $yol = $this->basePath . '/config/durumlar.json';
        $ham = is_file($yol) ? (string) file_get_contents($yol) : '';
        /** @var mixed $veri */
        $veri = $ham === '' ? null : json_decode($ham, true);

        $temiz = [];
        if (is_array($veri)) {
            foreach ([self::URUN, self::LISTE] as $kume) {
                if (!is_array($veri[$kume] ?? null)) {
                    continue;
                }
                foreach ($veri[$kume] as $kod => $kayit) {
                    if (!is_string($kod) || !is_array($kayit)) {
                        continue;
                    }
                    $satir = [];
                    foreach ($kayit as $alan => $deger) {
                        if (is_string($alan) && is_string($deger)) {
                            $satir[$alan] = $deger;
                        }
                    }
                    $temiz[$kume][$kod] = $satir;
                }
            }
        }

        return self::$onbellek = $temiz;
    }

    /** Testler için: dosya değiştiğinde önbelleği düşür. */
    public static function onbellegiTemizle(): void
    {
        self::$onbellek = null;
    }
}
