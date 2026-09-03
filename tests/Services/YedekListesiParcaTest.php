<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\BackupService;
use App\Services\Yedek\YedekSetiYazici;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * v1.2.2 B4 — PANEL KARTI PARÇALARI GÖRÜR.
 *
 * PM kararı: "tümünü zip indir" düğmesi YOK. Panel parçaları tek tek sunar,
 * her parçanın SHA-256'sı görünür ve manifest ayrıca indirilebilir.
 *
 * Bu ancak liste ucu parça bilgisini TAŞIRSA mümkündür. Taşımazsa panelin
 * elinde yalnız set adı olur ve kullanıcı "hangi dosyaları indirmem gerekiyor,
 * indirdiğim doğru mu?" sorusunu yanıtlayamaz — ki tek zip olmadığına göre bu
 * soru artık kullanıcının sorusudur.
 *
 * SHA NİYE GÖRÜNÜR: parçayı indiren kişi, indirmenin bozulmadığını kendi
 * makinesinde doğrulayabilsin diye. Sunucunun "indirildi" demesi, dosyanın
 * karşı tarafa sağlam ulaştığını göstermez.
 */
final class YedekListesiParcaTest extends TestCase
{
    use TempDirectory;

    private function kok(): string
    {
        $kok = $this->tempPath('yedek-listesi');
        if (!is_dir($kok . '/storage/backups')) {
            mkdir($kok . '/storage/backups', 0o775, true);
        }

        return $kok;
    }

    private function servis(string $kok): BackupService
    {
        return new BackupService(
            new \App\Core\Config([
                'APP_ENV' => 'local',
                'APP_URL' => 'https://tedarikapp.test',
                'TZ' => 'Europe/Istanbul',
                'APP_KEY' => str_repeat('cd', 32),
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '3306',
                'DB_NAME' => 'yok',
                'DB_USER' => 'yok',
                'DB_PASS' => '',
            ]),
            $kok,
        );
    }

    private function setYaz(string $kok): string
    {
        $yazici = new YedekSetiYazici($kok . '/storage/backups', '1.2.2', ['0035_bildirimler']);
        $set = $yazici->baslat('20260903-030000');
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
        $yazici->parcaEkle($set, 'medya-001.zip.enc', 'medya', 'M1');
        $yazici->parcaEkle($set, 'medya-002.zip.enc', 'medya', 'M2');

        return $yazici->tamamla($set);
    }

    public function testLISTEPARCALARIVESHALARINITASIR(): void
    {
        $kok = $this->kok();
        $this->setYaz($kok);

        $liste = $this->servis($kok)->list();

        self::assertCount(1, $liste);
        self::assertCount(4, $liste[0]['parcalar']);

        $ilk = $liste[0]['parcalar'][0];
        self::assertSame('veritabani.sql.enc', $ilk['ad']);
        self::assertSame(1, $ilk['sira']);
        self::assertSame(hash('sha256', 'DUMP'), $ilk['sha256']);
        self::assertSame(4, $ilk['boyut']);
    }

    public function testPARCALARSIRALIGELIR(): void
    {
        // Panel listeyi olduğu gibi basar; sıralamayı arayüze bırakmak, her
        // ekranda yeniden yapılması gereken (ve unutulabilecek) bir iş olurdu.
        $kok = $this->kok();
        $this->setYaz($kok);

        $parcalar = $this->servis($kok)->list()[0]['parcalar'];

        self::assertSame([1, 2, 3, 4], array_column($parcalar, 'sira'));
    }

    public function testMANIFESTINDIRILEBILIR(): void
    {
        // "Manifest indir" düğmesinin dayanağı: manifest de bir parça gibi
        // sunulabilmeli, yoksa kullanıcı setin ne içerdiğini elindeki
        // dosyalardan tahmin etmek zorunda kalır.
        $kok = $this->kok();
        $dizin = $this->setYaz($kok);

        $yol = $this->servis($kok)->parcaYolu(basename($dizin), 'MANIFEST.json');

        self::assertNotNull($yol);
        self::assertFileExists((string) $yol);
    }

    public function testDESENDISIPARCAADIREDDEDILIR(): void
    {
        // Yol kaçışı kalkanı: parça adı kullanıcıdan gelir.
        $kok = $this->kok();
        $dizin = $this->setYaz($kok);
        $servis = $this->servis($kok);

        self::assertNull($servis->parcaYolu(basename($dizin), '../config.php'));
        self::assertNull($servis->parcaYolu(basename($dizin), 'yok.enc'));
    }
}
