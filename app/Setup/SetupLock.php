<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Kurulum kilidi (K16, K33, K37).
 *
 * Kilit VERİTABANINDA tutulur (`settings` tablosu, `system.setup_lock` anahtarı):
 * üretim sunucusunda uygulama diske yazamıyor (PHP `nobody`, DSO), dolayısıyla
 * dosya tabanlı kilit yazılamaz ve sihirbaz asla kapanamazdı.
 *
 * `storage/setup.lock` DOSYASI hâlâ OKUNUR ama artık yazılmaz: K33 öncesi kurulmuş
 * sistemler kilitli kalmaya devam etsin diye (geriye dönük uyum).
 *
 * K37 — FAIL-CLOSED: bağlantı VERİLMİŞKEN kilit okunamıyorsa (DB erişilemiyor,
 * tablo yok) durum `unknown` döner ve SetupGuard bunu "kilitli" sayar. Kurulmuş bir
 * sistemde DB'nin geçici düşmesi, sihirbazı kimliksiz bir kapı olarak AÇAMAZ.
 * Bağlantı hiç verilmemişse (.env yok → kurulum yapılmamış) dosya denetimiyle yetinilir.
 */
final class SetupLock
{
    public const string SETTING_KEY = 'system.setup_lock';

    public const string STATE_LOCKED = 'locked';
    public const string STATE_UNLOCKED = 'unlocked';
    /** DB yapılandırılmış ama kilit okunamıyor — K37 gereği kilitli MUAMELESİ görür. */
    public const string STATE_UNKNOWN = 'unknown';

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

    /** Fail-closed görünüm: yalnızca KESİN "kilitsiz" durumda false döner (K37). */
    public function isLocked(): bool
    {
        return $this->status() !== self::STATE_UNLOCKED;
    }

    /**
     * Kilidin üç durumlu okuması (K37): locked / unlocked / unknown.
     * `unknown` yalnızca bağlantı yapılandırılmışken okuma hatasında döner.
     */
    public function status(): string
    {
        if ($this->connection !== null) {
            try {
                $stored = $this->readFromDatabaseStrict();
            } catch (Throwable) {
                return self::STATE_UNKNOWN;
            }

            if ($stored !== null) {
                return self::STATE_LOCKED;
            }
        }

        return $this->readFromLegacyFile() !== null ? self::STATE_LOCKED : self::STATE_UNLOCKED;
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
            return $this->readFromDatabaseStrict();
        } catch (Throwable) {
            // Raporlama amaçlı okuma: hata durumunda içerik bilinmiyor.
            // Kapı kararı status() üzerinden verilir (K37 fail-closed).
            return null;
        }
    }

    /**
     * Hata YUTMAYAN okuma: status() bağlantı hatasını `unknown`a çevirebilsin diye.
     *
     * @return array<string, mixed>|null
     */
    private function readFromDatabaseStrict(): ?array
    {
        if ($this->connection === null) {
            return null;
        }

        $statement = $this->connection->pdo()->prepare('SELECT value FROM settings WHERE `key` = :key');
        $statement->execute(['key' => self::SETTING_KEY]);
        $row = $statement->fetch();

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
