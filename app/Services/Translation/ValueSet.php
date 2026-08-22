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
    /** Detay panelinde katlanmadan gösterilecek varyasyon sayısı. */
    public const LIMIT = 3;

    /** Tek varyasyonlu üründe satır hücresine yazılabilecek en uzun değer (İE#17 G8-b). */
    private const TEK_DEGER_SINIRI = 40;

    /**
     * HAM METİN NORMALİZASYONU (İE#17 G9) — sunum hattının TEK giriş noktası.
     *
     * Canlı belirti: varyasyonlarda "英文版&gt;1" görünüyordu. Kaynak sayfadaki
     * değer ZATEN entity içeriyor ("&gt;"); sunum bir kez daha kaçırınca entity
     * ekrana düşüyor. Çözüm: değer sunuma girmeden ÖNCE bir kez çözülür,
     * çıktıda normal `htmlspecialchars` kaçışı AYNEN uygulanır.
     *
     * GÜVENLİK: bu bir kaçış DEĞİLDİR, kaçışın YERİNE DE GEÇMEZ. "&lt;script&gt;"
     * çözülüp "<script>" olur, çıkışta yeniden kaçar ve zararsız kalır
     * (CLAUDE.md §5 — XSS regresyon testi bunu sabitler).
     *
     * Görünmez boşluklar da temizlenir: 1688 değerleri sık sık NBSP taşır.
     */
    public static function normalize(string $ham): string
    {
        $cozulmus = html_entity_decode($ham, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cozulmus = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], [' ', '', ''], $cozulmus);

        return trim(preg_replace('/\s+/u', ' ', $cozulmus) ?? $cozulmus);
    }

    public function __construct(private readonly ?Glossary $glossary = null)
    {
    }

    /** Tek değer: sözlükte varsa Türkçesi, yoksa ham değer (veri ASLA kaybolmaz). */
    public function value(string $ham): string
    {
        // İE#17 G9: entity artıkları burada bir kez çözülür (tek merkez).
        $ham = self::normalize($ham);
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
        $ham = self::normalize($ham);
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
     * SATIR HÜCRESİ ÖZETİ (İE#17 G8-b) — kompakt rozet.
     *
     * Eski davranış "ilk 3 + … (N seçenek)" idi; 40 varyantlı üründe bu bile
     * satırda gereksiz yer kaplıyordu. Yeni kural: hücrede YALNIZ "N seçenek"
     * yazar. TEK varyasyon varsa ve kısaysa (≤ 40 karakter) değerin kendisi
     * basılır — "1 seçenek" demek, "Gri" demekten daha az bilgi verirdi.
     *
     * Tam liste detay panelindeki VARYASYONLAR bölümündedir (+N katlamasıyla).
     *
     * @param list<string> $degerler
     */
    public function ozet(array $degerler): ?string
    {
        $degerler = $this->values($degerler);
        if ($degerler === []) {
            return null;
        }
        if (count($degerler) === 1 && mb_strlen($degerler[0]) <= self::TEK_DEGER_SINIRI) {
            return $degerler[0];
        }

        return count($degerler) . ' seçenek';
    }
}
