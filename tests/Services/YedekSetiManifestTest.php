<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Yedek\YedekManifesti;
use PHPUnit\Framework\TestCase;

/**
 * v1.2.2 BLOK B1 — YEDEK SETİ MANİFESTİ.
 *
 * DENETİMİN TESPİTİ: "yedek tek başına geri dönülemez." Sebep, yedeğin bir
 * PAKET değil, yan yana duran birkaç dosya olmasıydı:
 *   `yedek-X.sql.enc` · `yedek-X.files.enc` · `yedek-X.media.json` · arşiv
 * Hangisinin hangisiyle gittiğini yalnız DOSYA ADI söylüyordu. Biri eksik ya
 * da yarım yazılmışsa bunu anlamanın yolu yoktu: elinizde "bir yedek" var
 * görünüyor, geri yüklemeye kalkınca eksik olduğu anlaşılıyordu — yani en
 * kötü anda.
 *
 * MANİFEST BU SORUYU KAPATIR: sette hangi parçalar var, her birinin boyutu ve
 * SHA-256'sı ne, hangi sürümden alındı, o anki migration defteri neydi.
 *
 * ATOMİK TAMAMLANMA: manifest EN SONDA yazılır. Yarıda kalan bir yedekte
 * manifest hiç oluşmaz ve set "tamamlanmamış" olarak görünür. Önce yazılsaydı
 * yarım set TAM görünürdü — sessiz veri kaybının klasik biçimi.
 */
final class YedekSetiManifestTest extends TestCase
{
    /** @return array<string, mixed> */
    private function ornekParcalar(): array
    {
        // `sira` + `toplam_parca`: parçaları birbirine BAĞLAYAN alanlar
        // (PM ara hükmü, 3 Eyl). Gerçek bir sette her zaman bulunurlar;
        // fikstür de bu yüzden onlarsız kurulmaz.
        return [
            ['ad' => 'veritabani.sql.enc', 'tur' => 'sql', 'sira' => 1, 'boyut' => 4096, 'sha256' => str_repeat('a', 64)],
            ['ad' => 'ayarlar.files.enc', 'tur' => 'config', 'sira' => 2, 'boyut' => 512, 'sha256' => str_repeat('b', 64)],
            ['ad' => 'medya-001.zip.enc', 'tur' => 'medya', 'sira' => 3, 'boyut' => 8192, 'sha256' => str_repeat('c', 64)],
        ];
    }

    private function manifest(array $ustyaz = []): YedekManifesti
    {
        return new YedekManifesti(array_merge([
            'set_id' => 'b1f0c2d4-1111-4222-8333-444455556666',
            'olusturuldu' => '2026-09-01T03:00:00+03:00',
            'surum' => '1.2.2',
            'sifreleme' => 'aes-256-gcm',
            'parcalar' => $this->ornekParcalar(),
            'toplam_parca' => count($this->ornekParcalar()),
            'migration_defteri' => ['0035_bildirimler', '0036_paylasim_anahtari_sifreli_alan'],
            'zorunlu_turler' => ['sql', 'config'],
        ], $ustyaz));
    }

    public function testTAMSETGECERLI(): void
    {
        self::assertTrue($this->manifest()->tamMi());
        self::assertSame([], $this->manifest()->eksikler());
    }

    public function testZORUNLUPARCAYOKSASETBASARISIZ(): void
    {
        // B1: zorunlu parça (SQL, config, storage sözlükleri, medya manifesti)
        // başarısızsa set BAŞARISIZDIR — indirilemez, gönderilemez. Yarım bir
        // seti "yedek" diye sunmak, olmayan bir güvenceyi satmaktır.
        $eksik = $this->manifest(['parcalar' => [
            ['ad' => 'ayarlar.files.enc', 'tur' => 'config', 'boyut' => 512, 'sha256' => str_repeat('b', 64)],
        ]]);

        self::assertFalse($eksik->tamMi());
        self::assertContains('sql', $eksik->eksikler());
    }

    public function testMEDYAZORUNLUDEGIL(): void
    {
        // Medya arşivi boyut sınırını aşabilir; o hâlde set "kısmi" olur ama
        // BAŞARISIZ olmaz — DB ve ayarlar geri yüklenebilir durumdadır.
        $medyasiz = $this->manifest([
            'parcalar' => [
                ['ad' => 'veritabani.sql.enc', 'tur' => 'sql', 'sira' => 1, 'boyut' => 4096, 'sha256' => str_repeat('a', 64)],
                ['ad' => 'ayarlar.files.enc', 'tur' => 'config', 'sira' => 2, 'boyut' => 512, 'sha256' => str_repeat('b', 64)],
            ],
            'toplam_parca' => 2,
        ]);

        self::assertTrue($medyasiz->tamMi());
        self::assertTrue($medyasiz->medyasizMi(), 'Medyasız set işaretlenmeli.');
        // H1: medyasızlık DURUM eksenini değiştirmez — config alındıysa set TAM'dır.
        self::assertSame(\App\Services\Yedek\YedekManifesti::DURUM_TAM, $medyasiz->durum());
    }

    public function testMEDYALISETKISMIDEGIL(): void
    {
        self::assertFalse($this->manifest()->medyasizMi());
    }

    public function testBOZUKSHAREDDEDILIR(): void
    {
        // 64 hane dışında bir özet, hesaplanmamış ya da kırpılmış demektir.
        // Doğrulama sırasında "eşleşmedi" demek yerine burada yakalanır.
        $bozuk = $this->manifest(['parcalar' => [
            ['ad' => 'veritabani.sql.enc', 'tur' => 'sql', 'boyut' => 4096, 'sha256' => 'kisa'],
            ['ad' => 'ayarlar.files.enc', 'tur' => 'config', 'boyut' => 512, 'sha256' => str_repeat('b', 64)],
        ]]);

        self::assertFalse($bozuk->tamMi());
    }

    public function testSIFIRBOYUTLUPARCAREDDEDILIR(): void
    {
        // 0 baytlık parça, yazımın yarıda kaldığının işaretidir.
        $bozuk = $this->manifest(['parcalar' => [
            ['ad' => 'veritabani.sql.enc', 'tur' => 'sql', 'boyut' => 0, 'sha256' => str_repeat('a', 64)],
            ['ad' => 'ayarlar.files.enc', 'tur' => 'config', 'boyut' => 512, 'sha256' => str_repeat('b', 64)],
        ]]);

        self::assertFalse($bozuk->tamMi());
    }

    public function testMIGRATIONDEFTERITASINIR(): void
    {
        // Geri yüklerken "bu yedek hangi şemaya ait?" sorusunun cevabı budur.
        // Defter olmadan, eski bir yedeği yeni koda geri yüklemek sessizce
        // uyumsuz bir sistem üretir.
        self::assertContains('0036_paylasim_anahtari_sifreli_alan', $this->manifest()->migrationDefteri());
    }

    public function testJSONTURUGIDIPGERIGELIR(): void
    {
        $json = $this->manifest()->jsonOlarak();
        $geri = YedekManifesti::jsondan($json);

        self::assertSame($this->manifest()->setId(), $geri->setId());
        self::assertSame($this->manifest()->migrationDefteri(), $geri->migrationDefteri());
        self::assertTrue($geri->tamMi());
    }

    public function testBOZUKJSONISTISNAATAR(): void
    {
        $this->expectException(\RuntimeException::class);
        YedekManifesti::jsondan('{bu json degil');
    }

    public function testESKISURUMMANIFESTIREDDEDILIR(): void
    {
        // Manifest biçimi ileride değişecek. Sürümsüz bir manifest okumak,
        // alanları yanlış yorumlayıp "doğrulandı" demek demektir.
        $this->expectException(\RuntimeException::class);
        YedekManifesti::jsondan(json_encode(['set_id' => 'x']) ?: '');
    }

    public function testPARCAOZETIINSANOKUR(): void
    {
        // Panelde "manifest ✓/✗, medya parça sayısı, boyut" gösterilecek (B4).
        $ozet = $this->manifest()->ozet();

        self::assertSame(3, $ozet['parca_sayisi']);
        self::assertSame(1, $ozet['medya_parca_sayisi']);
        self::assertSame(4096 + 512 + 8192, $ozet['toplam_bayt']);
        self::assertTrue($ozet['tam']);
    }
}
