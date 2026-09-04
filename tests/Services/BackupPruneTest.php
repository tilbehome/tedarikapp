<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Config;
use App\Services\BackupService;
use PHPUnit\Framework\TestCase;

/**
 * İE#11 EK-2 (1) — yedek saklama: eskiler silinir, EN YENİ 5 her koşulda kalır,
 * desen dışı dosyalara ASLA dokunulmaz.
 *
 * v1.2.2 B1: saklama birimi artık DOSYA değil SET. Kural aynı, taşıdığı şey
 * değişti: bir set bütün olarak gider — parçalarından biri kalırsa manifestsiz
 * bir yetim olur ve kimse ne olduğunu bilemez.
 */
final class BackupPruneTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/prune-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/storage/backups', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/storage/backups/*') ?: [] as $yol) {
            if (is_dir($yol)) {
                foreach (glob($yol . '/*') ?: [] as $parca) {
                    @unlink($parca);
                }
                @rmdir($yol);

                continue;
            }
            @unlink($yol);
        }
        parent::tearDown();
    }

    private function service(): BackupService
    {
        return new BackupService(new Config([
            'APP_ENV' => 'local', 'APP_URL' => 'https://t.test', 'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('ab', 32), 'DB_HOST' => 'x', 'DB_NAME' => 'x', 'DB_USER' => 'x',
        ]), $this->root);
    }

    /**
     * Diskte GERÇEK bir set kurar: parçalar + manifest.
     *
     * Elle uydurulmuş bir dizin, `list()` manifest okuyamadığı için görünmez
     * olurdu ve test hiçbir şey sınamazdı — bu yüzden set gerçek yazıcıyla
     * üretilir.
     */
    private function seed(string $damga, int $daysOld): void
    {
        $yazici = new \App\Services\Yedek\YedekSetiYazici(
            $this->root . '/storage/backups',
            '1.2.2',
            ['0035_bildirimler'],
        );
        $set = $yazici->baslat($damga);
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', 'DUMP-' . $damga);
        $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', 'CONFIG');
        $dizin = $yazici->tamamla($set);

        touch($dizin, time() - $daysOld * 86400);
    }

    public function testEskilerSilinirEnYeniBesKalir(): void
    {
        // 8 yedek: 3 taze + 5 eski (30 gün) — saklama 14 gün.
        for ($i = 1; $i <= 3; $i++) {
            $this->seed(sprintf('2026081%d-1200%02d', $i, $i), 1);
        }
        for ($i = 1; $i <= 5; $i++) {
            $this->seed(sprintf('2026070%d-1200%02d', $i, $i), 30);
        }

        $deleted = $this->service()->prune(14);

        // En yeni 5 (3 taze + eskilerin en yenisi 2) kalır; 3 eski silinir.
        self::assertCount(3, $deleted);
        self::assertCount(5, $this->service()->list());
    }

    public function testBesVeyaDahaAzYedekVarkenHicbirSeySilinmez(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seed(sprintf('2026060%d-120000', $i), 90);
        }

        self::assertSame([], $this->service()->prune(14), 'EN YENİ 5 her koşulda korunur.');
        self::assertCount(5, $this->service()->list());
    }

    public function testSETBUTUNOLARAKSILINIR(): void
    {
        // Parçalardan biri kalırsa manifestsiz bir yetim olur: dosya orada
        // durur, neye ait olduğu bilinemez, disk sessizce dolar.
        for ($i = 1; $i <= 6; $i++) {
            $this->seed(sprintf('2026040%d-120000', $i), 200);
        }

        $this->service()->prune(14);

        self::assertSame(
            [],
            glob($this->root . '/storage/backups/set-20260401-120000/*') ?: [],
            'Silinen setin parçaları da gitmeli.',
        );
        self::assertDirectoryDoesNotExist($this->root . '/storage/backups/set-20260401-120000');
    }

    public function testDesenDisiDosyalaraDokunulmaz(): void
    {
        file_put_contents($this->root . '/storage/backups/.htaccess', "Require all denied\n");
        file_put_contents($this->root . '/storage/backups/notlar.txt', 'dokunma');
        for ($i = 1; $i <= 6; $i++) {
            $this->seed(sprintf('2026050%d-120000', $i), 120);
        }

        $this->service()->prune(14);

        self::assertFileExists($this->root . '/storage/backups/.htaccess');
        self::assertFileExists($this->root . '/storage/backups/notlar.txt');
    }
}
