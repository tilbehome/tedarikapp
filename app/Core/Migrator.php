<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

/**
 * İleri-yönlü (forward-only) migration koşucusu — docs/04, İE#3 · K23 ile sertleştirildi.
 *
 * Geri alma yoktur; dönüş yolu runbook'taki deploy öncesi DB yedeğidir (docs/07 §5).
 *
 * K23 kuralları:
 *  • 1 migration = 1 DDL değişikliği. MySQL'de DDL örtük commit yapar; çok-DDL'li bir dosya
 *    yarıda kalırsa transaction geri almaz ve tekrar koşumda "tablo zaten var" ile patlar.
 *  • Uygulanan her dosyanın sha256'sı ve süresi kaydedilir. Uygulanmış bir dosya sonradan
 *    değiştirilirse koşum anlaşılır bir hatayla durur — sessiz şema kayması olmaz.
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

        $files = $this->migrationFiles();
        $applied = $this->appliedChecksums();
        $this->assertAppliedFilesUnchanged($files, $applied);

        $justApplied = [];
        foreach ($files as $name => $file) {
            if (array_key_exists($name, $applied)) {
                continue;
            }
            $this->apply($name, $file);
            $justApplied[] = $name;
        }

        return $justApplied;
    }

    /**
     * Henüz uygulanmamış migration adları — koşmadan.
     * `GET /api/system/status` bunu "güncelleme gerekiyor mu" sorusunu yanıtlamak için kullanır.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->appliedChecksums();
        $pending = [];
        foreach ($this->migrationFiles() as $name => $file) {
            if (!array_key_exists($name, $applied)) {
                $pending[] = $name;
            }
        }

        return $pending;
    }

    private function apply(string $name, string $file): void
    {
        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new RuntimeException(sprintf(
                'Migration "%s", %s arayüzünü uygulayan bir sınıf döndürmeli.',
                $name,
                Migration::class,
            ));
        }

        $checksum = $this->checksumOf($file);
        $startedAt = microtime(true);

        $this->pdo->beginTransaction();

        try {
            $migration->up($this->pdo);

            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $statement = $this->pdo->prepare(
                'INSERT INTO migrations (name, checksum, execution_ms, applied_at) VALUES (?, ?, ?, ?)',
            );
            $statement->execute([$name, $checksum, $elapsedMs, date('Y-m-d H:i:s')]);

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
    }

    /**
     * Uygulanmış bir dosya değiştirilmişse koşumu durdurur (K23).
     *
     * @param array<string, string> $files    ad → tam yol
     * @param array<string, string> $applied  ad → kayıtlı checksum
     */
    private function assertAppliedFilesUnchanged(array $files, array $applied): void
    {
        foreach ($applied as $name => $recordedChecksum) {
            if (!isset($files[$name])) {
                throw new RuntimeException(sprintf(
                    'Uygulanmış migration dosyası bulunamadı: "%s". Dosya silinmiş veya yeniden adlandırılmış olabilir; '
                    . 'migration dosyaları uygulandıktan sonra değiştirilmez.',
                    $name,
                ));
            }

            $currentChecksum = $this->checksumOf($files[$name]);
            if (!hash_equals($recordedChecksum, $currentChecksum)) {
                throw new RuntimeException(sprintf(
                    'Uygulanmış migration "%s" değiştirilmiş (checksum uyuşmuyor: kayıtlı %s, güncel %s). '
                    . 'Uygulanmış migration düzenlenmez — değişikliği yeni bir migration dosyasıyla yapın.',
                    $name,
                    substr($recordedChecksum, 0, 12),
                    substr($currentChecksum, 0, 12),
                ));
            }
        }
    }

    /**
     * Sıralı migration dosyaları: ad → tam yol.
     *
     * @return array<string, string>
     */
    private function migrationFiles(): array
    {
        $files = glob($this->migrationsDir . '/[0-9][0-9][0-9][0-9]_*.php');
        if ($files === false) {
            throw new RuntimeException('Migration klasörü okunamadı: ' . $this->migrationsDir);
        }
        sort($files, SORT_STRING);

        $map = [];
        foreach ($files as $file) {
            $map[basename($file, '.php')] = $file;
        }

        return $map;
    }

    /**
     * Uygulanmış migration'lar: ad → checksum.
     *
     * @return array<string, string>
     */
    private function appliedChecksums(): array
    {
        $statement = $this->pdo->query('SELECT name, checksum FROM migrations');
        if ($statement === false) {
            throw new RuntimeException('migrations tablosu okunamadı.');
        }

        /** @var array<string, string> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_KEY_PAIR);

        return $rows;
    }

    private function checksumOf(string $file): string
    {
        $checksum = hash_file('sha256', $file);
        if ($checksum === false) {
            throw new RuntimeException('Migration dosyasının özeti hesaplanamadı: ' . $file);
        }

        return $checksum;
    }

    private function ensureMigrationsTable(): void
    {
        // Hem MySQL hem SQLite'ta (testler) çalışan asgari ortak sözdizimi.
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';

        $this->pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS migrations (
                %s,
                name VARCHAR(190) NOT NULL UNIQUE,
                checksum CHAR(64) NOT NULL,
                execution_ms INT UNSIGNED NOT NULL,
                applied_at DATETIME NOT NULL
            )',
            $idColumn,
        ));

        $this->assertMigrationsTableIsCurrent();
    }

    /**
     * K23 öncesi şemayla oluşmuş bir `migrations` tablosu varsa `CREATE TABLE IF NOT EXISTS`
     * onu güncellemez; sorun ilk INSERT'te anlaşılmaz bir SQL hatası olarak patlar.
     * Burada erken ve anlaşılır bir hata veriyoruz.
     */
    private function assertMigrationsTableIsCurrent(): void
    {
        $statement = $this->pdo->query('SELECT * FROM migrations LIMIT 0');
        if ($statement === false) {
            throw new RuntimeException('migrations tablosu okunamadı.');
        }

        $columns = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $meta = $statement->getColumnMeta($i);
            if (is_array($meta)) {
                $columns[] = $meta['name'];
            }
        }

        $missing = array_diff(['name', 'checksum', 'execution_ms', 'applied_at'], $columns);
        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'migrations tablosu eski şemada (eksik kolon: %s). K23 ile checksum/execution_ms eklendi. '
                . 'Bu şema henüz üretimde koşmadığı için çözüm veritabanını sıfırlamaktır: '
                . 'DROP TABLE migrations; (ve varsa oluşmuş tablolar) sonra "php bin/migrate.php".',
                implode(', ', $missing),
            ));
        }
    }
}
