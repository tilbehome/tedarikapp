<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\IntegrityChecker;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * K43 KRİTİK: eksik açılmış release artık SESSİZ kalamaz.
 * MANIFEST.txt'e göre eksik ve bozuk dosyalar isim isim raporlanır.
 */
final class IntegrityCheckerTest extends TestCase
{
    use TempDirectory;

    private function writeManifest(array $files): void
    {
        $lines = ['# test manifesti'];
        foreach ($files as $relative => $content) {
            $lines[] = IntegrityChecker::manifestLine(hash('sha256', $content), $relative);
        }
        file_put_contents($this->tempPath(IntegrityChecker::MANIFEST_FILE), implode("\n", $lines) . "\n");
    }

    public function testManifestYoksaGelistirmeKurulumuSayilir(): void
    {
        $result = (new IntegrityChecker($this->tempRoot()))->check();

        self::assertFalse($result['manifest_exists']);
        self::assertTrue($result['ok'], 'Manifest yokken (geliştirme) hata ÜRETİLMEZ.');
        self::assertStringContainsString('geliştirme', $result['message']);
    }

    public function testTamKurulumdaButunlukTamam(): void
    {
        mkdir($this->tempPath('app'), 0775, true);
        file_put_contents($this->tempPath('app/a.php'), 'icerik-a');
        file_put_contents($this->tempPath('kok.txt'), 'icerik-kok');
        $this->writeManifest(['app/a.php' => 'icerik-a', 'kok.txt' => 'icerik-kok']);

        $result = (new IntegrityChecker($this->tempRoot()))->check();

        self::assertTrue($result['ok']);
        self::assertSame(2, $result['checked']);
        self::assertSame([], $result['missing']);
        self::assertSame([], $result['modified']);
    }

    public function testEksikDosyaIsimIsimRaporlanir(): void
    {
        // Üretim vakası: setup/ klasörü hiç açılmamış.
        mkdir($this->tempPath('app'), 0775, true);
        file_put_contents($this->tempPath('app/a.php'), 'icerik-a');
        $this->writeManifest([
            'app/a.php' => 'icerik-a',
            'setup/views/wizard.html' => 'sihirbaz',
            'setup/views/wizard.js' => 'js',
        ]);

        $result = (new IntegrityChecker($this->tempRoot()))->check();

        self::assertFalse($result['ok']);
        self::assertSame(2, $result['missing_count']);
        self::assertContains('setup/views/wizard.html', $result['missing']);
        self::assertContains('setup/views/wizard.js', $result['missing']);
        self::assertStringContainsString('eksiksiz', $result['message'], 'Çözüm yönlendirmesi olmalı.');
    }

    public function testDegistirilmisDosyaYakalanir(): void
    {
        file_put_contents($this->tempPath('bozuk.php'), 'yeni-icerik');
        $this->writeManifest(['bozuk.php' => 'orijinal-icerik']);

        $result = (new IntegrityChecker($this->tempRoot()))->check();

        self::assertFalse($result['ok']);
        self::assertSame(['bozuk.php'], $result['modified']);
    }

    public function testUzunListeSinirlanirAmaSayiTamKalir(): void
    {
        $manifest = [];
        for ($i = 0; $i < 60; $i++) {
            $manifest['yok/' . $i . '.php'] = 'x' . $i;
        }
        $this->writeManifest($manifest);

        $result = (new IntegrityChecker($this->tempRoot()))->check();

        self::assertSame(60, $result['missing_count'], 'Gerçek sayı korunmalı.');
        self::assertCount(50, $result['missing'], 'Yanıt listesi sınırlanmalı.');
    }
}
