<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Models\TranslationCacheRepository;

/**
 * EKRANDA GÖRÜNEN ÜRÜN ADI — TEK KAYNAK (D11b saha bulgusu, 25 Ağu 2026).
 *
 * BULGU: 20:15 sınavında ürünlerin TR çevirisi `llm:deepseek` ile
 * "Pedalsız Denge Bisikleti" olmuştu; 20:20'de liste ve çekmece hâlâ eski
 * "Bisiklet Yok…" başlığını basıyordu.
 *
 * KÖK NEDEN: `products.name` YAKALAMA ANINDA donduruluyor (CaptureService,
 * `normalized.name`). Çeviri turu ise yalnız `translation_cache`i tazeliyor
 * (D6). Yani sınav ile ekran FARKLI KAYNAK okuyor — D5'te popup/panel,
 * D9'da sayaç/işçi arasında yaşanan ayrışmanın üçüncü tekrarı.
 *
 * ÇÖZÜM VE SINIRI: `products.name` SESSİZCE EZİLMEZ (K54 — çeviri öneridir,
 * onaylamadan alana yazılmaz). Bunun yerine sunum katmanı, gösterilecek adı
 * şu sırayla çözer:
 *
 *   1. Kullanıcı adı ELLE düzenlediyse → o ad. Son söz insanındır.
 *   2. Çeviri belleğinde `llm:*` ya da `elle` satırı varsa → o metin (öneri
 *      rozetiyle gösterilir).
 *   3. Yoksa → `products.name` (yakalamadan gelen ad).
 *
 * Böylece tazeleme ekrana YANSIR ama hiçbir onaylı metin ezilmez.
 */
final class AdCozumleyici
{
    /** Gösterilen adın nereden geldiği — arayüz rozeti bunu kullanır. */
    public const KAYNAK_ELLE = 'elle';
    public const KAYNAK_CEVIRI = 'ceviri';
    public const KAYNAK_YAKALAMA = 'yakalama';

    public function __construct(
        private readonly TranslationCacheRepository $onbellek,
        private readonly string $hedefDil = 'tr',
    ) {
    }

    /**
     * @param array<string, mixed> $urun `products` satırı
     *
     * @return array{ad: string, kaynak: string, saglayici: string|null}
     */
    public function coz(array $urun, ?string $dil = null): array
    {
        $hedef = $dil ?? $this->hedefDil;

        $ad = trim((string) ($urun['name'] ?? ''));

        // 1) Elle düzenlenmiş ad hiçbir koşulda değişmez.
        if ((int) ($urun['name_elle'] ?? 0) === 1) {
            return ['ad' => $ad, 'kaynak' => self::KAYNAK_ELLE, 'saglayici' => null];
        }

        $orijinal = trim((string) ($urun['name_original'] ?? ''));
        if ($orijinal === '') {
            return ['ad' => $ad, 'kaynak' => self::KAYNAK_YAKALAMA, 'saglayici' => null];
        }

        // 2) Çeviri belleğindeki KALICI satır (llm:* ya da elle). Makine çevirisi
        //    burada KULLANILMAZ: `products.name` zaten büyük olasılıkla odur ve
        //    aynı metni "çeviri önerisi" diye ikinci kez göstermek yanıltıcıdır.
        $satir = $this->onbellek->find(
            TranslationCacheRepository::hash($orijinal, 'zh', $hedef),
        );
        if (
            $satir !== null
            && TranslationCacheRepository::kaliciMi($satir['provider'])
            && trim($satir['suggested_text']) !== ''
        ) {
            return [
                'ad' => trim($satir['suggested_text']),
                'kaynak' => self::KAYNAK_CEVIRI,
                'saglayici' => $satir['provider'],
            ];
        }

        return ['ad' => $ad, 'kaynak' => self::KAYNAK_YAKALAMA, 'saglayici' => null];
    }
}
