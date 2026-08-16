<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Zaman kaynağı soyutlaması — giriş kilidi (backoff) ve token ömürleri
 * zamana bağlı olduğu için testlerde sabitlenebilir bir saat gerekir.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
