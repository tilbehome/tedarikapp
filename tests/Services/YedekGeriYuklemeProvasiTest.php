<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Yedek\YedekManifesti;
use App\Services\Yedek\YedekProvasi;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * v1.2.2 BLOK B3 — GERİ YÜKLEME PROVASI (B5 borcu: HİÇ DENENMEMİŞTİ).
 *
 * DENETİMİN EN AĞIR TESPİTİ buydu: yedek alınıyordu, ama geri yüklenip
 * yüklenemeyeceği hiç sınanmamıştı. Denenmemiş bir yedek, bir yedek değildir
 * — yalnız yedek olduğuna dair bir inançtır. Gerçek gün geldiğinde eksik
 * çıkması, hiç yedek almamış olmaktan daha kötüdür: o inanç yüzünden başka
 * önlem alınmamıştır.
 *
 * PROVA ÜÇ ŞEYİ SORAR:
 *   1. Manifest okunuyor ve set TAM mı?
 *   2. Her parçanın diskteki SHA-256'sı manifesttekiyle AYNI mı? (bit
 *      çürümesi, yarım kopyalama, kırpılmış indirme)
 *   3. Yedeğin migration defteri, geri yükleneceği kodun beklediği defterle
 *      uyumlu mu? (eski yedeği yeni koda dökmek sessizce uyumsuz bir sistem
 *      üretir)
 *
 * BU SINIF GERİ YÜKLEME YAPMAZ — doğrular. Yıkıcı işlem ayrı bir komuttur
 * (`bin/restore.php`); doğrulamayı ondan ayırmak, "önce bak, sonra dök"
 * sırasını mümkün kılar.
 */
final class YedekGeriYuklemeProvasiTest extends TestCase
{
    use TempDirectory;

    /** Diskte gerçek bir yedek seti kurar. */
    private function setKur(array $ustyaz = [], bool $manifestYaz = true): string
    {
        $dizin = $this->tempPath('yedek-seti');
        if (!is_dir($dizin)) {
            mkdir($dizin, 0o775, true);
        }

        $parcalar = [];
        foreach ([
            ['veritabani.sql.enc', 'sql', 'SQL-DUMP-ICERIGI'],
            ['ayarlar.files.enc', 'config', 'CONFIG-ICERIGI'],
            ['medya-001.zip.enc', 'medya', 'MEDYA-ICERIGI'],
        ] as [$ad, $tur, $icerik]) {
            file_put_contents($dizin . '/' . $ad, $icerik);
            $parcalar[] = [
                'ad' => $ad,
                'tur' => $tur,
                'boyut' => strlen($icerik),
                'sha256' => hash('sha256', $icerik),
            ];
        }

        if ($manifestYaz) {
            $manifest = new YedekManifesti(array_merge([
                'set_id' => 'aaaa1111-2222-4333-8444-555566667777',
                'olusturuldu' => '2026-09-01T03:00:00+03:00',
                'surum' => '1.2.2',
                'sifreleme' => 'aes-256-gcm',
                'parcalar' => $parcalar,
                'migration_defteri' => ['0035_bildirimler', '0036_paylasim_anahtari_sifreli_alan'],
            ], $ustyaz));
            file_put_contents($dizin . '/MANIFEST.json', $manifest->jsonOlarak());
        }

        return $dizin;
    }

    public function testSAGLAMSETPROVAYIGECER(): void
    {
        $sonuc = (new YedekProvasi())->dogrula(
            $this->setKur(),
            ['0035_bildirimler', '0036_paylasim_anahtari_sifreli_alan'],
        );

        self::assertTrue($sonuc['gecerli'], json_encode($sonuc, JSON_UNESCAPED_UNICODE));
        self::assertSame([], $sonuc['sorunlar']);
        self::assertSame(3, $sonuc['dogrulanan_parca']);
    }

    public function testMANIFESTYOKSAPROVADUSERr(): void
    {
        // Manifest EN SONDA yazılır; yoksa yedek YARIDA KALMIŞ demektir.
        $sonuc = (new YedekProvasi())->dogrula($this->setKur(manifestYaz: false), []);

        self::assertFalse($sonuc['gecerli']);
        self::assertStringContainsString('MANIFEST', implode(' ', $sonuc['sorunlar']));
    }

    public function testEKSIKDOSYAYAKALANIR(): void
    {
        // Manifest "var" diyor, disk "yok" diyor. Bu ayrım ancak provada çıkar.
        $dizin = $this->setKur();
        unlink($dizin . '/medya-001.zip.enc');

        $sonuc = (new YedekProvasi())->dogrula($dizin, []);

        self::assertFalse($sonuc['gecerli']);
        self::assertStringContainsString('medya-001.zip.enc', implode(' ', $sonuc['sorunlar']));
    }

    public function testBOZULMUSDOSYAYAKALANIR(): void
    {
        // Bit çürümesi / yarım kopyalama: dosya VAR, boyut bile tutabilir,
        // ama içerik değişmiştir. Yalnız SHA yakalar.
        $dizin = $this->setKur();
        file_put_contents($dizin . '/veritabani.sql.enc', 'BOZULMUS-ICERIK!');

        $sonuc = (new YedekProvasi())->dogrula($dizin, []);

        self::assertFalse($sonuc['gecerli']);
        self::assertStringContainsString('özet uyuşmadı', implode(' ', $sonuc['sorunlar']));
    }

    public function testEKSIKMIGRATIONUYARIR(): void
    {
        // Yedek ESKİ şemadan; kod daha yeni. Geri yükleme mümkün ama SONRASINDA
        // migration koşmak gerekir. Bu bir hata değil, UYARIDIR — sessiz
        // kalırsa kullanıcı yarım bir şemayla çalışmaya devam eder.
        $sonuc = (new YedekProvasi())->dogrula(
            $this->setKur(),
            ['0035_bildirimler', '0036_paylasim_anahtari_sifreli_alan', '0037_yeni_bir_sey'],
        );

        self::assertTrue($sonuc['gecerli'], 'Eksik migration geri yüklemeyi ENGELLEMEZ.');
        self::assertContains('0037_yeni_bir_sey', $sonuc['yedekte_olmayan_migrationlar']);
    }

    public function testYEDEKTEFAZLAMIGRATIONVARSAUYARIR(): void
    {
        // Yedek YENİ, kod ESKİ: bu tehlikelidir — geri yüklenen veri, kodun
        // tanımadığı bir şemaya aittir. Engellenmez ama BAĞIRIR.
        $sonuc = (new YedekProvasi())->dogrula($this->setKur(), ['0035_bildirimler']);

        self::assertContains('0036_paylasim_anahtari_sifreli_alan', $sonuc['kodda_olmayan_migrationlar']);
        self::assertTrue($sonuc['ileri_surum_uyarisi']);
    }

    public function testDEFTERBEKLENTISIVERILMEZSEKARSILASTIRMAYAPILMAZ(): void
    {
        // Boş beklenti = "karşılaştırma istemiyorum". Bunu "hiçbir migration
        // beklemiyorum" diye yorumlamak, her yedeği ileri sürüm sanardı.
        $sonuc = (new YedekProvasi())->dogrula($this->setKur(), []);

        self::assertTrue($sonuc['gecerli']);
        self::assertFalse($sonuc['ileri_surum_uyarisi']);
    }

    public function testRAPORINSANOKUNURDUR(): void
    {
        // Prova çıktısı rapora giriyor (PM şartı): sayılarla konuşmalı.
        $sonuc = (new YedekProvasi())->dogrula($this->setKur(), []);
        $rapor = (new YedekProvasi())->rapor($sonuc);

        self::assertStringContainsString('3 parça', $rapor);
        self::assertStringContainsString('GEÇERLİ', $rapor);
    }
}
