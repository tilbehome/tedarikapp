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
 * Akış: metni normalize et → önbelleğe bak → yoksa sağlayıcıya sor → başarılıysa
 * önbelleğe yaz. HER hata yolu `null` döner ve akışı bloklamaz: kota bitmiş, servis
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
    ) {
    }

    /** Fazla boşlukları toplar; uzunluk sınırını AŞAN metin önerilmez (kota koruması). */
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @return array{suggestion: string|null, cached: bool, provider: string|null}
     */
    public function suggest(string $text): array
    {
        $bos = ['suggestion' => null, 'cached' => false, 'provider' => null];
        $normalized = self::normalize($text);
        if (!$this->enabled || $normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH) {
            return $bos;
        }

        $hash = TranslationCacheRepository::hash($normalized, $this->sourceLang, $this->targetLang);

        try {
            $cached = $this->cache->find($hash);
            if ($cached !== null) {
                return ['suggestion' => $cached['suggested_text'], 'cached' => true, 'provider' => $cached['provider']];
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

        return ['suggestion' => $suggestion, 'cached' => false, 'provider' => $this->client->name()];
    }
}
