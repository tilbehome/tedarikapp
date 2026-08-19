<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Yakalama işleme hatası — mesaj eklentiye/panele gösterilebilir (İE#11). */
final class CaptureException extends RuntimeException
{
}
