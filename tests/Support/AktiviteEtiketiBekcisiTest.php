<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * AKTİVİTE ETİKETİ BEKÇİSİ (v1.2.2 Blok 0.3).
 *
 * NE OLDU: `activity_log.action` değerlerinin Türkçe karşılığı
 * `frontend/src/lib/activityLabels.ts` tablosunda tutuluyor; tanınmayan kod
 * için bir yedek (`insanlastir()`) var. Yedek zamanla KALICI çözüm gibi
 * davranmaya başladı: panelde "Ceviri urun", "Migrate baseline" gibi ham
 * kodlar okunuyordu. Bunlar Türkçe değil, cümle değil, hatta doğru bile
 * değil — "Ceviri urun" bir eylem adı gibi durmuyor.
 *
 * Yedek bir NEZAKET katmanıdır: bilinmeyen bir kayıt gizlenmesin diye vardır.
 * Ama yedeğin varlığı, tabloyu güncellememenin bedelini GÖRÜNMEZ kılıyordu.
 * Bu bekçi bedeli görünür yapar: yeni bir `action` yazan kod, tabloya da bir
 * satır eklemeden yeşil geçemez.
 *
 * NEDEN KAYNAK TARAMASI: etiketler frontend'de, kodlar backend'de. Çalışma
 * zamanı testi ikisini bir arada göremez; tarama görür.
 */
final class AktiviteEtiketiBekcisiTest extends TestCase
{
    /**
     * Aktör TİPLERİ eylem değildir — `ActivityLog::ACTOR_*` sabitleri
     * `actor_type` kolonuna yazılır, `action`a değil.
     *
     * @var list<string>
     */
    private const AKTOR_TIPLERI = ['admin', 'extension', 'system'];

    /** @return list<string> backend'in yazdığı action kodları */
    private function backendKodlari(): array
    {
        $kok = dirname(__DIR__, 2);
        $kodlar = [];

        $dosyalar = [];
        $gezgin = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($kok . '/app', \FilesystemIterator::SKIP_DOTS),
        );
        /** @var \SplFileInfo $dosya */
        foreach ($gezgin as $dosya) {
            if ($dosya->getExtension() === 'php') {
                $dosyalar[] = $dosya->getPathname();
            }
        }
        foreach (glob($kok . '/bin/*.php') ?: [] as $betik) {
            $dosyalar[] = $betik;
        }

        foreach ($dosyalar as $yol) {
            $kaynak = (string) file_get_contents($yol);

            // `->record('entity', $id, 'action', ...)`
            if (preg_match_all("/->record\(\s*'[^']*'\s*,\s*[^,]+,\s*'([a-z0-9_]+)'/", $kaynak, $m) > 0) {
                foreach ($m[1] as $kod) {
                    $kodlar[$kod] = true;
                }
            }

            // `ActivityLog` sabitleri (recordAuth bunları kullanır)
            if (str_ends_with($yol, 'ActivityLog.php')) {
                preg_match_all("/const\s+[A-Z_]+\s*=\s*'([a-z0-9_]+)'/", $kaynak, $sabit);
                foreach ($sabit[1] as $kod) {
                    $kodlar[$kod] = true;
                }
            }
        }

        foreach (self::AKTOR_TIPLERI as $aktor) {
            unset($kodlar[$aktor]);
        }
        // `auth` bir ENTITY adıdır, eylem değil.
        unset($kodlar['auth']);

        return array_keys($kodlar);
    }

    /**
     * Yalnız `labels` bloğu — dosyada AYRICA `entityLabels` var (varlık adları:
     * "Liste", "Ürün"). İkisini karıştırmak, bekçiyi kendi kör noktasıyla
     * kırmızıya düşürür; nitekim ilk yazımda öyle oldu.
     */
    private function etiketBlogu(): string
    {
        $tablo = (string) file_get_contents(
            dirname(__DIR__, 2) . '/frontend/src/lib/activityLabels.ts',
        );
        $bas = strpos($tablo, 'const labels: Record<string, string> = {');
        self::assertIsInt($bas, 'labels tablosu bulunamadı; bekçi kör.');
        $son = strpos($tablo, '};', $bas);
        self::assertIsInt($son);

        return substr($tablo, $bas, $son - $bas);
    }

    /** @return list<string> etiket tablosundaki kodlar */
    private function etiketliKodlar(): array
    {
        preg_match_all("/^\s{2}([a-z0-9_]+):\s*'/m", $this->etiketBlogu(), $eslesme);

        return $eslesme[1];
    }

    public function testTARAMABOSADUSMEZ(): void
    {
        // Bekçi kendi kör noktasını üretmesin: iki taraf da dolu olmalı.
        self::assertGreaterThan(20, count($this->backendKodlari()), 'Backend kodları okunamadı.');
        self::assertGreaterThan(20, count($this->etiketliKodlar()), 'Etiket tablosu okunamadı.');
    }

    public function testHERACTIONKODUNUNTURKCEETIKETIVAR(): void
    {
        $eksik = array_values(array_diff($this->backendKodlari(), $this->etiketliKodlar()));
        sort($eksik);

        self::assertSame(
            [],
            $eksik,
            "Bu `action` kodlarının Türkçe etiketi YOK — panelde ham kod okunur:\n  "
            . implode("\n  ", $eksik)
            . "\nfrontend/src/lib/activityLabels.ts dosyasına birer satır ekleyin.\n"
            . '(Yedek `insanlastir()` bir nezaket katmanıdır, kalıcı çözüm değildir.)',
        );
    }

    public function testETIKETLERBOSDEGIL(): void
    {
        // Boş ya da tek harflik etiket, "eklendi" görüntüsü verip hiçbir şey
        // anlatmaz — bekçiyi kandırmanın en kolay yolu budur.
        preg_match_all("/^\s{2}[a-z0-9_]+:\s*'([^']*)'/m", $this->etiketBlogu(), $eslesme);
        $kisa = array_values(array_filter(
            $eslesme[1],
            static fn (string $etiket): bool => mb_strlen(trim($etiket)) < 5,
        ));

        self::assertSame([], $kisa, 'Anlamsız kısa etiket: ' . implode(', ', $kisa));
    }
}
