<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Services\BackupService;
use App\Services\Yedek\YedekGeriYukleyici;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * v1.2.2 B3 — GERİ YÜKLEME PROVASI (CI, gerçek MySQL).
 *
 * TURUN KAPANMA ŞARTI: "yedek alınıyor" ile "geri dönülebiliyor" aynı cümle
 * değildir ve aradaki farkı ancak GERÇEKTEN geri yükleyerek öğrenirsiniz.
 * Akış her vakada aynı: YEDEK AL → SİL → GERİ YÜKLE → BİREBİR Mİ?
 *
 * "Birebir" burada iki şeyi birden demek:
 *   · satır sayıları tablo tablo eşit,
 *   · medya dosyalarının SHA-256'ları eşit.
 * Dosya SAYMAK yetmez — aynı sayıda ama içeriği bozulmuş dosyalar saymayla
 * ayırt edilemez, ki sessiz bozulmanın tam olarak yaptığı şey budur.
 *
 * ÇOK PARÇALI VAKA ZORUNLUDUR (PM ara hükmü, 3 Eyl): tek parçalı mutlu yol
 * provayı kapatmaz. Bölme yolu, sıra bağını ve parçaların birleşmesini
 * sınayan TEK yoldur; ve büyük medya klasörü olan kurulum tam da bölme
 * yoluna düşen kurulumdur.
 *
 * @group mysql
 */
#[Group('mysql')]
final class YedekSetiGeriYuklemeTest extends TestCase
{
    private const KAYNAK_DB = 'yedek_prova_kaynak';

    private ?PDO $pdo = null;
    private string $kok = '';

    protected function setUp(): void
    {
        $dsn = getenv('TEDARIKAPP_TEST_DB_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('MySQL DSN yok (TEDARIKAPP_TEST_DB_DSN) — mysql grubu atlandı.');
        }

        $this->pdo = new PDO(
            $dsn,
            (string) getenv('TEDARIKAPP_TEST_DB_USER'),
            (string) getenv('TEDARIKAPP_TEST_DB_PASS'),
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->kok = sys_get_temp_dir() . '/yedek-prova-' . bin2hex(random_bytes(4));
        mkdir($this->kok . '/storage/backups', 0o775, true);
        mkdir($this->kok . '/public/media', 0o775, true);

        // GERÇEK KURULUMUN İKİZİ: kurulumda config.php her zaman vardır ve
        // TAM set üreten vakalar onunla koşar. H1'in KISMİ vakası bu dosyayı
        // bilerek siler — "config alınamadı" durumunun test eşdeğeri.
        file_put_contents(
            $this->kok . '/config.php',
            "<?php\nreturn ['APP_KEY' => 'prova'];\n",
        );
    }

    protected function tearDown(): void
    {
        $this->pdo?->exec('DROP DATABASE IF EXISTS ' . self::KAYNAK_DB);
        $this->sil($this->kok);
        parent::tearDown();
    }

    /** Tek parçalı mutlu yol: veritabanı gerçekten geri geliyor mu? */
    public function testTEKPARCALISETGERIYUKLENIR(): void
    {
        $servis = $this->kaynagiKur(medyaDosyaSayisi: 2, medyaBayt: 1024, sinirMb: 200);
        $geriYukleyici = new YedekGeriYukleyici($servis);

        $oncekiSayim = $this->satirSayimi();
        $set = $servis->create();
        $manifest = $geriYukleyici->kapiyiAc($set['set_dizini']);

        self::assertSame(1, $manifest->ozet()['medya_parca_sayisi'], 'Bu vaka TEK medya parçası olmalı.');

        // SİL: tabloları düşür — geri yükleme gerçekten sıfırdan kuruyor mu?
        $dusen = $geriYukleyici->hedefiTemizle($this->pdo());
        self::assertGreaterThan(0, $dusen);
        self::assertSame(0, $geriYukleyici->sayim($this->pdo())['tablo_sayisi'], 'Silme gerçekten olmalı.');

        $sonrakiSayim = $geriYukleyici->veritabaniniYukle($this->pdo(), $set['set_dizini'], $manifest);

        self::assertSame($oncekiSayim, $sonrakiSayim['tablolar'], 'Satır sayıları BİREBİR eşleşmeli.');
    }

    /**
     * ÇOK PARÇALI VAKA — provanın asıl yükünü taşıyan test.
     *
     * `BACKUP_MEDIA_MAX_MB` 1'e çekilir ve toplam ~2.4 MB yapay medya ile
     * bölme TETİKLENİR. Sonra set silinir, geri yüklenir; hem satırlar hem
     * her dosyanın SHA-256'sı karşılaştırılır.
     */
    public function testCOKPARCALISETBIREBIRGERIYUKLENIR(): void
    {
        $servis = $this->kaynagiKur(medyaDosyaSayisi: 4, medyaBayt: 600 * 1024, sinirMb: 1);
        $geriYukleyici = new YedekGeriYukleyici($servis);

        $oncekiSayim = $this->satirSayimi();
        $oncekiOzetler = $geriYukleyici->dosyaOzetleri($this->kok . '/public/media');
        self::assertCount(4, $oncekiOzetler);

        $set = $servis->create();
        $manifest = $geriYukleyici->kapiyiAc($set['set_dizini']);

        self::assertGreaterThan(
            1,
            $manifest->ozet()['medya_parca_sayisi'],
            'Bölme TETİKLENMELİ — tek parçalı bir set bu vakayı sınamaz.',
        );

        // SİL: hem veritabanı hem medya.
        $geriYukleyici->hedefiTemizle($this->pdo());
        foreach (glob($this->kok . '/public/media/*') ?: [] as $dosya) {
            unlink($dosya);
        }
        self::assertSame([], $geriYukleyici->dosyaOzetleri($this->kok . '/public/media'));

        // GERİ YÜKLE.
        $sonrakiSayim = $geriYukleyici->veritabaniniYukle($this->pdo(), $set['set_dizini'], $manifest);
        $medya = $geriYukleyici->medyayiYukle($this->kok . '/public/media', $set['set_dizini'], $manifest);

        self::assertSame($oncekiSayim, $sonrakiSayim['tablolar'], 'Satır sayıları BİREBİR eşleşmeli.');
        self::assertSame(4, $medya['dosya_sayisi']);
        self::assertSame(
            $oncekiOzetler,
            $geriYukleyici->dosyaOzetleri($this->kok . '/public/media'),
            'Medya dosyalarının SHA-256 haritası BİREBİR aynı olmalı.',
        );
    }

    /**
     * NEGATİF VAKA: kısmi setten SESSİZ geri yükleme imkânsız.
     *
     * Bir medya parçası silinir (indirilirken düşmüş, aktarımda kaybolmuş).
     * Kapı açılmamalı ve veritabanına TEK SATIR yazılmamalıdır — hasarlı bir
     * yedeğin sağlam veritabanının üstüne yazması, yedeğin hiç olmamasından
     * kötüdür.
     */
    public function testEKSIKPARCALISETGERIYUKLENMEZ(): void
    {
        $servis = $this->kaynagiKur(medyaDosyaSayisi: 4, medyaBayt: 600 * 1024, sinirMb: 1);
        $geriYukleyici = new YedekGeriYukleyici($servis);

        $set = $servis->create();
        $silinen = glob($set['set_dizini'] . '/medya-*.zip.enc') ?: [];
        self::assertNotSame([], $silinen);
        unlink((string) end($silinen));

        $oncekiSayim = $this->satirSayimi();

        try {
            $geriYukleyici->kapiyiAc($set['set_dizini']);
            self::fail('Eksik parçalı set geri yükleme kapısını AÇMAMALIYDI.');
        } catch (\RuntimeException $hata) {
            self::assertStringContainsString('DURDURULDU', $hata->getMessage());
        }

        self::assertSame(
            $oncekiSayim,
            $geriYukleyici->sayim($this->pdo())['tablolar'],
            'Kapı kapalıyken veritabanına DOKUNULMAMALI.',
        );
    }

    /**
     * H1 (e) — KISMİ VAKA: config yokken alınan set, bayrakla BİREBİR geri gelir.
     *
     * Bayraksız kapı kapalı (veritabanına dokunulmaz); `kismiKabul` ile
     * satır sayıları birebir. Kural değişikliğinin canlıdaki anlamı tam olarak
     * budur: izin kazası olan gece de veritabanı yedeklenmiş olur.
     */
    public function testKISMISETBAYRAKLABIREBIRGERIYUKLENIR(): void
    {
        $servis = $this->kaynagiKur(medyaDosyaSayisi: 2, medyaBayt: 1024, sinirMb: 200);
        unlink($this->kok . '/config.php'); // config alınamaz
        $geriYukleyici = new YedekGeriYukleyici($servis);

        $oncekiSayim = $this->satirSayimi();
        $set = $servis->create();

        self::assertSame(\App\Services\Yedek\YedekManifesti::DURUM_KISMI, $set['durum']);
        self::assertFileExists($set['set_dizini'] . '/veritabani.sql.enc');

        // Bayraksız: kapı kapalı, veritabanına dokunulmadı.
        try {
            $geriYukleyici->kapiyiAc($set['set_dizini']);
            self::fail('KISMİ set bayraksız kapıyı AÇMAMALIYDI.');
        } catch (\RuntimeException $hata) {
            self::assertStringContainsString('--kismi-kabul', $hata->getMessage());
        }
        self::assertSame($oncekiSayim, $geriYukleyici->sayim($this->pdo())['tablolar']);

        // Bayrakla: sil → geri yükle → birebir.
        $manifest = $geriYukleyici->kapiyiAc($set['set_dizini'], [], kismiKabul: true);
        $geriYukleyici->hedefiTemizle($this->pdo());
        $sonrakiSayim = $geriYukleyici->veritabaniniYukle($this->pdo(), $set['set_dizini'], $manifest);

        self::assertSame($oncekiSayim, $sonrakiSayim['tablolar'], 'KISMİ setten de satır sayıları BİREBİR gelmeli.');
    }

    /** BOZUK PARÇA: içerik değişmişse SHA tutmaz ve kapı açılmaz. */
    public function testBOZUKPARCALISETGERIYUKLENMEZ(): void
    {
        $servis = $this->kaynagiKur(medyaDosyaSayisi: 2, medyaBayt: 1024, sinirMb: 200);
        $geriYukleyici = new YedekGeriYukleyici($servis);

        $set = $servis->create();
        $sqlParcasi = $set['set_dizini'] . '/veritabani.sql.enc';
        file_put_contents($sqlParcasi, (string) file_get_contents($sqlParcasi) . 'BOZULDU');

        $this->expectException(\RuntimeException::class);
        $geriYukleyici->kapiyiAc($set['set_dizini']);
    }

    private function pdo(): PDO
    {
        assert($this->pdo instanceof PDO);

        return $this->pdo;
    }

    /**
     * Canlının küçük bir ikizi: iki tablo + yapay medya.
     *
     * `users` ve `migrations` bilerek var — geri yükleme sonrası anlamlılık
     * denetimi bu tabloları arar ve gerçek kurulumda da onlar aranır.
     */
    private function kaynagiKur(int $medyaDosyaSayisi, int $medyaBayt, int $sinirMb): BackupService
    {
        $pdo = $this->pdo();
        $pdo->exec('DROP DATABASE IF EXISTS ' . self::KAYNAK_DB);
        $pdo->exec('CREATE DATABASE ' . self::KAYNAK_DB . ' CHARACTER SET utf8mb4');
        $pdo->exec('USE ' . self::KAYNAK_DB);
        $pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, eposta VARCHAR(190)) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE migrations (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190)) ENGINE=InnoDB');
        $pdo->exec("INSERT INTO users VALUES (1, 'a@ornek.test'), (2, 'b@ornek.test')");
        $pdo->exec("INSERT INTO migrations (name) VALUES ('0035_bildirimler'), ('0036_paylasim_anahtari_sifreli_alan')");

        // Sıkıştırılamayan içerik: boyut sınırının gerçekten tetiklendiğinden
        // emin olmak için. Sıfırlarla dolu bir dosya zip'te birkaç bayta iner
        // ve bölme hiç olmazdı — test yeşil görünür, bölme yolu sınanmazdı.
        for ($i = 1; $i <= $medyaDosyaSayisi; $i++) {
            file_put_contents(
                sprintf('%s/public/media/gorsel-%03d.bin', $this->kok, $i),
                random_bytes($medyaBayt),
            );
        }

        return new BackupService($this->config($sinirMb), $this->kok);
    }

    /** @return array<string, int> */
    private function satirSayimi(): array
    {
        return (new YedekGeriYukleyici(new BackupService($this->config(200), $this->kok)))
            ->sayim($this->pdo())['tablolar'];
    }

    private function config(int $medyaSinirMb): Config
    {
        $dsn = (string) getenv('TEDARIKAPP_TEST_DB_DSN');
        preg_match('/host=([^;]+)/', $dsn, $host);
        preg_match('/port=([^;]+)/', $dsn, $port);

        return new Config([
            'APP_ENV' => 'local',
            'APP_URL' => 'https://tedarikapp.test',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('cd', 32),
            'DB_HOST' => $host[1] ?? '127.0.0.1',
            'DB_PORT' => $port[1] ?? '3306',
            'DB_NAME' => self::KAYNAK_DB,
            'DB_USER' => (string) getenv('TEDARIKAPP_TEST_DB_USER'),
            'DB_PASS' => (string) getenv('TEDARIKAPP_TEST_DB_PASS'),
            'BACKUP_MEDIA_MAX_MB' => (string) $medyaSinirMb,
        ]);
    }

    private function sil(string $yol): void
    {
        if (!is_dir($yol)) {
            return;
        }
        foreach (glob($yol . '/*') ?: [] as $alt) {
            is_dir($alt) ? $this->sil($alt) : @unlink($alt);
        }
        @rmdir($yol);
    }
}
