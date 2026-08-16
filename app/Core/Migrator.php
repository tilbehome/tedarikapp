<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

/**
 * İleri-yönlü (forward-only) migration koşucusu — docs/04, İE#3.
 * Geri alma yoktur; dönüş yolu runbook'taki deploy öncesi DB yedeğidir (docs/07 §5).
 * Her migration kendi transaction'ı içinde koşar; hata halinde kayıt düşülmez.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsDir,
    ) {
    }

    /**
     * Bekleyen migration'ları sırayla uygular; uygulananların adlarını döndürür.
     *
     * @return list<string>
     */
    public function run(): array
    {
        $this->ensureMigrationsTable();
        $applied = [];

        foreach ($this->pendingMigrations() as $name => $file) {
            $migration = require $file;
            if (!$migration instanceof Migration) {
                throw new RuntimeException(sprintf('Migration "%s", %s arayüzünü uygulayan bir sınıf döndürmeli.', $name, Migration::class));
            }

            $this->pdo->beginTransaction();

            try {
                $migration->up($this->pdo);
                $statement = $this->pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (?, ?)');
                $statement->execute([$name, date('Y-m-d H:i:s')]);
                // MySQL'de DDL örtük commit yapar ve transaction'ı kapatır; SQLite'ta (testler) açık kalır.
                if ($this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw new RuntimeException(sprintf('Migration "%s" başarısız: %s', $name, $e->getMessage()), 0, $e);
            }

            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * Sıralı, henüz uygulanmamış migration dosyaları: ad → tam yol.
     *
     * @return array<string, string>
     */
    private function pendingMigrations(): array
    {
        $files = glob($this->migrationsDir . '/[0-9][0-9][0-9][0-9]_*.php');
        if ($files === false) {
            throw new RuntimeException('Migration klasörü okunamadı: ' . $this->migrationsDir);
        }
        sort($files, SORT_STRING);

        $statement = $this->pdo->query('SELECT name FROM migrations');
        if ($statement === false) {
            throw new RuntimeException('migrations tablosu okunamadı.');
        }
        /** @var list<string> $alreadyApplied */
        $alreadyApplied = $statement->fetchAll(PDO::FETCH_COLUMN);

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $alreadyApplied, true)) {
                $pending[$name] = $file;
            }
        }

        return $pending;
    }

    private function ensureMigrationsTable(): void
    {
        // Hem MySQL hem SQLite'ta (testler) çalışan asgari ortak sözdizimi.
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';

        $this->pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS migrations (%s, name VARCHAR(190) NOT NULL UNIQUE, applied_at DATETIME NOT NULL)',
            $idColumn,
        ));
    }
}
