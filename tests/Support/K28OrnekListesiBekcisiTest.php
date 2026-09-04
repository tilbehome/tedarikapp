<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * v1.2.2 H2 — K28 BEKÇİSİ: `ornek-tedarik-listesi.xlsx` SABİTLENİR.
 *
 * K28 (public-uyum): depoda gerçek tedarik listesi DURMAZ. Gerçek dosya
 * (ürün, fiyat, 1688 bağlantısı, kişisel meta veri) çıkarıldı; yerine biçimi
 * birebir aynı, TEK uydurma satırlı `ornek-tedarik-listesi.xlsx` kondu.
 *
 * OLAY: bu dosya çalışma ağacında İKİ KEZ büyüdü (13.9 KB → 1.15 MB). İlki
 * `b0c6f1a` ile geri alındı, ikincisi 3 Eyl'de yakalandı. Kodda dosyaya ad
 * veren hiçbir yol yok — kaynak depo dışı. Bir kez public olan commit,
 * geçmişten silinse bile kopyalanmış olabilir; sızıntı geri alınamaz.
 *
 * BEKÇİ: SHA-256 sabit. Değişirse CI KIRMIZI ve mesaj ne yapılacağını söyler.
 * Ek kontrol: boyut 50 KB altı — tek satırlık sahte bir dosya 50 KB'yi
 * bulamaz; buluyorsa içinde başka bir şey vardır.
 */
final class K28OrnekListesiBekcisiTest extends TestCase
{
    /** Geri alınmış, tek satırlık sahte sürümün özeti (b0c6f1a). */
    private const BEKLENEN_SHA256 = '5f8dab53bfe6f6f157cc6da36cc1791a040988326aaaeca02e3900fb05e1d732';

    private const AZAMI_BAYT = 50 * 1024;

    private const MESAJ = 'K28: ornek-tedarik-listesi.xlsx sahte tek satır olmalı; '
        . '`git checkout -- ornek-tedarik-listesi.xlsx` ile geri al, gerçek veri commit\'lenmez. '
        . 'Dosyayı bilerek değiştirdiysen (yeni sahte şablon) bu testteki özeti PM onayıyla güncelle.';

    private function yol(): string
    {
        return dirname(__DIR__, 2) . '/ornek-tedarik-listesi.xlsx';
    }

    public function testDOSYADEPODAVAR(): void
    {
        self::assertFileExists($this->yol(), 'Excel çıktısının referans şablonu depoda durmalı (K28).');
    }

    public function testOZETSABIT(): void
    {
        self::assertSame(
            self::BEKLENEN_SHA256,
            hash_file('sha256', $this->yol()),
            self::MESAJ,
        );
    }

    public function testBOYUTSAHTEDOSYAYAYAKISIR(): void
    {
        // Özet testi zaten yakalar; bu ek kontrol, özeti "güncelleyiverme"
        // dürtüsüne karşı ikinci bir gözdür: 50 KB'yi aşan bir dosya tek
        // satırlık sahte şablon OLAMAZ, içinde başka bir şey vardır.
        self::assertLessThan(
            self::AZAMI_BAYT,
            (int) filesize($this->yol()),
            self::MESAJ,
        );
    }
}
