<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\ActivityLog;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * v1.2.2 B2 — APP_KEY EMANETİ.
 *
 * SORUNUN TAM HÂLİ: yedekler APP_KEY ile şifrelenir. Sunucu tamamen giderse
 * (hesap kapandı, disk gitti, sağlayıcı değişti) elinizde şifreli yedekler
 * kalır ve onları açacak anahtar YOKTUR — çünkü anahtar da o sunucudaydı.
 * Yedeklerin varlığı bu durumda hiçbir şey ifade etmez; en kötü sürprizdir,
 * çünkü tam da "yedeğim var" diye rahat olduğunuz noktada patlar.
 *
 * EMANET: anahtarı kullanıcının GÖREBİLECEĞİ ve sunucu dışında saklayacağı
 * tek bir yol. Yıkıcı olmayan ama SIZDIRICI bir işlemdir — anahtarı gören,
 * bütün yedekleri açabilir. Bu yüzden kapılar sıkıdır:
 *
 *   1. Girişli oturum YETMEZ: şifre YENİDEN istenir (oturum çalınmışsa,
 *      çalan kişi şifreyi bilmez).
 *   2. 2FA tanımlıysa kod da istenir.
 *   3. Her gösterim aktiviteye yazılır — kim, ne zaman gördü, geriye dönük
 *      sorulabilsin.
 *
 * "0'DA GİZLİ" KURALI BURADA GEÇMEZ (PM): kart, anahtar hiç görüntülenmemiş
 * olsa da kalıcı uyarısıyla durur. Uyarının amacı bir sayıyı bildirmek değil,
 * HENÜZ YAPILMAMIŞ bir işi hatırlatmaktır; gizlenirse hatırlatamaz.
 */
final class AppKeyEmanetiTest extends AuthTestCase
{
    private const SIFRE = 'cok-gizli-sifre';

    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => self::SIFRE]);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
    }

    /** @param array<string, mixed> $body */
    private function goster(array $body): ResponseInterface
    {
        return $this->call('POST', '/api/system/app-key/reveal', $body, [Csrf::HEADER => $this->csrf]);
    }

    private function appKey(): string
    {
        return (string) $this->config()->get('APP_KEY');
    }

    public function testGIRISLIOTURUMTEKBASINAYETMEZ(): void
    {
        // Oturum açık ama şifre verilmedi: anahtar GÖSTERİLMEZ.
        $response = $this->goster([]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringNotContainsString($this->appKey(), (string) $response->getBody());
    }

    public function testYANLISSIFREREDDEDILIR(): void
    {
        $response = $this->goster(['password' => 'yanlis-parola']);

        self::assertSame(401, $response->getStatusCode());
        self::assertStringNotContainsString($this->appKey(), (string) $response->getBody());
    }

    public function testDOGRUSIFREANAHTARIGOSTERIR(): void
    {
        $response = $this->goster(['password' => self::SIFRE]);

        self::assertSame(200, $response->getStatusCode());

        $govde = $this->json($response);
        self::assertSame($this->appKey(), $govde['data']['app_key']);
        // Kurtarma metni anahtarla BİRLİKTE gider: anahtarı saklayan kişi,
        // onunla ne yapacağını da elinde tutmalı. Ayrı bir belgeye bakmak
        // zorunda kalmak, felaket anında bakılmayacak demektir.
        self::assertNotSame('', $govde['data']['kurtarma_metni']);
        self::assertStringContainsString('bin/restore.php', $govde['data']['kurtarma_metni']);
    }

    public function testGOSTERIMAKTIVITEYEYAZILIR(): void
    {
        $this->goster(['password' => self::SIFRE]);

        $satir = $this->pdo
            ->query("SELECT action FROM activity_log WHERE action = 'app_key_revealed' ORDER BY id DESC LIMIT 1")
            ->fetchColumn();

        self::assertSame('app_key_revealed', $satir, 'Anahtarı kimin gördüğü kayda geçmeli.');
    }

    public function testBASARISIZDENEMEDEAKTIVITEYEYAZILIR(): void
    {
        // Başarısız deneme de kaydedilir: anahtarı almaya ÇALIŞAN birinin izi,
        // başaranınki kadar önemlidir.
        $this->goster(['password' => 'yanlis-parola']);

        $satir = $this->pdo
            ->query("SELECT action FROM activity_log WHERE action = 'app_key_reveal_failed' ORDER BY id DESC LIMIT 1")
            ->fetchColumn();

        self::assertSame('app_key_reveal_failed', $satir);
    }

    public function testETIKETLERSOZLUKTEVAR(): void
    {
        // Aktivite etiketi bekçisiyle aynı sözleşme: ham eylem adı ekranda
        // görünmez.
        $etiketler = (string) file_get_contents(
            dirname(__DIR__, 2) . '/frontend/src/lib/activityLabels.ts',
        );

        self::assertStringContainsString('app_key_revealed', $etiketler);
        self::assertStringContainsString('app_key_reveal_failed', $etiketler);
    }

    public function testSABITSINIFTAKAYITLI(): void
    {
        self::assertSame('app_key_revealed', ActivityLog::APP_KEY_REVEALED);
        self::assertSame('app_key_reveal_failed', ActivityLog::APP_KEY_REVEAL_FAILED);
    }
}
