<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Dates;
use DateTimeZone;

/**
 * Kullanıcı değer nesnesi.
 *
 * API'ye çıkan tek temsil `toApiArray()`'dir ve yalnızca docs/10 §2'deki
 * `{id, email, created_at}` alanlarını üretir — hash/secret sızıntısı yapısal olarak imkânsızdır.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?string $totpSecret,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $totpSecret = $row['totp_secret'] ?? null;

        return new self(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['password_hash'],
            is_string($totpSecret) && $totpSecret !== '' ? $totpSecret : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    /** @return array{id: int, email: string, created_at: string} */
    public function toApiArray(DateTimeZone $timezone): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'created_at' => Dates::toIso($this->createdAt, $timezone),
        ];
    }
}
