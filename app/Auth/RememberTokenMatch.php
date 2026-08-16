<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * RememberTokenService::validate() sonucu — durum + (geçerliyse) kime ait olduğu.
 */
final class RememberTokenMatch
{
    public function __construct(
        public readonly RememberTokenStatus $status,
        public readonly ?int $userId = null,
        public readonly ?int $tokenId = null,
    ) {
    }

    public static function of(RememberTokenStatus $status): self
    {
        return new self($status);
    }
}
