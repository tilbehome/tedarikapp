<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Değer kümesi yardımcısı (İE#14 A3) — varyasyon ve öznitelik DEĞERLERİ.
 *
 * İki iş yapar:
 *  1) DEĞERİ ÇEVİRİR: A2 hattının belirlenimci katmanından geçirir (sözlük → ham).
 *  2) UZUN LİSTEYİ ÖZETLER: ilk 3 + kalanın sayısı.
 *
 * NEDEN YALNIZ SÖZLÜK KATMANI (bilinçli sınır — SAPMA olarak raporlandı):
 * Bu sınıf BELGE/SAYFA ÜRETİM hattında çalışır. Katman 3 (makine çevirisi) ağa
 * çıkar; her varyasyon değeri için dış servise gitmek bir Excel'i dakikalarca
 * bekletir, kotayı tüketir ve K8'in "yalnız cURL, zaman aşımı sınırlı" ilkesiyle
 * render sırasında bağdaşmaz. Dahası aynı belgenin iki üretimi FARKLI metin
 * verebilirdi (K50 snapshot ilkesine aykırı). Bu yüzden hat burada belirlenimci
 * kısımla sınırlıdır: sözlükte varsa Türkçe, yoksa HAM değer korunur.
 * Makine katmanı kullanıcı tetiklediğinde (öneri düğmesi, K54) çalışır.
 */
final class ValueSet
{
    /** Belgede ve arayüzde gösterilecek EN ÇOK değer; kalanı sayı olarak özetlenir. */
    public const LIMIT = 3;

    public function __construct(private readonly ?Glossary $glossary = null)
    {
    }

    /** Tek değer: sözlükte varsa Türkçesi, yoksa ham değer (veri ASLA kaybolmaz). */
    public function value(string $ham): string
    {
        $ham = trim($ham);
        if ($ham === '' || $this->glossary === null) {
            return $ham;
        }

        // "Renk: 灰色" gibi etiketli değerlerde etiket ve değer ayrı çevrilir.
        $ayrac = mb_strpos($ham, ': ');
        if ($ayrac !== false) {
            $etiket = mb_substr($ham, 0, $ayrac);
            $deger = mb_substr($ham, $ayrac + 2);

            return $this->tek($etiket) . ': ' . $this->tek($deger);
        }

        // "灰色 / L" gibi birleşik varyasyon adlarında her parça ayrı çevrilir.
        if (str_contains($ham, ' / ')) {
            $parcalar = array_map(fn (string $p): string => $this->tek($p), explode(' / ', $ham));

            return implode(' / ', $parcalar);
        }

        return $this->tek($ham);
    }

    private function tek(string $ham): string
    {
        $ham = trim($ham);
        if ($ham === '' || $this->glossary === null) {
            return $ham;
        }

        return $this->glossary->lookup($ham) ?? $ham;
    }

    /**
     * @param list<string> $degerler
     *
     * @return list<string>
     */
    public function values(array $degerler): array
    {
        $out = [];
        foreach ($degerler as $deger) {
            $cevrilen = $this->value($deger);
            if ($cevrilen !== '' && !in_array($cevrilen, $out, true)) {
                $out[] = $cevrilen;
            }
        }

        return $out;
    }

    /**
     * Belge özeti: "Gri · Mavi · Siyah … (12 seçenek)". Liste kısaysa sayı EKLENMEZ —
     * "Gri (1 seçenek)" gürültüdür.
     *
     * @param list<string> $degerler
     */
    public function ozet(array $degerler, int $limit = self::LIMIT): ?string
    {
        $degerler = $this->values($degerler);
        if ($degerler === []) {
            return null;
        }

        $ilk = implode(' · ', array_slice($degerler, 0, $limit));

        return count($degerler) > $limit
            ? $ilk . ' … (' . count($degerler) . ' seçenek)'
            : $ilk;
    }
}
