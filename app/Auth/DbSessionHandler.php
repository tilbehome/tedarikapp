<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;
use SessionHandlerInterface;
use Throwable;

/**
 * PHP oturumlarının VERİTABANI deposu (K44, İE#9.4 — disksiz mod).
 *
 * Üretim sunucusu `session.save_path`e yazamıyor (nobody + yazılamaz docroot):
 * dosya tabanlı session her istekte kaybolur, login sonrası oturum düşer.
 * Bu handler ile oturum verisi `sessions` tablosunda yaşar; diske TEK BAYT yazılmaz.
 *
 * Veri base64'le saklanır: PHP'nin session serileştirmesi ikili veri içerebilir,
 * MEDIUMTEXT + utf8mb4'te bozulurdu.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?string $clientIp = null,
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return true; // depo DB — path/name kullanılmaz
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        try {
            $statement = $this->connection->pdo()->prepare('SELECT data FROM sessions WHERE id = :id');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();
        } catch (Throwable) {
            return ''; // okunamayan oturum = boş oturum; istek yine akar
        }

        if (!is_array($row) || !is_string($row['data'])) {
            return '';
        }

        $decoded = base64_decode($row['data'], true);

        return $decoded === false ? '' : $decoded;
    }

    public function write(string $id, string $data): bool
    {
        $now = Dates::toStorage(new DateTimeImmutable());

        try {
            $pdo = $this->connection->pdo();
            $update = $pdo->prepare(
                'UPDATE sessions SET data = :data, last_activity = :now, ip = :ip WHERE id = :id',
            );
            $update->execute(['id' => $id, 'data' => base64_encode($data), 'now' => $now, 'ip' => $this->clientIp]);

            if ($update->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM sessions WHERE id = :id');
                $exists->execute(['id' => $id]);
                if ($exists->fetch() === false) {
                    $insert = $pdo->prepare(
                        'INSERT INTO sessions (id, data, last_activity, ip) VALUES (:id, :data, :now, :ip)',
                    );
                    $insert->execute(['id' => $id, 'data' => base64_encode($data), 'now' => $now, 'ip' => $this->clientIp]);
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $statement = $this->connection->pdo()->prepare('DELETE FROM sessions WHERE id = :id');
            $statement->execute(['id' => $id]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $threshold = Dates::toStorage((new DateTimeImmutable())->modify(sprintf('-%d seconds', $max_lifetime)));
            $statement = $this->connection->pdo()->prepare('DELETE FROM sessions WHERE last_activity < :threshold');
            $statement->execute(['threshold' => $threshold]);

            return $statement->rowCount();
        } catch (Throwable) {
            return false;
        }
    }
}
