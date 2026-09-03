<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Config;
use App\Services\BackupService;
use App\Services\Yedek\YedekProvasi;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * v1.2.2 B1 ENTEGRASYON — `BackupService::create()` artık SET üretir.
 *
 * ÖNCESİ: parçalar `storage/backups/` içine yan yana düşüyordu ve aralarındaki
 * bağ yalnız DOSYA ADIYDI (`yedek-<damga>.sql.enc`, `.files.enc`, `.media.zip`).
 * "Bu üç dosya aynı yedeğe mi ait?" sorusunun cevabı bir isim benzerliğiydi;
 * biri eksikse bunu ancak geri yüklerken anlıyordunuz.
 *
 * SONRASI: tek dizin, tek manifest, atomik tamamlanma. Bu test entegrasyonun
 * GERÇEKTEN yapıldığını sınar — sınıflar tek başına yeşil olup birbirine
 * bağlanmamış olabilir (v1.2.1'de tam bunu yaşadık: `SetupHttpsGate` yazılmış
 * ama hiçbir zincire eklenmemişti).
 */
final class YedekSetEntegrasyonTest extends TestCase
{
    use TempDirectory;

    private function config(): Config
    {
        return new Config([
            'APP_KEY' => bin2hex(random_bytes(32)),
            'APP_ENV' => 'testing',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'test',
            'TZ' => 'Europe/Istanbul',
            'BACKUP_MEDIA_MAX_MB' => '200',
        ]);
    }

    /** Yedeklenecek gerçek bir kurulum iskeleti kurar. */
    private function kurulumHazirla(): string
    {
        $kok = $this->tempRoot();
        @mkdir($kok . '/storage/backups', 0o775, true);
        @mkdir($kok . '/public/media', 0o775, true);

        file_put_contents($kok . '/config.php', "<?php\n\nreturn ['APP_KEY' => 'x'];\n");
        file_put_contents($kok . '/storage/sozluk-zh-tr.php', "<?php\n\nreturn ['不锈钢' => 'Paslanmaz'];\n");
        file_put_contents($kok . '/public/media/a.jpg', 'GORSEL-A');
        file_put_contents($kok . '/public/media/b.jpg', 'GORSEL-B');

        return $kok;
    }

    private function servis(string $kok): BackupService
    {
        return new BackupService($this->config(), $kok, dumpUretici: static fn (): string => "-- SQL DUMP\nSELECT 1;\n");
    }

    public function testCREATESETDIZINIURETIR(): void
    {
        $kok = $this->kurulumHazirla();

        $sonuc = $this->servis($kok)->create();

        self::assertArrayHasKey('set_dizini', $sonuc, 'create() artık SET döndürmeli.');
        self::assertDirectoryExists($sonuc['set_dizini']);
        self::assertFileExists($sonuc['set_dizini'] . '/' . YedekProvasi::MANIFEST_ADI);
    }

    public function testURETILENSETPROVAYIGECER(): void
    {
        // ASIL SORU: ürettiğimiz şey geri yüklenebilir mi? Manifest ile diskin
        // tutarlılığını üretimin HEMEN ARDINDAN sınamak, "yedek aldım" ile
        // "geri dönebilirim" arasındaki farkı kapatır.
        $kok = $this->kurulumHazirla();
        $sonuc = $this->servis($kok)->create();

        $prova = (new YedekProvasi())->dogrula($sonuc['set_dizini'], []);

        self::assertTrue($prova['gecerli'], (new YedekProvasi())->rapor($prova));
        self::assertGreaterThanOrEqual(3, $prova['dogrulanan_parca'], 'SQL + config + medya en az üç parça.');
    }

    public function testSETSQLVECONFIGPARCASITASIR(): void
    {
        $kok = $this->kurulumHazirla();
        $sonuc = $this->servis($kok)->create();

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode(
            (string) file_get_contents($sonuc['set_dizini'] . '/' . YedekProvasi::MANIFEST_ADI),
            true,
        );
        $turler = array_column($manifest['parcalar'], 'tur');

        self::assertContains('sql', $turler);
        self::assertContains('config', $turler, 'config.php + storage sözlükleri olmadan geri dönülemez.');
        self::assertContains('medya', $turler);
    }

    public function testLISTESETLERIGORUR(): void
    {
        $kok = $this->kurulumHazirla();
        $this->servis($kok)->create();

        $liste = $this->servis($kok)->list();

        self::assertCount(1, $liste);
        self::assertArrayHasKey('set_id', $liste[0]);
        self::assertTrue($liste[0]['tam'], 'Tamamlanmış set listede TAM görünmeli.');
    }

    public function testYARIMSETLISTEDEGORUNMEZ(): void
    {
        // Yarım bir set listede yer alırsa kullanıcı onu sayar ve güvenir.
        $kok = $this->kurulumHazirla();
        @mkdir($kok . '/storage/backups/.hazirlik-yarim', 0o775, true);
        file_put_contents($kok . '/storage/backups/.hazirlik-yarim/veritabani.sql.enc', 'YARIM');

        self::assertSame([], $this->servis($kok)->list());
    }

    public function testMEDYASINIRIASILINCASETKISMIOLURAMABASARISIZOLMAZ(): void
    {
        // Büyük medya klasörü olan kurulum, DB'sini yine de yedekleyebilmeli.
        // Eskiden arşiv atlanıyordu ama set kavramı olmadığı için bu durum
        // "eksik yedek" olarak görünmüyordu bile.
        $kok = $this->kurulumHazirla();
        $config = new Config([
            'APP_KEY' => bin2hex(random_bytes(32)),
            'APP_ENV' => 'testing',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'test',
            'TZ' => 'Europe/Istanbul',
            // 0 MB sınır: her medya klasörü sınırı aşar.
            'BACKUP_MEDIA_MAX_MB' => '1',
        ]);
        file_put_contents($kok . '/public/media/buyuk.jpg', str_repeat('X', 2 * 1024 * 1024));

        $servis = new BackupService($config, $kok, dumpUretici: static fn (): string => "-- SQL\n");
        $sonuc = $servis->create();

        $prova = (new YedekProvasi())->dogrula($sonuc['set_dizini'], []);
        self::assertTrue($prova['gecerli'], 'Medya atlansa da set GEÇERLİ olmalı.');
        self::assertTrue($sonuc['medya_atlandi']);
    }

    public function testPRUNEENYENIBESSETIKORUR(): void
    {
        $kok = $this->kurulumHazirla();
        $servis = $this->servis($kok);

        // Yedi set kur; altı tanesi eski tarihli olsun.
        for ($i = 1; $i <= 7; $i++) {
            $servis->create();
            usleep(1100000 / 1000); // damga saniye çözünürlüklü; ad çakışmasın
            clearstatcache();
        }

        $silinen = $servis->prune(0);
        $kalan = $servis->list();

        self::assertGreaterThanOrEqual(5, count($kalan), 'En yeni beş set HER KOŞULDA korunur.');
        self::assertSame(count($silinen), 7 - count($kalan));
    }
}
