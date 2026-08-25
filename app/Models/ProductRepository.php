<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * products + product_images + product_status_history erişimi (docs/04 §2).
 *
 * Soft delete: normal sorgular `deleted_at IS NULL` filtreler (K15).
 * Durum geçişleri `product_status_history`'ye yazılır (K25) — activity_log'a gömülmez.
 */
final class ProductRepository
{
    private const COLUMNS = 'id, list_id, sort_no, category_id, platform, external_id,
        name, name_original, name_elle, detail, url, vendor_name, vendor_url, sku_selection, sku_matrix,
        main_image, main_image_source, video_url, qty, price_yuan, price_ddp_usd, price_target_try,
        units_per_carton, tracking_no,
        raw_attributes, country_of_origin, country_of_dispatch,
        status, note, created_at, updated_at, deleted_at';

    /** Uçlardan yazılabilen alanlar (docs/10 §4). */
    public const WRITABLE = [
        'category_id', 'platform', 'external_id', 'name', 'name_original', 'detail', 'url',
        'vendor_name', 'vendor_url', 'sku_selection', 'sku_matrix', 'main_image', 'main_image_source', 'video_url',
        'qty', 'price_yuan', 'price_ddp_usd', 'units_per_carton', 'tracking_no', 'note',
        // İE#13 F5: hedef satış fiyatı — yalnız iç kopya çıktısını besler.
        'price_target_try',
        // İE#11 EK-3 (2): yakalamanın RAW bloğu + menşe (capture ile dolar; panelden de düzenlenebilir).
        'raw_attributes', 'country_of_origin', 'country_of_dispatch',
    ];

    /**
     * ÇIKTIYI ETKİLEYEN ALANLAR — değişince liste `revision`ı artar (K25 · K57).
     *
     * İE#20 C9 DÜZELTMESİ: bu liste beş alandan ibaretti (`qty`, `price_yuan`,
     * `price_ddp_usd`, `name`, `sort_no`). Oysa belge bunlardan FAZLASINI basıyor:
     * kategori, detay, ürün linki, görsel, durum, platform/ilan no, varyant, koli
     * içi adet, not, video ve iç kopyadaki hedef satış. Bu alanlardan birini
     * değiştirmek belgeyi DEĞİŞTİRİYOR ama revizyon harfi yerinde kalıyordu:
     *
     *   • firmaya "Rev C" diye ikinci kez farklı bir belge gidiyor,
     *   • "çıktı güncel değil" rozeti yanılıyor (K25),
     *   • K57'nin "harf içerikten türer" sözü kâğıt üstünde kalıyordu.
     *
     * Kural artık şudur: SNAPSHOT'A GİREN HER KAYNAK ALAN BU LİSTEDEDİR. Bunu
     * `RevizyonSozlesmesiTest` denetler — ExportSnapshot'a yeni bir alan eklenir
     * ve buraya eklenmezse test KIRILIR.
     *
     * Listede OLMAYANLAR bilinçlidir: `tracking_no` (kargo takip — belgede yok;
     * değişince firmaya "yeni revizyon" demek yanlış alarmdır) ve
     * `vendor_name`/`vendor_url` (şablon v2 satıcıyı BASMAZ).
     */
    public const REVISION_FIELDS = [
        // Sayısal/parasal
        'qty', 'price_yuan', 'price_ddp_usd', 'price_target_try', 'units_per_carton',
        // Metin ve kimlik
        'name', 'name_original', 'detail', 'note', 'category_id',
        'platform', 'external_id',
        // Medya ve bağlantı
        'url', 'main_image', 'video_url',
        // Varyant seçimi (belgede "Varyant" sütunu)
        'sku_selection', 'sku_matrix',
        // Sıra ve durum
        'sort_no', 'status',
        // Ham yakalama bloğu: belgeye DOĞRUDAN girmez ama DETAY, MOQ ve VARYANT
        // sütunları ondan TÜRETİLİR (ProductDetails). Sözleşme testi bunu yakaladı —
        // "ham veri belgede yok" varsayımı yanlıştı.
        'raw_attributes',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM products WHERE id = :id';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{status?: string, category_id?: int, q?: string, hazir?: bool} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function forList(int $listId, array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->suzgec($listId, $filters);

        $sql = 'SELECT ' . self::COLUMNS . " FROM products p WHERE {$where} ORDER BY p.sort_no, p.id";
        if ($limit !== null) {
            // İE#20 C7: SAYFALAMA. Sınırsız sorgu, liste büyüdükçe hem sunucuyu
            // hem tarayıcıyı yorar; 500 ürünlük bir listede tek istek megabaytlarca
            // JSON taşır. Sınır AÇIKÇA verilir — sessiz kırpma yapılmaz.
            $sql .= ' LIMIT ' . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset);
        }

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * Süzgece uyan toplam ürün sayısı — sayfalama üst bilgisi (C7).
     *
     * @param array{status?: string, category_id?: int, q?: string, hazir?: bool} $filters
     */
    public function countForList(int $listId, array $filters = []): int
    {
        [$where, $params] = $this->suzgec($listId, $filters);

        $statement = $this->connection->pdo()->prepare("SELECT COUNT(*) FROM products p WHERE {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Ortak süzgeç — liste ve sayım aynı koşulları kullanır.
     *
     * İkisi ayrı yazılsaydı zamanla ayrışır ve "37 kayıt" yazan bir sayfa 40 satır
     * gösterirdi; sayfalamanın en sinsi hatası budur.
     *
     * @param array{status?: string, category_id?: int, q?: string, hazir?: bool} $filters
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function suzgec(int $listId, array $filters): array
    {
        $where = 'p.list_id = :list_id AND p.deleted_at IS NULL';
        $params = ['list_id' => $listId];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where .= ' AND p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset($filters['category_id'])) {
            $where .= ' AND p.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }
        if (isset($filters['hazir'])) {
            $where .= ' AND p.hazir = :hazir';
            $params['hazir'] = $filters['hazir'] ? 1 : 0;
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            // İE#20 C7: arama artık TÜRETİLMİŞ `arama_metni` alanına bakar (TR ad +
            // Çince başlık + çeviriler + ilan no). Alan boşsa (henüz tazelenmemiş
            // eski kayıt) eski üç sütunlu arama YEDEK olarak çalışır — göç sırasında
            // arama kesintiye uğramasın.
            //
            // Yer tutucular AYRI adlandırılır: native prepare'de tekrar eden ad
            // HY093 verir (v0.11.3 canlı vakası).
            $where .= ' AND (p.arama_metni LIKE :q_arama'
                . ' OR (p.arama_metni IS NULL AND (p.name LIKE :q_ad OR p.name_original LIKE :q_orijinal'
                . ' OR p.detail LIKE :q_detay)))';
            $desen = '%' . $filters['q'] . '%';
            $params['q_arama'] = $desen;
            $params['q_ad'] = $desen;
            $params['q_orijinal'] = $desen;
            $params['q_detay'] = $desen;
        }

        return [$where, $params];
    }

    /** @return list<string> Listedeki silinmemiş ürünlerin durumları. */
    public function statusesForList(int $listId): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT status FROM products WHERE list_id = :list_id AND deleted_at IS NULL',
        );
        $statement->execute(['list_id' => $listId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(static fn (array $row): string => (string) $row['status'], $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(int $listId, array $data, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO products (list_id, sort_no, category_id, platform, external_id, name,
                name_original, detail, url, vendor_name, vendor_url, sku_selection, sku_matrix,
                main_image, main_image_source, video_url, qty, price_yuan, price_ddp_usd, price_target_try,
                units_per_carton, raw_attributes, country_of_origin, country_of_dispatch,
                tracking_no, status, note, created_at, updated_at)
             VALUES (:list_id,
                COALESCE(:sort_no, (SELECT sonraki FROM (SELECT COALESCE(MAX(sort_no), 0) + 1 AS sonraki
                    FROM products WHERE list_id = :list_id_sira) AS s)), :category_id, :platform, :external_id, :name,
                :name_original, :detail, :url, :vendor_name, :vendor_url, :sku_selection, :sku_matrix,
                :main_image, :main_image_source, :video_url, :qty, :price_yuan, :price_ddp_usd, :price_target_try,
                :units_per_carton, :raw_attributes, :country_of_origin, :country_of_dispatch,
                :tracking_no, :status, :note, :created_at, :updated_at)',
        );
        $statement->execute([
            'list_id' => $listId,
            // İE#20 C9 — SORT_NO YARIŞI: sıra ÖNCE okunup sonra yazılıyordu.
            // İki ürün aynı anda eklendiğinde ikisi de aynı MAX+1 değerini okuyup
            // AYNI sırayı alıyordu; belge sıralaması rastgeleleşiyor, "yeniden
            // sırala" ekranı iki ürünü üst üste gösteriyordu. Sıra artık AYNI
            // DEYİMDE üretilir (okuma ile yazma arasında boşluk kalmaz); açıkça
            // verilen sort_no (liste kopyalama) yine önceliklidir.
            'sort_no' => $data['sort_no'] ?? null,
            'list_id_sira' => $listId,
            'category_id' => $data['category_id'] ?? null,
            'platform' => $data['platform'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'name' => $data['name'],
            'name_original' => $data['name_original'] ?? null,
            'detail' => $data['detail'] ?? null,
            'url' => $data['url'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'vendor_url' => $data['vendor_url'] ?? null,
            'sku_selection' => $data['sku_selection'] ?? null,
            'sku_matrix' => $data['sku_matrix'] ?? null,
            'main_image' => $data['main_image'] ?? null,
            'main_image_source' => $data['main_image_source'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'qty' => $data['qty'],
            'price_yuan' => $data['price_yuan'] ?? '0',
            'price_ddp_usd' => $data['price_ddp_usd'] ?? '0',
            // İE#13 F5: hedef satış — boş bırakılabilir (NULL = hedef yok).
            'price_target_try' => $data['price_target_try'] ?? null,
            'units_per_carton' => $data['units_per_carton'] ?? null,
            'raw_attributes' => $data['raw_attributes'] ?? null,
            'country_of_origin' => $data['country_of_origin'] ?? null,
            'country_of_dispatch' => $data['country_of_dispatch'] ?? null,
            'tracking_no' => $data['tracking_no'] ?? null,
            'status' => $data['status'] ?? 'to_order',
            'note' => $data['note'] ?? null,
            'created_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields, DateTimeImmutable $now): void
    {
        $assignments = [];
        $params = ['id' => $id, 'updated_at' => Dates::toStorage($now)];

        foreach ($fields as $column => $value) {
            if (!in_array($column, [...self::WRITABLE, 'status', 'sort_no', 'list_id'], true)) {
                continue;
            }
            $assignments[] = sprintf('%s = :%s', $column, $column);
            $params[$column] = $value;
        }

        // D11b: ADI KULLANICI YAZDIYSA İŞARETLENİR. Bu işaret olmadan, çeviri
        // turu tazelendiğinde sunum katmanı kullanıcının düzelttiği adı da
        // "eski çeviri" sanıp üzerine yeni öneriyi basardı (K54 ihlali).
        // `name_elle` UÇTAN YAZILAMAZ (docs/10 §4 sözleşmesi genişletilmedi):
        // yalnız adın kullanıcı tarafından değiştirilmesiyle 1 olur. Bayrak tek
        // başına değişmediği için revizyon sözleşmesi de bozulmaz — ad değişimi
        // zaten revizyonu artırır.
        if (array_key_exists('name', $fields)) {
            $assignments[] = 'name_elle = :name_elle';
            $params['name_elle'] = 1;
        }
        if ($assignments === []) {
            return;
        }
        $assignments[] = 'updated_at = :updated_at';

        $statement = $this->connection->pdo()->prepare(
            sprintf('UPDATE products SET %s WHERE id = :id', implode(', ', $assignments)),
        );
        $statement->execute($params);
    }

    public function softDelete(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE products SET deleted_at = :deleted_at, updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL',
        );
        $statement->execute([
            'id' => $id,
            'deleted_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
        ]);
    }

    public function restore(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE products SET deleted_at = NULL, updated_at = :updated_at WHERE id = :id',
        );
        $statement->execute(['id' => $id, 'updated_at' => Dates::toStorage($now)]);
    }

    public function forceDelete(int $id): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function trashed(): array
    {
        $statement = $this->connection->pdo()->query(
            'SELECT p.id, p.list_id, p.name, p.deleted_at, l.name AS list_name, l.deleted_at AS list_deleted_at
             FROM products p
             INNER JOIN lists l ON l.id = p.list_id
             WHERE p.deleted_at IS NOT NULL
             ORDER BY p.deleted_at DESC, p.id DESC',
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /** @return list<int> */
    public function expiredTrashIds(DateTimeImmutable $threshold): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id FROM products WHERE deleted_at IS NOT NULL AND deleted_at <= :threshold',
        );
        $statement->execute(['threshold' => Dates::toStorage($threshold)]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Aynı platform + external_id çifti sistemde var mı (K25 — tekrar UYARISI, engel değil).
     *
     * @return array<string, mixed>|null
     */
    public function findDuplicate(string $platform, string $externalId, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT p.id, p.list_id, p.name, l.name AS list_name
                FROM products p INNER JOIN lists l ON l.id = p.list_id
                WHERE p.platform = :platform AND p.external_id = :external_id
                  AND p.deleted_at IS NULL AND l.deleted_at IS NULL';
        $params = ['platform' => $platform, 'external_id' => $externalId];

        if ($excludeId !== null) {
            $sql .= ' AND p.id <> :exclude';
            $params['exclude'] = $excludeId;
        }

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function maxSortNo(int $listId): int
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COALESCE(MAX(sort_no), 0) AS max_sort FROM products WHERE list_id = :list_id',
        );
        $statement->execute(['list_id' => $listId]);
        $row = $statement->fetch();

        return is_array($row) ? (int) $row['max_sort'] : 0;
    }

    /**
     * Sıra numaralarını yeniden yazar.
     *
     * @param list<int> $orderedIds
     */
    public function reorder(int $listId, array $orderedIds, DateTimeImmutable $now): int
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE products SET sort_no = :sort_no, updated_at = :updated_at
             WHERE id = :id AND list_id = :list_id AND deleted_at IS NULL',
        );

        $updated = 0;
        foreach ($orderedIds as $index => $productId) {
            $statement->execute([
                'sort_no' => $index + 1,
                'updated_at' => Dates::toStorage($now),
                'id' => $productId,
                'list_id' => $listId,
            ]);
            $updated += $statement->rowCount();
        }

        return $updated;
    }

    /**
     * LLM TURU BEKLEYEN ürünler (İE#20 C4 · D6 saha bulgusu, 25 Ağu 2026).
     *
     * ESKİ ÖLÇÜT YANLIŞTI: "önbellekte o başlık için HERHANGİ bir satır var mı".
     * Yakalama anında makine katmanı (MyMemory) TR'yi zaten dolduruyordu; ürün
     * bu yüzden "çevrilmiş" sayılıyor ve LLM turu ONA HİÇ UĞRAMIYORDU. Saha
     * kanıtı: TR 4/4 ama sağlayıcı `mymemory` ve kalite düşük ("无脚踏 → Bisiklet
     * Yok"; doğrusu "pedalsız"), EN 2/4 `llm:deepseek` ve kaliteli. K56'nın
     * "TR+EN tek LLM isteğinde birlikte" ilkesi fiilen bozuluyordu.
     *
     * YENİ ÖLÇÜT: hedef dillerden HERHANGİ BİRİ için LLM'den (ya da onaylı elle
     * düzeltmeden) gelmiş satır YOKSA ürün LLM turuna girer. Makine çevirisi
     * artık ne olduğu şeydir: LLM gelene kadarki GEÇİCİ DOLDURMA.
     *
     * @param list<string> $hedefDiller boşsa birincil dil (tr) varsayılır
     *
     * @return list<int> ürün kimlikleri
     */
    public function cevrilmemisler(?int $listeId = null, int $limit = 500, array $hedefDiller = ['tr']): array
    {
        $diller = array_values(array_filter(array_map('trim', $hedefDiller), static fn (string $d): bool => $d !== ''));
        if ($diller === []) {
            $diller = ['tr'];
        }

        $params = [];
        $dilKosullari = [];
        foreach ($diller as $sira => $dil) {
            $ad = 'dil' . $sira;
            $params[$ad] = $dil;
            // "Bu dil için KALICI bir çeviri var mı?" — llm:* üretilmiş, `elle`
            // ise kullanıcı onaylamıştır (K54). İkisi de yoksa tur gerekir.
            $dilKosullari[] = "NOT EXISTS (
                      SELECT 1 FROM translation_cache c
                      WHERE c.source_text = p.name_original
                        AND c.target_lang = :{$ad}
                        AND (c.provider LIKE 'llm:%' OR c.provider = '" . TranslationCacheRepository::ELLE_SAGLAYICI . "')
                  )";
        }

        $sql = "SELECT p.id FROM products p
                WHERE p.deleted_at IS NULL
                  AND p.name_original IS NOT NULL AND TRIM(p.name_original) <> ''
                  AND (" . implode(' OR ', $dilKosullari) . ')';
        if ($listeId !== null) {
            $sql .= ' AND p.list_id = :list_id';
            $params['list_id'] = $listeId;
        }
        $sql .= ' ORDER BY p.id LIMIT ' . max(1, min(2000, $limit));

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($params);

        /** @var list<int> */
        return array_map(static fn (mixed $v): int => (int) $v, $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * ARAMA METNİNİ tazeler (İE#20 C7) — türetilmiş alandır, elle yazılmaz.
     */
    public function aramaMetniniTazele(int $urunId, string $metin): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE products SET arama_metni = :metin WHERE id = :id',
        );
        $statement->execute(['metin' => $metin, 'id' => $urunId]);
    }

    /**
     * KAYIP YAZMA KORUMASI (İE#20 C9 — iyimser kilit).
     *
     * İki kullanıcı aynı ürünü açıp kaydettiğinde ikincisi birincinin
     * değişikliğini SESSİZCE eziyordu; kimse bir şey kaybettiğini fark etmiyordu.
     * Artık istemci okuduğu `surum` değerini geri gönderir; uyuşmuyorsa güncelleme
     * REDDEDİLİR ve kullanıcıya "bu kayıt siz düzenlerken değişti" denir.
     *
     * @return bool sürüm tuttuysa true (ve sürüm bir artırıldı)
     */
    public function surumuIlerlet(int $urunId, int $beklenenSurum): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE products SET surum = surum + 1 WHERE id = :id AND surum = :surum',
        );
        $statement->execute(['id' => $urunId, 'surum' => $beklenenSurum]);

        return $statement->rowCount() === 1;
    }

    /** Durum geçişini tarihçeye yazar (K25). */
    public function recordStatusChange(
        int $productId,
        ?string $fromStatus,
        string $toStatus,
        DateTimeImmutable $now,
        string $actorType = 'admin',
        ?int $actorId = null,
        ?string $requestId = null,
    ): void {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO product_status_history
                (product_id, from_status, to_status, actor_type, actor_id, changed_at, request_id)
             VALUES (:product_id, :from_status, :to_status, :actor_type, :actor_id, :changed_at, :request_id)',
        );
        $statement->execute([
            'product_id' => $productId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'changed_at' => Dates::toStorage($now),
            'request_id' => $requestId,
        ]);
    }

    // ─────────────── Medya referansları (K37 §C7) ───────────────

    /**
     * Ürünün işaret ettiği medya referansları: ana görsel + ek görseller.
     * Kalıcı silme ÖNCESİ toplanır; dosyalar DB kaydı gittikten sonra temizlenir.
     *
     * @return list<string>
     */
    public function mediaReferencesForProduct(int $productId): array
    {
        $references = [];

        $statement = $this->connection->pdo()->prepare('SELECT main_image FROM products WHERE id = :id');
        $statement->execute(['id' => $productId]);
        $row = $statement->fetch();
        if (is_array($row) && is_string($row['main_image']) && $row['main_image'] !== '') {
            $references[] = $row['main_image'];
        }

        $statement = $this->connection->pdo()->prepare('SELECT path FROM product_images WHERE product_id = :id');
        $statement->execute(['id' => $productId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $imageRow) {
            $references[] = (string) $imageRow['path'];
        }

        return $references;
    }

    /**
     * Listedeki TÜM ürünlerin (soft-delete dahil — CASCADE ile birlikte gidecekler)
     * medya referansları. Liste kalıcı silinmeden önce çağrılır.
     *
     * @return list<string>
     */
    public function mediaReferencesForList(int $listId): array
    {
        $references = [];

        $statement = $this->connection->pdo()->prepare(
            'SELECT main_image FROM products WHERE list_id = :list_id AND main_image IS NOT NULL',
        );
        $statement->execute(['list_id' => $listId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $row) {
            if (is_string($row['main_image']) && $row['main_image'] !== '') {
                $references[] = $row['main_image'];
            }
        }

        $statement = $this->connection->pdo()->prepare(
            'SELECT pi.path FROM product_images pi
             INNER JOIN products p ON p.id = pi.product_id
             WHERE p.list_id = :list_id',
        );
        $statement->execute(['list_id' => $listId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $row) {
            $references[] = (string) $row['path'];
        }

        return $references;
    }

    /**
     * Sistemdeki TÜM medya referansları (soft-delete dahil: çöp kutusundaki ürün
     * geri alınabilir, görseli de yerinde kalmalı). Yetim dosya GC'si bunu kullanır.
     *
     * @return list<string>
     */
    public function allMediaReferences(): array
    {
        $references = [];

        $statement = $this->connection->pdo()->query(
            "SELECT main_image FROM products WHERE main_image IS NOT NULL AND main_image <> ''",
        );
        if ($statement !== false) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll();
            foreach ($rows as $row) {
                $references[] = (string) $row['main_image'];
            }
        }

        $statement = $this->connection->pdo()->query('SELECT path FROM product_images');
        if ($statement !== false) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll();
            foreach ($rows as $row) {
                $references[] = (string) $row['path'];
            }
        }

        return $references;
    }

    /**
     * Bu DOSYA ADINA işaret eden kayıt sayısı (kopyalanan listeler aynı görseli
     * paylaşır — dosya ancak son referans da silinince temizlenebilir).
     * Ad sunucu üretimi 32 hanelik hex olduğundan LIKE eşleşmesi güvenlidir.
     */
    public function mediaFileReferenceCount(string $fileName): int
    {
        $like = '%' . $fileName;

        $statement = $this->connection->pdo()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM products WHERE main_image LIKE :a)
              + (SELECT COUNT(*) FROM product_images WHERE path LIKE :b) AS total',
        );
        $statement->execute(['a' => $like, 'b' => $like]);
        $row = $statement->fetch();

        return is_array($row) ? (int) $row['total'] : 0;
    }

    /** @return list<array{id: int, url: string, sort: int}> */
    /**
     * Yakalamadan gelen ek görselleri REMOTE galeri satırı olarak yazar (İE#11).
     * K47 arşive-taşıma hattı bunları sonra indirir (storage_mode=remote + source_url).
     *
     * @param list<string> $urls
     */
    public function addRemoteImages(int $productId, array $urls): void
    {
        $statement = $this->connection->pdo()->prepare(
            "INSERT INTO product_images (product_id, path, sort, storage_mode, source_url)
             VALUES (:product_id, :path, :sort, 'remote', :source_url)",
        );
        $sort = 0;
        foreach ($urls as $url) {
            if (!str_starts_with($url, 'https://') || mb_strlen($url) > 1000) {
                continue;
            }
            $statement->execute(['product_id' => $productId, 'path' => $url, 'sort' => ++$sort, 'source_url' => $url]);
        }
    }

    /**
     * GALERİYİ KOPYALAR (İE#20 C9) — liste kopyalamada eksik kalan parça.
     *
     * Liste kopyalanırken yalnız `products` satırları çoğaltılıyordu; galeri
     * görselleri KOPYAYA GELMİYORDU. Kullanıcı için sonuç: "aynı listeyi
     * kopyaladım ama ürünlerin fotoğrafları gitti". Ana görsel ürün satırında
     * olduğu için duruyor, ek görseller kayboluyordu — hata yarım görünüyor ve
     * geç fark ediliyordu.
     *
     * Dosya KOPYALANMAZ, KAYIT kopyalanır: iki ürün aynı dosyayı gösterir. Aynı
     * görselin iki kopyası diski boşuna doldururdu; medya temizliği referans sayar.
     */
    public function copyImages(int $kaynakUrunId, int $hedefUrunId): int
    {
        $kaynak = $this->connection->pdo()->prepare(
            'SELECT path, sort, storage_mode, source_url FROM product_images
             WHERE product_id = :product_id ORDER BY sort, id',
        );
        $kaynak->execute(['product_id' => $kaynakUrunId]);

        $ekle = $this->connection->pdo()->prepare(
            'INSERT INTO product_images (product_id, path, sort, storage_mode, source_url)
             VALUES (:product_id, :path, :sort, :storage_mode, :source_url)',
        );

        $adet = 0;
        foreach ($kaynak->fetchAll() ?: [] as $satir) {
            $ekle->execute([
                'product_id' => $hedefUrunId,
                'path' => $satir['path'],
                'sort' => $satir['sort'],
                'storage_mode' => $satir['storage_mode'] ?? 'local',
                'source_url' => $satir['source_url'] ?? null,
            ]);
            $adet++;
        }

        return $adet;
    }

    /** @return list<array<string, mixed>> */
    public function images(int $productId): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, path, sort FROM product_images WHERE product_id = :product_id ORDER BY sort, id',
        );
        $statement->execute(['product_id' => $productId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $images = [];
        foreach ($rows as $row) {
            $path = (string) $row['path'];
            // D11a: UZAK GÖRSEL BOZUK ADRESE ÇEVRİLİYORDU.
            //
            // Galeri satırları arşive taşınana kadar `path` bir TAM ADRESTİR
            // (https://cdn.alicdn.com/...). Buradaki '/' öneki onu
            // "/https://cdn.alicdn.com/..." hâline getiriyordu: tarayıcı bunu
            // kendi alanında arıyor, 404 alıyor ve çekmecede BOŞ KARE kalıyordu.
            // "5 görsel" yazan sayaç doğruydu, adresler bozuktu.
            $uzak = str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
            $images[] = [
                'id' => (int) $row['id'],
                'url' => $uzak ? $path : '/' . ltrim($path, '/'),
                'sort' => (int) $row['sort'],
                // Arayüz uzak görseli İŞARETLER: kaynak site hotlink'e izin
                // vermeyebilir (alicdn Referer ACL) ve kare boş kalabilir.
                // Sessiz boş kare yerine "arşive alınıyor" denir.
                'uzak' => $uzak,
            ];
        }

        return $images;
    }
}
