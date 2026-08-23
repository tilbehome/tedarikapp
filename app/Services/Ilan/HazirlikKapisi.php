<?php

declare(strict_types=1);

namespace App\Services\Ilan;

/**
 * "HAZIR" KALİTE KAPISI (İE#20 C8).
 *
 * Bir ürünün firmaya gönderilmeye hazır sayılması için TAMAMLANMASI gereken
 * alanlar burada tanımlıdır. Kural SUNUCUDA yaşar (K14 ilkesinin genellemesi:
 * kural arayüzde değil, sunucuda zorlanır) — panel bunu yalnız GÖSTERİR.
 *
 * NEDEN BU ALANLAR:
 *  • **TR ad** — firma Çince başlığı okuyamaz; belgede anlaşılmayan satır olamaz.
 *  • **Kaynak linki** — "hangi ilan?" sorusunun tek kesin cevabı; sipariş
 *    doğrulaması buna dayanır.
 *  • **Ana görsel** — firma neyi teyit ettiğini görmeli; görselsiz satır tartışma
 *    üretir.
 *  • **Seçili varyant** — "hangi renk/ölçü?" belirsizse yanlış mal gelir.
 *  • **Miktar ve birim fiyat** — sipariş bunlarsız bir niyet beyanıdır.
 *  • **Kategori** — belgede gruplama ve raporlama buna dayanır.
 *
 * Eksik alanların LİSTESİ döner (sayı değil): kullanıcıya "3 alan eksik" demek
 * onu tahmine iter; "kategori, görsel, varyant" demek işini bitirtir.
 */
final class HazirlikKapisi
{
    /** @var array<string, string> alan → kullanıcıya görünen ad */
    public const ALANLAR = [
        'name' => 'Türkçe ürün adı',
        'url' => 'Kaynak linki',
        'main_image' => 'Ana görsel',
        'sku_selection' => 'Seçili varyant',
        'qty' => 'Miktar',
        'price_yuan' => 'Birim fiyat',
        'category_id' => 'Kategori',
    ];

    /**
     * Eksik alanların görünen adları.
     *
     * @param array<string, mixed> $urun ham ürün satırı
     *
     * @return list<string>
     */
    public static function eksikler(array $urun): array
    {
        $eksik = [];

        foreach (self::ALANLAR as $alan => $etiket) {
            if (!self::dolu($urun, $alan)) {
                $eksik[] = $etiket;
            }
        }

        return $eksik;
    }

    /**
     * Eksiklerin ALAN + ETİKET dökümü — panelin uyarı çipleri bunu kullanır.
     *
     * Panele yalnız etiket göndermek yetmezdi: çipe tıklayıp süzmek için kararlı
     * bir kimlik gerekir ve etiket metni bir gün değişebilir. Alan adı kimliktir,
     * etiket görünen yüzdür; ikisi de TEK KAYNAKTAN (bu sınıf) çıkar, böylece
     * panelde ikinci bir "eksik" tanımı yaşamaz.
     *
     * @param array<string, mixed> $urun
     *
     * @return list<array{alan: string, etiket: string}>
     */
    public static function eksikDokumu(array $urun): array
    {
        $dokum = [];

        foreach (self::ALANLAR as $alan => $etiket) {
            if (!self::dolu($urun, $alan)) {
                $dokum[] = ['alan' => $alan, 'etiket' => $etiket];
            }
        }

        return $dokum;
    }

    /** @param array<string, mixed> $urun */
    public static function hazirOlabilirMi(array $urun): bool
    {
        return self::eksikler($urun) === [];
    }

    /** @param array<string, mixed> $urun */
    private static function dolu(array $urun, string $alan): bool
    {
        $deger = $urun[$alan] ?? null;

        if ($alan === 'qty') {
            return is_numeric($deger) && (int) $deger > 0;
        }
        if ($alan === 'price_yuan') {
            // "0" bir fiyat değildir: girilmemiş demektir (İE#17 G3 ile aynı disiplin).
            return is_numeric($deger) && (float) $deger > 0;
        }
        if ($alan === 'category_id') {
            return $deger !== null && (int) $deger > 0;
        }
        if ($alan === 'sku_selection') {
            // JSON alan: boş dizi/nesne seçim YAPILMADIĞI anlamına gelir.
            if (!is_string($deger) || trim($deger) === '') {
                return false;
            }
            $cozulmus = json_decode($deger, true);

            return is_array($cozulmus) && $cozulmus !== [];
        }

        return is_string($deger) && trim($deger) !== '';
    }

    /**
     * Liste tamamlanabilir mi? (C8: BOŞ LİSTE TAMAMLANAMAZ)
     *
     * @param list<array<string, mixed>> $urunler
     *
     * @return array{tamamlanabilir: bool, neden: string|null, hazir_olmayan: int}
     */
    public static function listeTamamlanabilirMi(array $urunler): array
    {
        if ($urunler === []) {
            return [
                'tamamlanabilir' => false,
                // Boş liste "tamamlandı" olamaz: tamamlanan bir şey yok. Bu kaydın
                // kendisi bir hatadır ve raporlarda gerçek bir siparişmiş gibi sayılır.
                'neden' => 'Liste BOŞ — tamamlanacak ürün yok.',
                'hazir_olmayan' => 0,
            ];
        }

        $hazirOlmayan = 0;
        foreach ($urunler as $urun) {
            // İptal edilen ürün hazırlık kapısına girmez: sipariş edilmeyecek.
            if ((string) ($urun['status'] ?? '') === 'cancelled') {
                continue;
            }
            if (!self::hazirOlabilirMi($urun)) {
                $hazirOlmayan++;
            }
        }

        return [
            'tamamlanabilir' => true,
            'neden' => null,
            'hazir_olmayan' => $hazirOlmayan,
        ];
    }
}
