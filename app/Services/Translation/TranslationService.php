<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Core\Clock;
use App\Models\TranslationCacheRepository;
use Psr\Log\LoggerInterface;

/**
 * ZH→TR başlık ÖNERİSİ servisi (İE#13 Blok C — K54).
 *
 * K54 (kural): çeviri DAİMA öneridir. Bu servis hiçbir ürün alanına YAZMAZ; yalnız
 * metin döndürür. Yazma kararı kullanıcınındır ("Kullan" düğmesi) ve orijinal
 * (Çince) başlık her koşulda korunur.
 *
 * Akış (İE#14 A2 · K56): metni normalize et → YEREL SÖZLÜĞE bak (Katman 1) →
 * yoksa önbelleğe bak → yoksa makine çevirisine sor (Katman 3) → başarılıysa
 * önbelleğe yaz. Yanıttaki `source` alanı hangi katmandan geldiğini söyler
 * ('sozluk' | 'makine'); arayüz makine çevirisini etiketleyerek gösterir. HER hata yolu `null` döner ve akışı bloklamaz: kota bitmiş, servis
 * yavaş, ağ kapalı — kullanıcı sadece öneri görmez, işi durmaz.
 */
final class TranslationService
{
    public const MAX_LENGTH = 500;

    public function __construct(
        private readonly TranslationCacheRepository $cache,
        private readonly TranslationClient $client,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = true,
        private readonly string $sourceLang = 'zh',
        private readonly string $targetLang = 'tr',
        // İE#14 A2 (K56 Katman 1): sözlük ÖNCE bakılır — ağa çıkmadan, belirlenimci.
        private readonly ?Glossary $glossary = null,
    ) {
    }

    /** Fazla boşlukları toplar; uzunluk sınırını AŞAN metin önerilmez (kota koruması). */
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @return array{suggestion: string|null, cached: bool, provider: string|null, source: string|null}
     */
    public function suggest(string $text): array
    {
        $bos = ['suggestion' => null, 'cached' => false, 'provider' => null, 'source' => null];
        $normalized = self::normalize($text);
        if ($normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH) {
            return $bos;
        }

        // ── K56 KATMAN 1: yerel sözlük. Kapalı küme terimi ise iş burada biter;
        // ağa çıkılmaz, kota harcanmaz ve sonuç her çağrıda AYNIDIR. Bu katman
        // TRANSLATE_ENABLED=0 olsa bile çalışır: dış istek içermez.
        $sozlukten = $this->glossary?->lookup($normalized);
        if ($sozlukten !== null) {
            return ['suggestion' => $sozlukten, 'cached' => true, 'provider' => 'sozluk', 'source' => 'sozluk'];
        }

        if (!$this->enabled) {
            return $bos;
        }

        $hash = TranslationCacheRepository::hash($normalized, $this->sourceLang, $this->targetLang);

        try {
            $cached = $this->cache->find($hash);
            if ($cached !== null) {
                return [
                    'suggestion' => $cached['suggested_text'],
                    'cached' => true,
                    'provider' => $cached['provider'],
                    'source' => 'makine',
                ];
            }
        } catch (\Throwable $exception) {
            // Önbellek okunamıyorsa (ör. migration bekliyor) sağlayıcıya gitmeyi dene.
            $this->logger->warning('Çeviri önbelleği okunamadı.', ['hata' => $exception->getMessage()]);
        }

        try {
            $suggestion = $this->client->translate($normalized, $this->sourceLang, $this->targetLang);
        } catch (\Throwable $exception) {
            $this->logger->warning('Çeviri sağlayıcısı yanıt vermedi.', ['hata' => $exception->getMessage()]);

            return $bos;
        }

        if ($suggestion === null || $suggestion === '') {
            return $bos;
        }

        try {
            $this->cache->store(
                $hash,
                $normalized,
                $suggestion,
                $this->client->name(),
                $this->sourceLang,
                $this->targetLang,
                $this->clock->now(),
            );
        } catch (\Throwable $exception) {
            // Yazamamak öneriyi geçersiz kılmaz — sadece bir dahaki sefere yine sorulur.
            $this->logger->warning('Çeviri önbelleğine yazılamadı.', ['hata' => $exception->getMessage()]);
        }

        // Katman 3 çıktısı MAKİNE çevirisidir; arayüz bunu etiketleyerek gösterir.
        return ['suggestion' => $suggestion, 'cached' => false, 'provider' => $this->client->name(), 'source' => 'makine'];
    }
}
