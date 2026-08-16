<?php

declare(strict_types=1);

namespace App\Setup;

use DateTimeImmutable;
use RuntimeException;

/**
 * Kurulum kilidi (K16, İE#5 §10).
 *
 * `storage/setup.lock` YOKSA sihirbaz açıktır; VARSA tüm setup uçları 403 döner.
 * Kilit dosyası kurulumun en sonunda **atomik** yazılır: önce geçici dosyaya yazılır,
 * sonra `rename()` ile yerine taşınır. Yarım yazılmış bir kilit dosyası, kurulumu hem
 * bitmiş hem bitmemiş gösteren bir ara duruma yol açardı.
 */
final class SetupLock
{
    public function __construct(private readonly string $storagePath)
    {
    }

    public function path(): string
    {
        return $this->storagePath . '/setup.lock';
    }

    public function isLocked(): bool
    {
        return is_file($this->path());
    }

    /** @return array<string, mixed>|null Kilit içeriği (kurulum tarihi, sürüm) — okunamazsa null. */
    public function read(): ?array
    {
        if (!$this->isLocked()) {
            return null;
        }

        $raw = @file_get_contents($this->path());
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $details */
    public function write(DateTimeImmutable $now, array $details = []): void
    {
        if (!is_dir($this->storagePath) && !@mkdir($this->storagePath, 0775, true) && !is_dir($this->storagePath)) {
            throw new RuntimeException(sprintf('storage klasörü oluşturulamadı: %s', $this->storagePath));
        }

        $payload = json_encode(
            ['installed_at' => $now->format(DATE_ATOM)] + $details,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $temporary = $this->path() . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($temporary, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Kurulum kilidi yazılamadı: storage klasörü yazılabilir değil.');
        }

        // rename() aynı dosya sisteminde atomiktir: kilit ya tam vardır ya hiç yoktur.
        if (!@rename($temporary, $this->path())) {
            @unlink($temporary);

            throw new RuntimeException('Kurulum kilidi yerine taşınamadı.');
        }

        @chmod($this->path(), 0640);
    }
}
