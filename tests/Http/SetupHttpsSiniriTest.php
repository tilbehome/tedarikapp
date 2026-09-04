<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Connection;
use App\Core\SetupAppBuilder;
use App\Setup\SetupLock;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;
use Tests\Support\TempDirectory;

/**
 * SERTLEŞTİRME v1.2.1 BLOK C1 — KURULUM HTTPS SINIRI (TDR-011, TDR-018).
 *
 * ÜÇ AYRI KUSUR:
 *
 * 1. `SetupHttpsGate` SINIFI VARDI AMA HİÇBİR ZİNCİRE EKLENMEMİŞTİ.
 *    `grep -rn SetupHttpsGate app/` yalnız sınıfın kendi dosyasını buluyordu.
 *    Yani DB şifresi, yönetici şifresi ve TOTP kodu üretimde düz HTTP
 *    üzerinden gönderilebiliyordu. Yazılmış, test edilmiş, gerekçesi
 *    belgelenmiş bir güvenlik kontrolü — ve hiç çalışmıyordu. Ölü kontrol,
 *    hiç yazılmamış kontrolden TEHLİKELİDİR: belgede "var" görünür.
 *
 * 2. LOOPBACK İSTİSNASI SALDIRGANIN KONTROLÜNDEKİ DEĞERE BAKIYORDU.
 *    Kapı `$request->getUri()->getHost()` okuyordu; o değer `Host` başlığından
 *    gelir ve istemci onu istediği gibi yazar. `Host: localhost` yazan düz bir
 *    HTTP isteği kapıyı AÇIYORDU. Loopback kararı yalnız `REMOTE_ADDR` ile
 *    verilebilir — bağlantının gerçekten nereden geldiği tek orada yazar.
 *
 * 3. GÜVENİLİR PROXY LİSTESİ YOKTU. Ters proxy arkasında şema `http` görünür
 *    ve HTTPS yalnız `X-Forwarded-Proto` ile bilinir. Bu başlığı koşulsuz
 *    okumak kapıyı tamamen anlamsız kılardı (istemci kendi yazar); hiç
 *    okumamak ise proxy arkasındaki gerçek HTTPS kurulumlarını kilitler.
 *    Karar: başlık YALNIZ `TRUSTED_PROXIES` içindeki bir uzak adresten
 *    geldiğinde okunur.
 */
final class SetupHttpsSiniriTest extends TestCase
{
    use TempDirectory;

    private ArraySession $session;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySession();
        $this->clock = new FrozenClock();

        $root = dirname(__DIR__, 2);
        copy($root . '/.env.example', $this->tempPath('.env.example'));
        mkdir($this->tempPath('setup/views'), 0775, true);
        foreach (['wizard.html', 'wizard.js', 'wizard.css'] as $file) {
            copy($root . '/setup/views/' . $file, $this->tempPath('setup/views/' . $file));
        }
    }

    /**
     * @param array<string, string> $server ek sunucu parametreleri
     * @param array<string, string> $headers
     */
    private function istek(
        string $path,
        array $server = [],
        array $headers = [],
        string $appEnv = 'production',
        string $guvenilirProxy = '',
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest(
            'POST',
            $path,
            // `+` SOLDAKİ anahtarı korur: varsayılan SAĞDA olmalı, yoksa
            // testin verdiği REMOTE_ADDR hiç uygulanmaz.
            $server + ['REMOTE_ADDR' => '203.0.113.7'],
        );
        foreach ($headers as $ad => $deger) {
            $request = $request->withHeader($ad, $deger);
        }

        $app = SetupAppBuilder::build(
            $this->tempRoot(),
            new NullLogger(),
            $this->session,
            $this->clock,
            setupLock: $this->acikKilit(),
            appEnv: $appEnv,
            guvenilirProxyler: $guvenilirProxy,
        );

        return $app->handle($request);
    }

    /**
     * HTTPS kapısına mı takıldı?
     *
     * DURUM KODUNA BAKMAK YETMEZ: aynı 403'ü CSRF kapısı da döndürür. Kod
     * yerine hata KİMLİĞİNE bakarız — yoksa test, kapı hiç çalışmasa bile
     * yeşil kalırdı.
     */
    private function httpsKapisi(ResponseInterface $yanit): bool
    {
        return str_contains((string) $yanit->getBody(), 'HTTPS_REQUIRED');
    }

    /** Kurulum yapılmamış (kilit yazılmamış) sistem — sihirbaz açık. */
    private function acikKilit(): SetupLock
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE settings (key TEXT NOT NULL PRIMARY KEY, value TEXT NULL)');

        return new SetupLock(Connection::fromCallable(static fn (): \PDO => $pdo), $this->tempPath('storage'));
    }

    public function testDUZHTTPUZERINDESIRADIMIBASLAMAZ(): void
    {
        $yanit = $this->istek('/api/setup/database');

        self::assertTrue($this->httpsKapisi($yanit), 'Üretimde düz HTTP\'de sır adımı GEÇMEMELİ.');
    }

    public function testHTTPSUZERINDEGECER(): void
    {
        $yanit = $this->istek('/api/setup/database', ['HTTPS' => 'on']);

        self::assertFalse($this->httpsKapisi($yanit), 'Gerçek HTTPS kapıya takılmamalı.');
    }

    public function testSAHTEHOSTBASLIGIYLAKAPIACILMAZ(): void
    {
        // KUSUR 2: kapı `Host` başlığına bakıyordu ve o başlığı istemci yazar.
        // `Host: localhost` yazan düz HTTP isteği loopback sanılıp geçiyordu.
        $yanit = $this->istek('/api/setup/database', [], ['Host' => 'localhost']);

        self::assertTrue($this->httpsKapisi($yanit), 'Host başlığı loopback KANITI DEĞİLDİR.');
    }

    public function testGERCEKLOOPBACKGECER(): void
    {
        // Aynı makineden gelen trafik ağa çıkmaz; geliştirme kurulumları böyle
        // çalışır. Karar `REMOTE_ADDR`a dayanır — o değeri istemci yazamaz.
        $yanit = $this->istek('/api/setup/database', ['REMOTE_ADDR' => '127.0.0.1']);

        self::assertFalse($this->httpsKapisi($yanit));
    }

    public function testGUVENILMEYENKAYNAKTANFORWARDEDBASLIGIREDDEDILIR(): void
    {
        // KUSUR 3: `X-Forwarded-Proto: https` yazmak kapıyı açamamalı.
        $yanit = $this->istek('/api/setup/database', [], ['X-Forwarded-Proto' => 'https']);

        self::assertTrue(
            $this->httpsKapisi($yanit),
            'Güvenilir proxy listesinde OLMAYAN kaynaktan gelen forwarded başlığı okunmamalı.',
        );
    }

    public function testGUVENILIRPROXYDENFORWARDEDBASLIGIOKUNUR(): void
    {
        $yanit = $this->istek(
            '/api/setup/database',
            ['REMOTE_ADDR' => '10.0.0.5'],
            ['X-Forwarded-Proto' => 'https'],
            guvenilirProxy: '10.0.0.5',
        );

        self::assertFalse(
            $this->httpsKapisi($yanit),
            'Ters proxy arkasındaki gerçek HTTPS kurulumu kilitlenmemeli.',
        );
    }

    public function testSIRTASIMAYANADIMLARKAPIYATAKILMAZ(): void
    {
        // Kapı YALNIZ sır taşıyan adımlar içindir; durum sorgusunu kilitlemek
        // sihirbazı düz HTTP'de tamamen açılamaz yapardı ve kullanıcı neyin
        // yanlış olduğunu göremezdi.
        $yanit = $this->istek('/api/setup/state');

        self::assertFalse($this->httpsKapisi($yanit));
    }

    public function testGELISTIRMEORTAMINDAKAPIKAPALI(): void
    {
        $yanit = $this->istek('/api/setup/database', [], [], appEnv: 'local');

        self::assertFalse($this->httpsKapisi($yanit));
    }

    public function testKAPIZINCIREGERCEKTENBAGLI(): void
    {
        // BEKÇİ: bu sınıfın en pahalı dersi, kapının YAZILMIŞ ama BAĞLANMAMIŞ
        // olmasıydı. Davranış testleri kapıyı dolaylı sınar; bu test doğrudan
        // bağlantının kendisini sınar ki biri middleware satırını silerse
        // sebebi anlaşılır bir hata alsın.
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/SetupAppBuilder.php');

        self::assertStringContainsString(
            'new SetupHttpsGate(',
            $kaynak,
            'SetupHttpsGate middleware zincirine EKLENMEMİŞ — kapı ölü.',
        );
    }

    public function testYAPILANDIRMAORNEGIANAHTARITASIR(): void
    {
        // Anahtar `.env.example`de yoksa kimse varlığını bilmez ve proxy
        // arkasındaki kurulumlar sebebini bulamadan kilitli kalır.
        $ornek = (string) file_get_contents(dirname(__DIR__, 2) . '/.env.example');

        self::assertStringContainsString('TRUSTED_PROXIES', $ornek);
    }
}
