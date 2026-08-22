<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Models\SettingsRepository;
use App\Services\Ilan\SkorHesaplayici;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * İE#20 C6 — TedarikApp Skoru v1.
 *
 * En kritik iki iddia sınanır:
 *  • **Veri yoksa skor GİZLİDİR** (0 değil, NULL). Eksik sinyali sıfır saymak,
 *    bilmediğimiz bir ilanı "kötü" göstermektir.
 *  • **Kıyas platform İÇİNDEDİR.** 1688'de 5.000 satış sıradan, başka platformda
 *    olağanüstü olabilir; ham sayıyı karşılaştırmak farklı cetvelleri kıyaslamaktır.
 */
final class SkorHesaplayiciTest extends TestCase
{
    private PDO $pdo;
    private SkorHesaplayici $skor;
    private DateTimeImmutable $simdi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE settings (`key` VARCHAR(190) PRIMARY KEY, value TEXT NULL)');
        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->pdo->exec('CREATE TABLE translation_cache (id INTEGER PRIMARY KEY, source_hash TEXT)');

        foreach (['0023_create_listings', '0025_add_listings_skor'] as $ad) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $ad . '.php';
            $migration->up($this->pdo);
        }

        $connection = Connection::fromCallable(fn (): PDO => $this->pdo);
        $this->skor = new SkorHesaplayici($connection, new SettingsRepository($connection));
        $this->simdi = new DateTimeImmutable('2026-08-22 12:00:00');
    }

    /** @param array<string, mixed> $alanlar */
    private function ilanEkle(int $urunId, array $alanlar): void
    {
        $varsayilan = [
            'product_id' => $urunId,
            'platform_kod' => '1688',
            'external_id' => null,
            'url' => null,
            'baslik_orijinal' => null,
            'satici_ad' => null,
            'satici_yil' => null,
            'satici_puan' => null,
            'satis_adedi' => null,
            'degerlendirme_adedi' => null,
            'degerlendirme_puani' => null,
            'yanit_orani' => null,
            'birim_fiyat' => null,
            'ham_veri' => null,
            'yakalandi_at' => null,
            'created_at' => '2026-08-01 00:00:00',
            'updated_at' => '2026-08-01 00:00:00',
        ];
        $satir = array_merge($varsayilan, $alanlar);

        $kolonlar = implode(', ', array_keys($satir));
        $yerTutucu = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($satir)));
        $this->pdo->prepare("INSERT INTO listings ({$kolonlar}) VALUES ({$yerTutucu})")->execute($satir);
    }

    public function testSINYALYOKSASKORGIZLIDIR(): void
    {
        // Yalnız veri tamlığı hesaplanabilir (o da düşük): asgari bileşen sayısı yok.
        $this->ilanEkle(1, []);

        $sonuc = $this->skor->hesapla(1, $this->simdi);

        self::assertNull($sonuc['skor'], 'Veri yokken skor üretiliyor — uydurma.');
        self::assertNotNull($sonuc['neden']);
        self::assertStringContainsString('GİZLİ', $sonuc['neden']);
    }

    public function testILANIOLMAYANURUNSKORALMAZ(): void
    {
        $sonuc = $this->skor->hesapla(99, $this->simdi);

        self::assertNull($sonuc['skor']);
        self::assertSame('Bu ürünün ilan kaydı yok.', $sonuc['neden']);
    }

    public function testYETERLISINYALVARSASKORURETILIR(): void
    {
        $this->ilanEkle(1, [
            'satis_adedi' => 5000,
            'degerlendirme_adedi' => 300,
            'degerlendirme_puani' => '4.80',
            'satici_yil' => 8,
            'satici_puan' => '4.60',
            'yanit_orani' => '95.00',
            'external_id' => '9001',
            'url' => 'https://detail.1688.com/offer/9001.html',
            'baslik_orijinal' => '测试',
            'satici_ad' => 'Ningbo Co.',
            'birim_fiyat' => '12.5000',
            'yakalandi_at' => '2026-08-20 10:00:00',
        ]);

        $sonuc = $this->skor->hesapla(1, $this->simdi);

        self::assertNotNull($sonuc['skor']);
        self::assertGreaterThan(0, $sonuc['skor']);
        self::assertLessThanOrEqual(100, $sonuc['skor']);
        self::assertArrayHasKey('satis', $sonuc['bilesenler'], 'Bileşen dökümü ürün detayında gösterilecek.');
        self::assertSame(35, $sonuc['bilesenler']['satis']['agirlik']);
    }

    public function testKIYASPLATFORMICINDEDIR(): void
    {
        // Aynı ham satış adedi, iki farklı platformda FARKLI yüzdelik dilime düşmeli.
        // 1688'de 1000 satış düşük (rakipler 5000-9000), yeni platformda yüksek.
        foreach ([5000, 7000, 9000] as $i => $adet) {
            $this->ilanEkle(10 + $i, ['platform_kod' => '1688', 'satis_adedi' => $adet]);
        }
        foreach ([10, 20, 30] as $i => $adet) {
            $this->ilanEkle(20 + $i, ['platform_kod' => 'yenisite', 'satis_adedi' => $adet]);
        }

        $this->ilanEkle(1, [
            'platform_kod' => '1688',
            'satis_adedi' => 1000,
            'degerlendirme_adedi' => 5,
            'satici_yil' => 1,
            'yakalandi_at' => '2026-08-20 10:00:00',
        ]);
        $this->ilanEkle(2, [
            'platform_kod' => 'yenisite',
            'satis_adedi' => 1000,
            'degerlendirme_adedi' => 5,
            'satici_yil' => 1,
            'yakalandi_at' => '2026-08-20 10:00:00',
        ]);

        $dusuk = $this->skor->hesapla(1, $this->simdi);
        $yuksek = $this->skor->hesapla(2, $this->simdi);

        self::assertSame(0.0, $dusuk['bilesenler']['satis']['puan'], '1688 dağılımında 1000 satış EN ALTTA olmalı.');
        self::assertSame(1.0, $yuksek['bilesenler']['satis']['puan'], 'Kendi platformunda 1000 satış EN ÜSTTE olmalı.');
        self::assertGreaterThan((int) $dusuk['skor'], (int) $yuksek['skor']);
    }

    public function testESKIYAKALAMATAZELIKPUANINIDUSURUR(): void
    {
        $this->ilanEkle(1, [
            'satis_adedi' => 100,
            'degerlendirme_adedi' => 10,
            'satici_yil' => 3,
            'yakalandi_at' => '2026-08-20 10:00:00', // 2 gün önce
        ]);
        $this->ilanEkle(2, [
            'satis_adedi' => 100,
            'degerlendirme_adedi' => 10,
            'satici_yil' => 3,
            'yakalandi_at' => '2025-08-20 10:00:00', // 1 yıl önce
        ]);

        $taze = $this->skor->hesapla(1, $this->simdi);
        $bayat = $this->skor->hesapla(2, $this->simdi);

        self::assertSame(1.0, $taze['bilesenler']['tazelik']['puan']);
        self::assertSame(0.0, $bayat['bilesenler']['tazelik']['puan']);
        self::assertGreaterThan((int) $bayat['skor'], (int) $taze['skor']);
    }

    public function testAGIRLIKLARAYARDANGELIR(): void
    {
        $this->pdo->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?)')->execute([
            SkorHesaplayici::KEY_AGIRLIKLAR,
            json_encode(['satis' => 90, 'degerlendirme' => 5, 'satici' => 5, 'tazelik' => 0, 'veri_tamligi' => 0], JSON_THROW_ON_ERROR),
        ]);

        $agirliklar = $this->skor->agirliklar();

        self::assertSame(90, $agirliklar['satis']);
        self::assertSame(0, $agirliklar['tazelik']);
    }

    public function testBozukAgirlikAyariVARSAYILANADUSER(): void
    {
        $this->pdo->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?)')->execute([
            SkorHesaplayici::KEY_AGIRLIKLAR,
            'bu json degil',
        ]);

        self::assertSame(SkorHesaplayici::VARSAYILAN_AGIRLIKLAR, $this->skor->agirliklar());
    }

    public function testHesaplaVeYazILANAISLER(): void
    {
        $this->ilanEkle(1, [
            'satis_adedi' => 500,
            'degerlendirme_adedi' => 40,
            'satici_yil' => 4,
            'yakalandi_at' => '2026-08-21 10:00:00',
        ]);

        $skor = $this->skor->hesaplaVeYaz(1, $this->simdi);

        $satir = $this->pdo->query('SELECT skor, skor_bilesenleri, skor_at FROM listings WHERE product_id = 1')->fetch();
        self::assertSame($skor, (int) $satir['skor']);
        self::assertNotNull($satir['skor_at']);
        self::assertStringContainsString('satis', (string) $satir['skor_bilesenleri'], 'Bileşen dökümü saklanmalı.');
    }
}
