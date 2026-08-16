<?php

declare(strict_types=1);

namespace App\Auth;

use SensitiveParameter;

/**
 * Şifre hash'leme — Argon2id (K16, docs/04 §5). Maliyet parametreleri PHP varsayılanıdır:
 * PHP sürümü yükseldikçe varsayılan sertleşir, `needsRehash` ile mevcut şifreler kendiliğinden taşınır.
 */
final class PasswordHasher
{
    /** docs/04 §2d: şifre en az 10 karakter. */
    public const int MIN_LENGTH = 10;

    public function hash(#[SensitiveParameter] string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_ARGON2ID);
    }

    public function verify(#[SensitiveParameter] string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
