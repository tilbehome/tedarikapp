<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Connection;

/**
 * Toplu arşive geçiş (K47 — İE#9.6 Görev 2).
 *
 * Hotlink döneminden kalan uzak görselleri (products.main_image `http…` URL'leri ve
 * product_images `storage_mode=remote` satırları) MediaService üzerinden indirir →
 * yeniden kodlar → yerel yola çevirir.
 *
 * Tasarım kuralları:
 *  • İDEMPOTENT: başarıyla taşınan kayıt yerel yola döner ve aday sorgusuna bir daha
 *    girmez; araç istendiği kadar tekrar koşulabilir.
 *  • PARTİ PARTİ: tek çağrı en fazla `$limit` kayıt işler — PHP zaman aşımına takılmaz;
 *    kalan sayısı dönüşte bildirilir, çağıran (CLI döngüsü / panel düğmesi) devam eder.
 *  • BOZMAZ: indirme başarısız olursa (403/404/zaman aşımı/bozuk içerik) kayıt AYNEN
 *    kalır (remote), hata ürün + URL + hata sınıfıyla raporlanır; sonraki koşumda
 *    yeniden denenir. Referans sayımı değişmez: yalnız aynı satırın path/main_image
 *    değeri güncellenir, satır silinmez/eklenmez.
 */
final class MediaMigrator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MediaService $media,
    ) {
    }

    /**
     * Bir parti uzak görseli arşive taşır.
     *
     * @return array{
     *     mode: string,
     *     scanned: int,
     *     migrated: int,
     *     failed: list<array{kind: string, id: int, product_id: int, url: string, error: string}>,
     *     remaining: int
     * }
     */
    public function migrateBatch(int $limit = 20): array
    {
        if ($this->media->mode() !== MediaService::MODE_DOWNLOAD) {
            // Yazılamayan diske taşıma denenmez — çağıran kullanıcıya net mesaj gösterir.
            return ['mode' => MediaService::MODE_HOTLINK, 'scanned' => 0, 'migrated' => 0, 'failed' => [], 'remaining' => $this->remainingCount()];
        }

        $scanned = 0;
        $migrated = 0;
        $failed = [];

        foreach ($this->remoteMainImages($limit) as $row) {
            $scanned++;
            $result = $this->fetchLocal((string) $row['main_image']);
            if (isset($result['error'])) {
                $failed[] = ['kind' => 'main_image', 'id' => (int) $row['id'], 'product_id' => (int) $row['id'], 'url' => (string) $row['main_image'], 'error' => $result['error']];

                continue;
            }
            $statement = $this->connection->pdo()->prepare('UPDATE products SET main_image = :path WHERE id = :id');
            $statement->execute(['path' => $result['url'], 'id' => (int) $row['id']]);
            $migrated++;
        }

        $left = $limit - $scanned;
        if ($left > 0) {
            foreach ($this->remoteGalleryImages($left) as $row) {
                $scanned++;
                $source = (string) ($row['source_url'] ?? '') !== '' ? (string) $row['source_url'] : (string) $row['path'];
                $result = $this->fetchLocal($source);
                if (isset($result['error'])) {
                    $failed[] = ['kind' => 'product_image', 'id' => (int) $row['id'], 'product_id' => (int) $row['product_id'], 'url' => $source, 'error' => $result['error']];

                    continue;
                }
                $statement = $this->connection->pdo()->prepare(
                    "UPDATE product_images SET path = :path, storage_mode = 'local', source_url = :source WHERE id = :id",
                );
                $statement->execute(['path' => $result['path'], 'source' => $source, 'id' => (int) $row['id']]);
                $migrated++;
            }
        }

        return [
            'mode' => MediaService::MODE_DOWNLOAD,
            'scanned' => $scanned,
            'migrated' => $migrated,
            'failed' => $failed,
            'remaining' => $this->remainingCount(),
        ];
    }

    /** Taşınmayı bekleyen toplam uzak kayıt sayısı (main_image + galeri). */
    public function remainingCount(): int
    {
        $pdo = $this->connection->pdo();
        $main = $pdo->query("SELECT COUNT(*) FROM products WHERE main_image LIKE 'http%'");
        $gallery = $pdo->query("SELECT COUNT(*) FROM product_images WHERE storage_mode = 'remote'");

        return ($main === false ? 0 : (int) $main->fetchColumn())
            + ($gallery === false ? 0 : (int) $gallery->fetchColumn());
    }

    /**
     * @return array{path: string, url: string}|array{error: string}
     */
    private function fetchLocal(string $url): array
    {
        try {
            $stored = $this->media->store($url);
        } catch (MediaException $e) {
            // Sınıf adı hata raporunda ayırt edicidir (güvenlik reddi mi, ağ hatası mı).
            return ['error' => basename(str_replace('\\', '/', $e::class)) . ': ' . $e->getMessage()];
        }

        if ($stored['mode'] !== MediaService::MODE_DOWNLOAD || !is_string($stored['path'])) {
            return ['error' => 'Yazma başarısız: medya klasörü bu istekte yazılamadı.'];
        }

        return ['path' => $stored['path'], 'url' => $stored['url']];
    }

    /** @return list<array{id: int|string, main_image: string}> */
    private function remoteMainImages(int $limit): array
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT id, main_image FROM products WHERE main_image LIKE 'http%' ORDER BY id LIMIT " . max(1, $limit),
        );
        $statement->execute();

        /** @var list<array{id: int|string, main_image: string}> */
        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{id: int|string, product_id: int|string, path: string, source_url: string|null}> */
    private function remoteGalleryImages(int $limit): array
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT id, product_id, path, source_url FROM product_images WHERE storage_mode = 'remote' ORDER BY id LIMIT " . max(1, $limit),
        );
        $statement->execute();

        /** @var list<array{id: int|string, product_id: int|string, path: string, source_url: string|null}> */
        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
