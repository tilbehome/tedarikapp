<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Connection;
use App\Core\Migrator;
use App\Middleware\Csrf;
use App\Setup\SetupLock;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * K37 §E12 — GERÇEK MySQL entegrasyonu (üç canlı-koşum hatasının kanıtladığı boşluk).
 *
 * SQLite sözleşme testleri MySQL'e özgü DDL'i (DECIMAL kesinlik, VARCHAR uzunluk,
 * FK CASCADE, utf8mb4) göremez. Bu sınıf CI'daki MySQL 8.4 service container'ına
 * bağlanır: temiz migration → şema doğrulamaları → kritik HTTP akışları
 * (kurulum kilidi, auth, liste + ürün + para).
 *
 * Yerelde `TEDARIKAPP_TEST_DB_DSN` tanımlı değilse atlanır (K35 rejimi):
 *   TEDARIKAPP_TEST_DB_DSN="mysql:host=127.0.0.1;port=3306;dbname=tedarikapp_test;charset=utf8mb4"
 *   TEDARIKAPP_TEST_DB_USER=root  TEDARIKAPP_TEST_DB_PASS=root
 */
#[Group('mysql')]
final class MySqlIntegrationTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        $dsn = getenv('TEDARIKAPP_TEST_DB_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('MySQL entegrasyonu için TEDARIKAPP_TEST_DB_DSN gerekli (CI: MySQL 8.4 service).');
        }

        parent::setUp(); // sqlite kurar; hemen ardından MySQL ile DEĞİŞTİRİLİR

        $this->pdo = new \PDO(
            $dsn,
            (string) (getenv('TEDARIKAPP_TEST_DB_USER') ?: 'root'),
            (string) (getenv('TEDARIKAPP_TEST_DB_PASS') ?: ''),
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        $this->connection = Connection::fromCallable(fn (): \PDO => $this->pdo);

        $this->wipeDatabase();

        // Temiz migration: 0001'den sona kadar GERÇEK dosyalar, GERÇEK MySQL'de.
        $applied = (new Migrator($this->pdo, dirname(__DIR__, 2) . '/migrations'))->run();
        self::assertGreaterThanOrEqual(16, count($applied), 'Tüm migration dosyaları uygulanmalı.');
    }

    private function wipeDatabase(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS `' . (string) $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array<string, mixed> */
    private function column(string $table, string $column): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_DEFAULT, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
        );
        $statement->execute(['t' => $table, 'c' => $column]);
        $row = $statement->fetch();
        self::assertIsArray($row, sprintf('%s.%s kolonu bulunamadı.', $table, $column));

        return $row;
    }

    private function loginAsAdmin(): void
    {
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $totp = $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        self::assertSame(200, $totp->getStatusCode(), 'MySQL üzerinde 2FA girişi çalışmalı: ' . (string) $totp->getBody());
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
    }

    /** @param array<string, mixed>|null $body */
    private function write(string $method, string $path, ?array $body = null): ResponseInterface
    {
        return $this->call($method, $path, $body ?? [], [Csrf::HEADER => $this->csrf]);
    }

    // ─────────────── Şema doğrulamaları ───────────────

    public function testSemaKritikKolonlarDogru(): void
    {
        // Para kolonları ASLA float değil (K14): kurlar DECIMAL(12,4),
        // birim fiyatlar da DECIMAL(12,4) (K24 — TL karşılıkları hesaplanır, saklanmaz).
        $yuanRate = $this->column('lists', 'yuan_rate');
        self::assertSame('decimal', $yuanRate['DATA_TYPE']);
        self::assertSame(4, (int) $yuanRate['NUMERIC_SCALE']);

        $price = $this->column('products', 'price_yuan');
        self::assertSame('decimal', $price['DATA_TYPE']);
        self::assertSame(4, (int) $price['NUMERIC_SCALE']);

        // K37 §C9: main_image 1000'e genişletildi (uzun imzalı CDN adresleri).
        $mainImage = $this->column('products', 'main_image');
        self::assertSame(1000, (int) $mainImage['CHARACTER_MAXIMUM_LENGTH']);

        $storageMode = $this->column('product_images', 'storage_mode');
        self::assertSame('local', trim((string) $storageMode['COLUMN_DEFAULT'], "'"));
        $sourceUrl = $this->column('product_images', 'source_url');
        self::assertSame('YES', $sourceUrl['IS_NULLABLE']);
    }

    public function testListeSilinceUrunlerCascadeIleGider(): void
    {
        $this->loginAsAdmin();
        $list = $this->json($this->write('POST', '/api/lists', ['name' => 'Cascade Testi']))['data'];
        $this->write('POST', '/api/lists/' . $list['id'] . '/products', [
            'name' => 'Ürün', 'qty' => 1, 'price_yuan' => '1.00',
        ]);

        $this->pdo->prepare('DELETE FROM lists WHERE id = :id')->execute(['id' => $list['id']]);

        $count = $this->pdo->query('SELECT COUNT(*) AS c FROM products')->fetch();
        self::assertSame(0, (int) $count['c'], 'FK CASCADE gerçek MySQL şemasında çalışmalı.');
    }

    // ─────────────── Kritik HTTP akışları ───────────────

    public function testKurulumKilidiMysqlUzerindeYazilirVeOkunur(): void
    {
        $lock = new SetupLock($this->connection);

        self::assertSame(SetupLock::STATE_UNLOCKED, $lock->status());
        $lock->write($this->clock->now(), ['db_version' => '8.4', 'media_mode' => 'hotlink']);

        self::assertSame(SetupLock::STATE_LOCKED, $lock->status());
        $details = $lock->read();
        self::assertIsArray($details);
        self::assertSame('hotlink', $details['media_mode']);
    }

    public function testAuthListeUrunVeParaAkisiMysqlUzerindeCalisir(): void
    {
        $this->loginAsAdmin();

        // Liste + ürün + para (altın test: ¥9,00 × 7,04 = ₺63,36).
        $list = $this->json($this->write('POST', '/api/lists', ['name' => 'Eylül 2026 DDP', 'period' => 'EYLÜL 2026']))['data'];
        self::assertSame('7.0400', $list['yuan_rate']);

        $product = $this->json($this->write('POST', '/api/lists/' . $list['id'] . '/products', [
            'name' => 'Termos Yemek Kabı', 'name_original' => '保温饭盒', 'qty' => 24, 'price_yuan' => '9.00',
        ]))['data'];
        self::assertSame('63.36', $product['price_yuan_tl']);
        self::assertSame('1520.64', $product['line_total_yuan_tl']);
        self::assertSame('保温饭盒', $product['name_original'], 'utf8mb4: Çince başlık bozulmadan dönmeli.');

        // Kur PUT + rate_history tek transaction (K37 §B5) — MySQL üzerinde.
        $rates = $this->write('PUT', '/api/settings/rates', ['yuan_tl' => '7.2000']);
        self::assertSame(200, $rates->getStatusCode());
        $history = $this->json($this->call('GET', '/api/settings/rates/history?currency=CNY'))['data'];
        self::assertNotEmpty($history);
        self::assertSame('7.2000', $history[0]['rate']);

        // Durum makinesi (docs/04 §2b) gerçek şemada.
        $status = $this->write('PATCH', '/api/products/' . $product['id'] . '/status', ['status' => 'ordered']);
        self::assertSame(200, $status->getStatusCode());
        $skip = $this->write('PATCH', '/api/products/' . $product['id'] . '/status', ['status' => 'received']);
        self::assertSame(422, $skip->getStatusCode(), 'Durum atlama MySQL üzerinde de reddedilmeli.');
    }

    /**
     * CANLI HATA REGRESYONU (22 Ağu 2026) — arama uçları MySQL'de 500 veriyordu.
     *
     * `... name LIKE :q OR supplier_name LIKE :q` aynı yer tutucuyu iki kez
     * kullanıyordu. Üretim PDO'su native prepare kullanır (EMULATE_PREPARES=false)
     * ve MySQL bunu HY093 ile reddeder. SQLite süiti emülasyon açık koştuğu için
     * kusuru GÖREMİYORDU — bu yüzden regresyon TAM OLARAK BURADA durur: bu sınıf
     * üretimle aynı sürücü ayarıyla gerçek MySQL'e bağlanır.
     */
    public function testARAMA_UCLARI_MYSQL_UZERINDE_CALISIR(): void
    {
        $this->loginAsAdmin();

        $liste = $this->json($this->write('POST', '/api/lists', [
            'name' => 'Mutfak Grubu',
            'supplier_name' => 'Ningbo Kitchen Co.',
        ]))['data'];

        $this->write('POST', '/api/lists/' . $liste['id'] . '/products', [
            'name' => 'Termos Yemek Kabı',
            'name_original' => '保温饭盒',
            'qty' => 5,
            'price_yuan' => '12.00',
        ]);

        // 1) LİSTE ARAMASI — iki sütun (name + supplier_name) tek değerle taranır.
        $eslesen = $this->call('GET', '/api/lists?q=Mutfak');
        self::assertSame(200, $eslesen->getStatusCode(), 'Liste araması MySQL üzerinde 500 vermemeli.');
        self::assertCount(1, $this->json($eslesen)['data']);

        // Tedarikçi adından da bulunmalı (ikinci yer tutucu gerçekten bağlanıyor mu?).
        $tedarikciden = $this->call('GET', '/api/lists?q=Ningbo');
        self::assertSame(200, $tedarikciden->getStatusCode());
        self::assertCount(1, $this->json($tedarikciden)['data']);

        // 2) SONUÇSUZ ARAMA — canlıda hatayı tetikleyen tam senaryo.
        $bos = $this->call('GET', '/api/lists?q=MUTF-OLMAYAN-LISTE');
        self::assertSame(200, $bos->getStatusCode(), 'Eşleşme yoksa da 200 dönmeli.');
        self::assertSame([], $this->json($bos)['data']);

        // 3) ÜRÜN ARAMASI — üç sütun (ad, orijinal ad, detay) aynı desenle taranır.
        $urun = $this->call('GET', '/api/lists/' . $liste['id'] . '/products?q=Termos');
        self::assertSame(200, $urun->getStatusCode(), 'Ürün araması MySQL üzerinde 500 vermemeli.');
        self::assertCount(1, $this->json($urun)['data']);

        $cince = $this->call('GET', '/api/lists/' . $liste['id'] . '/products?q=保温');
        self::assertSame(200, $cince->getStatusCode());
        self::assertCount(1, $this->json($cince)['data'], 'Orijinal başlıktan da bulunmalı.');
    }

    /**
     * v1.2.1 D8 — BASELINE, DAR KOLONU "UYGULANMIŞ" SAYMAZ (PM merge şartı).
     *
     * VARLIK ÖLÇÜTÜ GENİŞLETME MIGRATION'LARI İÇİN YANLIŞ YÜKLEMDİR.
     * 0036 kolonu YARATMIYOR, GENİŞLETİYOR: kolon `VARCHAR(12)` olarak zaten
     * vardı. Varlığa bakan bir baseline migration'ı "uygulandı" diye deftere
     * işler, genişletme HİÇ KOŞMAZ ve şifreli anahtar yazılırken MySQL
     * "Data too long" verir — kurulum sessizce bozuk kalır, üstelik defterde
     * "uygulandı" yazar. Aramanın en zor olduğu yer tam da orasıdır.
     *
     * Bu vaka SQLITE'TA KURULAMAZ: orada VARCHAR uzunluğu bağlayıcı değildir
     * ve "dar kolon" diye bir durum yoktur. Bu yüzden gerçek MySQL'de sınanır.
     */
    public function testDARKOLONBASELINEUYGULANMISSAYILMAZ(): void
    {
        $migrator = new Migrator($this->pdo, dirname(__DIR__, 2) . '/migrations');

        // ÖN KOŞUL: setUp temiz migration koştu; kolon GENİŞ olmalı.
        self::assertGreaterThanOrEqual(96, $this->kolonGenisligi('lists', 'share_key_plain'));

        // Kolonu 0036 ÖNCESİ hâline daraltıp defter kaydını sil: "0036 hiç
        // koşmamış ama kolon var" durumunu birebir kur.
        $this->pdo->exec('ALTER TABLE lists MODIFY share_key_plain VARCHAR(12) NULL');
        $this->pdo->exec("DELETE FROM migrations WHERE name = '0036_paylasim_anahtari_sifreli_alan'");

        $sonuc = $migrator->baseline();

        self::assertNotContains(
            '0036_paylasim_anahtari_sifreli_alan',
            $sonuc['recorded'],
            'DAR kolon "uygulanmış" sayıldı — genişletme hiç koşmaz ve kurulum sessizce bozuk kalır.',
        );
        $atlananlar = array_column($sonuc['skipped'], 'name');
        self::assertContains('0036_paylasim_anahtari_sifreli_alan', $atlananlar);

        // İKİZ VAKA: genişletme KOŞTUKTAN sonra baseline onu uygulanmış sayar.
        $this->pdo->exec('ALTER TABLE lists MODIFY share_key_plain VARCHAR(96) NULL');
        $ikinci = (new Migrator($this->pdo, dirname(__DIR__, 2) . '/migrations'))->baseline();

        self::assertContains(
            '0036_paylasim_anahtari_sifreli_alan',
            $ikinci['recorded'],
            'Kolon genişletildikten sonra baseline onu uygulanmış SAYMALI.',
        );
    }

    /** Kolonun MySQL'deki gerçek karakter genişliği. */
    private function kolonGenisligi(string $tablo, string $kolon): int
    {
        $statement = $this->pdo->prepare(
            'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $statement->execute([$tablo, $kolon]);

        return (int) $statement->fetchColumn();
    }

    public function testSIFRELIANAHTARMYSQLEYAZILIR(): void
    {
        // Asıl arızanın uçtan uca kanıtı: 69 karakterlik şifreli değer gerçek
        // MySQL kolonuna YAZILABİLMELİ. Genişlik bekçisi zarfı ölçer; bu test
        // veritabanının onu kabul ettiğini gösterir.
        $sifreli = (new \App\Core\Encrypter(
            $this->config(),
            baglam: \App\Core\Encrypter::BAGLAM_PAYLASIM_ANAHTARI,
        ))->encrypt('A1B2C3');

        $this->pdo->exec(
            "INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at)
             VALUES ('Sifreli anahtar', '7', '41', '2026-09-01 10:00:00', '2026-09-01 10:00:00')",
        );
        $listeId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('UPDATE lists SET share_key_plain = :deger WHERE id = :id')
            ->execute(['deger' => $sifreli, 'id' => $listeId]);

        self::assertSame(
            $sifreli,
            (string) $this->pdo->query('SELECT share_key_plain FROM lists WHERE id = ' . $listeId)->fetchColumn(),
            'Şifreli değer MySQL kolonuna eksiksiz yazılmalı (kırpılmamalı).',
        );
    }
}
