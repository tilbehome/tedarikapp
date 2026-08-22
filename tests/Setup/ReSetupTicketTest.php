<?php

declare(strict_types=1);

namespace Tests\Setup;

use App\Core\Connection;
use App\Setup\ReSetupTicket;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * İE#19 G2 — yeniden kurulum bileti.
 *
 * Sınanan şey davranışın KENDİSİDİR: bilet süreli mi, tek kullanımlık mı, DB'de
 * yalnız özeti mi duruyor, yeni bilet eskisini geçersiz kılıyor mu.
 */
final class ReSetupTicketTest extends TestCase
{
    private PDO $pdo;
    private ReSetupTicket $bilet;
    private DateTimeImmutable $simdi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE settings (`key` VARCHAR(190) PRIMARY KEY, value TEXT NULL)');

        $this->bilet = new ReSetupTicket(Connection::fromCallable(fn (): PDO => $this->pdo));
        $this->simdi = new DateTimeImmutable('2026-08-22 12:00:00');
    }

    public function testUretilenBiletAyniAndaGecerlidir(): void
    {
        $token = $this->bilet->issue($this->simdi, 'app_key');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertTrue($this->bilet->validate($token, $this->simdi));
    }

    public function testDuzMetinBiletVeritabaninaYAZILMAZ(): void
    {
        $token = $this->bilet->issue($this->simdi, 'app_key');

        $saklanan = (string) $this->pdo->query("SELECT value FROM settings WHERE `key` = 'system.setup_ticket'")->fetchColumn();

        self::assertStringNotContainsString($token, $saklanan, 'Bilet DB\'de düz metin duruyor — DB\'yi okuyan geçerli bilet üretebilir.');
        self::assertStringContainsString(hash('sha256', $token), $saklanan, 'Özet saklanmalı.');
    }

    public function testOnBesDakikaSonraGecersizdir(): void
    {
        $token = $this->bilet->issue($this->simdi, 'app_key');

        self::assertTrue($this->bilet->validate($token, $this->simdi->modify('+899 seconds')));
        self::assertFalse(
            $this->bilet->validate($token, $this->simdi->modify('+901 seconds')),
            '15 dakikayı geçen bilet kabul edilmemeli.',
        );
    }

    public function testYeniBiletEskisiniGecersizKilar(): void
    {
        $eski = $this->bilet->issue($this->simdi, 'app_key');
        $yeni = $this->bilet->issue($this->simdi, 'admin:a@b.test');

        self::assertFalse($this->bilet->validate($eski, $this->simdi), 'Eski bilet hâlâ geçerli — iki kapı açık kalır.');
        self::assertTrue($this->bilet->validate($yeni, $this->simdi));
    }

    public function testTuketilenBiletBirDahaGecmez(): void
    {
        $token = $this->bilet->issue($this->simdi, 'app_key');
        $this->bilet->consume();

        self::assertFalse($this->bilet->validate($token, $this->simdi));
    }

    public function testBozukVeBosDegerlerReddedilir(): void
    {
        $this->bilet->issue($this->simdi, 'app_key');

        self::assertFalse($this->bilet->validate(null, $this->simdi));
        self::assertFalse($this->bilet->validate('', $this->simdi));
        self::assertFalse($this->bilet->validate('kisa', $this->simdi));
        self::assertFalse($this->bilet->validate(str_repeat('z', 64), $this->simdi));
    }

    public function testDescribeSirIcermez(): void
    {
        $token = $this->bilet->issue($this->simdi, 'admin:sahip@ornek.test');
        $ozet = $this->bilet->describe($this->simdi);

        self::assertNotNull($ozet);
        self::assertSame('admin:sahip@ornek.test', $ozet['issued_to']);
        self::assertSame(900, $ozet['expires_in_seconds']);
        self::assertStringNotContainsString($token, json_encode($ozet, JSON_THROW_ON_ERROR));
    }
}
