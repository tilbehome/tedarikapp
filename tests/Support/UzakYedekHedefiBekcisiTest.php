<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * v1.2.2 B5 — UZAK YEDEK HEDEFİ: TANIMLI, UYGULANMAMIŞ.
 *
 * İş emri açık: "uzak hedef config anahtarları YALNIZ TANIMLANIR, uygulaması
 * V3-G'ye bırakılır." Bu bekçi o sınırı kod tarafında tutar.
 *
 * NEDEN BEKÇİ GEREKİR: yarım uygulanmış bir gönderim, hiç uygulanmamış
 * olmaktan tehlikelidir. Anahtarları dolduran kullanıcı, yedeklerinin uzak
 * hedefe gittiğini varsayar; kod bir yerde okuyup bir yerde okumuyorsa bu
 * varsayım sessizce yanlış olur ve yanlışlığı ancak yedeğe ihtiyaç duyulduğu
 * gün anlaşılır.
 *
 * BEKÇİ NE ZAMAN KALKAR: V3-G'de gönderim gerçekten yazıldığında bu test
 * silinir — ve silinmesi, işin yapıldığının bilinçli beyanı olur.
 */
final class UzakYedekHedefiBekcisiTest extends TestCase
{
    private const ANAHTARLAR = [
        'BACKUP_REMOTE_DRIVER',
        'BACKUP_REMOTE_PATH',
        'BACKUP_REMOTE_HOST',
        'BACKUP_REMOTE_USER',
        'BACKUP_REMOTE_SECRET',
        'BACKUP_REMOTE_BUCKET',
    ];

    private function kok(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testANAHTARLARORNEKENVDETANIMLI(): void
    {
        $ornek = (string) file_get_contents($this->kok() . '/.env.example');

        foreach (self::ANAHTARLAR as $anahtar) {
            self::assertStringContainsString(
                $anahtar . '=',
                $ornek,
                $anahtar . ' .env.example içinde tanımlı olmalı.',
            );
        }
    }

    public function testANAHTARLARHENUZOKUNMUYOR(): void
    {
        // Anahtarları okuyan İLK satır, "tanımlı ama uygulanmamış" sözünü
        // bozar. Uygulama geldiğinde bu test silinir.
        $okuyanlar = [];

        foreach ($this->phpDosyalari() as $dosya) {
            $kaynak = (string) file_get_contents($dosya);
            foreach (self::ANAHTARLAR as $anahtar) {
                // `get('BACKUP_REMOTE_...')` gibi GERÇEK bir okuma aranır;
                // .env.example'daki tanım ya da bu bekçinin kendi listesi
                // eşleşmesin diye tırnak içinde ve nokta/parantezle birlikte.
                if (preg_match('/(get|getenv|getInt|getPositiveInt|getBool)\s*\(\s*[\'"]' . $anahtar . '[\'"]/', $kaynak) === 1) {
                    $okuyanlar[] = basename($dosya) . ' → ' . $anahtar;
                }
            }
        }

        self::assertSame(
            [],
            $okuyanlar,
            'Uzak yedek hedefi V3-G kapsamındadır; yarım uygulama, kullanıcıya '
            . 'olmayan bir güvence satar.',
        );
    }

    public function testACIKLAMAUYGULANMADIGINISOYLUYOR(): void
    {
        // Anahtarı gören kullanıcı, doldurunca bir şey olacağını sanmamalı.
        $ornek = (string) file_get_contents($this->kok() . '/.env.example');

        self::assertMatchesRegularExpression(
            '/HEN\x{00DC}Z UYGULANMAMI\x{015E}TIR/u',
            $ornek,
            '.env.example bu anahtarların henüz çalışmadığını AÇIKÇA söylemeli.',
        );
    }

    /** @return list<string> */
    private function phpDosyalari(): array
    {
        $dosyalar = [];
        foreach (['app', 'bin', 'config'] as $klasor) {
            $dizin = $this->kok() . '/' . $klasor;
            if (!is_dir($dizin)) {
                continue;
            }
            $gezgin = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dizin, \FilesystemIterator::SKIP_DOTS),
            );
            /** @var \SplFileInfo $dosya */
            foreach ($gezgin as $dosya) {
                if ($dosya->isFile() && $dosya->getExtension() === 'php') {
                    $dosyalar[] = $dosya->getPathname();
                }
            }
        }

        return $dosyalar;
    }
}
