<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Katmanlı ürün çevirmeni (İE#14 A2 · K56) — bu sürümün TEK uygulaması.
 *
 * Sıra her alan için aynıdır:
 *   1) YEREL SÖZLÜK (Katman 1) — belirlenimci, ağsız, kotasız. Kapalı kümeler.
 *   2) (Katman 2 — LLM) V3-A'da gelecek; arayüz hazır, burada YOK.
 *   3) MAKİNE ÇEVİRİSİ (Katman 3) — mevcut MyMemory; sonucu "makine" etiketiyle
 *      işaretlenir ki arayüz kullanıcıya güven derecesini gösterebilsin.
 *   4) Hiçbiri veremezse HAM değer korunur (veri kaybolmaz).
 *
 * Başlık gibi serbest metinler sözlükte bulunmaz; onlar doğrudan Katman 3'e gider.
 * Marka/model/ölçü değerleri `Glossary::translatable()` ile elenir ve ham kalır.
 *
 * K54: burada üretilen her şey ÖNERİDİR — hiçbir alan kendiliğinden yazılmaz.
 */
final class LayeredTranslator implements TranslatorInterface
{
    /** Tek çağrıda makine çevirisine gidilecek EN ÇOK alan (kota koruması). */
    private const MAKINE_LIMITI = 12;

    public function __construct(
        private readonly Glossary $glossary,
        private readonly TranslationService $machine,
    ) {
    }

    public function name(): string
    {
        return 'katmanli';
    }

    /** @return array<string, string> */
    public function getGlossary(string $sourceLang = 'zh'): array
    {
        return $this->glossary->all($sourceLang);
    }

    /**
     * @param array<string, mixed> $urun
     *
     * @return array<string, mixed>
     */
    public function translateProduct(array $urun): array
    {
        $dil = is_string($urun['source_lang'] ?? null) && $urun['source_lang'] !== ''
            ? (string) $urun['source_lang']
            : null;

        $kaynaklar = [];
        $makineSayaci = 0;

        $cevir = function (string $deger, string $alan) use ($dil, &$kaynaklar, &$makineSayaci): string {
            $ham = trim($deger);
            if ($ham === '') {
                return $deger;
            }

            $alanDili = $dil ?? Glossary::detect($ham);

            // Katman 1 — sözlük.
            $sozlukten = $this->glossary->lookup($ham, $alanDili);
            if ($sozlukten !== null) {
                $kaynaklar[$alan] = 'sozluk';

                return $sozlukten;
            }

            // Çevrilmemesi gereken değer (marka/model/ölçü) ya da zaten Latin metin.
            if (!$this->glossary->translatable($ham, $alanDili)) {
                $kaynaklar[$alan] = 'ham';

                return $ham;
            }

            // Katman 3 — makine çevirisi (kota koruması: alan sayısı sınırlı).
            if ($makineSayaci >= self::MAKINE_LIMITI) {
                $kaynaklar[$alan] = 'ham';

                return $ham;
            }
            $makineSayaci++;
            $oneri = $this->machine->suggest($ham)['suggestion'] ?? null;
            if (is_string($oneri) && $oneri !== '') {
                $kaynaklar[$alan] = 'makine';

                return $oneri;
            }

            $kaynaklar[$alan] = 'ham';

            return $ham;
        };

        $cikti = $urun;

        if (is_string($urun['name'] ?? null) && $urun['name'] !== '') {
            $cikti['name'] = $cevir((string) $urun['name'], 'name');
        }
        if (is_string($urun['category'] ?? null) && $urun['category'] !== '') {
            $cikti['category'] = $cevir((string) $urun['category'], 'category');
        }

        if (is_array($urun['attributes'] ?? null)) {
            $ozellikler = [];
            foreach ($urun['attributes'] as $anahtar => $deger) {
                if (!is_string($anahtar) || !is_scalar($deger)) {
                    continue;
                }
                // Öznitelik ADI da çevrilir (品牌 → Marka); DEĞER ayrı kuraldan geçer.
                $trAnahtar = $this->glossary->lookup($anahtar, $dil) ?? $anahtar;
                $ozellikler[$trAnahtar] = $cevir((string) $deger, 'attributes.' . $anahtar);
            }
            $cikti['attributes'] = $ozellikler;
        }

        if (is_array($urun['variants'] ?? null)) {
            $varyasyonlar = [];
            foreach ($urun['variants'] as $index => $varyasyon) {
                if (is_string($varyasyon) && $varyasyon !== '') {
                    $varyasyonlar[] = $cevir($varyasyon, 'variants.' . $index);
                }
            }
            $cikti['variants'] = $varyasyonlar;
        }

        $cikti['meta'] = ['provider' => $this->name(), 'sources' => $kaynaklar];

        return $cikti;
    }
}
