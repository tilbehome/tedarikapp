<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Uygulama sürümü — `GET /api/system/status` ve kurulum kilidi bunu raporlar.
 *
 * Tek kaynak burasıdır; release çıkarılırken CHANGELOG ile birlikte güncellenir (docs/07 §4).
 */
final class AppVersion
{
    public const VALUE = '0.12.1-beta';
}
