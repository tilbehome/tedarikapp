<?php

declare(strict_types=1);

// Test fikstürü: 0001'den sonra koşması beklenir (sıra doğrulaması).
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE fixture_b (id INTEGER PRIMARY KEY AUTOINCREMENT, fixture_a_id INTEGER NOT NULL)');
    }
};
