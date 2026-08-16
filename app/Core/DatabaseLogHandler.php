<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Monolog kayıtlarını `app_logs` tablosuna yazar (K33).
 *
 * Üretimde uygulama diske yazamıyor (PHP `nobody`, DSO) — dosya hedefi orada kullanılamaz.
 *
 * KURAL: log yazmak uygulamayı ASLA düşürmez. Veritabanı erişilemezse kayıt sessizce
 * düşürülür; aksi hâlde "loglama başarısız" hatası asıl hatanın üstünü örterdi.
 * Bağlantı tembeldir: log çağrısı olmayan isteklerde DB'ye dokunulmaz.
 */
final class DatabaseLogHandler extends AbstractProcessingHandler
{
    /** Aynı istekte tekrar tekrar denenmesin: bir kez patlarsa o istek boyunca susar. */
    private bool $disabled = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly ?RequestContext $requestContext = null,
        int|string|Level $level = Level::Warning,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if ($this->disabled) {
            return;
        }

        try {
            $statement = $this->connection->pdo()->prepare(
                'INSERT INTO app_logs (channel, level_name, level, message, context, extra, request_id, logged_at)
                 VALUES (:channel, :level_name, :level, :message, :context, :extra, :request_id, :logged_at)',
            );
            $statement->execute([
                'channel' => $record->channel,
                'level_name' => $record->level->getName(),
                'level' => $record->level->value,
                'message' => $record->message,
                'context' => $this->encode($record->context),
                'extra' => $this->encode($record->extra),
                'request_id' => $this->requestContext?->id(),
                'logged_at' => $record->datetime->format(Dates::STORAGE_FORMAT),
            ]);
        } catch (Throwable) {
            // Veritabanı yoksa/erişilemiyorsa log kaybedilir ama uygulama çalışmaya devam eder.
            $this->disabled = true;
        }
    }

    /** @param array<array-key, mixed> $data */
    private function encode(array $data): ?string
    {
        if ($data === []) {
            return null;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? null : $json;
    }
}
