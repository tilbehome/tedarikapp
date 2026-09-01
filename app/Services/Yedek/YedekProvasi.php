<?php

declare(strict_types=1);

namespace App\Services\Yedek;

use Throwable;

/**
 * GERİ YÜKLEME PROVASI (v1.2.2 B3 — B5 borcu).
 *
 * DENETİMİN EN AĞIR TESPİTİ: yedek alınıyordu ama geri yüklenip
 * yüklenemeyeceği HİÇ SINANMAMIŞTI. Denenmemiş bir yedek, bir yedek değildir
 * — yalnız yedek olduğuna dair bir inançtır. Gerçek gün geldiğinde eksik
 * çıkması, hiç yedek almamış olmaktan DAHA KÖTÜDÜR: o inanç yüzünden başka
 * hiçbir önlem alınmamıştır.
 *
 * BU SINIF GERİ YÜKLEME YAPMAZ — DOĞRULAR. Yıkıcı işlem ayrı bir komuttur
 * (`bin/restore.php`). Ayrım bilinçli: "önce bak, sonra dök" sırası ancak
 * bakmak yıkıcı olmadığında mümkündür.
 */
final class YedekProvasi
{
    public const MANIFEST_ADI = 'MANIFEST.json';

    /**
     * Seti doğrular.
     *
     * @param  list<string> $beklenenMigrationlar kodun beklediği defter; BOŞ
     *         geçilirse karşılaştırma YAPILMAZ. Boşu "hiçbir migration
     *         beklemiyorum" diye yorumlamak, her yedeği ileri sürüm sanmak
     *         olurdu.
     * @return array{
     *     gecerli: bool,
     *     sorunlar: list<string>,
     *     dogrulanan_parca: int,
     *     yedekte_olmayan_migrationlar: list<string>,
     *     kodda_olmayan_migrationlar: list<string>,
     *     ileri_surum_uyarisi: bool,
     *     set_id: ?string
     * }
     */
    public function dogrula(string $setDizini, array $beklenenMigrationlar): array
    {
        $sonuc = [
            'gecerli' => false,
            'sorunlar' => [],
            'dogrulanan_parca' => 0,
            'yedekte_olmayan_migrationlar' => [],
            'kodda_olmayan_migrationlar' => [],
            'ileri_surum_uyarisi' => false,
            'set_id' => null,
        ];

        $manifestYolu = rtrim($setDizini, '/\\') . '/' . self::MANIFEST_ADI;
        if (!is_file($manifestYolu)) {
            // Manifest EN SONDA yazılır; yoksa yedek YARIDA KALMIŞ demektir.
            $sonuc['sorunlar'][] = self::MANIFEST_ADI . ' yok — yedek yarıda kalmış olabilir.';

            return $sonuc;
        }

        try {
            $manifest = YedekManifesti::jsondan((string) file_get_contents($manifestYolu));
        } catch (Throwable $hata) {
            $sonuc['sorunlar'][] = 'Manifest okunamadı: ' . $hata->getMessage();

            return $sonuc;
        }

        $sonuc['set_id'] = $manifest->setId();

        foreach ($manifest->eksikler() as $eksik) {
            $sonuc['sorunlar'][] = 'Manifest eksik/bozuk: ' . $eksik;
        }

        foreach ($manifest->parcalar() as $parca) {
            $yol = rtrim($setDizini, '/\\') . '/' . $parca['ad'];
            if (!is_file($yol)) {
                // Manifest "var" diyor, disk "yok" diyor.
                $sonuc['sorunlar'][] = $parca['ad'] . ' diskte YOK.';

                continue;
            }

            // BOYUT DEĞİL ÖZET: bit çürümesi ve yarım kopyalama boyutu
            // koruyabilir, özeti koruyamaz.
            if (!hash_equals((string) $parca['sha256'], (string) hash_file('sha256', $yol))) {
                $sonuc['sorunlar'][] = $parca['ad'] . ' özet uyuşmadı (bozulmuş ya da yarım kopyalanmış).';

                continue;
            }

            $sonuc['dogrulanan_parca']++;
        }

        if ($beklenenMigrationlar !== []) {
            $yedektekiler = $manifest->migrationDefteri();

            // Yedek ESKİ, kod YENİ: geri yükleme mümkün, sonrasında migration
            // koşmak gerekir. Hata değil UYARI — sessiz kalırsa kullanıcı
            // yarım bir şemayla çalışmaya devam eder.
            /** @var list<string> $eksikler */
            $eksikler = array_values(array_diff($beklenenMigrationlar, $yedektekiler));
            $sonuc['yedekte_olmayan_migrationlar'] = $eksikler;

            // Yedek YENİ, kod ESKİ: TEHLİKELİ. Geri yüklenen veri, kodun
            // tanımadığı bir şemaya aittir. Engellenmez ama bağırır.
            $sonuc['kodda_olmayan_migrationlar'] = array_values(
                array_diff($yedektekiler, $beklenenMigrationlar),
            );
            $sonuc['ileri_surum_uyarisi'] = $sonuc['kodda_olmayan_migrationlar'] !== [];
        }

        $sonuc['gecerli'] = $sonuc['sorunlar'] === [];

        return $sonuc;
    }

    /**
     * İnsan okunur rapor — CI çıktısına ve PM raporuna girer.
     *
     * Sayılarla konuşur: "geçerli" demek yetmez, KAÇ parçanın doğrulandığı
     * görülmeli. Sıfır parçalı bir "geçerli" hiçbir şey kanıtlamaz.
     *
     * @param array{gecerli: bool, sorunlar: list<string>, dogrulanan_parca: int, yedekte_olmayan_migrationlar: list<string>, kodda_olmayan_migrationlar: list<string>, ileri_surum_uyarisi: bool, set_id: ?string} $sonuc
     */
    public function rapor(array $sonuc): string
    {
        $satirlar = [];
        $satirlar[] = sprintf(
            'YEDEK PROVASI: %s — %d parça doğrulandı%s',
            $sonuc['gecerli'] ? 'GEÇERLİ' : 'BAŞARISIZ',
            $sonuc['dogrulanan_parca'],
            $sonuc['set_id'] === null ? '' : ' (set ' . $sonuc['set_id'] . ')',
        );

        foreach ($sonuc['sorunlar'] as $sorun) {
            $satirlar[] = '  [SORUN] ' . $sorun;
        }
        foreach ($sonuc['yedekte_olmayan_migrationlar'] as $eksik) {
            $satirlar[] = '  [UYARI] yedekte yok, geri yükleme sonrası koşacak: ' . $eksik;
        }
        foreach ($sonuc['kodda_olmayan_migrationlar'] as $fazla) {
            $satirlar[] = '  [DİKKAT] yedek İLERİ sürümden: ' . $fazla;
        }

        return implode("\n", $satirlar);
    }
}
