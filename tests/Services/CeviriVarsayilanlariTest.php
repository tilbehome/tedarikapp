<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Config;
use App\Core\Connection;
use App\Core\Encrypter;
use App\Models\SettingsRepository;
use App\Services\Translation\CeviriAyarlari;
use App\Services\Translation\LlmTranslator;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * İE#20 D1 — LLM varsayılanları.
 *
 * NEDEN TEST EDİLİYOR: bayat bir model kimliği SESSİZ arızadır. Sağlayıcı
 * `model_not_found` döndürür, `translateProduct()` bunu yakalayıp yedek katmana
 * düşer ve kullanıcı hiçbir hata görmez — "çeviri neden zayıf?" sorusunun cevabı
 * hiçbir ekranda bulunmaz. Kimlikleri teste bağlamak, gelecekteki bir emeklilikte
 * kodun sessizce bozulmasını değil, süitin KIRILMASINI sağlar.
 */
final class CeviriVarsayilanlariTest extends TestCase
{
    private PDO $pdo;
    private CeviriAyarlari $ayarlar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE settings (`key` VARCHAR(190) PRIMARY KEY, value TEXT NULL)');

        $config = new Config([
            'APP_ENV' => 'local',
            'APP_URL' => 'https://ornek.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('a1b2c3d4', 8),
        ]);
        $connection = Connection::fromCallable(fn (): PDO => $this->pdo);

        $this->ayarlar = new CeviriAyarlari(new SettingsRepository($connection), new Encrypter($config));
    }

    /** @return list<array{string, string}> */
    public static function saglayiciModelleri(): array
    {
        return [
            [LlmTranslator::SAGLAYICI_DEEPSEEK, 'deepseek-v4-flash'],
            [LlmTranslator::SAGLAYICI_ANTHROPIC, 'claude-sonnet-4-6'],
            [LlmTranslator::SAGLAYICI_OPENAI, 'gpt-5.6-terra'],
        ];
    }

    #[DataProvider('saglayiciModelleri')]
    public function testVarsayilanModelSAGLAYICIBASINADOGRU(string $saglayici, string $beklenen): void
    {
        self::assertSame($beklenen, LlmTranslator::varsayilanModel($saglayici));
    }

    public function testEMEKLIKIMLIKLERGERIDONMEZ(): void
    {
        // Bu üç ad geçmişte kullanıldı ve artık GEÇERSİZ. Biri geri sızarsa
        // çeviri sessizce yedek katmana düşer.
        $emekli = ['deepseek-chat', 'claude-sonnet-5', 'gpt-4.1-mini'];

        foreach (LlmTranslator::SAGLAYICILAR as $saglayici) {
            self::assertNotContains(
                LlmTranslator::varsayilanModel($saglayici),
                $emekli,
                $saglayici . ' için emekli/geçersiz model kimliği kullanılıyor.',
            );
        }
    }

    public function testBOSAYARDAVARSAYILANDEEPSEEKTIR(): void
    {
        // Emir D1-2: varsayılan sağlayıcı deepseek. Gerekçe maliyettir — varsayılanın
        // kullanıcının cebini koruması gerekir.
        $ozet = $this->ayarlar->ozet();

        self::assertSame('deepseek', $ozet['saglayici']);
        self::assertSame('deepseek-v4-flash', $ozet['model']);
        self::assertSame('deepseek-v4-flash', $ozet['varsayilan_model']);
        self::assertSame('', $ozet['model_ham'], 'Ayar boşken ham değer BOŞ olmalı (panel yer tutucu gösterir).');
    }

    public function testSAGLAYICIDEGISINCEVARSAYILANMODELDEDEGISIR(): void
    {
        $this->ayarlar->saglayiciKaydet(LlmTranslator::SAGLAYICI_ANTHROPIC);

        $ozet = $this->ayarlar->ozet();

        self::assertSame('claude-sonnet-4-6', $ozet['varsayilan_model']);
        self::assertSame('claude-sonnet-4-6', $ozet['model'], 'Model ayarı boşken etkin değer sağlayıcıyı izlemeli.');
        self::assertSame('', $ozet['model_ham']);
    }

    public function testELLEYAZILANMODELVARSAYILANIEZER(): void
    {
        $this->ayarlar->modelKaydet('deepseek-reasoner');

        $ozet = $this->ayarlar->ozet();

        self::assertSame('deepseek-reasoner', $ozet['model']);
        self::assertSame('deepseek-reasoner', $ozet['model_ham']);
        self::assertSame('deepseek-v4-flash', $ozet['varsayilan_model'], 'Yer tutucu yine varsayılanı göstermeli.');
    }

    public function testMODELBOSALTILABILIR(): void
    {
        // Yanlış yazılmış bir model adı SİLİNEBİLMELİ; boş değer "varsayılana dön"dür.
        $this->ayarlar->modelKaydet('yanlis-model-adi');
        self::assertSame('yanlis-model-adi', $this->ayarlar->model());

        $this->ayarlar->modelKaydet('');

        self::assertSame('', $this->ayarlar->modelHam());
        self::assertSame('deepseek-v4-flash', $this->ayarlar->model());
    }

    public function testOZETSIRICERMEZ(): void
    {
        $this->ayarlar->anahtariKaydet('sk-cok-gizli-anahtar-1234');

        $ozet = $this->ayarlar->ozet();
        $json = json_encode($ozet, JSON_THROW_ON_ERROR);

        self::assertTrue($ozet['anahtar_tanimli']);
        self::assertStringNotContainsString('cok-gizli-anahtar', $json, 'Anahtar panele sızıyor.');
        self::assertSame('sk-…1234', $ozet['anahtar_onizleme']);
    }
}
