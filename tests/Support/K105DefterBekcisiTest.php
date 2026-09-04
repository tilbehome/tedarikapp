<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * K105 — MİKRO ETKİLEŞİM STANDARDI DEFTER BEKÇİSİ (31 Ağu 2026).
 *
 * NEDEN: standart bir belgede yazılı kalırsa unutulur. Defterdeki `k105`
 * sütunu onu her senaryoya iliştirir; bu bekçi de sütunun DOLU kalmasını ve
 * yalnız tanımlı değerleri almasını zorlar.
 *
 * YENİ EKRANDA `p-borcu` AÇILAMAZ: borç, mevcut V3-A/B ekranları için tanınmış
 * bir istisnadır (PM: çalışan ekranlar elden geçirilmez). Yeni bir ekranın borçla
 * doğması, standardı ilk günden kâğıt üstünde bırakırdı.
 */
final class K105DefterBekcisiTest extends TestCase
{
    private const GECERLI = ['kapsandi', 'kirmizi', 'yok', 'p-borcu'];

    /** @return array<string, array<string, mixed>> */
    private function senaryolar(): array
    {
        $ham = (string) file_get_contents(
            dirname(__DIR__, 2) . '/docs/v3/hazirlik/e2e-kapsam-defteri.json',
        );
        /** @var array<string, mixed> $defter */
        $defter = json_decode($ham, true, 512, JSON_THROW_ON_ERROR);

        /** @var array<string, array<string, mixed>> $senaryolar */
        $senaryolar = $defter['senaryolar'] ?? [];

        return $senaryolar;
    }

    public function testSTANDARTBELGESIVAR(): void
    {
        // Sütun bir belgeye işaret eder; belge yoksa sütun anlamsızdır.
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/v3/k105-mikro-etkilesim-standardi.md');
    }

    public function testHERSENARYODAK105ALANIVAR(): void
    {
        $eksik = [];
        foreach ($this->senaryolar() as $kod => $satir) {
            if (!array_key_exists('k105', $satir)) {
                $eksik[] = $kod;
            }
        }

        self::assertSame([], $eksik, 'K105 sütunu eksik: ' . implode(', ', $eksik));
    }

    public function testK105DEGERLERITANIMLI(): void
    {
        // Serbest metin, sütunu bir süre sonra anlamsız kılar: kimse "yarım"
        // ile "kısmen" arasındaki farkı hatırlamaz.
        $bozuk = [];
        foreach ($this->senaryolar() as $kod => $satir) {
            $deger = (string) ($satir['k105'] ?? '');
            if (!in_array($deger, self::GECERLI, true)) {
                $bozuk[] = $kod . '=' . $deger;
            }
        }

        self::assertSame([], $bozuk, 'Tanımsız K105 değeri: ' . implode(', ', $bozuk));
    }

    public function testDEFTERBOSDEGIL(): void
    {
        // Bekçi boş bir defteri sessizce onaylamasın.
        self::assertGreaterThan(50, count($this->senaryolar()));
    }

    public function testSTANDARTMATRISINALTIOGETIPINITASIR(): void
    {
        // Matris eksilirse standart sessizce daralır. Altı satır PM listesidir.
        $belge = (string) file_get_contents(
            dirname(__DIR__, 2) . '/docs/v3/k105-mikro-etkilesim-standardi.md',
        );

        foreach (['Satır / kart', 'Alan', 'Tablo', 'Liste / belge / link', 'Sayfa', 'Yıkıcı eylem'] as $baslik) {
            self::assertStringContainsString($baslik, $belge, 'Matriste eksik öğe tipi: ' . $baslik);
        }
    }

    public function testORTAKBILESENLERADLANDIRILMIS(): void
    {
        $belge = (string) file_get_contents(
            dirname(__DIR__, 2) . '/docs/v3/k105-mikro-etkilesim-standardi.md',
        );

        foreach (
            ['SatirEylemMenusu', 'AlanEylemleri', 'SecimCubugu', 'GeriAlToast', 'TabloAyarlari'] as $bilesen
        ) {
            self::assertStringContainsString($bilesen, $belge, 'Ortak bileşen tanımsız: ' . $bilesen);
        }
    }
}
