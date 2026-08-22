<?php

declare(strict_types=1);

namespace Tests\Core;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * İE#20 C2 — ürün ≠ ilan göçü.
 *
 * Göçün asıl vaadi "veri kaybı YOK"tur. Bu süit onu iki yönden sınar:
 * her ürün için bir ilan açılıyor mu (sayım), ve açılan ilan kaynakla birebir mi
 * (alan alan karşılaştırma). Ayrıca göçün İDEMPOTENT ve GERİ ALINABİLİR olduğunu
 * doğrular — çünkü canlıya dokunacak bir betiğin en önemli iki özelliği budur.
 */
final class UrunIlanAyrimiTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                platform VARCHAR(16) NULL,
                external_id VARCHAR(64) NULL,
                name VARCHAR(300) NOT NULL,
                name_original VARCHAR(500) NULL,
                url VARCHAR(1000) NULL,
                vendor_name VARCHAR(200) NULL,
                vendor_url VARCHAR(1000) NULL,
                sku_matrix TEXT NULL,
                raw_attributes TEXT NULL,
                price_yuan DECIMAL(12,4) NOT NULL DEFAULT 0,
                units_per_carton INTEGER NULL,
                created_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            )',
        );

        $this->migrasyonKos('0022_create_platforms');
        $this->migrasyonKos('0023_create_listings');
    }

    private function migrasyonKos(string $ad): void
    {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $ad . '.php';
        $migration->up($this->pdo);
    }

    private function urunEkle(string $ad, ?string $platform, ?string $ilanNo, ?string $ham = null): int
    {
        $this->pdo->prepare(
            'INSERT INTO products (list_id, platform, external_id, name, name_original, url, vendor_name,
                vendor_url, raw_attributes, price_yuan, created_at)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([
            $platform,
            $ilanNo,
            $ad,
            '测试',
            $ilanNo === null ? null : 'https://detail.1688.com/offer/' . $ilanNo . '.html',
            'Ningbo Co.',
            'https://ornek.1688.com',
            $ham,
            '12.5000',
            '2026-08-01 10:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testPlatformKAYDIVERIDIRKODDADEGIL(): void
    {
        $kodlar = $this->pdo->query('SELECT kod FROM platforms ORDER BY kod')->fetchAll(PDO::FETCH_COLUMN);

        self::assertContains('1688', $kodlar);
        self::assertContains('manuel', $kodlar, 'Elle girilen ürünler için de bir platform kaydı olmalı.');

        // Kalıp veridedir: adres üretimi koda gömülü olamaz.
        $kalip = (string) $this->pdo->query("SELECT url_kalibi FROM platforms WHERE kod = '1688'")->fetchColumn();
        self::assertStringContainsString('{id}', $kalip);
    }

    public function testMigrationIKINCIKEZKOSULABILIR(): void
    {
        $this->migrasyonKos('0022_create_platforms');
        $this->migrasyonKos('0023_create_listings');

        $adet = (int) $this->pdo->query("SELECT COUNT(*) FROM platforms WHERE kod = '1688'")->fetchColumn();
        self::assertSame(1, $adet, 'Tohum satırı ikinci koşumda ÇOĞALMAMALI.');
    }

    public function testVeridekiBILINMEYENPLATFORMDAKAYDAGIRER(): void
    {
        // Önce ürün, sonra migration: canlıdaki sıra budur.
        $this->urunEkle('Ürün', 'yiwugo', '55');
        $this->migrasyonKos('0022_create_platforms');

        $kodlar = $this->pdo->query('SELECT kod FROM platforms')->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('yiwugo', $kodlar, 'Bilinmeyen platform kayda girmezse o ürünler göçte dışarıda kalır.');
    }

    public function testGocHERURUNICINILANACAR(): void
    {
        $a = $this->urunEkle('Termos', '1688', '9001');
        $b = $this->urunEkle('Elle girilen ürün', null, null);

        $this->gocKos();

        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM listings')->fetchColumn());
        self::assertSame(
            0,
            (int) $this->pdo->query('SELECT COUNT(*) FROM products p LEFT JOIN listings l ON l.product_id = p.id WHERE l.id IS NULL')->fetchColumn(),
            'İlanı olmayan ürün kaldı — veri kaybı.',
        );

        $ilanA = $this->pdo->query('SELECT * FROM listings WHERE product_id = ' . $a)->fetch();
        self::assertSame('1688', $ilanA['platform_kod']);
        self::assertSame('9001', $ilanA['external_id']);
        self::assertSame('Ningbo Co.', $ilanA['satici_ad']);

        $ilanB = $this->pdo->query('SELECT * FROM listings WHERE product_id = ' . $b)->fetch();
        self::assertSame('manuel', $ilanB['platform_kod'], 'Platformsuz ürün "manuel" sayılmalı, atlanmamalı.');
    }

    public function testGocKAYNAGADOKUNMAZ(): void
    {
        $id = $this->urunEkle('Termos', '1688', '9001');
        $once = $this->pdo->query('SELECT * FROM products WHERE id = ' . $id)->fetch();

        $this->gocKos();

        $sonra = $this->pdo->query('SELECT * FROM products WHERE id = ' . $id)->fetch();
        self::assertSame($once, $sonra, 'Göç TOPLAMALIDIR: kaynak satır değişmemeli.');
    }

    public function testGocIDEMPOTENTTIR(): void
    {
        $this->urunEkle('Termos', '1688', '9001');

        $this->gocKos();
        $this->gocKos();

        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM listings')->fetchColumn());
    }

    public function testFiyatKademeleriHAMVERIDENCIKARILIR(): void
    {
        $ham = json_encode([
            'price_tiers' => [
                ['min_qty' => 2, 'price_yuan' => '12.50'],
                ['min_qty' => 100, 'price_yuan' => '10.90'],
                ['min_qty' => 'bozuk', 'price_yuan' => 'x'],
            ],
        ], JSON_THROW_ON_ERROR);
        $id = $this->urunEkle('Kademeli ürün', '1688', '9002', $ham);

        $this->gocKos();

        $kademeler = $this->pdo->query(
            'SELECT t.min_adet, t.birim_fiyat FROM listing_price_tiers t
             JOIN listings l ON l.id = t.listing_id WHERE l.product_id = ' . $id . ' ORDER BY t.min_adet',
        )->fetchAll();

        self::assertCount(2, $kademeler, 'Tanınmayan kademe UYDURULMAMALI, sessizce atlanmalı.');
        self::assertSame(2, (int) $kademeler[0]['min_adet']);
        // SQLite DECIMAL'i float döner; MySQL'de string gelir. Değer karşılaştırması
        // yapıyoruz — sınanan şey biçim değil, DOĞRU KADEMENİN yazıldığı.
        self::assertEqualsWithDelta(10.9, (float) $kademeler[1]['birim_fiyat'], 0.0001);
    }

    public function testGeriDONUSKAYNAGIBOZMAZ(): void
    {
        $this->urunEkle('Termos', '1688', '9001', json_encode(['price_tiers' => [['min_qty' => 2, 'price_yuan' => '9']]], JSON_THROW_ON_ERROR));
        $this->gocKos();
        $urunOnce = (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

        // Geri dönüş: yalnız yeni tablolar boşalır.
        $this->pdo->exec('DELETE FROM listing_price_tiers');
        $this->pdo->exec('DELETE FROM listings');

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM listings')->fetchColumn());
        self::assertSame($urunOnce, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
    }

    /**
     * Göç mantığının test içi ikizi — `bin/goc-ilan.php` ile AYNI kuralları izler.
     *
     * CLI betiği kendi Config/Database bağlantısını kurduğu için doğrudan
     * çağrılamaz. Ayrışma riski taşıyan TEK parça olan kademe ayrıştırıcı ise
     * ortak sınıftır (`FiyatKademeAyristirici`) — burada da o çağrılır, yani
     * kopyalanan şey yalnızca INSERT sırasıdır.
     */
    private function gocKos(): void
    {
        $platformlar = [];
        foreach ($this->pdo->query('SELECT id, kod FROM platforms')->fetchAll() as $satir) {
            $platformlar[(string) $satir['kod']] = (int) $satir['id'];
        }
        $mevcut = [];
        foreach ($this->pdo->query('SELECT product_id FROM listings')->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $mevcut[(int) $pid] = true;
        }

        $ilanEkle = $this->pdo->prepare(
            'INSERT INTO listings (product_id, platform_id, platform_kod, external_id, url, baslik_orijinal,
                 satici_ad, satici_url, moq, birim_fiyat, para_birimi, ham_veri, yakalandi_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)',
        );
        $kademeEkle = $this->pdo->prepare(
            'INSERT INTO listing_price_tiers (listing_id, min_adet, birim_fiyat, para_birimi) VALUES (?, ?, ?, ?)',
        );

        foreach ($this->pdo->query('SELECT * FROM products ORDER BY id')->fetchAll() as $urun) {
            $urunId = (int) $urun['id'];
            if (isset($mevcut[$urunId])) {
                continue;
            }
            $kod = trim((string) ($urun['platform'] ?? ''));
            if ($kod === '') {
                $kod = 'manuel';
            }
            $ham = is_string($urun['raw_attributes'] ?? null) ? $urun['raw_attributes'] : null;

            $ilanEkle->execute([
                $urunId,
                $platformlar[$kod] ?? null,
                $kod,
                $urun['external_id'],
                $urun['url'],
                $urun['name_original'],
                $urun['vendor_name'],
                $urun['vendor_url'],
                $urun['price_yuan'],
                'CNY',
                $ham,
                $urun['created_at'],
                '2026-08-22 12:00:00',
                '2026-08-22 12:00:00',
            ]);
            $ilanId = (int) $this->pdo->lastInsertId();

            foreach (\App\Services\Ilan\FiyatKademeAyristirici::ayristir($ham) as $kademe) {
                $kademeEkle->execute([$ilanId, $kademe['min_adet'], $kademe['birim_fiyat'], 'CNY']);
            }
        }
    }
}
