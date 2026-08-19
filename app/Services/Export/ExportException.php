<?php

declare(strict_types=1);

namespace App\Services\Export;

use RuntimeException;

/** Export üretim hatası — mesaj kullanıcıya gösterilebilir (teknik ayrıntı loga). */
class ExportException extends RuntimeException
{
}
