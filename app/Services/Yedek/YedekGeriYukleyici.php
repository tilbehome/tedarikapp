<?php

declare(strict_types=1);

namespace App\Services\Yedek;

use App\Services\BackupService;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * YEDEK SETİNDEN GERİ YÜKLEME (v1.2.2 B3).
 *
 * `YedekProvasi` DOĞRULAR, bu sınıf GERİ YÜKLER. Ayrı olmalarının sebebi
 * yıkıcılıktır: doğrulamayı kullanıcı istediği an, korkmadan çalıştırabilmeli;
 * geri yükleme ise üstüne yazan bir işlemdir ve ancak bilinçli olarak
 * istenmelidir.
 *
 * DOĞRULAMA ÖNCE, TEK PARÇA BİLE AÇILMADAN ÖNCE (PM ara hükmü, 3 Eyl):
 * set provadan geçmezse buradan HİÇBİR ŞEY yazılmaz. "Elimizdekini yükleyelim,
 * eksik olan sonra bakarız" yaklaşımı, kısmi bir yedeği tam bir veritabanının
 * ÜSTÜNE yazar — ve o noktada geri dönülecek bir yer kalmamıştır. Kısmi setten
 * SESSİZ geri yükleme imkânsız olmalıdır; bu sınıfın tek kapısı budur.
 *
 * SIRA MANİFESTTEN OKUNUR, dosya adından türetilmez: adlandırma değişirse
 * türetme sessizce bozulur, manifest ise yalan söylemek için değiştirilmek
 * zorundadır.
 */
final class YedekGeriYukleyici
{
    /** Tek sorguda yüklenmeyecek kadar büyük dökümler için parça sınırı. */
    private const SQL_PARCA_BAYT = 4 * 1024 * 1024;

    public function __construct(
        private readonly BackupService $yedekServisi,
        private readonly YedekProvasi $prova = new YedekProvasi(),
    ) {
    }

    /**
     * Seti doğrular ve manifesti döner.
     *
     * @param  list<string>     $beklenenMigrationlar
     * @throws RuntimeException set geri yüklenemez durumdaysa
     */
    public function kapiyiAc(string $setDizini, array $beklenenMigrationlar = []): YedekManifesti
    {
        $sonuc = $this->prova->dogrula($setDizini, $beklenenMigrationlar);
        if (!$sonuc['gecerli']) {
            throw new RuntimeException(
                'GERİ YÜKLEME DURDURULDU — set provayı geçemedi:' . PHP_EOL
                . '  · ' . implode(PHP_EOL . '  · ', $sonuc['sorunlar']) . PHP_EOL
                . 'Kısmi bir seti yüklemek, sağlam veritabanının üstüne eksik veri yazmak olurdu.',
            );
        }

        return YedekManifesti::jsondan(
            (string) file_get_contents($setDizini . '/' . YedekProvasi::MANIFEST_ADI),
        );
    }

    /**
     * Setteki SQL parçasını hedef bağlantıya yükler.
     *
     * @return array{tablo_sayisi: int, satir_sayisi: int, tablolar: array<string, int>}
     */
    public function veritabaniniYukle(PDO $hedef, string $setDizini, YedekManifesti $manifest): array
    {
        $sql = $this->yedekServisi->decrypt($this->parcaIcerigi($setDizini, $manifest, 'sql'));
        if (trim($sql) === '') {
            throw new RuntimeException('SQL parçası çözüldü ama İÇİ BOŞ — bu setten geri dönülemez.');
        }

        // Yükleme sırasında yabancı anahtar sırası dert olmasın: döküm tablo
        // tablo yazılır ve bir tablo, henüz yazılmamış bir tabloya referans
        // verebilir. Yükleme bitince kısıt yeniden açılır.
        $hedef->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->sqlParcalari($sql) as $parca) {
                $hedef->exec($parca);
            }
        } finally {
            $hedef->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return $this->sayim($hedef);
    }

    /**
     * Setteki medya parçalarını SIRAYLA açar.
     *
     * SIRA ÖNEMLİDİR: parçalar aynı adlı bir dosyanın farklı sürümlerini
     * taşıyabilir (yedek alınırken dosya değişmişse) ve SON yazılan kazanmalı.
     *
     * @return array{dosya_sayisi: int, parca_sayisi: int}
     */
    public function medyayiYukle(string $hedefDizin, string $setDizini, YedekManifesti $manifest): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive yok — medya parçaları açılamaz.');
        }
        if (!is_dir($hedefDizin) && !@mkdir($hedefDizin, 0o775, true) && !is_dir($hedefDizin)) {
            throw new RuntimeException('Medya hedef dizini açılamadı: ' . $hedefDizin);
        }

        $dosya = 0;
        $parcaSayisi = 0;

        foreach ($manifest->siraliParcalar() as $parca) {
            if ($parca['tur'] !== 'medya') {
                continue;
            }
            $parcaSayisi++;
            $gecici = $hedefDizin . '/.geri-yukleme-' . $parcaSayisi . '.zip';
            $icerik = $this->yedekServisi->decrypt(
                (string) file_get_contents($setDizini . '/' . $parca['ad']),
            );
            if (@file_put_contents($gecici, $icerik) === false) {
                throw new RuntimeException('Medya parçası geçici dosyaya yazılamadı: ' . $parca['ad']);
            }

            try {
                $zip = new ZipArchive();
                if ($zip->open($gecici) !== true) {
                    throw new RuntimeException('Medya arşivi açılamadı: ' . $parca['ad']);
                }
                // GİRDİLER TEK TEK YAZILIR, `extractTo` ile TOPLUCA DEĞİL.
                // İki sebep:
                //   1. Arşivdeki adlar `media/<dosya>` önekli (yedek alırken
                //      öyle yazılıyor). Hedefe olduğu gibi açmak
                //      `public/media/media/...` üretirdi — geri yüklenen
                //      görseller görünmezdi.
                //   2. ZIP girdi adı `../` içerebilir (zip-slip). `basename()`
                //      dizin bileşenini tümden atar; bizim arşivlerimiz düz
                //      olduğu için hiçbir şey kaybetmez, kötü niyetli ya da
                //      bozuk bir arşivde ise hedef dizinin dışına çıkılamaz.
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $girdiAdi = (string) $zip->getNameIndex($i);
                    if ($girdiAdi === '' || str_ends_with($girdiAdi, '/')) {
                        continue;
                    }
                    $icerikGirdi = $zip->getFromIndex($i);
                    if ($icerikGirdi === false) {
                        throw new RuntimeException('Medya girdisi okunamadı: ' . $girdiAdi);
                    }
                    $hedefYol = $hedefDizin . '/' . basename($girdiAdi);
                    if (@file_put_contents($hedefYol, $icerikGirdi) === false) {
                        throw new RuntimeException('Medya dosyası yazılamadı: ' . $hedefYol);
                    }
                    $dosya++;
                }
                $zip->close();
            } finally {
                @unlink($gecici);
            }
        }

        return ['dosya_sayisi' => $dosya, 'parca_sayisi' => $parcaSayisi];
    }

    /**
     * Ayar parçasını ÇÖZER ama diske YAZMAZ.
     *
     * Bilinçli: `config.php` içinde APP_KEY ve DB parolası vardır; onu geri
     * yüklemek, çalışan bir kurulumun kimliğini sessizce değiştirebilir.
     * Operatör içeriği görür ve hangi dosyayı geri koyacağına kendi karar
     * verir (K44 ruhu: yıkıcı olan adım elle onaylanır).
     *
     * @return array<string, string> göreli yol → içerik
     */
    public function ayarlariCoz(string $setDizini, YedekManifesti $manifest): array
    {
        return $this->yedekServisi->decryptFiles($this->parcaIcerigi($setDizini, $manifest, 'config'));
    }

    /**
     * Hedef veritabanındaki tabloları BOŞALTIR (geri yükleme öncesi).
     *
     * `DROP` değil `DROP TABLE` listesi: şema da yedekten gelmeli, yoksa eski
     * bir sütun ortada kalır ve yedek "yüklendi" görünürken şema karışıktır.
     */
    public function hedefiTemizle(PDO $hedef): int
    {
        $tablolar = $this->tabloAdlari($hedef);
        if ($tablolar === []) {
            return 0;
        }

        $hedef->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tablolar as $tablo) {
                $hedef->exec('DROP TABLE IF EXISTS `' . $tablo . '`');
            }
        } finally {
            $hedef->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return count($tablolar);
    }

    /** @return array{tablo_sayisi: int, satir_sayisi: int, tablolar: array<string, int>} */
    public function sayim(PDO $hedef): array
    {
        $tablolar = [];
        $toplam = 0;

        foreach ($this->tabloAdlari($hedef) as $tablo) {
            $sayi = (int) $hedef->query('SELECT COUNT(*) FROM `' . $tablo . '`')->fetchColumn();
            $tablolar[$tablo] = $sayi;
            $toplam += $sayi;
        }

        return ['tablo_sayisi' => count($tablolar), 'satir_sayisi' => $toplam, 'tablolar' => $tablolar];
    }

    /**
     * Dizindeki dosyaların SHA-256 haritası — geri yüklemenin BİREBİR olduğunu
     * kanıtlamak için. Dosya saymak yetmez: aynı sayıda ama bozuk içerik,
     * saymayla ayırt edilemez.
     *
     * @return array<string, string> göreli yol → sha256
     */
    public function dosyaOzetleri(string $dizin): array
    {
        if (!is_dir($dizin)) {
            return [];
        }

        $ozetler = [];
        $gezgin = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dizin, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $dosya */
        foreach ($gezgin as $dosya) {
            if (!$dosya->isFile()) {
                continue;
            }
            $goreli = str_replace('\\', '/', substr($dosya->getPathname(), strlen($dizin) + 1));
            $ozetler[$goreli] = (string) hash_file('sha256', $dosya->getPathname());
        }

        ksort($ozetler);

        return $ozetler;
    }

    /** @return list<string> */
    private function tabloAdlari(PDO $hedef): array
    {
        try {
            $satirlar = $hedef->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable) {
            return [];
        }

        $adlar = [];
        foreach ($satirlar as $tablo) {
            $tablo = (string) $tablo;
            // Beklenmeyen ad, sorguya sokulmadan ELENİR: tablo adları
            // parametrelenemez, o yüzden tek savunma budur.
            if (preg_match('/^[A-Za-z0-9_]+$/', $tablo) === 1) {
                $adlar[] = $tablo;
            }
        }

        return $adlar;
    }

    private function parcaIcerigi(string $setDizini, YedekManifesti $manifest, string $tur): string
    {
        foreach ($manifest->siraliParcalar() as $parca) {
            if ($parca['tur'] === $tur) {
                $icerik = @file_get_contents($setDizini . '/' . $parca['ad']);
                if ($icerik === false) {
                    throw new RuntimeException('Parça okunamadı: ' . $parca['ad']);
                }

                return $icerik;
            }
        }

        throw new RuntimeException('Sette "' . $tur . '" türünde parça yok.');
    }

    /**
     * Dökümü ifade sınırlarında böler.
     *
     * NEDEN BÖLÜNÜR: büyük bir dökümü tek sorguda göndermek, dökümün
     * tamamını hem PHP belleğinde hem sunucu tamponunda tutar. Paylaşımlı
     * hostingde `memory_limit` bunu kaldırmaz ve geri yükleme tam da en çok
     * ihtiyaç duyulan anda — büyük veritabanında — başarısız olur.
     *
     * @return list<string>
     */
    private function sqlParcalari(string $sql): array
    {
        if (strlen($sql) <= self::SQL_PARCA_BAYT) {
            return [$sql];
        }

        $parcalar = [];
        $tampon = '';
        foreach (explode(";\n", $sql) as $ifade) {
            $tampon .= $ifade . ";\n";
            if (strlen($tampon) >= self::SQL_PARCA_BAYT) {
                $parcalar[] = $tampon;
                $tampon = '';
            }
        }
        if (trim($tampon) !== '') {
            $parcalar[] = $tampon;
        }

        return $parcalar;
    }
}
