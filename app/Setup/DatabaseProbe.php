<?php

declare(strict_types=1);

namespace App\Setup;

use PDO;
use SensitiveParameter;
use Throwable;

/**
 * Kurulum sırasında veritabanı bağlantı denemesi (İE#5 §11b).
 *
 * `SELECT VERSION()` sonucu ekranda ve kurulum kilidinde saklanır: üretimde MySQL mi
 * MariaDB mi, hangi sürüm — deploy notlarına elle yazılmak yerine kaynaktan kaydedilir.
 *
 * GÜVENLİK: bu sınıf DB şifresini hiçbir yere yazmaz; hata mesajları da şifreyi
 * içermeyecek şekilde sadeleştirilir (PDO istisnası bazen DSN'i metne katar).
 */
final class DatabaseProbe
{
    /** @return array{ok: bool, version?: string, charset?: string, error?: string} */
    public function probe(
        string $host,
        int $port,
        string $database,
        string $user,
        #[SensitiveParameter] string $password,
    ): array {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $this->humanize($e)];
        }

        $version = 'bilinmiyor';
        $statement = $pdo->query('SELECT VERSION() AS version');
        $row = $statement === false ? false : $statement->fetch();
        if (is_array($row) && isset($row['version'])) {
            $version = (string) $row['version'];
        }

        $charset = 'bilinmiyor';
        $charsetStatement = $pdo->query("SHOW VARIABLES LIKE 'character_set_database'");
        $charsetRow = $charsetStatement === false ? false : $charsetStatement->fetch();
        if (is_array($charsetRow) && isset($charsetRow['Value'])) {
            $charset = (string) $charsetRow['Value'];
        }

        // utf8mb4 ZORUNLU (docs/04): Çince orijinal başlıklar utf8'e sığmaz.
        if (!str_starts_with($charset, 'utf8mb4')) {
            return [
                'ok' => false,
                'version' => $version,
                'charset' => $charset,
                'error' => sprintf(
                    'Veritabanı karakter seti "%s" — utf8mb4 olmalı. Çince ürün başlıkları aksi hâlde kaydedilemez. '
                    . 'Çözüm: ALTER DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
                    $charset,
                    $database,
                ),
            ];
        }

        return ['ok' => true, 'version' => $version, 'charset' => $charset];
    }

    /** PDO hatasını kullanıcıya gösterilebilir, sır içermeyen bir cümleye çevirir. */
    private function humanize(Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'Access denied') =>
                'Kullanıcı adı veya şifre hatalı (veritabanı sunucusu erişimi reddetti).',
            str_contains($message, 'Unknown database') =>
                'Bu isimde bir veritabanı yok. cPanel > MySQL Veritabanları\'ndan oluşturup tekrar deneyin.',
            str_contains($message, 'Connection refused'), str_contains($message, 'No such host'),
            str_contains($message, 'getaddrinfo') =>
                'Veritabanı sunucusuna ulaşılamadı. Sunucu adı ve port doğru mu?',
            str_contains($message, 'timed out') =>
                'Veritabanı sunucusu zaman aşımına uğradı.',
            default => 'Veritabanına bağlanılamadı. Bilgileri kontrol edip tekrar deneyin.',
        };
    }
}
