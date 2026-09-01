<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Config;
use App\Core\Encrypter;
use PHPUnit\Framework\TestCase;

/**
 * v1.2.1 D8 — ŞİFRELİ DEĞER KOLONA SIĞAR (CI'da yakalanan kusurun bekçisi).
 *
 * NE OLDU: `share_key_plain` `VARCHAR(12)` idi (6 haneli düz anahtar için).
 * D8 ile değer şifrelenince ~69 karaktere çıktı. SQLite kolon uzunluğunu
 * ZORLAMAZ, MySQL strict modda "Data too long" ile reddeder — kırmızı ancak
 * CI'ın MySQL koşan E2E işinde çıktı ve paylaşım sayfası 500 verdi.
 *
 * DERS: şema genişliği bir DAVRANIŞ sözleşmesidir ve yerel SQLite süiti onu
 * SINAMAZ. Bu yüzden sözleşme burada, veritabanından bağımsız olarak sınanır:
 * şifreli zarfın azami boyu ile migration'daki kolon genişliği karşılaştırılır.
 *
 * Bekçi, "yerelde geçti ama MySQL'de patladı" sınıfını erken yakalamak içindir.
 */
final class SifreliAlanGenisligiTest extends TestCase
{
    /** Migration'da tanımlı genişlik. */
    private const KOLON_GENISLIGI = 96;

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
        ]);
    }

    public function testMIGRATIONKOLONUGENISLETIYOR(): void
    {
        $migration = (string) file_get_contents(
            dirname(__DIR__, 2) . '/migrations/0036_paylasim_anahtari_sifreli_alan.php',
        );

        self::assertStringContainsString(
            'share_key_plain VARCHAR(' . self::KOLON_GENISLIGI . ')',
            $migration,
            'Kolon genişliği bu testin varsaydığı değerle aynı olmalı.',
        );
    }

    public function testSIFRELIANAHTARKOLONASIGAR(): void
    {
        // Altı hanelik anahtar alfabesinin TAMAMI denenir: zarf uzunluğu
        // içeriğe göre bir iki bayt oynayabilir ve sınıra dayanmış bir tasarım
        // ancak "bazı anahtarlarda" patlar — en sinsi hata türü.
        $sifreleyici = new Encrypter($this->config(), baglam: Encrypter::BAGLAM_PAYLASIM_ANAHTARI);
        $enUzun = 0;

        for ($i = 0; $i < 200; $i++) {
            $anahtar = '';
            for ($h = 0; $h < 6; $h++) {
                $anahtar .= '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)];
            }
            $enUzun = max($enUzun, strlen($sifreleyici->encrypt($anahtar)));
        }

        self::assertLessThanOrEqual(
            self::KOLON_GENISLIGI,
            $enUzun,
            'Şifreli anahtar kolona SIĞMIYOR — MySQL strict modda "Data too long" verir '
            . '(SQLite bunu sınamaz, kusur ancak CI/üretimde görünür).',
        );
    }

    public function testYEDEKARKAUCTADASIGAR(): void
    {
        // Sunucuda libsodium yoksa AES-GCM yedeği kullanılır ve zarf uzunluğu
        // FARKLIDIR. Yalnız tercih edilen arka ucu sınamak, sodium'suz bir
        // sunucuda aynı arızayı yeniden üretirdi (K39 dersi).
        $yedek = new Encrypter(
            $this->config(),
            useSodium: false,
            baglam: Encrypter::BAGLAM_PAYLASIM_ANAHTARI,
        );

        self::assertLessThanOrEqual(self::KOLON_GENISLIGI, strlen($yedek->encrypt('A1B2C3')));
    }

    public function testBASELINEOLCUTUGENISLIKTIR(): void
    {
        // İKİZ VAKA'NIN SQLITE TARAFI (PM merge şartı).
        //
        // Asıl davranış — "kolon var ama dar → baseline uygulanmış saymaz" —
        // gerçek MySQL'de sınanıyor (`MySqlIntegrationTest`), çünkü SQLite'ta
        // VARCHAR uzunluğu bağlayıcı değildir ve "dar kolon" diye bir durum
        // yoktur. Burada sınanan şey ÖLÇÜTÜN KENDİSİ: 0036 kaydı varlığa mı
        // bakıyor, genişliğe mi?
        //
        // Varlık ölçütü GENİŞLETME migration'ları için YANLIŞ YÜKLEMDİR: kolon
        // zaten vardı, değişen genişliktir. Varlığa bakan bir baseline,
        // genişletmeyi "uygulandı" diye deftere işler ve DDL hiç koşmaz.
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Migrator.php');
        $kayit = substr(
            $kaynak,
            (int) strpos($kaynak, "'0036_paylasim_anahtari_sifreli_alan' => ["),
            220,
        );

        self::assertStringContainsString(
            "'column_min_length' => ['lists', 'share_key_plain', " . self::KOLON_GENISLIGI . ']',
            $kayit,
            '0036 baseline kaydı kolon GENİŞLİĞİNE bakmalı; varlık ölçütü bu migration için yanlış yüklemdir.',
        );
        self::assertStringNotContainsString(
            "'column' => ['lists', 'share_key_plain']",
            $kayit,
            'Varlık ölçütü geri gelmiş.',
        );
    }
}
