<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Connection;
use App\Core\KatalogDurumu;
use App\Services\Bildirim\BildirimKatalogu;
use App\Services\Bildirim\BildirimRepository;
use App\Services\Bildirim\BildirimYayinci;
use App\Services\Bildirim\GrupAnahtariCozucu;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FrozenClock;

/**
 * K99 — KATALOG EKSİKKEN AÇILIŞ DAVRANIŞI (madde 3'ün kanıtı).
 *
 * ÖNCESİ: katalog yoksa `yayimla()` istisnayı YUTUYOR ve `null` dönüyordu.
 * Uygulama çalışmaya devam ediyor, hiçbir bildirim üretilmiyor, hiçbir yerde
 * bir işaret yok. Canlıda tam olarak bu yaşandı.
 *
 * SONRASI: eksiklik AÇILIŞTA yakalanır (`KatalogDurumu`) ve yayıncı artık
 * yutmaz. Bu test ikisini de kanıtlar.
 */
final class KatalogDurumuTest extends TestCase
{
    private string $bosKok;

    protected function setUp(): void
    {
        parent::setUp();
        // Katalogların OLMADIĞI bir kök: paketin bozuk hâlinin taklidi.
        $this->bosKok = sys_get_temp_dir() . '/tedarikapp-katalogsuz-' . bin2hex(random_bytes(4));
        mkdir($this->bosKok . '/config', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->bosKok . '/config/*') ?: [] as $dosya) {
            @unlink($dosya);
        }
        @rmdir($this->bosKok . '/config');
        @rmdir($this->bosKok);
        parent::tearDown();
    }

    public function testGERCEKKOKSAGLIKLIDIR(): void
    {
        $durum = new KatalogDurumu(dirname(__DIR__, 2));

        self::assertTrue($durum->saglikliMi(), 'Repo kökünde kataloglar yerinde olmalı.');
        self::assertCount(2, $durum->dokum(), 'Sağlıklı kataloglar da dökümde görünmeli.');
        foreach ($durum->dokum() as $satir) {
            self::assertTrue($satir['saglikli']);
            self::assertNull($satir['hata']);
            self::assertStringStartsWith('config/', (string) $satir['yol'], 'K99: kataloglar config/ altında.');
        }
    }

    public function testEKSIKKATALOGSESSIZKALMAZ(): void
    {
        $durum = new KatalogDurumu($this->bosKok);

        self::assertFalse($durum->saglikliMi());
        self::assertFalse($durum->katalogSaglikli('bildirim'));
        self::assertFalse($durum->katalogSaglikli('panorama'));
        self::assertStringContainsString('bulunamadı', (string) $durum->hata('bildirim'));
        // Mesaj NE YAPILACAĞINI da söylemeli — "hata oluştu" yetmez.
        self::assertStringContainsString('Paket eksik', (string) $durum->hata('bildirim'));
    }

    public function testBOZUKJSONDAEKSIKSAYILIR(): void
    {
        // Dosya VAR ama ayrıştırılamıyor: eksik dosyadan daha sinsi bir durum,
        // çünkü "dosya orada" diye bakan bir denetim bunu geçirirdi.
        file_put_contents($this->bosKok . '/config/bildirim-olay-katalogu.json', '{ bozuk json');
        file_put_contents($this->bosKok . '/config/panorama-brifing-katalogu.json', '{"brifing_sablonlari":[]}');

        $durum = new KatalogDurumu($this->bosKok);

        self::assertFalse($durum->katalogSaglikli('bildirim'));
        self::assertStringContainsString('bozuk JSON', (string) $durum->hata('bildirim'));
        self::assertTrue($durum->katalogSaglikli('panorama'), 'Geçerli JSON sağlıklı sayılmalı.');
    }

    public function testYAYINCIARTIKYUTMAZ(): void
    {
        // MADDE 3'ÜN ASIL KANITI: katalog yokken `yayimla()` sessizce null
        // dönmez — istisna YUKARI ÇIKAR ve görünür.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        /** @var \App\Core\Migration $migration */
        $migration = require dirname(__DIR__, 2) . '/migrations/0035_bildirimler.php';
        $migration->up($pdo);

        $baglanti = Connection::fromCallable(fn (): PDO => $pdo);
        $yayinci = new BildirimYayinci(
            new BildirimRepository($baglanti),
            new BildirimKatalogu($this->bosKok),
            new GrupAnahtariCozucu(),
            new FrozenClock(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bildirim olay kataloğu okunamadı');

        $yayinci->yayimla('NTF-LIST-CREATED', ['liste_id' => 1], 42);
    }

    public function testKATALOGVARSAYAYINCICALISIR(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        /** @var \App\Core\Migration $migration */
        $migration = require dirname(__DIR__, 2) . '/migrations/0035_bildirimler.php';
        $migration->up($pdo);

        $baglanti = Connection::fromCallable(fn (): PDO => $pdo);
        $yayinci = new BildirimYayinci(
            new BildirimRepository($baglanti),
            new BildirimKatalogu(dirname(__DIR__, 2)),
            new GrupAnahtariCozucu(),
            new FrozenClock(),
        );

        $id = $yayinci->yayimla('NTF-LIST-CREATED', ['liste_id' => 1, 'liste_adi' => 'Test'], 42);

        self::assertGreaterThan(0, $id);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn());
    }
}
