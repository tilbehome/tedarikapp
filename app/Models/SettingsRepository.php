<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;

/**
 * settings tablosu — anahtar/değer ayarları (docs/04 §2).
 *
 * Kur burada tutulur; liste OLUŞTURULURKEN o anki değer listeye kopyalanır ve
 * `sent` durumunda kilitlenir (K4). Ayar değişmesi eski listelerin TL'sini etkilemez.
 */
final class SettingsRepository
{
    public const string KEY_YUAN_RATE = 'yuan_tl';
    public const string KEY_USD_RATE = 'usd_tl';

    /**
     * Ayar henüz girilmemişken kullanılan başlangıç değerleri.
     * Gerçek değerler `PUT /api/settings/rates` ile girilir (ayarlar iş emri);
     * o güne kadar liste oluşturulabilsin diye makul bir başlangıç verilir.
     */
    public const string DEFAULT_YUAN_RATE = '7.0400';
    public const string DEFAULT_USD_RATE = '41.5000';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $statement = $this->connection->pdo()->prepare('SELECT value FROM settings WHERE `key` = :key');
        $statement->execute(['key' => $key]);
        $row = $statement->fetch();

        if (!is_array($row) || $row['value'] === null) {
            return $default;
        }

        return (string) $row['value'];
    }

    public function set(string $key, string $value): void
    {
        $pdo = $this->connection->pdo();

        // MySQL ve SQLite'ta ortak çalışan yol: önce güncelle, etkilenen satır yoksa ekle.
        $update = $pdo->prepare('UPDATE settings SET value = :value WHERE `key` = :key');
        $update->execute(['key' => $key, 'value' => $value]);

        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT 1 FROM settings WHERE `key` = :key');
            $exists->execute(['key' => $key]);
            if ($exists->fetch() === false) {
                $insert = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (:key, :value)');
                $insert->execute(['key' => $key, 'value' => $value]);
            }
        }
    }

    public function yuanRate(): string
    {
        return $this->get(self::KEY_YUAN_RATE, self::DEFAULT_YUAN_RATE) ?? self::DEFAULT_YUAN_RATE;
    }

    public function usdRate(): string
    {
        return $this->get(self::KEY_USD_RATE, self::DEFAULT_USD_RATE) ?? self::DEFAULT_USD_RATE;
    }
}
