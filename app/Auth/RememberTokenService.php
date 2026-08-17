<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * "Beni hatırla" token'ları — selector + validator deseni (docs/04 §2, K16).
 *
 * Çerez değeri `selector:validator` biçimindedir:
 *   • selector  → düz saklanır, tekil indekslidir; kaydı BULMAK için kullanılır.
 *   • validator → yalnızca hash'i saklanır; kaydı DOĞRULAMAK için kullanılır.
 *
 * Validator 256 bit kriptografik rastgeledir ve arama tek satır üzerinden yapıldığından
 * hızlı hash (SHA-256) doğru tercihtir: Argon2id'nin koruduğu düşük-entropili sır burada yoktur.
 * Karşılaştırma `hash_equals` ile sabit zamanlıdır.
 */
final class RememberTokenService
{
    public const COOKIE_NAME = 'tedarikapp_remember';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Yeni token üretir ve saklar.
     *
     * @return array{id: int, cookie: string, expires_at: DateTimeImmutable}
     */
    public function issue(int $userId, DateTimeImmutable $now, int $lifetimeMinutes): array
    {
        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = $now->modify(sprintf('+%d minutes', $lifetimeMinutes));

        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_at)
             VALUES (:user_id, :selector, :token_hash, :expires_at, :created_at)',
        );
        $statement->execute([
            'user_id' => $userId,
            'selector' => $selector,
            'token_hash' => hash('sha256', $validator),
            'expires_at' => Dates::toStorage($expiresAt),
            'created_at' => Dates::toStorage($now),
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'cookie' => $selector . ':' . $validator,
            'expires_at' => $expiresAt,
        ];
    }

    /** Çerez değerini doğrular; çağıran sonucu duruma göre işler (çalıntı token → hepsini sil). */
    public function validate(?string $cookieValue, DateTimeImmutable $now): RememberTokenMatch
    {
        if ($cookieValue === null || !str_contains($cookieValue, ':')) {
            return RememberTokenMatch::of(RememberTokenStatus::Absent);
        }

        [$selector, $validator] = explode(':', $cookieValue, 2);
        if ($selector === '' || $validator === '') {
            return RememberTokenMatch::of(RememberTokenStatus::Absent);
        }

        $statement = $this->connection->pdo()->prepare(
            'SELECT id, user_id, token_hash, expires_at FROM remember_tokens WHERE selector = :selector',
        );
        $statement->execute(['selector' => $selector]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return RememberTokenMatch::of(RememberTokenStatus::Unknown);
        }

        if (!hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
            return new RememberTokenMatch(RememberTokenStatus::Stolen, (int) $row['user_id'], (int) $row['id']);
        }

        if ((string) $row['expires_at'] <= Dates::toStorage($now)) {
            return new RememberTokenMatch(RememberTokenStatus::Expired, (int) $row['user_id'], (int) $row['id']);
        }

        return new RememberTokenMatch(RememberTokenStatus::Valid, (int) $row['user_id'], (int) $row['id']);
    }

    /**
     * Kullanıcının token'larını listeler (docs/10: id, created_at, expires_at).
     *
     * @return list<array{id: int, created_at: string, expires_at: string}>
     */
    public function listForUser(int $userId): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, created_at, expires_at FROM remember_tokens WHERE user_id = :user_id ORDER BY created_at DESC, id DESC',
        );
        $statement->execute(['user_id' => $userId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        $tokens = [];
        foreach ($rows as $row) {
            $tokens[] = [
                'id' => (int) $row['id'],
                'created_at' => (string) $row['created_at'],
                'expires_at' => (string) $row['expires_at'],
            ];
        }

        return $tokens;
    }

    /** @return bool Kayıt gerçekten silindiyse true (başkasının token'ı silinemez). */
    public function revoke(int $tokenId, int $userId): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'DELETE FROM remember_tokens WHERE id = :id AND user_id = :user_id',
        );
        $statement->execute(['id' => $tokenId, 'user_id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function revokeAllForUser(int $userId): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
    }

    public function purgeExpired(DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM remember_tokens WHERE expires_at <= :now');
        $statement->execute(['now' => Dates::toStorage($now)]);
    }
}
