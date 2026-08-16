<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Log gizleme (redaction) işlemcisi — K27, CLAUDE.md §5 ("koda, loga, repoya sır yazılmaz").
 *
 * Log çağrılarının her birinde "acaba bu alan sır mı" diye düşünmek insan hatasına açıktır;
 * bu yüzden gizleme MERKEZİ ve varsayılan olarak açıktır: adı hassas bir terimi İÇEREN her
 * alan, iç içe dizilerde de olsa, değeri okunmadan `[GİZLENDİ]` ile değiştirilir.
 *
 * Eşleşme bilinçli olarak geniştir (örn. `error_code` de gizlenir): fazladan gizlemek
 * zararsız, eksik gizlemek onarılamaz.
 */
final class LogRedactor implements ProcessorInterface
{
    public const string PLACEHOLDER = '[GİZLENDİ]';

    /** Alan adında geçtiğinde değeri gizlenen terimler (küçük harfe indirgenmiş karşılaştırma). */
    private const array SENSITIVE_TERMS = [
        'authorization',
        'cookie',
        'password',
        'code',
        'token',
        'secret',
        'db_pass',
        'app_key',
        'csrf',
        'hash',
    ];

    /**
     * Beyaz liste: hassas terim İÇERSE bile gizlenmeyen alanlar (İE#5).
     *
     * `error_code` "code" içerdiği için gizleniyordu; `request_id` de logun asıl işe yarayan
     * bağıdır. İkisi de sır değildir — gizlenmeleri hata ayıklamayı imkânsız hâle getiriyordu.
     * Karşılaştırma TAM ad üzerinden yapılır: `error_code_secret` gibi bir alan yine gizlenir.
     */
    private const array ALLOWED_KEYS = [
        'error_code',
        'request_id',
    ];

    /** İç içe yapılarda sonsuz döngüye/aşırı derinliğe karşı sınır. */
    private const int MAX_DEPTH = 8;

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record
            ->with(context: $this->redactArray($record->context, 0))
            ->with(extra: $this->redactArray($record->extra, 0));
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function redactArray(array $values, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [self::PLACEHOLDER];
        }

        $clean = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $clean[$key] = self::PLACEHOLDER;

                continue;
            }
            $clean[$key] = is_array($value) ? $this->redactArray($value, $depth + 1) : $value;
        }

        return $clean;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);
        if (in_array($normalized, self::ALLOWED_KEYS, true)) {
            return false;
        }
        foreach (self::SENSITIVE_TERMS as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }

        return false;
    }
}
