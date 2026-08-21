<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Ürün çevirisi sözleşmesi (İE#14 A2 · K56 KATMAN 2 ARAYÜZÜ).
 *
 * NEDEN ÜRÜNÜN TAMAMI: alan alan çeviri bağlamı kaybeder — "白色" tek başına
 * "Beyaz"dır ama ürün bağlamında "beyaz gövde" olabilir. LLM motoru (V3-A) ürünün
 * TAMAMINI tek çağrıda alır: başlık + kategori + özellik çiftleri + varyasyonlar;
 * JSON girer, JSON çıkar. Bu sürümde motor YOK — arayüz ve kayıt mekanizması var
 * ki V3-A'da sağlayıcı değişince ÇAĞIRAN KOD DEĞİŞMESİN.
 *
 * K54 (değişmez): hiçbir uygulama veriyi kendiliğinden YAZMAZ; yalnız ÖNERİ üretir.
 * Ortak kural: marka, model kodu, ölçü/sayı/birim, ilan no ve "Orijinal başlık"
 * ASLA çevrilmez; TR değer üstte, orijinal altında/parantezde kalır.
 */
interface TranslatorInterface
{
    /**
     * Ürünün tamamını çevirir.
     *
     * Girdi (yalnız dolu alanlar gönderilir):
     *   ['name' => string, 'category' => ?string, 'attributes' => array<string,string>,
     *    'variants' => list<string>, 'source_lang' => 'zh'|'en']
     *
     * Çıktı AYNI anahtarları taşır; çevrilemeyen alan GİRDİDEKİ hâliyle döner ve
     * `meta.sources` o alanın hangi katmandan geldiğini söyler
     * ('sozluk' | 'makine' | 'ham').
     *
     * @param array<string, mixed> $urun
     *
     * @return array<string, mixed>
     */
    public function translateProduct(array $urun): array;

    /**
     * Sözlük — LLM isteğine "bu terimleri şöyle çevir" olarak gömülebilsin diye
     * dışa verilir (K56 Katman 2 gereği).
     *
     * @return array<string, string>
     */
    public function getGlossary(string $sourceLang = 'zh'): array;

    /** Kayıtlarda ve arayüzde görünen sağlayıcı adı (sır içermez). */
    public function name(): string;
}
