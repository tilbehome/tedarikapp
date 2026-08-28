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

    /**
     * ONAYLI ELLE DÜZELTME sağlayıcısı (K54).
     *
     * Kullanıcının onayladığı çeviri, hiçbir otomatik tur tarafından EZİLMEZ —
     * ne makine ne LLM. Değer sabit olarak burada durur ki hem yazan hem
     * koruyan taraf aynı adı kullansın.
     */
    public const ELLE_SAGLAYICI = 'elle';

    /** Satır LLM'den mi geldi? (`llm:deepseek`, `llm:openai`, ...) */
    public static function llmMi(string $provider): bool
    {
        return str_starts_with($provider, 'llm:');
    }

    /** Satır KALICI mı — yani üzerine otomatik yazılmamalı mı? */
    public static function kaliciMi(string $provider): bool
    {
        return self::llmMi($provider) || $provider === self::ELLE_SAGLAYICI;
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
     * Bu metin, bu dil için KALICI olarak çevrilmiş mi? (D12)
     *
     * Kalıcı = `llm:*` üretimi ya da `elle` onayı (K54). Makine çevirisi
     * (`mymemory` vb.) GEÇİCİ DOLDURMADIR ve bir dili tamamlanmış saymaz;
     * aksi hâlde yıllarca "TR dolu" görünüp aslında bozuk kalan satırlar
     * kimsenin dikkatini çekmez (D6 saha bulgusu).
     */
    public function kaliciVarMi(string $sourceText, string $targetLang): bool
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT COUNT(*) FROM translation_cache
             WHERE source_text = :metin AND target_lang = :dil
               AND (provider LIKE 'llm:%' OR provider = :elle)",
        );
        $statement->execute([
            'metin' => $sourceText,
            'dil' => $targetLang,
            'elle' => self::ELLE_SAGLAYICI,
        ]);

        return (int) $statement->fetchColumn() > 0;
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

    /**
     * LLM SONUCUYLA TAZELE (D6 saha bulgusu, 25 Ağu 2026).
     *
     * `store()` yalnızca INSERT eder: aynı anahtarda satır varsa sessizce geçer.
     * Bu, makine çevirisinin (MyMemory) LLM sonucuyla ASLA değişmemesi demekti —
     * sahada TR alanları kalıcı olarak düşük kaliteli makine çevirisinde kaldı.
     *
     * Burada makine satırının ÜZERİNE yazılır; ama yalnız makine satırının:
     *   • `llm:*` satırı zaten güncel sonuçtur (aynı turun ikinci yazımı),
     *   • `elle` satırı kullanıcının ONAYIDIR (K54) — otomatik tur onu ezemez.
     *
     * Dönen değer ne yapıldığını söyler; çağıran loglayabilsin diye.
     *
     * @return 'eklendi'|'tazelendi'|'korundu'
     */
    public function tazele(
        string $hash,
        string $sourceText,
        string $suggestedText,
        string $provider,
        string $sourceLang,
        string $targetLang,
        DateTimeImmutable $now,
        string $surum = '',
    ): string {
        $mevcut = $this->find($hash);
        if ($mevcut === null) {
            $this->store($hash, $sourceText, $suggestedText, $provider, $sourceLang, $targetLang, $now, $surum);

            return 'eklendi';
        }

        if (self::kaliciMi($mevcut['provider'])) {
            return 'korundu';
        }

        $statement = $this->connection->pdo()->prepare(
            'UPDATE translation_cache
                SET suggested_text = :suggested_text, provider = :provider, surum = :surum, created_at = :created_at
              WHERE source_hash = :hash',
        );
        $statement->execute([
            'suggested_text' => mb_substr($suggestedText, 0, 1000),
            'provider' => $provider,
            'surum' => $surum,
            'created_at' => Dates::toStorage($now),
            'hash' => $hash,
        ]);

        return 'tazelendi';
    }

    /**
     * İKİ ANAHTAR BİRDEN tazele (D6).
     *
     * Sistemde iki anahtar uzayı vardır ve ikisi de canlıdır:
     *   • SÜRÜMLÜ (İE#21 B12) — sağlayıcı/model/prompt/sözlük değişince satırı
     *     kendiliğinden geçersizleştiren doğru anahtar;
     *   • SÜRÜMSÜZ — makine katmanının (MyMemory) ve `bin/ceviri-sinavi.php`
     *     gibi okuyucuların baktığı eski anahtar.
     *
     * Sahada yalnız sürümlü satır yazılıyordu; kullanıcının GÖRDÜĞÜ satır ise
     * sürümsüz olandı ve makine çevirisinde donmuştu. İkisi birden yazılır.
     *
     * @return array<string, string> anahtar sürümü → sonuç ('eklendi'|'tazelendi'|'korundu')
     */
    public function tazeleTumAnahtarlar(
        string $sourceText,
        string $suggestedText,
        string $provider,
        string $sourceLang,
        string $targetLang,
        DateTimeImmutable $now,
        string $surum,
    ): array {
        $sonuclar = [];
        foreach (array_unique([$surum, '']) as $anahtarSurumu) {
            $sonuclar[$anahtarSurumu] = $this->tazele(
                self::hash($sourceText, $sourceLang, $targetLang, $anahtarSurumu),
                $sourceText,
                $suggestedText,
                $provider,
                $sourceLang,
                $targetLang,
                $now,
                $anahtarSurumu,
            );
        }

        return $sonuclar;
    }
}
