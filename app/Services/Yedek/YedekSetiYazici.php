<?php

declare(strict_types=1);

namespace App\Services\Yedek;

use RuntimeException;

/**
 * YEDEK SETİNİ ATOMİK YAZAR (v1.2.2 B1).
 *
 * ESKİ HÂL: parçalar doğrudan `storage/backups/` içine yan yana yazılıyordu.
 * Gece koşusu ortada kesilirse (süre sınırı, disk dolması, süreç ölümü) yarım
 * bir yedek KALICI olarak orada duruyor ve liste onu tam bir yedek gibi
 * gösteriyordu. Kullanıcı "üç yedeğim var" diyordu; gerçekte ikisi eksikti.
 * Bu, yedeğin en kötü kusurudur: var olduğuna inandırır, olmadığını en kötü
 * anda söyler.
 *
 * YENİ HÂL — ÜÇ ADIM:
 *   1. Parçalar HAZIRLIK dizinine yazılır (`.hazirlik-<set_id>`),
 *   2. set doğrulanır ve MANİFEST hazırlık dizinine konur,
 *   3. dizin TEK ADIMDA nihai adına taşınır.
 *
 * `rename()` aynı dosya sisteminde atomiktir: ya eski ad vardır ya yeni.
 * "Yarısı taşınmış dizin" diye bir ara hâl yoktur — bütün tasarım buna dayanır.
 *
 * Yarıda kalan koşum `.hazirlik-` önekli bir dizin bırakır: `setler()` onu
 * GÖRMEZ (nokta öneki hem listeden hem çoğu dosya yöneticisinden gizler) ve
 * `yarimlariTemizle()` bir sonraki koşumda siler.
 */
final class YedekSetiYazici
{
    private const HAZIRLIK_ONEKI = '.hazirlik-';
    private const SET_ONEKI = 'set-';

    /**
     * @param list<string> $migrationDefteri yedeğin alındığı andaki uygulanmış
     *        migration listesi — geri yüklerken "bu yedek hangi şemaya ait?"
     *        sorusunun tek cevabı budur
     */
    public function __construct(
        private readonly string $kokDizin,
        private readonly string $surum,
        private readonly array $migrationDefteri,
        private readonly string $sifrelemeAdi = 'aes-256-gcm',
    ) {
    }

    /**
     * Yeni set açar (hazırlık dizini).
     *
     * @return array{set_id: string, damga: string, hazirlik: string, parcalar: list<array{ad: string, tur: string, boyut: int, sha256: string}>}
     */
    public function baslat(string $damga): array
    {
        $setId = $this->uuid();
        $hazirlik = $this->kokDizin . '/' . self::HAZIRLIK_ONEKI . $setId;

        if (!@mkdir($hazirlik, 0o775, true) && !is_dir($hazirlik)) {
            throw new RuntimeException('Yedek hazırlık dizini açılamadı: ' . $hazirlik);
        }

        return ['set_id' => $setId, 'damga' => $damga, 'hazirlik' => $hazirlik, 'parcalar' => []];
    }

    /**
     * Parçayı hazırlık dizinine yazar ve özetini SETE İŞLER.
     *
     * ÖZET GERÇEK İÇERİKTEN hesaplanır, çağırandan alınmaz: çağıranın verdiği
     * bir özet, yazım sırasında bozulan içeriği yakalayamazdı.
     *
     * @param array{set_id: string, damga: string, hazirlik: string, parcalar: list<array{ad: string, tur: string, boyut: int, sha256: string}>} $set
     */
    public function parcaEkle(array &$set, string $ad, string $tur, string $icerik): void
    {
        $yol = $set['hazirlik'] . '/' . $ad;
        if (@file_put_contents($yol, $icerik) === false) {
            throw new RuntimeException('Yedek parçası yazılamadı: ' . $ad);
        }
        @chmod($yol, 0o600);

        $set['parcalar'][] = [
            'ad' => $ad,
            'tur' => $tur,
            'boyut' => (int) filesize($yol),
            'sha256' => (string) hash_file('sha256', $yol),
        ];
    }

    /**
     * Manifesti yazar ve seti nihai adına TAŞIR.
     *
     * DOĞRULAMA ÖNCE: zorunlu parçası olmayan bir set nihai ada HİÇ ulaşmaz.
     * Yarım seti "indirilebilir" yapmak, olmayan bir güvenceyi satmaktır.
     *
     * @param  array{set_id: string, damga: string, hazirlik: string, parcalar: list<array{ad: string, tur: string, boyut: int, sha256: string}>} $set
     * @return string nihai dizin yolu
     */
    public function tamamla(array $set): string
    {
        $manifest = new YedekManifesti([
            'set_id' => $set['set_id'],
            'olusturuldu' => date(DATE_ATOM),
            'surum' => $this->surum,
            'sifreleme' => $this->sifrelemeAdi,
            'parcalar' => $set['parcalar'],
            'migration_defteri' => $this->migrationDefteri,
        ]);

        if (!$manifest->tamMi()) {
            throw new RuntimeException(
                'Yedek seti TAMAMLANAMADI — zorunlu parça eksik ya da bozuk: '
                . implode(', ', $manifest->eksikler())
                . '. Hazırlık dizini bırakıldı, sonraki koşum temizler.',
            );
        }

        $manifestYolu = $set['hazirlik'] . '/' . YedekProvasi::MANIFEST_ADI;
        if (@file_put_contents($manifestYolu, $manifest->jsonOlarak()) === false) {
            throw new RuntimeException('Manifest yazılamadı.');
        }
        @chmod($manifestYolu, 0o600);

        $nihai = $this->kokDizin . '/' . self::SET_ONEKI . $set['damga'];
        if (!@rename($set['hazirlik'], $nihai)) {
            throw new RuntimeException('Yedek seti nihai adına taşınamadı: ' . $nihai);
        }

        return $nihai;
    }

    /**
     * Tamamlanmış setler — YENİDEN ESKİYE.
     *
     * Hazırlık dizinleri BURADA GÖRÜNMEZ: yarım bir set listede yer alırsa
     * kullanıcı onu sayar ve güvenir.
     *
     * @return list<string> dizin yolları
     */
    public function setler(): array
    {
        $bulunan = glob($this->kokDizin . '/' . self::SET_ONEKI . '*', GLOB_ONLYDIR) ?: [];
        // Ad damgası `YYYYMMDD-HHMMSS` olduğu için ters alfabetik = yeniden eskiye.
        rsort($bulunan);

        return $bulunan;
    }

    /**
     * Yarım kalmış hazırlık dizinlerini siler.
     *
     * @return int silinen dizin sayısı
     */
    public function yarimlariTemizle(): int
    {
        $silinen = 0;
        foreach (glob($this->kokDizin . '/' . self::HAZIRLIK_ONEKI . '*', GLOB_ONLYDIR) ?: [] as $dizin) {
            foreach (glob($dizin . '/*') ?: [] as $dosya) {
                @unlink($dosya);
            }
            if (@rmdir($dizin)) {
                $silinen++;
            }
        }

        return $silinen;
    }

    /** RFC 4122 v4 — set kimliği; dosya adından bağımsız, kalıcı referans. */
    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0F) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
