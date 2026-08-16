<?php

declare(strict_types=1);

// Test fikstürü: transaction geri alma doğrulaması — tablo oluşturur, sonra patlar.
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE fixture_broken (id INTEGER PRIMARY KEY AUTOINCREMENT)');
        $pdo->exec('THIS IS NOT VALID SQL');
    }
};
