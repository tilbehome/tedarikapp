<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Config;
use App\Services\BackupService;
use App\Services\Yedek\YedekGeriYukleyici;
use App\Services\Yedek\YedekManifesti;
use App\Services\Yedek\YedekProvasi;
use App\Services\Yedek\YedekSetiYazici;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempDirectory;

/**
 * v1.2.2 H1 — CONFIG EKSİKSE SET KISMİ, SQL YİNE YAZILIR.
 *
 * ESKİ KURAL: `config` zorunlu parçaydı; okunamazsa SET REDDEDİLİYORDU. Yani
 * `config.php` üstünde bir gecelik izin kazası, o gece VERİTABANI YEDEĞİNİN de
 * alınmaması demekti. Oysa ayarlar yeniden girilebilir; veritabanı girilemez.
 * Kaybı en büyük olan parçayı, kaybı en küçük olan parça yüzünden düşürmek
 * orantısızdı (PM hükmü, 4 Eyl).
 *
 * YENİ KURAL:
 *   · Yalnız `sql` zorunludur. Config okunamazsa set KISMİ olur, reddedilmez.
 *   · Manifest bunu SÖYLER: `durum` = TAM | KISMI, `eksik` = ["config"],
 *     `sebep` = kısa hata metni. Sessiz kısmilik yoktur.
 *   · Parça bağı (sira / toplam_parca / sha256) KISMİ sette de FAIL-CLOSED
 *     kalır. `toplam_parca` config'siz beklenen sayıdır; liste kendi içinde
 *     tam olmak zorundadır. KISMİ ≠ eksik parça: kısmi set "bilerek daha az
 *     parça" demektir, "parçası kaybolmuş" değil.
 *   · Geri yükleme KISMİ seti `--kismi-kabul` olmadan yüklemez.
 */
final class YedekKismiSetTest extends TestCase
{
    use TempDirectory;

    /** @return list<array<string, mixed>> */
    private function configsizParcalar(): array
    {
        return [
            ['ad' => 'veritabani.sql.enc', 'tur' => 'sql', 'sira' => 1, 'boyut' => 10, 'sha256' => str_repeat('a', 64)],
            ['ad' => 'medya-001.zip.enc', 'tur' => 'medya', 'sira' => 2, 'boyut' => 10, 'sha256' => str_repeat('c', 64)],
        ];
    }

    private function manifest(array $ustyaz = []): YedekManifesti
    {
        return new YedekManifesti(array_merge([
            'set_id' => 'dddd1111-2222-4333-8444-555566667777',
            'olusturuldu' => '2026-09-04T03:00:00+03:00',
            'surum' => '1.2.2',
            'sifreleme' => 'aes-256-gcm',
            'parcalar' => $this->configsizParcalar(),
            'toplam_parca' => 2,
            'migration_defteri' => ['0035_bildirimler'],
            'eksik' => ['config'],
            'sebep' => 'config.php okunamadı: izin reddedildi',
        ], $ustyaz));
    }

    // ── Manifest sözleşmesi ────────────────────────────────────────────

    public function testCONFIGSIZSETGECERLIAMAKISMI(): void
    {
        $manifest = $this->manifest();

        self::assertTrue($manifest->tamMi(), implode(', ', $manifest->eksikler()));
        self::assertSame(YedekManifesti::DURUM_KISMI, $manifest->durum());
        self::assertSame(['config'], $manifest->eksikBilesenler());
        self::assertSame('config.php okunamadı: izin reddedildi', $manifest->sebep());
    }

    public function testEKSIKYOKSADURUMTAM(): void
    {
        $tam = $this->manifest(['eksik' => [], 'sebep' => null]);

        self::assertSame(YedekManifesti::DURUM_TAM, $tam->durum());
        self::assertSame([], $tam->eksikBilesenler());
    }

    public function testSQLYINEZORUNLU(): void
    {
        // Kural gevşedi ama sıfırlanmadı: SQL'siz "yedek" yedek değildir.
        $sqlsiz = $this->manifest([
            'parcalar' => [
                ['ad' => 'medya-001.zip.enc', 'tur' => 'medya', 'sira' => 1, 'boyut' => 10, 'sha256' => str_repeat('c', 64)],
            ],
            'toplam_parca' => 1,
        ]);

        self::assertFalse($sqlsiz->tamMi());
        self::assertContains('sql', $sqlsiz->eksikler());
    }

    public function testKISMISETTEPARCABAGIFAILCLOSED(): void
    {
        // (d) KISMİ ≠ eksik parça. Kısmi sette bir parça KAYBOLMUŞSA set yine
        // GEÇERSİZDİR — kısmilik, eksik parçayı örten bir bahane olamaz.
        $bozuk = $this->manifest(['toplam_parca' => 3]);

        self::assertFalse($bozuk->tamMi());
        self::assertStringContainsString('toplam', implode(' ', $bozuk->eksikler()));
    }

    public function testDURUMJSONAGIDIPGERIGELIR(): void
    {
        $geri = YedekManifesti::jsondan($this->manifest()->jsonOlarak());

        self::assertSame(YedekManifesti::DURUM_KISMI, $geri->durum());
        self::assertSame(['config'], $geri->eksikBilesenler());
        self::assertSame($geri->durum(), $geri->ozet()['durum']);
        self::assertSame(['config'], $geri->ozet()['eksik']);
    }

    // ── Yazıcı ─────────────────────────────────────────────────────────

    public function testYAZICIEKSIGIMANIFESTEYAZAR(): void
    {
        $kok = $this->tempPath('backups-kismi');
        @mkdir($kok, 0o775, true);

        $yazici = new YedekSetiYazici($kok, '1.2.2', []);
        $set = $yazici->baslat('20260904-030000');
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
        $yazici->eksikBildir($set, 'config', 'config.php okunamadı');
        $dizin = $yazici->tamamla($set);

        $manifest = YedekManifesti::jsondan((string) file_get_contents($dizin . '/' . YedekProvasi::MANIFEST_ADI));

        self::assertSame(YedekManifesti::DURUM_KISMI, $manifest->durum());
        self::assertSame(['config'], $manifest->eksikBilesenler());
        self::assertSame('config.php okunamadı', $manifest->sebep());
        self::assertSame(1, $manifest->toplamParca(), 'toplam_parca config\'siz BEKLENEN sayıdır.');
    }

    // ── Servis: (a) config okunamaz → KISMİ set üretilir ───────────────

    public function testCONFIGOKUNAMAZSASETYINEURETILIR(): void
    {
        $kok = $this->tempPath('kok-kismi');
        @mkdir($kok . '/storage/backups', 0o775, true);
        // config.php YOK — okunamaz durumun test eşdeğeri.

        $servis = new BackupService($this->config(), $kok, dumpUretici: static fn (): string => 'CREATE TABLE t (id INT);');
        $sonuc = $servis->create();

        self::assertSame(YedekManifesti::DURUM_KISMI, $sonuc['durum']);
        self::assertSame(['config'], $sonuc['eksik']);
        self::assertFileExists($sonuc['set_dizini'] . '/veritabani.sql.enc', 'SQL parçası YİNE yazılmalı.');
        self::assertFileDoesNotExist($sonuc['set_dizini'] . '/ayarlar.files.enc');
    }

    public function testCONFIGVARSASETTAM(): void
    {
        $kok = $this->tempPath('kok-tam');
        @mkdir($kok . '/storage/backups', 0o775, true);
        file_put_contents($kok . '/config.php', "<?php\nreturn [];\n");

        $sonuc = (new BackupService($this->config(), $kok, dumpUretici: static fn (): string => 'CREATE TABLE t (id INT);'))->create();

        self::assertSame(YedekManifesti::DURUM_TAM, $sonuc['durum']);
        self::assertSame([], $sonuc['eksik']);
    }

    public function testLISTEDURUMUTASIR(): void
    {
        // Görünürlük (madde 4): panel rozeti bu alandan beslenir.
        $kok = $this->tempPath('kok-liste');
        @mkdir($kok . '/storage/backups', 0o775, true);
        $servis = new BackupService($this->config(), $kok, dumpUretici: static fn (): string => 'X');
        $servis->create();

        $satir = $servis->list()[0];

        self::assertSame(YedekManifesti::DURUM_KISMI, $satir['durum']);
        self::assertSame(['config'], $satir['eksik']);
    }

    // ── Geri yükleme kapısı: (b) bayraksız red, (c) bayrakla geçer ─────

    public function testKISMISETBAYRAKSIZGERIYUKLENMEZ(): void
    {
        $kok = $this->tempPath('kok-kapi');
        @mkdir($kok . '/storage/backups', 0o775, true);
        $servis = new BackupService($this->config(), $kok, dumpUretici: static fn (): string => 'X');
        $sonuc = $servis->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--kismi-kabul');
        (new YedekGeriYukleyici($servis))->kapiyiAc($sonuc['set_dizini']);
    }

    public function testKISMISETBAYRAKLAGECER(): void
    {
        $kok = $this->tempPath('kok-kapi-ok');
        @mkdir($kok . '/storage/backups', 0o775, true);
        $servis = new BackupService($this->config(), $kok, dumpUretici: static fn (): string => 'X');
        $sonuc = $servis->create();

        $manifest = (new YedekGeriYukleyici($servis))->kapiyiAc($sonuc['set_dizini'], [], kismiKabul: true);

        self::assertSame(YedekManifesti::DURUM_KISMI, $manifest->durum());
    }

    public function testTAMSETBAYRAKISTEMEZ(): void
    {
        $kok = $this->tempPath('kok-tam-kapi');
        @mkdir($kok . '/storage/backups', 0o775, true);
        file_put_contents($kok . '/config.php', "<?php\nreturn [];\n");
        $servis = new BackupService($this->config(), $kok, dumpUretici: static fn (): string => 'X');
        $sonuc = $servis->create();

        $manifest = (new YedekGeriYukleyici($servis))->kapiyiAc($sonuc['set_dizini']);

        self::assertSame(YedekManifesti::DURUM_TAM, $manifest->durum());
    }

    private function config(): Config
    {
        return new Config([
            'APP_ENV' => 'local',
            'APP_URL' => 'https://tedarikapp.test',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('cd', 32),
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_NAME' => 'yok',
            'DB_USER' => 'yok',
            'DB_PASS' => '',
        ]);
    }
}
