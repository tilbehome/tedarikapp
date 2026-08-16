<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Geçersiz durum geçişi. Uç bunu 422 `STATE_TRANSITION` + `meta.allowed` zarfına çevirir
 * (docs/10 §4) — kullanıcı yalnızca "olmaz" değil, "buradan nereye gidebilirsin" görür.
 */
final class StateTransitionException extends RuntimeException
{
    /** @param list<string> $allowed */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly array $allowed,
        string $message,
    ) {
        parent::__construct($message);
    }
}
