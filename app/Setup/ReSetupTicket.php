<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\Connection;
use DateTimeImmutable;
use SensitiveParameter;
use Throwable;

/**
 * YENİDEN KURULUM BİLETİ (İE#19 G2 · E6).
 *
 * ESKİ DAVRANIŞ VE NEDEN YANLIŞTI: `/api/setup/unlock` sahiplik kanıtı doğrulanınca
 * kurulum KİLİDİNİ SİLİYORDU. Kilit silindiği anda sihirbaz İNTERNETE AÇILIYOR ve
 * kanıt gösteren kişi kadar, o anda adresi bilen HERKES kurulumu sürdürebiliyordu:
 * kanıt bir kez veriliyor, açıklık kalıcı oluyordu. Üstelik işlem geri alınamıyordu —
 * kullanıcı sihirbazı yarıda bıraksa sistem kilitsiz kalıyordu.
 *
 * YENİ DAVRANIŞ: kilit ASLA silinmez. Kanıt karşılığında 15 dakikalık, TEK
 * KULLANIMLIK ve İSTEMCİYE BAĞLI bir bilet üretilir. SetupGuard yalnız bileti
 * taşıyan isteği geçirir; bilet çerezde taşınır (HttpOnly), süresi dolunca ya da
 * kurulum tamamlanınca düşer. Yeni bilet üretmek eskisini geçersiz kılar.
 *
 * Bilet DB'de yalnız ÖZETİYLE durur (SHA-256): veritabanını okuyan biri geçerli bir
 * bilet üretemez. Çerezdeki değer tek gerçek kopyadır.
 */
final class ReSetupTicket
{
    public const COOKIE_NAME = 'tedarikapp_resetup';
    public const SETTING_KEY = 'system.setup_ticket';
    /** 15 dakika (emir metni). */
    public const LIFETIME_SECONDS = 900;

    public function __construct(private readonly ?Connection $connection)
    {
    }

    /**
     * Bilet üretir ve DB'ye özetini yazar; DÜZ değeri döner (çağıran çereze yazar).
     *
     * @param string $issuedTo denetim izi: 'app_key' veya 'admin:<e-posta>'
     */
    public function issue(DateTimeImmutable $now, string $issuedTo): string
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Yeniden kurulum bileti üretilemedi: veritabanı bağlantısı yok.');
        }

        $token = bin2hex(random_bytes(32));
        $payload = json_encode([
            'hash' => hash('sha256', $token),
            'expires_at' => $now->getTimestamp() + self::LIFETIME_SECONDS,
            'issued_to' => $issuedTo,
            'issued_at' => $now->format(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->put($payload);

        return $token;
    }

    /** Bilet geçerli mi? (var · özet eşleşiyor · süresi dolmamış) */
    public function validate(#[SensitiveParameter] ?string $token, DateTimeImmutable $now): bool
    {
        if (!is_string($token) || preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return false;
        }

        $stored = $this->read();
        if ($stored === null) {
            return false;
        }

        $hash = is_string($stored['hash'] ?? null) ? $stored['hash'] : '';
        $expires = is_int($stored['expires_at'] ?? null) ? $stored['expires_at'] : 0;

        if ($expires <= $now->getTimestamp()) {
            return false;
        }

        return hash_equals($hash, hash('sha256', $token));
    }

    /** Kurulum tamamlandığında ya da bilet iptal edilirken çağrılır. */
    public function consume(): void
    {
        if ($this->connection === null) {
            return;
        }

        try {
            $statement = $this->connection->pdo()->prepare('DELETE FROM settings WHERE `key` = :key');
            $statement->execute(['key' => self::SETTING_KEY]);
        } catch (Throwable) {
            // Tablo yoksa bilet de yoktur.
        }
    }

    /**
     * Denetim/rapor amaçlı: biletin kime, ne zaman verildiği (özet HARİÇ).
     *
     * @return array{issued_to: string, issued_at: string|null, expires_in_seconds: int}|null
     */
    public function describe(DateTimeImmutable $now): ?array
    {
        $stored = $this->read();
        if ($stored === null) {
            return null;
        }

        $expires = is_int($stored['expires_at'] ?? null) ? $stored['expires_at'] : 0;

        return [
            'issued_to' => is_string($stored['issued_to'] ?? null) ? $stored['issued_to'] : 'bilinmiyor',
            'issued_at' => is_string($stored['issued_at'] ?? null) ? $stored['issued_at'] : null,
            'expires_in_seconds' => max(0, $expires - $now->getTimestamp()),
        ];
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        if ($this->connection === null) {
            return null;
        }

        try {
            $statement = $this->connection->pdo()->prepare('SELECT value FROM settings WHERE `key` = :key');
            $statement->execute(['key' => self::SETTING_KEY]);
            $row = $statement->fetch();
        } catch (Throwable) {
            return null;
        }

        if (!is_array($row) || !is_string($row['value'] ?? null)) {
            return null;
        }

        $decoded = json_decode($row['value'], true);

        return is_array($decoded) ? $decoded : null;
    }

    private function put(string $payload): void
    {
        $pdo = $this->connection?->pdo();
        if ($pdo === null) {
            return;
        }

        // SetupLock ile aynı desen: önce güncelle, satır yoksa ekle (MySQL + SQLite ortak).
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
    }
}
