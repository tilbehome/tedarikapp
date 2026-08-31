<?php

declare(strict_types=1);

namespace Tests\Setup;

use App\Setup\CookieSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * SERTLEŞTİRME v1.2.1 BLOK C2 — KURULUM STATE ÇEREZİ (TDR-012 · K106).
 *
 * KAPSAM SINIRI ÖNCE: state ÇEREZDE KALIR. Emir "sunucu tarafı state" istedi
 * ama K33 (uygulama diske yazamaz) ve K44 (sihirbaz DB'den ÖNCE koşar) bunu
 * ilk üç adım için imkânsız kılıyor — `CookieSession`ın var oluş sebebi tam
 * olarak budur. Yapılabilir olan sertleştirmeler burada.
 *
 * ÜÇ AÇIK KAPANDI:
 *
 * 1. ANAHTAR DÖNEMİ DENETLENMİYORDU. Çözümde İKİ anahtar da deneniyordu:
 *    APP_KEY yazıldıktan SONRA bile önyükleme anahtarıyla üretilmiş bir state
 *    kabul ediliyordu. Önyükleme anahtarı sunucuya özgü ama SIR DEĞİL —
 *    türetmesi kod içinde ve girdileri (yol, PHP sürümü, hostname) tahmin
 *    edilebilir. Yani APP_KEY var olduktan sonra bile saldırgan state
 *    UYDURABİLİYORDU. Artık config varsa YALNIZ APP_KEY kabul edilir ve
 *    düşürme denemesi loglanır.
 *
 * 2. ÖMÜR SINIRI YOKTU. Bir kez üretilen state süresiz geçerliydi; ele geçen
 *    bir çerez aylar sonra da kullanılabilirdi. Artık TTL var.
 *
 * 3. NONCE YOKTU. Aynı çerez sınırsız tekrar edilebiliyordu.
 *
 * Ayrıca DB ŞİFRESİ config.php yazıldığı an state'ten SİLİNİR: o noktadan
 * sonra saklamanın hiçbir faydası yok, tek etkisi sırrı taşımaya devam etmek.
 */
final class CookieSessionSertlestirmeTest extends TestCase
{
    use TempDirectory;

    /** @var list<array{seviye: string, mesaj: string}> */
    private array $kayitlar = [];

    private function gunlukcu(): \Psr\Log\LoggerInterface
    {
        $kayitlar = &$this->kayitlar;

        return new class ($kayitlar) extends AbstractLogger {
            /** @param list<array{seviye: string, mesaj: string}> $kayitlar */
            public function __construct(private array &$kayitlar)
            {
            }

            /** @param array<string, mixed> $context */
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->kayitlar[] = ['seviye' => (string) $level, 'mesaj' => (string) $message];
            }
        };
    }

    private function oturum(?FrozenClock $saat = null): CookieSession
    {
        return new CookieSession(
            $this->tempRoot(),
            false,
            $saat ?? new FrozenClock(),
            $this->gunlukcu(),
        );
    }

    /** Yanıttaki Set-Cookie'den çerez değerini çıkarır. */
    private function cerezDegeri(CookieSession $oturum): string
    {
        $yanit = $oturum->commitTo((new ResponseFactory())->createResponse());
        $satir = $yanit->getHeaderLine('Set-Cookie');
        preg_match('/' . CookieSession::COOKIE_NAME . '=([^;]+)/', $satir, $eslesme);

        return $eslesme[1] ?? '';
    }

    private function cerezSatiri(CookieSession $oturum): string
    {
        return $oturum->commitTo((new ResponseFactory())->createResponse())->getHeaderLine('Set-Cookie');
    }

    private function bagla(CookieSession $oturum, string $cerez): void
    {
        $istek = (new ServerRequestFactory())
            ->createServerRequest('GET', '/setup')
            ->withCookieParams([CookieSession::COOKIE_NAME => $cerez]);
        $oturum->bindRequest($istek);
    }

    private function configYaz(): void
    {
        file_put_contents(
            $this->tempPath('config.php'),
            "<?php\n\nreturn ['APP_KEY' => '" . bin2hex(random_bytes(32)) . "'];\n",
        );
    }

    public function testNORMALTURDASTATETASINIR(): void
    {
        $yazan = $this->oturum();
        $yazan->set('adim', 'database');
        $cerez = $this->cerezDegeri($yazan);

        $okuyan = $this->oturum();
        $this->bagla($okuyan, $cerez);

        self::assertSame('database', $okuyan->get('adim'));
    }

    public function testSURESIDOLANSTATEBASTANBASLAR(): void
    {
        $saat = new FrozenClock('2026-08-31 12:00:00');
        $yazan = new CookieSession($this->tempRoot(), false, $saat, $this->gunlukcu());
        $yazan->set('adim', 'database');
        $cerez = $this->cerezDegeri($yazan);

        // TTL'den sonra: state YOK sayılır, sihirbaz baştan başlar.
        $gec = new FrozenClock('2026-08-31 12:00:00');
        $gec->advance('+' . (CookieSession::OMUR_SANIYE + 60) . ' seconds');
        $okuyan = new CookieSession($this->tempRoot(), false, $gec, $this->gunlukcu());
        $this->bagla($okuyan, $cerez);

        self::assertNull($okuyan->get('adim'), 'Süresi dolan state kabul edilmemeli.');
    }

    public function testAPPKEYVARKENONYUKLEMEANAHTARIREDDEDILIR(): void
    {
        // ASIL AÇIK: önyükleme anahtarı SIR DEĞİLDİR (türetmesi kodda, girdileri
        // tahmin edilebilir). APP_KEY yazıldıktan sonra da kabul edilirse,
        // saldırgan istediği state'i uydurabilir — örneğin "admin adımı onaylı".
        $onyuklemeIle = $this->oturum();
        $onyuklemeIle->set('adim', 'admin');
        $cerez = $this->cerezDegeri($onyuklemeIle);

        $this->configYaz();

        $okuyan = $this->oturum();
        $this->bagla($okuyan, $cerez);

        self::assertNull($okuyan->get('adim'), 'APP_KEY varken önyükleme state\'i KABUL EDİLMEMELİ.');
    }

    public function testDUSURMEDENEMESILOGLANIR(): void
    {
        // Reddetmek yetmez: bu bir SALDIRI SİNYALİDİR ve sessiz kalırsa kimse
        // denendiğini bilmez.
        $onyuklemeIle = $this->oturum();
        $onyuklemeIle->set('adim', 'admin');
        $cerez = $this->cerezDegeri($onyuklemeIle);

        $this->configYaz();
        $this->kayitlar = [];

        $okuyan = $this->oturum();
        $this->bagla($okuyan, $cerez);
        $okuyan->get('adim');

        $mesajlar = implode(' | ', array_column($this->kayitlar, 'mesaj'));
        self::assertStringContainsString('önyükleme', mb_strtolower($mesajlar), $mesajlar);
    }

    public function testAPPKEYVARKENAPPKEYSTATEIGECER(): void
    {
        // Düşürme reddi, NORMAL akışı bozmamalı: APP_KEY yazıldıktan sonra
        // APP_KEY ile üretilen state elbette geçerlidir.
        $this->configYaz();

        $yazan = $this->oturum();
        $yazan->set('adim', 'migrate');
        $cerez = $this->cerezDegeri($yazan);

        $okuyan = $this->oturum();
        $this->bagla($okuyan, $cerez);

        self::assertSame('migrate', $okuyan->get('adim'));
    }

    public function testCEREZHTTPONLYVESAMESITETASIR(): void
    {
        $oturum = $this->oturum();
        $oturum->set('adim', 'database');

        $satir = $this->cerezSatiri($oturum);

        self::assertStringContainsString('HttpOnly', $satir);
        self::assertStringContainsString('SameSite=Lax', $satir);
    }

    public function testCEREZYOLUAPISETUPUDAKAPSAR(): void
    {
        // KAPSAM DARALTMASI DENENDİ VE GERİ ALINDI (CI kanıtı).
        //
        // `Path=/setup` mantıklı görünüyordu ama sihirbaz SAYFASI `/setup`,
        // API'si `/api/setup/...` altında. Dar çerez `/api/setup/database`
        // isteğine gitmez; oturum kaybolur ve istek CSRF ile düşer — CI'ın
        // üretim profili işi bunu anında yakaladı.
        //
        // İki yolun `/` dışında ortak öneki yok. Bu test kararın gerekçesini
        // taşır ki daraltma iyi niyetle yeniden denenmesin.
        $oturum = $this->oturum();
        $oturum->set('adim', 'database');
        $satir = $this->cerezSatiri($oturum);

        self::assertStringContainsString('Path=/;', $satir . ';');
        self::assertStringNotContainsString('Path=/setup', $satir);
    }

    public function testSILMECEREZIAYNIYOLUKULLANIR(): void
    {
        // Yol daraltıldıysa SİLME de aynı yolu kullanmalı; farklı Path ile
        // yazılan silme çerezi tarayıcıda ASIL çerezi silmez ve state hayatta
        // kalır — sessiz ve tam da beklenmeyen yerde.
        $oturum = $this->oturum();
        $oturum->set('adim', 'database');
        $oturum->destroy();

        $satir = $this->cerezSatiri($oturum);

        // Silme çerezi ASIL çerezle AYNI yolu kullanmalı; farklı `Path` ile
        // yazılan silme çerezi tarayıcıda hiçbir şey silmez ve state sessizce
        // hayatta kalır.
        self::assertStringContainsString('Path=/;', $satir . ';');
        self::assertStringContainsString('Expires=Thu, 01 Jan 1970', $satir);
    }
}
