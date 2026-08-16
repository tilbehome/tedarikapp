<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Her migration dosyası bu arayüzü uygulayan bir sınıf örneği döndürür.
 * İleri-yönlüdür (forward-only) — geri alma runbook'taki DB yedeğiyle yapılır.
 */
interface Migration
{
    public function up(PDO $pdo): void;
}
