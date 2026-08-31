<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Config;
use App\Core\Encrypter;
use PHPUnit\Framework\TestCase;

/**
 * SERTLEŞTİRME v1.2.1 BLOK D8 — ERİŞİM ANAHTARI DİNLENMEDE ŞİFRELİ (TDR-034).
 *
 * DURUM: `share_key_plain` DÜZ METİN saklanıyordu ve bu BİLİNÇLİ bir istisnaydı
 * (K62): anahtar 6 hanedir ve panelin onu KULLANICIYA GÖSTERMESİ gerekir
 * ("firmaya şu kodu ilet"). Tek yönlü özet saklansaydı kod bir daha okunamazdı.
 *
 * AMA "GÖSTERİLEBİLİR OLMASI" ile "DÜZ SAKLANMASI" aynı şey değildir. Veritabanı
 * yedeği sızarsa (off-site yedek, paylaşımlı hosting komşusu, çalınan dump)
 * bütün paylaşım anahtarları okunur hâlde çıkar. Geri döndürülebilir ŞİFRELEME
 * hem gösterilebilirliği korur hem de yedeği tek başına yetersiz kılar.
 *
 * AYRI BAĞLAM ANAHTARI: TOTP sırrıyla AYNI türetilmiş anahtar KULLANILMAZ.
 * Bir bağlamda çözülen şifreli metnin başka bağlama taşınabilmesi (confused
 * deputy) böyle engellenir; HKDF etiketi bağlamı ayırır.
 */
final class ErisimAnahtariSifreliTest extends TestCase
{
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

    public function testPAYLASIMBAGLAMITOTPTENFARKLIANAHTARURETIR(): void
    {
        $config = $this->config();
        $totp = new Encrypter($config);
        $paylasim = new Encrypter($config, baglam: Encrypter::BAGLAM_PAYLASIM_ANAHTARI);

        $sifreli = $paylasim->encrypt('A1B2C3');

        // Farklı bağlam → farklı alt anahtar → çözemez.
        $this->expectException(\RuntimeException::class);
        $totp->decrypt($sifreli);
    }

    public function testAYNIBAGLAMCOZER(): void
    {
        $config = $this->config();
        $yazan = new Encrypter($config, baglam: Encrypter::BAGLAM_PAYLASIM_ANAHTARI);
        $okuyan = new Encrypter($config, baglam: Encrypter::BAGLAM_PAYLASIM_ANAHTARI);

        self::assertSame('A1B2C3', $okuyan->decrypt($yazan->encrypt('A1B2C3')));
    }

    public function testVARSAYILANBAGLAMDEGISMEDI(): void
    {
        // GERİYE DÖNÜK UYUM: mevcut TOTP sırları varsayılan bağlamla yazıldı.
        // Varsayılan etiket değişseydi kurulu her sistemde 2FA çözülemez olurdu.
        $config = $this->config();

        self::assertSame(
            'A1B2C3',
            (new Encrypter($config))->decrypt((new Encrypter($config))->encrypt('A1B2C3')),
        );
    }

    public function testSIFRELIMETINDUZDEGERITASIMAZ(): void
    {
        $sifreli = (new Encrypter($this->config(), baglam: Encrypter::BAGLAM_PAYLASIM_ANAHTARI))->encrypt('A1B2C3');

        self::assertStringNotContainsString('A1B2C3', $sifreli);
    }
}
