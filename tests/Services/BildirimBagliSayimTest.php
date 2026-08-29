<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Bildirim\BagliOlaylar;
use PHPUnit\Framework\TestCase;

/**
 * V3-B A3 BEKÇİSİ — BAĞLANAN OLAY SAYISI KATALOGLA TUTAR.
 *
 * Nöbet Raporu 5'in 3 numaralı riski: "28 mi 24 mü?" tartışması. Bir sayıyı
 * belgede yazmak yetmez — belge ile kod arasındaki fark, tam olarak D12'de
 * panelin "üç dil zaten tamamdı" demesine yol açan boşluktur.
 *
 * Bu test üç şeyi zorlar:
 *   1. BAGLI + BEKLEYEN = katalogdaki olay sayısı (37). Bir olay ikisinden
 *      birinde OLMAK ZORUNDA; unutulan olay kırmızıdır.
 *   2. Hiçbir kod iki listede birden olamaz.
 *   3. Uydurma kod yok — her iki listedeki her kod katalogda tanımlı.
 *
 * Sayı DEĞİŞTİĞİNDE bu test kırılır ve karar kaydı da güncellenmek zorunda
 * kalır. İstenen davranış budur: sessiz kayma olmaz.
 */
final class BildirimBagliSayimTest extends TestCase
{
    private const KATALOG = '/config/bildirim-olay-katalogu.json';

    /** @return list<string> */
    private function katalogKodlari(): array
    {
        $ham = (string) file_get_contents(dirname(__DIR__, 2) . self::KATALOG);
        /** @var array{olaylar: list<array<string, mixed>>} $veri */
        $veri = json_decode($ham, true, 512, JSON_THROW_ON_ERROR);

        return array_map(static fn (array $olay): string => (string) $olay['olay_kodu'], $veri['olaylar']);
    }

    public function testBAGLIVEBEKLEYENTOPLAMIKATALOGAESIT(): void
    {
        $katalog = $this->katalogKodlari();
        $toplam = count(BagliOlaylar::BAGLI) + count(BagliOlaylar::BEKLEYEN);

        self::assertSame(
            count($katalog),
            $toplam,
            sprintf(
                'Katalog %d olay tanımlıyor; sicil %d bağlı + %d bekleyen = %d sayıyor. '
                . 'Her olay ikisinden birinde olmalı.',
                count($katalog),
                count(BagliOlaylar::BAGLI),
                count(BagliOlaylar::BEKLEYEN),
                $toplam,
            ),
        );
    }

    public function testKATALOGDAKIHEROLAYSINIFLANDIRILMIS(): void
    {
        $siniflanan = array_merge(BagliOlaylar::BAGLI, array_keys(BagliOlaylar::BEKLEYEN));
        $unutulan = array_values(array_diff($this->katalogKodlari(), $siniflanan));

        self::assertSame(
            [],
            $unutulan,
            "Bu olaylar ne bağlı ne bekleyen listesinde — sınıflandırılmamış olay, unutulmuş olaydır:\n  "
            . implode("\n  ", $unutulan),
        );
    }

    public function testSICILDEUYDURMAKODYOK(): void
    {
        $katalog = $this->katalogKodlari();
        $siniflanan = array_merge(BagliOlaylar::BAGLI, array_keys(BagliOlaylar::BEKLEYEN));
        $hayalet = array_values(array_diff($siniflanan, $katalog));

        self::assertSame([], $hayalet, 'Katalogda olmayan olay kodu: ' . implode(', ', $hayalet));
    }

    public function testBIROLAYIKILISTEDEBIRDENOLAMAZ(): void
    {
        $cakisan = array_values(array_intersect(BagliOlaylar::BAGLI, array_keys(BagliOlaylar::BEKLEYEN)));

        self::assertSame([], $cakisan, 'Hem bağlı hem bekleyen sayılan olay: ' . implode(', ', $cakisan));
    }

    public function testHERBEKLEYENOLAYINGEREKCESIVAR(): void
    {
        foreach (BagliOlaylar::BEKLEYEN as $kod => $sebep) {
            self::assertNotSame('', trim($sebep), $kod . ': gerekçesiz bekleyen olay, unutulmuş olaydır.');
            self::assertGreaterThan(20, mb_strlen($sebep), $kod . ': gerekçe bir cümle olmalı.');
        }
    }

    public function testBAGLIOLAYSAYISI27DIR(): void
    {
        // PM mutabakatı Nöbet 5'te 28 idi; UYGULAMA BULGUSU iki olayı düşürdü
        // (NTF-CAPTURE-BATCH-ACCEPTED ve NTF-OFFLINE-QUEUED — ikisi de capture
        // şeması değişikliği ister, PM kararı). Sözlük içe aktarımı (C3) biri
        // ekledi. Geçerli sayım aşağıdadır; sayı değişirse karar kaydı da
        // güncellenmelidir — sessiz kayma olmaz.
        self::assertCount(27, BagliOlaylar::BAGLI);
        self::assertCount(10, BagliOlaylar::BEKLEYEN);
    }
}
