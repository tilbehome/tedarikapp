<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Yedek\YedekSetiYazici;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempDirectory;

/**
 * v1.2.2 BLOK B1 — SET ATOMİK YAZILIR.
 *
 * ESKİ HÂL: parçalar doğrudan `storage/backups/` içine, yan yana yazılıyordu.
 * Gece koşusu ortada kesilirse (süre sınırı, disk dolması, süreç ölümü) yarım
 * bir yedek KALICI olarak orada duruyordu ve `list()` onu tam bir yedek gibi
 * gösteriyordu. Kullanıcı "3 yedeğim var" diyordu; gerçekte ikisi eksikti.
 *
 * YENİ HÂL:
 *   1. Parçalar bir HAZIRLIK dizinine yazılır (`.hazirlik-<set>`),
 *   2. hepsi başarıyla yazıldıysa MANIFEST hazırlık dizinine konur,
 *   3. dizin TEK ADIMDA nihai adına taşınır (rename — aynı dosya sisteminde
 *      atomiktir).
 *
 * Yarıda kalan koşum, adı `.hazirlik-` ile başlayan bir dizin bırakır: liste
 * onu GÖRMEZ ve bir sonraki koşum temizler. "Yarım ama tam görünen" hâl
 * ortadan kalkar.
 */
final class YedekSetiYazimiTest extends TestCase
{
    use TempDirectory;

    private function kok(): string
    {
        $dizin = $this->tempPath('backups');
        if (!is_dir($dizin)) {
            mkdir($dizin, 0o775, true);
        }

        return $dizin;
    }

    private function yazici(): YedekSetiYazici
    {
        return new YedekSetiYazici($this->kok(), '1.2.2', ['0035_bildirimler']);
    }

    public function testSETNIHAIADINDAOLUSUR(): void
    {
        $yazici = $this->yazici();
        $set = $yazici->baslat('20260901-030000');
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
        $yol = $yazici->tamamla($set);

        self::assertDirectoryExists($yol);
        self::assertFileExists($yol . '/MANIFEST.json');
        self::assertFileExists($yol . '/veritabani.sql.enc');
        self::assertStringNotContainsString('.hazirlik-', $yol);
    }

    public function testTAMAMLANMADANNIHAIADYOK(): void
    {
        // ASIL KORUMA: yarım set, tam set gibi GÖRÜNMEMELİ.
        $yazici = $this->yazici();
        $set = $yazici->baslat('20260901-030000');
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');

        // `tamamla()` çağrılmadı — gece koşusu burada kesildi.
        self::assertSame([], $yazici->setler(), 'Yarım set listede GÖRÜNMEMELİ.');
    }

    public function testYARIMSETSONRAKIKOSUMDATEMIZLENIR(): void
    {
        $yazici = $this->yazici();
        $yarim = $yazici->baslat('20260901-030000');
        $yazici->parcaEkle($yarim, 'veritabani.sql.enc', 'sql', 'DUMP');

        $temizlenen = $yazici->yarimlariTemizle();

        self::assertSame(1, $temizlenen, 'Yarım hazırlık dizini temizlenmeli.');
        self::assertSame([], glob($this->kok() . '/.hazirlik-*') ?: []);
    }

    public function testZORUNLUPARCAYOKSATAMAMLANMAZ(): void
    {
        // Manifest yazılmadan önce set doğrulanır: SQL'i olmayan bir "yedek"
        // indirilebilir olmamalı (B1: zorunlu parça yoksa set BAŞARISIZ).
        $yazici = $this->yazici();
        $set = $yazici->baslat('20260901-030000');
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');

        $this->expectException(RuntimeException::class);
        $yazici->tamamla($set);
    }

    public function testBASARISIZTAMAMLAMAHAZIRLIGIBIRAKIR(): void
    {
        // Başarısızlıkta nihai ad OLUŞMAZ; hazırlık dizini kalır ve bir
        // sonraki koşumda temizlenir. Kalıntı bırakmak, sessizce yarım bir
        // set üretmekten iyidir.
        $yazici = $this->yazici();
        $set = $yazici->baslat('20260901-030000');
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');

        try {
            $yazici->tamamla($set);
        } catch (RuntimeException) {
            // beklenen
        }

        self::assertSame([], $yazici->setler());
        self::assertNotSame([], glob($this->kok() . '/.hazirlik-*') ?: []);
    }

    public function testMANIFESTPARCAOZETLERINITASIR(): void
    {
        $yazici = $this->yazici();
        $set = $yazici->baslat('20260901-030000');
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
        $yol = $yazici->tamamla($set);

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($yol . '/MANIFEST.json'), true);
        $sql = $manifest['parcalar'][0];

        self::assertSame(hash('sha256', 'DUMP'), $sql['sha256'], 'Özet GERÇEK içerikten hesaplanmalı.');
        self::assertSame(4, $sql['boyut']);
        self::assertSame('1.2.2', $manifest['surum']);
        self::assertContains('0035_bildirimler', $manifest['migration_defteri']);
    }

    public function testMEDYAPARCALARABOLUNUR(): void
    {
        // B1: BACKUP_MEDIA_MAX_MB aşımında medya AYRI PARÇALARA bölünür,
        // TEK manifest altında. Eskiden arşiv tamamen atlanıyordu — büyük
        // medya klasörü olan kurulum, görsellerini hiç yedekleyemiyordu.
        $yazici = $this->yazici();
        $set = $yazici->baslat('20260901-030000');
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
        $yazici->parcaEkle($set, 'medya-001.zip.enc', 'medya', 'M1');
        $yazici->parcaEkle($set, 'medya-002.zip.enc', 'medya', 'M2');
        $yol = $yazici->tamamla($set);

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($yol . '/MANIFEST.json'), true);
        $medya = array_filter($manifest['parcalar'], static fn (array $p): bool => $p['tur'] === 'medya');

        self::assertCount(2, $medya, 'İki medya parçası TEK manifestte olmalı.');
    }

    public function testSETIDBENZERSIZ(): void
    {
        $yazici = $this->yazici();

        self::assertNotSame(
            $yazici->baslat('20260901-030000')['set_id'],
            $yazici->baslat('20260901-030001')['set_id'],
        );
    }

    public function testSETLERYENIDENESKIYESIRALI(): void
    {
        $yazici = $this->yazici();
        foreach (['20260901-030000', '20260902-030000'] as $damga) {
            $set = $yazici->baslat($damga);
            $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
            $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
            $yazici->tamamla($set);
        }

        $setler = $yazici->setler();

        self::assertCount(2, $setler);
        self::assertStringContainsString('20260902', $setler[0], 'En yeni set BAŞTA olmalı.');
    }

    public function testAYNISANIYEDEIKIYEDEKCAKISMAZ(): void
    {
        // GERÇEK SENARYO, test artefaktı değil: damga saniye çözünürlüklüdür.
        // Cron gece koşusu ile kullanıcının elle aldığı yedek aynı saniyeye
        // denk gelirse ikincisi — hazırlığı tamamlanmış olmasına rağmen —
        // KAYBOLURDU. Yedek almanın en olası anı, birinin "önce bir yedek
        // alayım" dediği andır ve o an cron saatine denk gelebilir.
        $yazici = $this->yazici();

        $yollar = [];
        foreach ([1, 2] as $_) {
            $set = $yazici->baslat('20260903-080904');
            $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
            $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
            $yollar[] = $yazici->tamamla($set);
        }

        self::assertNotSame($yollar[0], $yollar[1], 'İkinci set AYRI bir dizine gitmeli.');
        self::assertDirectoryExists($yollar[0]);
        self::assertDirectoryExists($yollar[1]);
        self::assertCount(2, $yazici->setler(), 'İki set de listede olmalı.');
    }
}
