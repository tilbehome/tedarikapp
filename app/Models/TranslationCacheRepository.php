<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * translation_cache erişimi (İE#13 C2).
 *
 * Anahtar `source_hash` = sha256("kaynak|hedef|metin") — aynı başlık ikinci kez dış
 * servise SORULMAZ. Yalnız BAŞARILI çeviriler saklanır: başarısızlık önbelleğe
 * yazılsaydı geçici bir kesinti kalıcı "öneri yok" durumuna dönerdi.
 */
final class TranslationCacheRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Önbellek anahtarı.
     *
     * İE#21 B12: anahtara ÜRETİM KOŞULLARININ sürümü katılır (sağlayıcı, model,
     * prompt, sözlük, normalizasyon — bkz. CeviriSurumu). Sürüm verilmezse eski
     * davranış korunur; bu, sürüm bilmeyen çağıranların (eski kayıtları okuyan
     * raporlar) çalışmaya devam etmesi içindir, YENİ yazımlarda sürüm ZORUNLUDUR.
     */
    public static function hash(
        string $text,
        string $sourceLang,
        string $targetLang,
        string $surum = '',
    ): string {
        $govde = $sourceLang . '|' . $targetLang . '|' . $text;

        return hash('sha256', $surum === '' ? $govde : $surum . '|' . $govde);
    }

    /** @return array{suggested_text: string, provider: string}|null */
    public function find(string $hash): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT suggested_text, provider FROM translation_cache WHERE source_hash = :hash',
        );
        $statement->execute(['hash' => $hash]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        return ['suggested_text' => (string) $row['suggested_text'], 'provider' => (string) $row['provider']];
    }

    /**
     * Kaydı yazar; yarış durumunda (aynı metin iki istekte birden) UNIQUE ihlali
     * hata değildir — mevcut kayıt zaten aynı işi görür, sessizce yutulur.
     * Taşınabilir SQL: MySQL'e özgü "ON DUPLICATE KEY" kullanılmaz.
     */
    public function store(
        string $hash,
        string $sourceText,
        string $suggestedText,
        string $provider,
        string $sourceLang,
        string $targetLang,
        DateTimeImmutable $now,
        string $surum = '',
    ): void {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO translation_cache
                (source_hash, source_lang, target_lang, source_text, suggested_text, provider, surum, created_at)
             VALUES (:hash, :source_lang, :target_lang, :source_text, :suggested_text, :provider, :surum, :created_at)',
        );

        try {
            $statement->execute([
                'hash' => $hash,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'source_text' => mb_substr($sourceText, 0, 1000),
                'suggested_text' => mb_substr($suggestedText, 0, 1000),
                'provider' => $provider,
                // Satırın hangi koşullarda üretildiği KAYITTA da durur: anahtar
                // tek yönlüdür, "bu çeviri hangi modelle yapıldı" sorusunu
                // yanıtlayamaz. Sürüm kolonu bunu sorgulanabilir kılar.
                'surum' => $surum,
                'created_at' => Dates::toStorage($now),
            ]);
        } catch (\PDOException $exception) {
            if ($this->find($hash) === null) {
                throw $exception; // UNIQUE dışı gerçek bir hata — yutulmaz
            }
        }
    }
}
