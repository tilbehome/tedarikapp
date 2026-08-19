<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Medya indirme/işleme hatası. Mesajı kullanıcıya gösterilebilir (teknik ayrıntı içermez);
 * ayrıntı loga yazılır (CLAUDE.md §6).
 */
class MediaException extends RuntimeException
{
}
