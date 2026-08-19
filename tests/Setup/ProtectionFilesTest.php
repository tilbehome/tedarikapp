<?php

declare(strict_types=1);

namespace Tests\Setup;

use PHPUnit\Framework\TestCase;

/**
 * K33/K34 smoke — koruma dosyaları REPO İLE gelmeli.
 *
 * `public/media` paylaşımlı sunucuda 777 yapılacak tek klasör. Yazılabilir +
 * webden erişilebilir bir klasörde çalıştırılabilir dosya = uzaktan kod çalıştırma.
 * Bu dosyaların deploy paketinde bulunmaması, izin verildiği anda kapıyı açık bırakır —
 * bu yüzden varlıkları teste bağlanmıştır.
 */
final class ProtectionFilesTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function contents(string $relative): string
    {
        $path = $this->root() . '/' . $relative;
        self::assertFileExists($path, $relative . ' repoda bulunmalı.');

        return (string) file_get_contents($path);
    }

    public function testMediaHtaccessRepodaVar(): void
    {
        self::assertFileExists($this->root() . '/public/media/.htaccess');
    }

    public function testMediaHtaccessCalistirmayiKapatir(): void
    {
        $content = $this->contents('public/media/.htaccess');

        self::assertStringContainsString('SetHandler none', $content);
        self::assertStringContainsString('php_flag engine off', $content);
        self::assertMatchesRegularExpression('/FilesMatch\s+"\\\\\.\(ph\./', $content, 'php/phtml/phar kapsanmalı.');
        self::assertStringContainsString('Require all denied', $content);
        self::assertStringContainsString('Options -ExecCGI -Indexes', $content);
    }

    public function testMediaHtaccessDizinListelemeyiVeIndekslemeyiKapatir(): void
    {
        $content = $this->contents('public/media/.htaccess');

        self::assertStringContainsString('-Indexes', $content);
        self::assertStringContainsString('X-Robots-Tag', $content);
        self::assertStringContainsString('noindex', $content);
    }

    public function testMediaHtaccessHotlinkKuraliBosRefereriIzinVerir(): void
    {
        $content = $this->contents('public/media/.htaccess');

        // Boş referer engellenirse WhatsApp/WeChat önizlemesi ve doğrudan açılan
        // paylaşım linkleri kırılır — kural bilerek boş referer'a izin verir.
        self::assertStringContainsString('RewriteCond %{HTTP_REFERER} !^$', $content);
        self::assertStringContainsString('tilbehometoptan', $content);
    }

    public function testRobotsTxtTumSiteyiKapatir(): void
    {
        // İE#10.5 ek (Ürün Sahibi kararı): uygulama aramaya TAMAMEN kapalı —
        // tek tek yollar yerine kökten Disallow: / (media ve /p/ dahil her şeyi kapsar).
        $content = $this->contents('public/robots.txt');

        self::assertStringContainsString('User-agent: *', $content);
        self::assertMatchesRegularExpression('#Disallow: /\s#', $content, 'Kök Disallow: / olmalı.');
    }

    public function testGitignoreMediaHtaccessiDISLAMAZ(): void
    {
        // public/media/* yok sayılıyor; koruma dosyası istisna olmalı, yoksa deploy
        // paketine hiç girmez ve sunucuda kapı açık kalır.
        $content = $this->contents('.gitignore');

        self::assertStringContainsString('!public/media/.htaccess', $content);
    }
}
