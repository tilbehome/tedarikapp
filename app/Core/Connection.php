<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use PDO;

/**
 * Tembel PDO taşıyıcısı: bağlantı ilk sorguda kurulur.
 * Uygulama önyüklemesi (rota tanımı, middleware kurulumu) veritabanına dokunmaz —
 * böylece DB kapalıyken bile sağlık ucu docs/10 hata zarfını üretebilir.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /** @param Closure(): PDO $factory */
    public function __construct(private readonly Closure $factory)
    {
    }

    /** @param callable(): PDO $factory */
    public static function fromCallable(callable $factory): self
    {
        return new self($factory(...));
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= ($this->factory)();
    }
}
