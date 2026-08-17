<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use PDO;
use Throwable;

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

    /**
     * Çok adımlı yazma akışlarının tek transaction sarmalayıcısı (K37 §B5).
     *
     * Ortadaki bir adım patlarsa TÜM yazmalar geri alınır — yarım kayıt
     * (tarihçesiz ürün, rate_history'siz kur, revision'ı artmamış liste) kalmaz.
     * İç içe çağrıda dıştaki transaction'a KATILIR: MySQL'de iç içe
     * beginTransaction örtük commit üretirdi, bu sessiz veri kaybı demekti.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     *
     * @throws Throwable operasyonun fırlattığı hata, rollback SONRASI aynen iletilir
     */
    public function transaction(callable $operation): mixed
    {
        $pdo = $this->pdo();

        if ($pdo->inTransaction()) {
            return $operation();
        }

        $pdo->beginTransaction();

        try {
            $result = $operation();
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            try {
                $pdo->rollBack();
            } catch (Throwable) {
                // Bağlantı kopmuş/transaction zaten düşmüş olabilir; asıl hata aşağıda iletilir.
            }

            throw $exception;
        }
    }
}
