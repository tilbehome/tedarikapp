<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Kurulum kilidi (K16, K33).
 *
 * Kilit VERİTABANINDA tutulur (`settings` tablosu, `system.setup_lock` anahtarı):
 * üretim sunucusunda uygulama diske yazamıyor (PHP `nobody`, DSO), dolayısıyla
 * dosya tabanlı kilit yazılamaz ve sihirbaz asla kapanamazdı.
 *
 * `storage/setup.lock` DOSYASI hâlâ OKUNUR ama artık yazılmaz: K33 öncesi kurulmuş
 * sistemler kilitli kalmaya devam etsin diye (geriye dönük uyum).
 *
 * Veritabanına erişilemiyorsa kilit YOK sayılır — bu doğru davranıştır: DB yoksa
 * kurulum zaten yapılmamıştır ve sihirbazın açılması gerekir.
 */
final class SetupLock
{
    public const string SETTING_KEY = 'system.setup_lock';

    public function __construct(
        private readonly ?Connection $connection = null,
        private readonly ?string $storagePath = null,
    ) {
    }

    /** K33 öncesi kurulumlardan kalan dosya (yalnızca okunur). */
    public function legacyFilePath(): ?string
    {
        return $this->storagePath === null ? null : $this->storagePath . '/setup.lock';
    }

    public function isLocked(): bool
    {
        return $this->read() !== null;
    }

    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        $stored = $this->readFromDatabase();
        if ($stored !== null) {
            return $stored;
        }

        return $this->readFromLegacyFile();
    }

    /** @param array<string, mixed> $details */
    public function write(DateTimeImmutable $now, array $details = []): void
    {
        if ($this->connection === null) {
            throw new RuntimeException('Kurulum kilidi yazılamadı: veritabanı bağlantısı yok.');
        }

        $payload = json_encode(
            ['installed_at' => $now->format(DATE_ATOM)] + $details,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        try {
            $pdo = $this->connection->pdo();

            // MySQL ve SQLite'ta ortak yol: önce güncelle, etkilenen satır yoksa ekle.
            $update = $pdo->prepare('UPDATE settings SET value = :value WHERE `key` = :key');
            $update->execute(['key' => self::SETTING_KEY, 'value' => $payload]);

            if ($update->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM settings WHERE `key` = :key');
                $exists->execute(['key' => self::SETTING_KEY]);
                if ($exists->fetch() === false) {
                    $insert = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (:key, :value)');
                    $insert->execute(['key' => self::SETTING_KEY, 'value' => $payload]);
                }
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Kurulum kilidi veritabanına yazılamadı: ' . $e->getMessage(), 0, $e);
        }

        // Yazma denetimi: kilit gerçekten okunabiliyor mu? Yazılamamış bir kilit,
        // sihirbazı sonsuza dek açık bırakırdı.
        if ($this->readFromDatabase() === null) {
            throw new RuntimeException('Kurulum kilidi yazıldı ama geri okunamadı.');
        }
    }

    /** @return array<string, mixed>|null */
    private function readFromDatabase(): ?array
    {
        if ($this->connection === null) {
            return null;
        }

        try {
            $statement = $this->connection->pdo()->prepare('SELECT value FROM settings WHERE `key` = :key');
            $statement->execute(['key' => self::SETTING_KEY]);
            $row = $statement->fetch();
        } catch (Throwable) {
            // Tablo yok veya DB erişilemiyor → kurulum yapılmamış demektir.
            return null;
        }

        if (!is_array($row) || !is_string($row['value'])) {
            return null;
        }

        $decoded = json_decode($row['value'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<string, mixed>|null */
    private function readFromLegacyFile(): ?array
    {
        $path = $this->legacyFilePath();
        if ($path === null || !is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded + ['source' => 'legacy_file'] : null;
    }

    /** Kilit anahtarının `settings` tablosunda saklandığı gerçeği; testler ve raporlama için. */
    public function storesInDatabase(): bool
    {
        return $this->connection !== null;
    }

    /** Dates sınıfına bağımlılığı korumak için (depolama biçimi tek yerde tanımlı). */
    public static function storageFormat(): string
    {
        return Dates::STORAGE_FORMAT;
    }
}
