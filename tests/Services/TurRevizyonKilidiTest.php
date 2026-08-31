<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;

/**
 * V3-C BLOK A4 — TUR KİLİDİ, LİSTE REVİZYONUNA BAĞLIDIR (K57 hattı).
 *
 * KORUNAN FELAKET: firmaya "şu ürünleri fiyatla" dedik; sonra listede bir
 * ürünün adını ya da miktarını değiştirdik. Firma BAŞKA bir şeyi fiyatlamış
 * oldu ve kimse farkı göremedi — teklif geldiğinde satırlar tutuyor gibi
 * görünür, çünkü karşılaştırma canlı listeye bakar.
 *
 * ZİNCİR: `supplier_rounds.rfq_snapshot_id` → `rfq_snapshots.list_revision`.
 * Tur açılırken listenin O ANKİ revizyonu snapshot'a YAZILIR ve donar. K57
 * gereği revizyon harfi içerikten türediği için, liste sonradan değişince
 * `lists.revision` ilerler ve tur ile liste arasındaki fark ÖLÇÜLEBİLİR
 * hâle gelir ("bu tur Rev C üzerinden konuşuldu, liste artık Rev D").
 *
 * Zincirin herhangi bir halkası kopsa bu ölçüm imkânsızlaşır; bu yüzden
 * halkalar şema seviyesinde denetlenir.
 */
final class TurRevizyonKilidiTest extends TestCase
{
    private function goc(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/0036_firmalar_ve_turlar.php');
    }

    public function testTURSNAPSHOTAREFERANSVERIR(): void
    {
        // Tur snapshot'a bağlanmazsa "firma neyi gördü?" sorusunun cevabı yok.
        self::assertSame(
            2,
            substr_count($this->goc(), 'rfq_snapshot_id INTEGER NULL')
            + substr_count($this->goc(), 'rfq_snapshot_id BIGINT UNSIGNED NULL'),
            'supplier_rounds hem SQLite hem MySQL şemasında rfq_snapshot_id taşımalı.',
        );
    }

    public function testSNAPSHOTLISTEREVIZYONUNUDONDURUR(): void
    {
        // `list_revision` DEFAULT 0 ile açılır ama tur açılışında listenin o
        // anki değeriyle DOLDURULUR (Blok B). Kolon yoksa doldurulacak yer de
        // olmaz — kilit şema seviyesinde başlar.
        $goc = $this->goc();

        self::assertStringContainsString('list_revision INTEGER NOT NULL', $goc, 'SQLite şemasında list_revision yok.');
        self::assertStringContainsString('list_revision INT UNSIGNED NOT NULL', $goc, 'MySQL şemasında list_revision yok.');
    }

    public function testRFQSATIRLARIKENDIKIMLIGINITASIR(): void
    {
        // `rfq_satir_id` UUID: Excel şablonundaki gizli satır imzası ve
        // yapıştır-ayrıştır eşleştirmesi buna dayanır. Otomatik artan id
        // kullanılsaydı bir kurulumun dosyası başka kurulumun satırına
        // denk gelebilirdi.
        $goc = $this->goc();

        self::assertStringContainsString('rfq_satir_id', $goc);
        self::assertMatchesRegularExpression(
            '/UNIQUE\s*(KEY\s+\w+\s*)?\(\s*rfq_snapshot_id\s*,\s*rfq_satir_id\s*\)/i',
            $goc,
            'Aynı snapshot içinde satır kimliği TEKİL olmalı; değilse eşleştirme yanlış satırı günceller.',
        );
    }

    public function testTURNOSUDURUMADINAGOMULMEZ(): void
    {
        // #15 §2: tur numarası durumun PARÇASI değildir. "TUR2_BEKLIYOR" gibi
        // adlar durum makinesini tur sayısı kadar çoğaltır ve üçüncü turda
        // yeni durum eklemek gerekir.
        $goc = $this->goc();

        self::assertMatchesRegularExpression('/tur_no\s+INT/i', $goc, 'tur_no ayrı kolon olmalı.');
        self::assertDoesNotMatchRegularExpression(
            '/"[A-Z_]*TUR[0-9][A-Z_]*"/',
            $goc,
            'Durum adına tur numarası gömülmüş.',
        );
    }

    public function testAYNIFIRMAAYNITURIKIKEZACILAMAZ(): void
    {
        // Aynı liste + firma + tur no için iki satır, iki ayrı teklif demektir
        // ve hangisinin geçerli olduğu bilinemez.
        self::assertMatchesRegularExpression(
            '/UNIQUE\s*(KEY\s+\w+\s*)?\(\s*list_id\s*,\s*supplier_id\s*,\s*tur_no\s*\)/i',
            $this->goc(),
            'supplier_rounds (list_id, supplier_id, tur_no) tekilliği şart.',
        );
    }
}
