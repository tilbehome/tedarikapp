<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * PDO bağlantı fabrikası — utf8mb4 zorunlu (Çince orijinal başlıklar, docs/04),
 * gerçek prepared statements (emulasyon KAPALI), hatalar istisna olarak fırlar.
 */
final class Database
{
    public static function connect(Config $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config->get('DB_HOST'),
            $config->getInt('DB_PORT', 3306),
            $config->get('DB_NAME'),
        );

        return new PDO(
            $dsn,
            $config->get('DB_USER'),
            $config->get('DB_PASS', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );
    }
}
