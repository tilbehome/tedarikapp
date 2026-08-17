<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * K37 §B5: transaction sarmalayıcısı — commit, rollback ve iç içe katılım.
 */
final class ConnectionTest extends TestCase
{
    private PDO $pdo;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);
    }

    private function rowCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) AS c FROM items')->fetch()['c'];
    }

    public function testBasariliOperasyonCommitEdilirVeSonucDoner(): void
    {
        $result = $this->connection->transaction(function (): string {
            $this->pdo->exec("INSERT INTO items (name) VALUES ('a')");

            return 'tamam';
        });

        self::assertSame('tamam', $result);
        self::assertSame(1, $this->rowCount());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testHataTumAdimlariGeriAlirVeAyniIstisnayiIletir(): void
    {
        try {
            $this->connection->transaction(function (): void {
                $this->pdo->exec("INSERT INTO items (name) VALUES ('a')");
                $this->pdo->exec("INSERT INTO items (name) VALUES ('b')");

                throw new RuntimeException('yapay hata');
            });
            self::fail('İstisna iletilmeliydi.');
        } catch (RuntimeException $e) {
            self::assertSame('yapay hata', $e->getMessage());
        }

        self::assertSame(0, $this->rowCount(), 'İki insert de geri alınmalı — yarım kayıt yok.');
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testIcIceCagriDistakiTransactionaKatilir(): void
    {
        try {
            $this->connection->transaction(function (): void {
                $this->pdo->exec("INSERT INTO items (name) VALUES ('dis')");

                // İç çağrı yeni transaction AÇMAZ (MySQL'de örtük commit olurdu);
                // dıştaki bütünlüğe katılır.
                $this->connection->transaction(function (): void {
                    $this->pdo->exec("INSERT INTO items (name) VALUES ('ic')");
                });

                throw new RuntimeException('dis katman patladi');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(0, $this->rowCount(), 'İç transaction da dışla birlikte geri alınmalı.');
    }
}
