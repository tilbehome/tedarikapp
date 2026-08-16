<?php

declare(strict_types=1);

// Test fikstürü: SQLite uyumlu basit tablo (Migrator sıra/tekrar testleri için).
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE fixture_a (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }
};
