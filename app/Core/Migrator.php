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
    /** Eşzamanlı koşumu engelleyen MySQL adlandırılmış kilidi (G7). */
    private const ADVISORY_LOCK_ADI = 'tedarikapp_migrate';
    private const ADVISORY_LOCK_BEKLEME = 15;

    /**
     * K49 BASELINE haritası: migration adı → defterde "uygulanmış" sayılabilmesi için
     * veritabanında GERÇEKTEN var olması gereken nesne(ler).
     *  • ['table' => 'ad'] — tablo var olmalı
     *  • ['column' => ['tablo', 'kolon']] — kolon var olmalı (ALTER migration'ları)
     *  • ['column_min_length' => ['tablo', 'kolon', 96]] — kolon EN AZ bu
     *    genişlikte olmalı. GENİŞLETME migration'ları için: varlık ölçütü
     *    onlarda yanlış yüklemdir (kolon zaten vardır, değişen genişliktir).
     * Haritada OLMAYAN migration baseline'lanamaz (güvenli varsayılan): atlanır ve
     * raporlanır — gelecekteki YENİ migration'lar böylece asla sahte "uygulanmış"
     * işaretlenemez, normal `run()` ile koşarlar.
     *
     * @var array<string, list<array{table?: string, column?: array{string, string}, column_min_length?: array{string, string, int}}>>
     */
    private const BASELINE_OBJECTS = [
        '0001_create_users' => [['table' => 'users']],
        '0002_create_recovery_codes' => [['table' => 'recovery_codes']],
        '0003_create_remember_tokens' => [['table' => 'remember_tokens']],
        '0004_create_settings' => [['table' => 'settings']],
        '0005_create_rate_history' => [['table' => 'rate_history']],
        '0006_create_categories' => [['table' => 'categories']],
        '0007_create_activity_log' => [['table' => 'activity_log']],
        '0008_create_lists' => [['table' => 'lists']],
        '0009_create_products' => [['table' => 'products']],
        '0010_create_product_images' => [['table' => 'product_images']],
        '0011_create_product_status_history' => [['table' => 'product_status_history']],
        '0012_create_exports' => [['table' => 'exports']],
        '0013_create_app_logs' => [['table' => 'app_logs']],
        '0014_add_products_raw_attributes' => [['column' => ['products', 'raw_attributes']]],
        '0015_add_products_country_fields' => [
            ['column' => ['products', 'country_of_origin']],
            ['column' => ['products', 'country_of_dispatch']],
        ],
        '0016_media_storage_columns' => [
            ['column' => ['product_images', 'storage_mode']],
            ['column' => ['product_images', 'source_url']],
        ],
        '0017_create_sessions' => [['table' => 'sessions']],
        '0018_add_products_main_image_source' => [['column' => ['products', 'main_image_source']]],
        '0019_create_inbox_items' => [['table' => 'inbox_items']],
        '0020_create_translation_cache' => [
            ['table' => 'translation_cache'],
            ['column' => ['products', 'price_target_try']],
        ],
        // İE#20 C2: ürün ≠ ilan ayrımı (platform kaydı + ilan tabloları).
        '0022_create_platforms' => [['table' => 'platforms']],
        '0023_create_listings' => [
            ['table' => 'listings'],
            ['table' => 'listing_price_tiers'],
        ],
        // İE#20 C3/C6/C7/C8: kuyruk, skor, arama ve kalite kapısı.
        '0024_create_jobs' => [['table' => 'jobs']],
        '0025_add_listings_skor' => [
            ['column' => ['listings', 'skor']],
            ['column' => ['translation_cache', 'guven']],
        ],
        // D11b: ürün adını kullanıcı mı yazdı? (çeviri tazelemesi onaylı adı ezmesin)
        '0031_urun_adi_elle' => [
            ['column' => ['products', 'name_elle']],
        ],
        // D12 (K87): ürünün KAYNAK DİLİ — kanonik üçlüde hangi dilin orijinal
        // olduğunu bu alan söyler; olmadan kaynak dile çeviri üretilebilir.
        '0032_urun_kaynak_dili' => [
            ['column' => ['products', 'source_lang']],
        ],
        // İE#22 A1: kur snapshot omurgası. DDL ve göç AYRI dosyadır (K23);
        // ikisi de haritada olmalı — 0032 dersi: haritada olmayan migration
        // K49 baseline akışında SESSİZCE atlanır.
        '0033_kur_snapshotlari' => [
            ['table' => 'rate_snapshots'],
        ],
        // Göç dosyasının "nesnesi" tablonun kendisidir: veri taşır, şema
        // değiştirmez. Baseline'da tablo varsa göç de uygulanmış sayılır —
        // dosya zaten idempotenttir, ikinci koşum satır çoğaltmaz.
        '0034_kur_snapshot_gocu' => [
            ['table' => 'rate_snapshots'],
        ],
        // V3-B A1: bildirim merkezi. Tek tablo, veri göçü YOK — bildirim
        // "şu an ne oluyor" demektir, geçmiş audit satırları buraya taşınmaz.
        '0035_bildirimler' => [
            ['table' => 'notifications'],
        ],
        // v1.2.1 D8: kolon GENİŞLETMESİ.
        //
        // VARLIK ÖLÇÜTÜ GENİŞLETME MIGRATION'LARI İÇİN YANLIŞ YÜKLEMDİR.
        // `['column' => [...]]` "kolon var mı?" diye sorar; ama bu migration
        // kolonu YARATMIYOR, GENİŞLETİYOR. Kolon `VARCHAR(12)` olarak zaten
        // vardı — varlığa bakan bir baseline, migration'ı "uygulanmış" sayıp
        // deftere işler ve genişletme HİÇ KOŞMAZ. Şifreli anahtar yazılmaya
        // çalışıldığında MySQL "Data too long" verir; kurulum sessizce bozuk
        // kalır ve sebebi migration defterinde "uygulandı" diye görünür.
        //
        // Ölçüt bu yüzden GENİŞLİKTİR: kolon en az 96 karakter mi?
        '0036_paylasim_anahtari_sifreli_alan' => [
            ['column_min_length' => ['lists', 'share_key_plain', 96]],
        ],
        // V3-C A1 (5 Eyl merge: D8 0036 kaldı, V3-C 0037-0040'a yeniden numaralandı):
        // firma döngüsü omurgası. DDL ve göç AYRI dosyalarda (K23);
        // her biri haritada olmalı — 0032 dersi: haritada olmayan migration
        // K49 baseline akışında SESSİZCE atlanır.
        // Dosyanın YARATTIĞI HER TABLO listelenir: baseline kontrolü "hepsi
        // varsa uygulanmış say" der. Yarısını listeleseydik, eksik yaratılmış
        // bir kurulumda 0037 "uygulanmış" damgalanır ve kalan tablolar bir
        // daha ASLA açılmazdı.
        '0037_firmalar_ve_turlar' => [
            ['table' => 'suppliers'],
            ['table' => 'supplier_contacts'],
            ['table' => 'rfq_snapshots'],
            ['table' => 'rfq_lines'],
            ['table' => 'supplier_rounds'],
            ['table' => 'quote_responses'],
            ['table' => 'quote_lines'],
            ['table' => 'quote_price_tiers'],
            ['table' => 'quote_alternatives'],
        ],
        '0038_paylasim_tablosu' => [
            ['table' => 'shares'],
            ['table' => 'share_dispatch_log'],
        ],
        // Göç dosyasının "nesnesi" hedef tablodur: veri taşır, şema
        // değiştirmez. Baseline'da tablo varsa göç de uygulanmış sayılır —
        // dosya idempotenttir (WHERE NOT EXISTS), ikinci koşum satır çoğaltmaz.
        '0039_paylasim_gocu' => [
            ['table' => 'shares'],
        ],
        '0040_belgeler_ve_sablonlar' => [
            ['table' => 'documents'],
            ['table' => 'document_links'],
            ['table' => 'list_templates'],
        ],
        // İE#21 B1: Keşif havuzu — küme anahtarı ve normalize arama alanı.
        '0030_kesif_havuzu' => [
            ['column' => ['listings', 'kume_anahtari']],
            ['column' => ['products', 'arama_normal']],
        ],
        // İE#21 C3: ivme bileşeni için toplam satış.
        '0029_ilan_satis_toplam' => [
            ['column' => ['listings', 'satis_toplam']],
        ],
        // İE#21 B11: kuyruk sertleştirme — kira token'ı, açık kira bitişi, hata sınıfı.
        '0028_kuyruk_sertlestirme' => [
            ['column' => ['jobs', 'kilit_token']],
            ['column' => ['jobs', 'kilit_bitis']],
        ],
        // İE#21 B12: sürümlü çeviri belleği — satırın hangi koşullarda üretildiği.
        '0027_ceviri_bellegi_surumu' => [
            ['column' => ['translation_cache', 'surum']],
        ],
        '0026_arama_ve_kalite' => [
            ['column' => ['products', 'arama_metni']],
            ['column' => ['products', 'hazir']],
            ['column' => ['products', 'surum']],
        ],
        // İE#18 G6 (K62): erişim anahtarı kolonları.
        '0021_add_lists_share_key' => [
            ['column' => ['lists', 'share_key_hash']],
            ['column' => ['lists', 'share_key_plain']],
            ['column' => ['lists', 'share_key_enabled']],
        ],
    ];

    /**
     * KAYITLI DEĞİŞİKLİK DEFTERİ (İE#19 G7).
     *
     * K23 kuralı nettir: uygulanmış bir migration dosyası DEĞİŞTİRİLMEZ; değişiklik
     * yeni dosyayla yapılır. Bu kuralın tek istisnası, dosyanın DAVRANIŞINI değil
     * DAYANIKLILIĞINI düzelten, PM onaylı ve BURAYA YAZILMIŞ değişikliklerdir:
     * 0016 iki ALTER'li olduğu için yarım kalabiliyordu ve yarım kalınca bir daha
     * asla tamamlanamıyordu (MySQL'de DDL örtük commit yapar). Dosyayı idempotent
     * yapmak, "aynı sonucu üretmeye devam eden" bir düzeltmedir.
     *
     * Buradaki eşleşme sessiz DEĞİLDİR: defterdeki checksum yenisiyle GÜNCELLENİR ve
     * `run()` dönüşünde raporlanır. Haritada olmayan her checksum farkı eskisi gibi
     * koşumu DURDURUR — koruma kalkmadı, kapısı belgelendi.
     *
     * @var array<string, list<string>> migration adı → kabul edilen ESKİ checksum'lar
     */
    private const KABUL_EDILEN_ESKI_CHECKSUMLAR = [
        // İE#19 G7 öncesi (iki ALTER'li, idempotent olmayan) 0016.
        '0016_media_storage_columns' => ['7e37b3dfe43eca4aac0625e439c944dd4de37a79f0b325f7471da8432a706b13'],
    ];

    /** @var list<string> bu koşumda checksum'u tazelenen migration'lar */
    private array $tazelenenChecksumlar = [];

    /** @param array<string, list<array{table?: string, column?: array{string, string}}>>|null $baselineObjects test amaçlı harita (null = gerçek harita) */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsDir,
        private readonly ?array $baselineObjects = null,
    ) {
    }

    /**
     * K49 BASELINE: defteri gerçeğe eşitler — HİÇBİR DDL ÇALIŞTIRMAZ.
     *
     * Canlı vaka: uygulama tabloları var ama `migrations` defteri boş ("Uygulanan 0 /
     * Bekleyen 17") — tablolar defter dışı bir yolla gelmiş. Bekleyen her migration
     * için hedef nesnenin gerçekten var olduğu şema sorgusuyla (MySQL:
     * information_schema, SQLite: pragma) doğrulanır; VARSA kayıt checksum'uyla
     * deftere işlenir, YOKSA atlanır ve nedeniyle raporlanır. İdempotent: deftere
     * işlenenler bir sonraki çağrıda "bekleyen" değildir.
     *
     * @return array{recorded: list<string>, skipped: list<array{name: string, reason: string}>}
     */
    public function baseline(): array
    {
        $this->ensureMigrationsTable();

        $files = $this->migrationFiles();
        $applied = $this->appliedChecksums();
        $map = $this->baselineObjects ?? self::BASELINE_OBJECTS;

        $recorded = [];
        $skipped = [];
        foreach ($files as $name => $file) {
            if (array_key_exists($name, $applied)) {
                continue;
            }

            if (!array_key_exists($name, $map)) {
                $skipped[] = ['name' => $name, 'reason' => 'Baseline haritasında yok — yeni migration, normal koşumla uygulanmalı.'];

                continue;
            }

            $missing = $this->missingObject($map[$name]);
            if ($missing !== null) {
                $skipped[] = ['name' => $name, 'reason' => 'Hedef nesne veritabanında yok: ' . $missing];

                continue;
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO migrations (name, checksum, execution_ms, applied_at) VALUES (?, ?, ?, ?)',
            );
            $statement->execute([$name, $this->checksumOf($file), 0, date('Y-m-d H:i:s')]);
            $recorded[] = $name;
        }

        return ['recorded' => $recorded, 'skipped' => $skipped];
    }

    /**
     * @param list<array{table?: string, column?: array{string, string}, column_min_length?: array{string, string, int}}> $objects
     *
     * @return string|null eksik nesnenin tanımı; hepsi varsa null
     */
    private function missingObject(array $objects): ?string
    {
        foreach ($objects as $object) {
            if (isset($object['table']) && !$this->tableExists($object['table'])) {
                return 'tablo ' . $object['table'];
            }
            if (isset($object['column']) && !$this->columnExists($object['column'][0], $object['column'][1])) {
                return 'kolon ' . $object['column'][0] . '.' . $object['column'][1];
            }
            if (isset($object['column_min_length'])) {
                [$tablo, $kolon, $enAz] = $object['column_min_length'];
                if (!$this->columnAtLeast($tablo, $kolon, $enAz)) {
                    return sprintf('kolon %s.%s en az %d karakter olmalı (dar kalmış)', $tablo, $kolon, $enAz);
                }
            }
        }

        return null;
    }

    private function tableExists(string $table): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
            $statement->execute([$table]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        );
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Kolon EN AZ bu genişlikte mi? (v1.2.1 — genişletme migration'ları için)
     *
     * SQLite'ta VARCHAR uzunluğu BAĞLAYICI DEĞİLDİR: sürücü onu bir kısıt
     * olarak uygulamaz. Bu yüzden orada kolon varsa yeterlidir — genişletme
     * migration'ı da SQLite'ta zaten no-op'tur; "dar" diye bir durum yok.
     *
     * MySQL/MariaDB'de ölçüt `CHARACTER_MAXIMUM_LENGTH`tir. Dar kalmışsa
     * migration BEKLEYEN sayılır ve normal koşumda uygulanır — yani kurulum
     * kendini onarır, sessizce bozuk kalmaz.
     */
    private function columnAtLeast(string $table, string $column, int $enAz): bool
    {
        if (!$this->columnExists($table, $column)) {
            return false;
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return true;
        }

        $statement = $this->pdo->prepare(
            'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $statement->execute([$table, $column]);
        $uzunluk = $statement->fetchColumn();

        // NULL uzunluk = metin olmayan tip (INT, DATETIME…). Genişlik ölçütü
        // orada anlamsızdır; kolonun varlığı yeterli sayılır.
        if ($uzunluk === null || $uzunluk === false) {
            return true;
        }

        return (int) $uzunluk >= $enAz;
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?');
            $statement->execute([$table, $column]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Bekleyen migration'ları sırayla uygular; uygulananların adlarını döndürür.
     *
     * @return list<string>
     */
    public function run(): array
    {
        // İE#19 G7 — DB ADVISORY LOCK: iki koşum aynı anda başlayamaz.
        //
        // Migrate üç yerden tetiklenir: sihirbaz, panel "Sistem durumu" düğmesi ve
        // `bin/migrate.php`. İkisi aynı anda koşarsa ikisi de aynı bekleyen listeyi
        // görür ve aynı DDL'i çalıştırmaya kalkar; MySQL'de DDL geri sarılmadığı için
        // sonuç yarım şema + "Duplicate column" hatasıdır. Kilit tüm koşumu kapsar.
        $kilitAlindi = $this->advisoryLockAl();

        try {
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
        } finally {
            if ($kilitAlindi) {
                $this->advisoryLockBirak();
            }
        }
    }

    /** Bu koşumda defteri tazelenen migration adları (raporlama için). */
    /** @return list<string> */
    public function tazelenenChecksumlar(): array
    {
        return $this->tazelenenChecksumlar;
    }

    /** MySQL adlandırılmış kilidi; SQLite'ta (testler) kavram yok — sessizce atlanır. */
    private function advisoryLockAl(): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return false;
        }

        $statement = $this->pdo->prepare('SELECT GET_LOCK(:ad, :saniye)');
        $statement->execute(['ad' => self::ADVISORY_LOCK_ADI, 'saniye' => self::ADVISORY_LOCK_BEKLEME]);
        $sonuc = $statement->fetchColumn();

        if ((int) $sonuc !== 1) {
            throw new RuntimeException(
                'Başka bir migration koşumu sürüyor (veritabanı kilidi alınamadı). '
                . 'Diğer koşumun bitmesini bekleyin; aynı anda iki güncelleme şemayı yarım bırakabilir.',
            );
        }

        return true;
    }

    private function advisoryLockBirak(): void
    {
        try {
            $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:ad)');
            $statement->execute(['ad' => self::ADVISORY_LOCK_ADI]);
        } catch (Throwable) {
            // Bağlantı kapandıysa MySQL kilidi kendiliğinden bırakır.
        }
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
            if (!hash_equals($recordedChecksum, $currentChecksum)
                && in_array($recordedChecksum, self::KABUL_EDILEN_ESKI_CHECKSUMLAR[$name] ?? [], true)) {
                // Kayıtlı, gerekçesi belgelenmiş dayanıklılık düzeltmesi: defter tazelenir.
                $guncelle = $this->pdo->prepare('UPDATE migrations SET checksum = ? WHERE name = ?');
                $guncelle->execute([$currentChecksum, $name]);
                $this->tazelenenChecksumlar[] = $name;

                continue;
            }
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
