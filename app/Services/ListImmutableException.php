<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Terminal (completed/cancelled) listeye mutasyon denemesi (K37 §B4).
 * API katmanında 422 + `LIST_IMMUTABLE` koduna çevrilir.
 */
final class ListImmutableException extends RuntimeException
{
    public function __construct(
        public readonly string $listStatus,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Bu liste "%s" durumunda ve artık değiştirilemez. Devam etmek için listeyi kopyalayın.',
            $listStatus,
        ));
    }
}
