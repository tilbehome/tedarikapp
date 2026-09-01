<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;

/**
 * SERTLEŞTİRME v1.2.1 A6 EKİ — SINAV ARACI EKRANLA AYNI KAYNAĞI OKUR.
 *
 * D11 KÖK NEDENİ (28 Ağu bulgusu): "tazelenen çeviri liste/çekmecede eski
 * başlığı gösteriyor; SINAV İLE EKRAN FARKLI KAYNAK OKUYOR."
 *
 * Kanıt zinciri üç dosyada duruyor:
 *
 *   1. `LlmTranslator::onbellegeYaz()` İKİ satır yazar: SÜRÜMLÜ anahtar +
 *      SÜRÜMSÜZ anahtar (`tazeleTumAnahtarlar`).
 *   2. `ValueSet` (ekrandaki DEĞERLER) SÜRÜMLÜ anahtarı okur — ve sürüm
 *      sözlükten türer.
 *   3. `bin/ceviri-sinavi.php` SÜRÜMSÜZ anahtarı okuyordu.
 *
 * Kuyruk yolu boş sözlükle koştuğu için (A6) SÜRÜMLÜ satır YANLIŞ bir
 * anahtara yazıldı; SÜRÜMSÜZ satır ise doğru yazıldı. Sonuç tam olarak
 * bulgudaki tabloydu: sınav "4/4 llm:deepseek" der (sürümsüzü görür),
 * ekrandaki değerler ham Çince kalır (sürümlüyü bulamaz).
 *
 * Bu test sınavın artık İKİ anahtarı da okuduğunu ve ayrışmayı GÖRÜNÜR
 * kıldığını zorlar. Ayrışmayı gizleyen bir teşhis aracı, teşhissizlikten
 * kötüdür: yanlış yere güven verir.
 */
final class CeviriSinaviAyniKaynakTest extends TestCase
{
    private function kaynak(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/bin/ceviri-sinavi.php');
    }

    public function testSINAVSURUMLUANAHTARIDAOKUR(): void
    {
        $kaynak = $this->kaynak();

        self::assertStringContainsString(
            'SozlukFabrikasi::kur(',
            $kaynak,
            'Sınav sözlüğü tek fabrikadan kurmalı; ekranla aynı sürümü hesaplayamazsa aynı satırı da okuyamaz.',
        );
        self::assertStringContainsString(
            'CeviriSurumu::kur(',
            $kaynak,
            'Sürüm anahtarı hesaplanmıyorsa sınav yalnız sürümsüz satırı görür.',
        );
    }

    public function testSINAVAYRISMAYIRAPORLAR(): void
    {
        // İki anahtar farklı sonuç veriyorsa BU BİR BULGUDUR. Sınavın onu
        // yutup tek sayı basması, D11'i aylarca görünmez tuttu.
        self::assertStringContainsString(
            'ayrisma',
            $this->kaynak(),
            'Sınav, sürümlü ve sürümsüz satırın ayrışmasını raporlamalı.',
        );
    }

    public function testEKRANDEGERYOLUSURUMLUANAHTARIOKUR(): void
    {
        // Zincirin ekran ucu: bu değişirse yukarıdaki gerekçe çürür ve
        // sınavın neyi taklit ettiği anlamsızlaşır.
        $valueSet = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Translation/ValueSet.php',
        );

        self::assertStringContainsString(
            '$this->surumAnahtari,',
            $valueSet,
            'ValueSet sürümlü anahtarı okumayı bıraktıysa D11 gerekçesi güncellenmeli.',
        );
    }

    public function testCEVIRICIIKIANAHTARIDAYAZAR(): void
    {
        // Zincirin yazıcı ucu. İkisi birden yazılmasaydı sınav ile ekranın
        // ayrışması mümkün olmazdı — ayrışmanın kaynağı tam olarak budur.
        $cevirici = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Translation/LlmTranslator.php',
        );

        self::assertStringContainsString('tazeleTumAnahtarlar(', $cevirici);
    }
}
