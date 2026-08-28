<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * İE#22 E3 — BİLDİRİM OLAY KATALOĞU 37 OLAYDIR.
 *
 * K82 (25 Ağu 2026) iki paylaşım olayını düşürdü: erişim anahtarının ömrü
 * yoktur (K62), dolayısıyla "süresi doluyor / doldu" diye bir olay üretilemez.
 * Karar alındı ama JSON dosyası 39 kayıtla kaldı; her sayımda "37 mi 39 mu?"
 * tartışması yeniden açılıyordu.
 *
 * Bu test sayıyı ve düşen iki kodun YOKLUĞUNU sabitler. Katalog V3-B'nin
 * bildirim altyapısına girdi olacak; oradaki "yalnız tetiklenebilir olanlar
 * yazılır" süzgeci yanlış bir toplamla çalışmamalı.
 */
final class BildirimKatalogTest extends TestCase
{
    private const KATALOG = '/docs/v3/hazirlik/v3-b/bildirim-olay-katalogu.json';

    /** @return array<string, mixed> */
    private function katalog(): array
    {
        $ham = (string) file_get_contents(dirname(__DIR__, 2) . self::KATALOG);
        /** @var array<string, mixed> $veri */
        $veri = json_decode($ham, true, 512, JSON_THROW_ON_ERROR);

        return $veri;
    }

    public function testKATALOG37OLAYTASIR(): void
    {
        /** @var list<array<string, mixed>> $olaylar */
        $olaylar = $this->katalog()['olaylar'];

        self::assertCount(37, $olaylar, 'K82 sonrası katalog 37 olaydır; sayı değiştiyse karar kaydı da güncellenmeli.');
    }

    public function testK82NINDUSURDUGUIKIOLAYYOK(): void
    {
        /** @var list<array<string, mixed>> $olaylar */
        $olaylar = $this->katalog()['olaylar'];
        $kodlar = array_column($olaylar, 'olay_kodu');

        self::assertNotContains('NTF-SHARE-EXPIRY-NEAR', $kodlar, 'K62: anahtarın süresi yoktur.');
        self::assertNotContains('NTF-SHARE-EXPIRED', $kodlar, 'K62: anahtarın süresi yoktur.');
    }

    public function testOLAYKODLARIBENZERSIZ(): void
    {
        /** @var list<array<string, mixed>> $olaylar */
        $olaylar = $this->katalog()['olaylar'];
        $kodlar = array_column($olaylar, 'olay_kodu');

        self::assertSame(count($kodlar), count(array_unique($kodlar)), 'Aynı olay kodu iki kez tanımlanamaz.');
    }

    public function testHEROLAYINTETIKVEGRUBUVAR(): void
    {
        /** @var list<array<string, mixed>> $olaylar */
        $olaylar = $this->katalog()['olaylar'];

        foreach ($olaylar as $olay) {
            $kod = (string) ($olay['olay_kodu'] ?? '?');
            self::assertNotEmpty($olay['tetik'] ?? '', $kod . ': tetiği olmayan olay hiç üretilmez.');
            self::assertNotEmpty($olay['grup'] ?? '', $kod . ': grubu olmayan olay merkezde sınıflanamaz.');
        }
    }
}
