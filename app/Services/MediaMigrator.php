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
     * İE#10 Blok 5b: kalıcı-başarısız kayıtlar parti başını TUTMAZ — çağıran, önceki
     * turlarda başarısız olan kimlikleri `$excludeProducts`/`$excludeImages` ile geçer;
     * seçim onları atlar ve sıra hiç denenmemişlere gelir. Başarısızlar bozulmaz,
     * dışlama yalnız BU koşum turunun belleğidir (kalıcı işaret yok — sonraki koşumda
     * yeniden denenirler).
     *
     * @param list<int> $excludeProducts bu turda atlanacak ürün kimlikleri (main_image)
     * @param list<int> $excludeImages bu turda atlanacak galeri kayıt kimlikleri
     *
     * @return array{
     *     mode: string,
     *     scanned: int,
     *     migrated: int,
     *     failed: list<array{kind: string, id: int, product_id: int, url: string, error: string}>,
     *     remaining: int
     * }
     */
    public function migrateBatch(int $limit = 20, array $excludeProducts = [], array $excludeImages = []): array
    {
        if ($this->media->mode() !== MediaService::MODE_DOWNLOAD) {
            // Yazılamayan diske taşıma denenmez — çağıran kullanıcıya net mesaj gösterir.
            return ['mode' => MediaService::MODE_HOTLINK, 'scanned' => 0, 'migrated' => 0, 'failed' => [], 'remaining' => $this->remainingCount()];
        }

        $scanned = 0;
        $migrated = 0;
        $failed = [];

        foreach ($this->remoteMainImages($limit, $excludeProducts) as $row) {
            $scanned++;
            $result = $this->fetchLocal((string) $row['main_image']);
            if (isset($result['error'])) {
                $failed[] = ['kind' => 'main_image', 'id' => (int) $row['id'], 'product_id' => (int) $row['id'], 'url' => (string) $row['main_image'], 'error' => $result['error']];

                continue;
            }
            if (!$this->anaGorseliYaz((int) $row['id'], (string) $row['main_image'], $result)) {
                // CAS tutmadı: satır bu arada değişmiş. Atlanır, sonraki turda
                // GÜNCEL url'den yeniden denenir.
                continue;
            }
            $migrated++;
        }

        $left = $limit - $scanned;
        if ($left > 0) {
            foreach ($this->remoteGalleryImages($left, $excludeImages) as $row) {
                $scanned++;
                $source = (string) ($row['source_url'] ?? '') !== '' ? (string) $row['source_url'] : (string) $row['path'];
                $result = $this->fetchLocal($source);
                if (isset($result['error'])) {
                    $failed[] = ['kind' => 'product_image', 'id' => (int) $row['id'], 'product_id' => (int) $row['product_id'], 'url' => $source, 'error' => $result['error']];

                    continue;
                }
                if (!$this->galeriGorseliYaz((int) $row['id'], (string) $row['path'], $source, $result)) {
                    continue;
                }
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

    /**
     * TEK ÜRÜNÜN medyasını arşive indirir (D11a, 25 Ağu 2026).
     *
     * SAHA BULGUSU: yakalamada yalnız ANA GÖRSEL indiriliyordu; galeri satırları
     * alicdn adresiyle `remote` kalıyor ve tarayıcı onları çizemiyordu (alicdn
     * Referer ACL). Çekmece "5 görsel" derken dördü boş kare görünüyordu.
     *
     * Parti taşıyıcısı (`migrateBatch`) tüm sistemi tarar; bu metot ise yeni
     * yakalanan TEK ürünü hedefler ve kuyruk işinden çağrılır — yakalamadan
     * dakikalar sonra galeri kendiliğinden yerele iner.
     *
     * @param  ?callable(): void $kontrol A2 — her dış adımın ÖNCESİNDE ve
     *         SONRASINDA çağrılır; kira kaybedilmişse istisna atar ve döngü durur
     * @return array{indirilen: int, basarisiz: list<array{id: int, url: string, hata: string}>}
     *
     * @param  ?\App\Services\Kuyruk\BellekButcesi $butce D6 — her indirmeden ÖNCE sorulur;
     *         dolmuşsa kalan görseller sonraki tura kalır ve iş ERTELENİR (hata değil).
     *         İnenler korunur: satırları zaten `local`e çevrilmiştir.
     *
     * @throws MedyaEksik eksik görsel kaldıysa (A4) — iş BİTMİŞ sayılmaz
     * @throws \App\Services\Kuyruk\IsErtelendi bellek bütçesi dolduysa (D6)
     */
    public function urununMedyasi(
        int $urunId,
        int $limit = 24,
        ?callable $kontrol = null,
        ?\App\Services\Kuyruk\BellekButcesi $butce = null,
    ): array {
        $indirilen = 0;
        $basarisiz = [];
        /**
         * Her hatanın kalıcı olup olmadığı — sonda TEK KARARA indirgenir.
         *
         * TEK BİR GEÇİCİ HATA BÜTÜNÜ GEÇİCİ YAPAR: hepsi kalıcı değilse
         * yeniden denemek hâlâ bir şey kurtarabilir.
         *
         * @var list<bool>
         */
        $kaliciBayraklari = [];

        if ($this->media->mode() !== MediaService::MODE_DOWNLOAD) {
            return ['indirilen' => 0, 'basarisiz' => []];
        }

        // 1) Ana görsel hâlâ uzaksa (yakalamada indirme başarısız olmuştur).
        $anaSorgu = $this->connection->pdo()->prepare(
            "SELECT id, main_image FROM products
             WHERE id = :id AND deleted_at IS NULL AND main_image LIKE 'http%'",
        );
        $anaSorgu->execute(['id' => $urunId]);
        $ana = $anaSorgu->fetch(\PDO::FETCH_ASSOC);
        if (is_array($ana)) {
            $this->butceyiDenetle($butce, $indirilen, 'ana görsel');
            // A2: DIŞ ADIMDAN ÖNCE. Kira bizde değilse indirmeye hiç başlamayız.
            if ($kontrol !== null) {
                $kontrol();
            }
            $sonuc = $this->fetchLocal((string) $ana['main_image']);
            // A2: DIŞ ADIMDAN SONRA. Uzun süren indirmenin ardından kira tazelenir.
            if ($kontrol !== null) {
                $kontrol();
            }
            if (isset($sonuc['error'])) {
                $basarisiz[] = ['id' => $urunId, 'url' => (string) $ana['main_image'], 'hata' => (string) $sonuc['error']];
                $kaliciBayraklari[] = (bool) $sonuc['kalici'];
            } else {
                // A5: toplu yolla AYNI CAS. Tek ürün yolu kuyruktan koşar ve
                // toplu göçle aynı anda aynı satıra uzanabilir.
                if ($this->anaGorseliYaz($urunId, (string) $ana['main_image'], $sonuc)) {
                    $indirilen++;
                }
            }
        }

        // 2) Galeri satırları.
        $galeri = $this->connection->pdo()->prepare(
            "SELECT id, path, source_url FROM product_images
             WHERE product_id = :id AND storage_mode = 'remote'
             ORDER BY sort, id LIMIT " . max(1, $limit),
        );
        $galeri->execute(['id' => $urunId]);
        foreach ($galeri->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $satir) {
            $kaynak = (string) ($satir['source_url'] ?? '') !== ''
                ? (string) $satir['source_url']
                : (string) $satir['path'];
            $this->butceyiDenetle($butce, $indirilen, 'galeri #' . (int) $satir['id']);
            if ($kontrol !== null) {
                $kontrol();
            }
            $sonuc = $this->fetchLocal($kaynak);
            if ($kontrol !== null) {
                $kontrol();
            }
            if (isset($sonuc['error'])) {
                // BOZMAZ: satır remote kalır, arayüz bunu işaretler, sonraki tur
                // yeniden dener. Sessiz boş kare artık yok.
                $basarisiz[] = ['id' => (int) $satir['id'], 'url' => $kaynak, 'hata' => (string) $sonuc['error']];
                $kaliciBayraklari[] = (bool) $sonuc['kalici'];

                continue;
            }

            if ($this->galeriGorseliYaz((int) $satir['id'], (string) $satir['path'], $kaynak, $sonuc)) {
                $indirilen++;
            }
        }

        if ($basarisiz !== []) {
            // A4: EKSİK KALAN GÖRSEL VARSA İŞ BİTMEZ. Dönüşü okumayan bir
            // çağıran kısmi başarıyı tam başarı sanardı — ve tam olarak öyle
            // oluyordu: iki görsel sonsuza kadar uzak kalıyordu.
            // Kalıcı sayılması için HEPSİNİN kalıcı olması gerekir.
            $kalici = !in_array(false, $kaliciBayraklari, true);

            throw new MedyaEksik($urunId, $indirilen, count($basarisiz), $kalici, $basarisiz);
        }

        return ['indirilen' => $indirilen, 'basarisiz' => $basarisiz];
    }

    /**
     * D6 — PAHALI ADIMDAN ÖNCE BÜTÇE SORULUR.
     *
     * Bütçe dolmuşsa istisna atılır ve döngü orada durur. O ana kadar inen
     * görsellerin satırları zaten `local`e çevrilmiştir; erteleme "kaldığı
     * yerden devam" sözünü bu sayede tutar. Bütçe verilmemişse sınır yoktur
     * (mevcut çağıranlar kırılmaz).
     *
     * @throws \App\Services\Kuyruk\IsErtelendi
     */
    private function butceyiDenetle(?\App\Services\Kuyruk\BellekButcesi $butce, int $indirilen, string $adim): void
    {
        if ($butce === null || !$butce->asildi()) {
            return;
        }

        throw new \App\Services\Kuyruk\IsErtelendi(sprintf(
            'bellek bütçesi doldu (%d/%d MB) — %s öncesinde durdu, %d görsel indi, kalanlar sonraki tura',
            $butce->kullanimMb(),
            $butce->butceMb(),
            $adim,
            $indirilen,
        ));
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
     * ANA GÖRSEL YAZIMI — SATIR CAS (v1.2.1 A5 · TDR-004).
     *
     * `WHERE id AND main_image = <indirmeye başlarken gördüğümüz url>`.
     * İndirme saniyeler sürer; o aralıkta ürün yeniden yakalanmış ve satırda
     * BAŞKA bir url belirmiş olabilir. Koşulsuz yazım, eski indirmeyi yeni
     * url'nin üstüne basardı ve sonuç sessizce yanlış olurdu: görsel yanlış
     * ama "yerel" görünür.
     *
     * CAS tutmazsa indirilen dosya SİLİNİR — öksüz dosya diskte birikirse
     * kimse fark etmez ve hangi dosyanın kime ait olduğu bilinemez hâle gelir.
     *
     * @param  array{path?: string, url?: string, error?: string} $result
     * @return bool yazım uygulandı mı
     */
    private function anaGorseliYaz(int $urunId, string $eskiUrl, array $result): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE products SET main_image = :path, main_image_source = :source
             WHERE id = :id AND main_image = :eski',
        );
        $statement->execute([
            'path' => $result['url'] ?? '',
            'source' => $eskiUrl,
            'id' => $urunId,
            'eski' => $eskiUrl,
        ]);

        if ($statement->rowCount() === 1) {
            return true;
        }

        $this->indirileniSil($result['path'] ?? null);

        return false;
    }

    /**
     * GALERİ GÖRSELİ YAZIMI — SATIR CAS (aynı gerekçe).
     *
     * Koşula `storage_mode = 'remote'` de girer: satır bu arada başka bir
     * koşum tarafından yerelleştirilmişse iki indirme aynı satıra yazar ve
     * biri diğerinin dosyasını öksüz bırakırdı.
     *
     * @param  array{path?: string, url?: string, error?: string} $result
     * @return bool yazım uygulandı mı
     */
    private function galeriGorseliYaz(int $gorselId, string $eskiPath, string $source, array $result): bool
    {
        $statement = $this->connection->pdo()->prepare(
            "UPDATE product_images SET path = :path, storage_mode = 'local', source_url = :source
             WHERE id = :id AND storage_mode = 'remote' AND path = :eski",
        );
        $statement->execute([
            'path' => $result['path'] ?? '',
            'source' => $source,
            'id' => $gorselId,
            'eski' => $eskiPath,
        ]);

        if ($statement->rowCount() === 1) {
            return true;
        }

        $this->indirileniSil($result['path'] ?? null);

        return false;
    }

    /** CAS tutmayınca indirilen dosyayı siler; öksüz dosya bırakmayız. */
    private function indirileniSil(?string $goreliYol): void
    {
        if ($goreliYol === null || $goreliYol === '') {
            return;
        }

        // `deleteFile()` yalnız medya klasöründeki, desene uyan dosya adlarını
        // siler — yol enjeksiyonuyla klasör dışına çıkılamaz.
        $this->media->deleteFile(basename($goreliYol));
    }

    /**
     * @return array{path: string, url: string}|array{error: string, kalici: bool}
     */
    private function fetchLocal(string $url): array
    {
        try {
            $stored = $this->media->store($url);
        } catch (MediaException $e) {
            // A8: KALICILIK TİPTEN OKUNUR, mesajdan değil. `MediaDeniedException`
            // güvenlik/beyaz liste reddidir ve tekrar denemek DÜZELTMEZ; diğer
            // MediaException'lar ağ/dosya hatasıdır ve geçicidir.
            // Sınıf adı hata raporunda ayırt edicidir (güvenlik reddi mi, ağ hatası mı).
            return [
                'error' => basename(str_replace('\\', '/', $e::class)) . ': ' . $e->getMessage(),
                'kalici' => $e instanceof MediaDeniedException,
            ];
        }

        if ($stored['mode'] !== MediaService::MODE_DOWNLOAD || !is_string($stored['path'])) {
            // Yazma arızası GEÇİCİDİR: klasör izni düzelebilir, disk boşalabilir.
            return ['error' => 'Yazma başarısız: medya klasörü bu istekte yazılamadı.', 'kalici' => false];
        }

        return ['path' => (string) $stored['path'], 'url' => (string) $stored['url']];
    }

    /**
     * @param list<int> $exclude
     *
     * @return list<array{id: int|string, main_image: string}>
     */
    private function remoteMainImages(int $limit, array $exclude = []): array
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT id, main_image FROM products WHERE main_image LIKE 'http%'"
            . $this->exclusion($exclude)
            . ' ORDER BY id LIMIT ' . max(1, $limit),
        );
        $statement->execute();

        /** @var list<array{id: int|string, main_image: string}> */
        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<int> $exclude
     *
     * @return list<array{id: int|string, product_id: int|string, path: string, source_url: string|null}>
     */
    private function remoteGalleryImages(int $limit, array $exclude = []): array
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT id, product_id, path, source_url FROM product_images WHERE storage_mode = 'remote'"
            . $this->exclusion($exclude)
            . ' ORDER BY id LIMIT ' . max(1, $limit),
        );
        $statement->execute();

        /** @var list<array{id: int|string, product_id: int|string, path: string, source_url: string|null}> */
        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dışlama parçası — kimlikler tam sayıya zorlanır (SQL enjeksiyonu yapısal olarak imkânsız).
     *
     * @param list<int> $ids
     */
    private function exclusion(array $ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));

        return $ids === [] ? '' : ' AND id NOT IN (' . implode(',', $ids) . ')';
    }
}
