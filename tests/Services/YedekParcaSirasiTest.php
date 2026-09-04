<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Yedek\YedekManifesti;
use App\Services\Yedek\YedekProvasi;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * v1.2.2 B1 EK ŞART 1 — MANİFEST PARÇALARI BAĞLAR.
 *
 * PM şartı: "tam seti tek zip indirme yok" kararının bedeli, parçaların
 * birbirine bağlı olduğunun MANİFESTTE yazılı olmasıdır. Aksi hâlde kullanıcı
 * beş parçanın üçünü indirir, geri yükler ve eksikliği ancak veriye baktığında
 * — belki haftalar sonra — fark eder.
 *
 * ÜÇ BAĞ:
 *   1. `sira` — parçanın setteki yeri. Medya parçaları sırayla açılmalıdır;
 *      002'yi 001'den önce açmak, aynı ada sahip dosyaların yanlış sürümünü
 *      bırakabilir.
 *   2. `toplam_parca` — sette KAÇ parça olduğu. Eksik parçayı ancak beklenen
 *      sayıyı bilerek anlarsınız; elinizdekileri saymak yetmez.
 *   3. `sha256` — her parçanın kimliği (zaten vardı).
 *
 * KISMİ SETTEN SESSİZ GERİ YÜKLEME İMKÂNSIZ OLMALI: bu dosyadaki negatif
 * vakalar tam olarak bunu zorlar.
 */
final class YedekParcaSirasiTest extends TestCase
{
    use TempDirectory;

    /** @return list<array<string, mixed>> */
    private function parcalar(): array
    {
        return [
            ['ad' => 'veritabani.sql.enc', 'tur' => 'sql', 'sira' => 1, 'boyut' => 10, 'sha256' => str_repeat('a', 64)],
            ['ad' => 'ayarlar.files.enc', 'tur' => 'config', 'sira' => 2, 'boyut' => 10, 'sha256' => str_repeat('b', 64)],
            ['ad' => 'medya-001.zip.enc', 'tur' => 'medya', 'sira' => 3, 'boyut' => 10, 'sha256' => str_repeat('c', 64)],
            ['ad' => 'medya-002.zip.enc', 'tur' => 'medya', 'sira' => 4, 'boyut' => 10, 'sha256' => str_repeat('d', 64)],
        ];
    }

    private function manifest(array $ustyaz = []): YedekManifesti
    {
        return new YedekManifesti(array_merge([
            'set_id' => 'cccc1111-2222-4333-8444-555566667777',
            'olusturuldu' => '2026-09-03T03:00:00+03:00',
            'surum' => '1.2.2',
            'sifreleme' => 'aes-256-gcm',
            'parcalar' => $this->parcalar(),
            'toplam_parca' => 4,
            'migration_defteri' => ['0035_bildirimler'],
        ], $ustyaz));
    }

    public function testSIRALIVETAMSETGECERLI(): void
    {
        self::assertTrue($this->manifest()->tamMi(), implode(', ', $this->manifest()->eksikler()));
        self::assertSame(4, $this->manifest()->toplamParca());
    }

    public function testTOPLAMPARCAUYUSMAZSAREDDEDILIR(): void
    {
        // Manifest "5 parça" diyor, listede 4 var: bir parça manifest yazılırken
        // düşmüş. Elde olanı saymak bunu YAKALAYAMAZDI.
        $eksik = $this->manifest(['toplam_parca' => 5]);

        self::assertFalse($eksik->tamMi());
        self::assertStringContainsString('toplam', implode(' ', $eksik->eksikler()));
    }

    public function testSIRAYOKSAREDDEDILIR(): void
    {
        // Sırasız manifest, bu şarttan ÖNCE yazılmış bir settir. Sessizce
        // kabul etmek, sıralamanın garanti edilmediği bir seti geri
        // yüklenebilir saymak olurdu — fail-closed.
        $parcalar = $this->parcalar();
        unset($parcalar[2]['sira']);

        self::assertFalse($this->manifest(['parcalar' => array_values($parcalar)])->tamMi());
    }

    public function testSIRATEKRARLANAMAZ(): void
    {
        $parcalar = $this->parcalar();
        $parcalar[3]['sira'] = 3;

        $bozuk = $this->manifest(['parcalar' => $parcalar]);

        self::assertFalse($bozuk->tamMi());
        self::assertStringContainsString('sıra', implode(' ', $bozuk->eksikler()));
    }

    public function testSIRABOSLUKSUZOLMALI(): void
    {
        // 1,2,3,5 → dördüncü parça YOK. Sıra numarası bunu ele verir; dosya
        // saymak vermez (dört dosya var, biri yanlış numarada).
        $parcalar = $this->parcalar();
        $parcalar[3]['sira'] = 5;

        self::assertFalse($this->manifest(['parcalar' => $parcalar, 'toplam_parca' => 4])->tamMi());
    }

    public function testPARCALARSIRAYLADONER(): void
    {
        // Geri yükleme sırası: SQL → config → medya 001 → medya 002.
        // Manifest karışık yazılmış olsa bile okuma SIRALI olmalı.
        $karisik = array_reverse($this->parcalar());

        $sirali = $this->manifest(['parcalar' => $karisik])->siraliParcalar();

        self::assertSame(
            ['veritabani.sql.enc', 'ayarlar.files.enc', 'medya-001.zip.enc', 'medya-002.zip.enc'],
            array_column($sirali, 'ad'),
        );
    }

    public function testYAZICISIRAVETOPLAMYAZAR(): void
    {
        // Bağlar manifestte yazılı DEĞİLSE şart kâğıt üstünde kalır.
        $kok = $this->tempPath('backups-sira');
        @mkdir($kok, 0o775, true);

        $yazici = new \App\Services\Yedek\YedekSetiYazici($kok, '1.2.2', ['0035_bildirimler']);
        $set = $yazici->baslat('20260903-120000');
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
        $yazici->parcaEkle($set, 'medya-001.zip.enc', 'medya', 'M1');
        $dizin = $yazici->tamamla($set);

        $manifest = YedekManifesti::jsondan(
            (string) file_get_contents($dizin . '/' . YedekProvasi::MANIFEST_ADI),
        );

        self::assertSame(3, $manifest->toplamParca());
        self::assertSame([1, 2, 3], array_column($manifest->siraliParcalar(), 'sira'));
    }

    public function testPROVAEKSIKPARCADAKIRMIZIDURUR(): void
    {
        // UÇTAN UCA NEGATİF VAKA: kısmi setten sessiz geri yükleme imkânsız.
        $kok = $this->tempPath('backups-eksik');
        @mkdir($kok, 0o775, true);

        $yazici = new \App\Services\Yedek\YedekSetiYazici($kok, '1.2.2', []);
        $set = $yazici->baslat('20260903-130000');
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP');
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
        $yazici->parcaEkle($set, 'medya-001.zip.enc', 'medya', 'M1');
        $yazici->parcaEkle($set, 'medya-002.zip.enc', 'medya', 'M2');
        $dizin = $yazici->tamamla($set);

        // Kullanıcı yalnız üç parçayı indirdi (ya da biri aktarımda düştü).
        unlink($dizin . '/medya-002.zip.enc');

        $sonuc = (new YedekProvasi())->dogrula($dizin, []);

        self::assertFalse($sonuc['gecerli'], 'Eksik parçalı set GEÇERLİ sayılamaz.');
        self::assertStringContainsString('medya-002.zip.enc', implode(' ', $sonuc['sorunlar']));
    }
}
