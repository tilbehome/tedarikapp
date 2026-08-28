<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * SÜRÜMLÜ ÇEVİRİ BELLEĞİ — önbellek anahtarının bileşenleri (İE#21 B12).
 *
 * SORUN: önbellek anahtarı yalnız "kaynak metin + dil çifti" idi. Bu, ÇEVİRİYİ
 * ÜRETEN HER ŞEYİN sabit olduğunu varsayar — oysa değildir:
 *
 *   • sağlayıcı değişir (DeepSeek → Anthropic),
 *   • model değişir (aynı sağlayıcıda bile çıktı başkalaşır),
 *   • sistem istemi (prompt) düzeltilir — "pazarlama sıfatı ekleme" kuralını
 *     eklediğimiz an eski çeviriler o kurala UYMAYAN metinlerdir,
 *   • sözlük güncellenir — "其他 → Diğer" terimi eklendiğinde eski çeviride hâlâ
 *     "其他" yazıyordur,
 *   • normalizasyon değişir (entity çözme, NBSP temizliği).
 *
 * Bunların hiçbiri kaynak metni değiştirmez; eski anahtar aynı kalır ve sistem
 * ESKİ, ARTIK YANLIŞ çeviriyi sonsuza dek doğru sanar. Üstelik bu sessizdir:
 * kimse "önbellek bayat" demez, yalnız çeviri kalitesi bir yerde donar.
 *
 * ÇÖZÜM: anahtara üretim koşullarının ÖZETİ katılır. Koşullardan biri değişirse
 * anahtar değişir, çeviri yeniden üretilir, eski satır kendiliğinden ölür
 * (silinmez — geçmiş kayıttır, sorgulanabilir kalır).
 *
 * MALİYET BİLİNÇLİDİR: sözlüğe tek terim eklemek bütün önbelleği tazeler. Bu
 * kabul edilmiş bir bedeldir; alternatifi "hangi çeviri hangi sözlükle üretildi"
 * sorusunu hiç cevaplayamamaktır.
 */
final class CeviriSurumu
{
    /**
     * SİSTEM İSTEMİ SÜRÜMÜ — `LlmTranslator::sistemIstemi()` her değiştiğinde
     * ELLE artırılır. Otomatik türetmek (metnin hash'i) cazip ama yanlış olurdu:
     * yorum düzeltmesi bile bütün önbelleği çöpe atardı.
     */
    public const PROMPT_SURUMU = '2';

    /**
     * NORMALİZASYON SÜRÜMÜ — `ValueSet::normalize()` davranışı değişince artırılır
     * (entity çözme, görünmez boşluk temizliği, boşluk sıkıştırma).
     */
    public const NORMALIZASYON_SURUMU = '1';

    public function __construct(
        private readonly string $saglayici,
        private readonly string $model,
        private readonly string $sozlukSurumu,
        private readonly string $promptSurumu = self::PROMPT_SURUMU,
        private readonly string $normalizasyonSurumu = self::NORMALIZASYON_SURUMU,
    ) {
    }

    /** Ayarlardan ve sözlükten kurar — çağıranların tek yolu budur. */
    public static function kur(CeviriAyarlari $ayarlar, Glossary $sozluk): self
    {
        return new self($ayarlar->saglayici(), $ayarlar->model(), $sozluk->surum());
    }

    /**
     * Önbellek anahtarına giren KISA özet.
     *
     * Kısa tutulur (12 hane): anahtarın tamamı zaten sha256'dır, buradaki parça
     * yalnız "hangi koşullar" sorusunu ayırt etmeye yeter. Uzun olması güvenlik
     * kazandırmaz, yalnız satırı şişirir.
     */
    public function anahtar(): string
    {
        return substr(hash('sha256', implode('|', [
            'p' . $this->promptSurumu,
            'n' . $this->normalizasyonSurumu,
            's' . $this->saglayici,
            'm' . $this->model,
            'g' . $this->sozlukSurumu,
        ])), 0, 12);
    }

    /**
     * İnsan okunur döküm — Ayarlar > Çeviri ekranı ve teşhis için.
     *
     * @return array<string, string>
     */
    public function ozet(): array
    {
        return [
            'anahtar' => $this->anahtar(),
            'saglayici' => $this->saglayici,
            'model' => $this->model,
            'sozluk' => $this->sozlukSurumu,
            'prompt' => $this->promptSurumu,
            'normalizasyon' => $this->normalizasyonSurumu,
        ];
    }
}
