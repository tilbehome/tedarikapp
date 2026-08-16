<?php

declare(strict_types=1);

// Test fikstürü: başarılı ilk adım (başarısız senaryoda öncesinde koşar).
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE fixture_good (id INTEGER PRIMARY KEY AUTOINCREMENT)');
    }
};
