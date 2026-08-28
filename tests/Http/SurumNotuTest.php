<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Controllers\SurumNotuController;
use App\Core\AppVersion;
use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * V3-B B4 — "YENİLİKLER" BALONU.
 *
 * İki şey sınanır:
 *   1. Not KULLANICI DİLİYLE ve dosyadan gelir; kodda kopyası YOKTUR.
 *   2. Okundu işareti SUNUCUDA tutulur — ikinci cihazda balon yeniden çıkmaz.
 *
 * Ayrıca yol geçişi (path traversal) kapalıdır: sürüm adı dosya yoluna girer.
 */
final class SurumNotuTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
    }

    public function testGUNCELSURUMNOTUDOSYADANGELIR(): void
    {
        $veri = $this->json($this->call('GET', '/api/surum-notu'))['data'];

        self::assertSame(AppVersion::VALUE, $veri['surum']);
        self::assertNotEmpty($veri['maddeler'], 'Geçerli sürümün notu docs/surum-notlari altında olmalı.');
        self::assertTrue($veri['gorulmedi'], 'Hiç işaretlenmemişken balon görünmeli.');
    }

    public function testGORULDUISARETISUNUCUDATUTULUR(): void
    {
        $this->call('POST', '/api/surum-notu/goruldu', [], [Csrf::HEADER => $this->csrf]);

        $veri = $this->json($this->call('GET', '/api/surum-notu'))['data'];

        self::assertFalse($veri['gorulmedi'], 'İşaretten sonra balon bir daha çıkmamalı.');
        self::assertSame(
            AppVersion::VALUE,
            $this->settingsValue(SurumNotuController::KEY_GORULEN),
            'İşaret ayarlara yazılmalı — tarayıcıya değil.',
        );
    }

    public function testGECMISTEENYENISURUMBASTA(): void
    {
        /** @var array{surumler: list<array{surum: string}>} $veri */
        $veri = $this->json($this->call('GET', '/api/surum-notu/gecmis'))['data'];

        self::assertNotEmpty($veri['surumler']);
        $surumler = array_column($veri['surumler'], 'surum');
        $sirali = $surumler;
        usort($sirali, static fn (string $a, string $b): int => version_compare($b, $a));

        self::assertSame($sirali, $surumler, 'Sürümler metin sırasıyla değil SÜRÜM sırasıyla dizilmeli.');
    }

    public function testMADDELERDEHTMLYOK(): void
    {
        // Not dosyaları panele DÜZ METİN olarak gider; HTML gönderilseydi
        // docs/ altındaki bir dosya XSS yüzeyi olurdu.
        /** @var array{maddeler: list<string>} $veri */
        $veri = $this->json($this->call('GET', '/api/surum-notu'))['data'];

        foreach ($veri['maddeler'] as $madde) {
            self::assertStringNotContainsString('<', $madde, 'Sürüm notu maddesi HTML taşıyamaz.');
        }
    }

    private function settingsValue(string $anahtar): ?string
    {
        $statement = $this->pdo->prepare('SELECT value FROM settings WHERE key = :k');
        $statement->execute(['k' => $anahtar]);
        $deger = $statement->fetchColumn();

        return is_string($deger) ? $deger : null;
    }
}
