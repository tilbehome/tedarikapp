<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Connection;

/**
 * Medya bütünlük denetimi + onarım (İE#10 5d — K43 integrity'nin medya eşi).
 *
 * Canlı vaka: DB "arşivlendi" diyordu, panel yer tutucu gösteriyordu — DB↔disk
 * ayrışması ihtimaline karşı yerel /media kayıtları diskle karşılaştırılır; dosyası
 * KAYIP kayıtlar, saklanan orijinal adresten (`main_image_source` / `source_url`)
 * yeniden indirilerek onarılır. media-migrate hattının uzantısıdır: idempotent,
 * parti parti, kayıt bozmaz (kaynağı olmayan/indirilemeyen kayıt AYNEN kalır ve
 * raporlanır). Export ve paylaşım sayfası görselleri aynı /media kayıtlarından
 * okuduğu için bu denetim o yüzeyleri de kapsar.
 *
 * TERMİNAL LİSTE KURALI (İE#19 E8 · K37 §B4): `completed`/`cancelled` bir listenin
 * ürünleri onarım kapsamı DIŞINDADIR. Onarım "aynı görseli geri getiriyorum" gibi
 * masum görünür ama kapalı bir listenin BELGE GÖRÜNÜMÜNÜ değiştirir: dosya yolu
 * değişir, revizyon içerikten türediği için (K57) belge yeni revizyon üretir ve
 * firmaya gönderilmiş bir liste kendiliğinden farklılaşır. Donmuş kayıt donmuş
 * kalır; atlananlar raporlanır, sessizce yutulmaz.
 */
final class MediaIntegrity
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MediaService $media,
    ) {
    }

    /**
     * Bir parti denetle + onar.
     *
     * @return array{
     *     mode: string,
     *     checked: int,
     *     missing: int,
     *     repaired: int,
     *     skipped_terminal: int,
     *     failed: list<array{kind: string, id: int, product_id: int, reference: string, error: string}>,
     * }
     */
    public function repairBatch(int $limit = 20): array
    {
        $checked = 0;
        $missing = 0;
        $repaired = 0;
        $atlananTerminal = 0;
        $failed = [];
        $archiveOn = $this->media->mode() === MediaService::MODE_DOWNLOAD;

        foreach ($this->localMainImages() as $row) {
            $checked++;
            if ($this->fileExists((string) $row['main_image'])) {
                continue;
            }
            $missing++;
            if ($this->terminalListede((int) $row['id'])) {
                $atlananTerminal++;

                continue; // E8: kapalı listenin belgesi sessizce değişemez
            }
            if ($missing > $limit) {
                continue; // parti sınırı: kalan kayıplar sayılır ama bu turda onarılmaz
            }

            $source = (string) ($row['main_image_source'] ?? '');
            if ($source === '' || !$archiveOn) {
                $failed[] = $this->failure('main_image', (int) $row['id'], (int) $row['id'], (string) $row['main_image'], $source === ''
                    ? 'Orijinal adres kayıtlı değil — panelden görsel adresi yeniden girilebilir.'
                    : 'Medya klasörü yazılamıyor — arşiv modu kapalı.');

                continue;
            }

            $result = $this->download($source);
            if (isset($result['error'])) {
                $failed[] = $this->failure('main_image', (int) $row['id'], (int) $row['id'], $source, $result['error']);

                continue;
            }
            $statement = $this->connection->pdo()->prepare('UPDATE products SET main_image = :path WHERE id = :id');
            $statement->execute(['path' => $result['url'], 'id' => (int) $row['id']]);
            $repaired++;
        }

        foreach ($this->localGalleryImages() as $row) {
            $checked++;
            if ($this->fileExists((string) $row['path'])) {
                continue;
            }
            $missing++;
            if ($this->terminalListede((int) $row['product_id'])) {
                $atlananTerminal++;

                continue; // E8
            }
            if ($missing > $limit) {
                continue;
            }

            $source = (string) ($row['source_url'] ?? '');
            if ($source === '' || !$archiveOn) {
                $failed[] = $this->failure('product_image', (int) $row['id'], (int) $row['product_id'], (string) $row['path'], $source === ''
                    ? 'Orijinal adres kayıtlı değil.'
                    : 'Medya klasörü yazılamıyor — arşiv modu kapalı.');

                continue;
            }

            $result = $this->download($source);
            if (isset($result['error'])) {
                $failed[] = $this->failure('product_image', (int) $row['id'], (int) $row['product_id'], $source, $result['error']);

                continue;
            }
            $statement = $this->connection->pdo()->prepare('UPDATE product_images SET path = :path WHERE id = :id');
            $statement->execute(['path' => $result['path'], 'id' => (int) $row['id']]);
            $repaired++;
        }

        return [
            'mode' => $archiveOn ? MediaService::MODE_DOWNLOAD : MediaService::MODE_HOTLINK,
            'checked' => $checked,
            'missing' => $missing,
            'repaired' => $repaired,
            'skipped_terminal' => $atlananTerminal,
            'failed' => $failed,
        ];
    }

    /**
     * E8: ürünün listesi terminal mi? (tek sorgu — parti başına ürün sayısı azdır)
     */
    private function terminalListede(int $productId): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT l.status FROM products p JOIN lists l ON l.id = p.list_id WHERE p.id = :id',
        );
        $statement->execute(['id' => $productId]);
        $status = $statement->fetchColumn();

        return is_string($status) && in_array($status, ListMutationPolicy::TERMINAL_STATUSES, true);
    }

    /**
     * Tek ürünün ana görselini onarır (panel "yeniden dene" — İE#10 5d).
     *
     * Sıra: dosya zaten varsa dokunma; main_image uzaksa arşive al; yerel+kayıpsa
     * main_image_source'tan indir. Hiçbiri mümkün değilse hata mesajı döner.
     *
     * @return array{repaired: bool, main_image: ?string, error: ?string}
     */
    public function repairProduct(int $productId): array
    {
        if ($this->terminalListede($productId)) {
            return [
                'repaired' => false,
                'main_image' => null,
                'error' => 'Ürünün listesi tamamlanmış/iptal edilmiş; kapalı listenin görselleri değiştirilemez '
                    . '(devam etmek için listeyi kopyalayın).',
            ];
        }

        $statement = $this->connection->pdo()->prepare(
            'SELECT id, main_image, main_image_source FROM products WHERE id = :id',
        );
        $statement->execute(['id' => $productId]);
        $row = $statement->fetch();
        if (!is_array($row) || !is_string($row['main_image']) || $row['main_image'] === '') {
            return ['repaired' => false, 'main_image' => null, 'error' => 'Ürünün görseli yok.'];
        }

        $current = (string) $row['main_image'];
        if (str_starts_with($current, '/media/') && $this->fileExists($current)) {
            return ['repaired' => false, 'main_image' => $current, 'error' => null]; // zaten sağlam
        }

        $source = str_starts_with($current, 'http') ? $current : (string) ($row['main_image_source'] ?? '');
        if ($source === '') {
            return ['repaired' => false, 'main_image' => $current, 'error' => 'Orijinal adres kayıtlı değil — görsel adresini yeniden girin.'];
        }

        $result = $this->download($source);
        if (isset($result['error'])) {
            return ['repaired' => false, 'main_image' => $current, 'error' => $result['error']];
        }

        $update = $this->connection->pdo()->prepare(
            'UPDATE products SET main_image = :path, main_image_source = :source WHERE id = :id',
        );
        $update->execute(['path' => $result['url'], 'source' => $source, 'id' => $productId]);

        return ['repaired' => true, 'main_image' => $result['url'], 'error' => null];
    }

    /** Bekleyen (dosyası kayıp) kayıt sayısı — panel durumu için. */
    public function missingCount(): int
    {
        $count = 0;
        foreach ($this->localMainImages() as $row) {
            if (!$this->fileExists((string) $row['main_image'])) {
                $count++;
            }
        }
        foreach ($this->localGalleryImages() as $row) {
            if (!$this->fileExists((string) $row['path'])) {
                $count++;
            }
        }

        return $count;
    }

    /** @return array{path: string, url: string}|array{error: string} */
    private function download(string $url): array
    {
        try {
            $stored = $this->media->store($url);
        } catch (MediaException $e) {
            return ['error' => basename(str_replace('\\', '/', $e::class)) . ': ' . $e->getMessage()];
        }

        if ($stored['mode'] !== MediaService::MODE_DOWNLOAD || !is_string($stored['path'])) {
            return ['error' => 'Yazma başarısız: medya klasörü bu istekte yazılamadı.'];
        }

        return ['path' => $stored['path'], 'url' => $stored['url']];
    }

    private function fileExists(string $reference): bool
    {
        $name = $this->media->fileNameFor($reference);

        return $name !== null && is_file($this->media->directory() . '/' . $name);
    }

    /** @return list<array<string, mixed>> */
    private function localMainImages(): array
    {
        $statement = $this->connection->pdo()->query(
            "SELECT id, main_image, main_image_source FROM products WHERE main_image LIKE '/media/%'",
        );

        /** @var list<array<string, mixed>> */
        return $statement === false ? [] : ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }

    /** @return list<array<string, mixed>> */
    private function localGalleryImages(): array
    {
        $statement = $this->connection->pdo()->query(
            "SELECT id, product_id, path, source_url FROM product_images WHERE storage_mode = 'local'",
        );

        /** @var list<array<string, mixed>> */
        return $statement === false ? [] : ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array{kind: string, id: int, product_id: int, reference: string, error: string} */
    private function failure(string $kind, int $id, int $productId, string $reference, string $error): array
    {
        return ['kind' => $kind, 'id' => $id, 'product_id' => $productId, 'reference' => $reference, 'error' => $error];
    }
}
