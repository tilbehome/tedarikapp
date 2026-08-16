<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;
use SensitiveParameter;

/**
 * users tablosu erişimi (docs/04 §2). Yalnızca parametreli sorgu — CLAUDE.md §5.
 */
final class UserRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, email, password_hash, totp_secret, created_at, updated_at FROM users WHERE email = :email',
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return is_array($row) ? User::fromRow($row) : null;
    }

    public function findById(int $id): ?User
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, email, password_hash, totp_secret, created_at, updated_at FROM users WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? User::fromRow($row) : null;
    }

    public function count(): int
    {
        $statement = $this->connection->pdo()->query('SELECT COUNT(*) AS total FROM users');
        $row = $statement === false ? false : $statement->fetch();

        return is_array($row) ? (int) $row['total'] : 0;
    }

    /** @return int Oluşturulan kullanıcının kimliği. */
    public function create(
        string $email,
        #[SensitiveParameter] string $passwordHash,
        #[SensitiveParameter] string $encryptedTotpSecret,
        DateTimeImmutable $now,
    ): int {
        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO users (email, password_hash, totp_secret, created_at, updated_at)
             VALUES (:email, :password_hash, :totp_secret, :created_at, :updated_at)',
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'totp_secret' => $encryptedTotpSecret,
            'created_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Argon2id parametreleri değiştiğinde şifre sessizce yeni maliyetle yeniden hash'lenir. */
    public function updatePasswordHash(int $userId, #[SensitiveParameter] string $passwordHash, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id',
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'updated_at' => Dates::toStorage($now),
            'id' => $userId,
        ]);
    }
}
