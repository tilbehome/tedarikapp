<?php

declare(strict_types=1);

namespace App\Services\Yedek;

use RuntimeException;

/**
 * YEDEK SETİ MANİFESTİ (v1.2.2 B1).
 *
 * DENETİMİN TESPİTİ: "yedek tek başına geri dönülemez." Sebep, yedeğin bir
 * PAKET değil, yan yana duran birkaç dosya olmasıydı — `yedek-X.sql.enc`,
 * `yedek-X.files.enc`, medya manifesti, medya arşivi. Hangisinin hangisiyle
 * gittiğini yalnız DOSYA ADI söylüyordu. Biri eksik ya da yarım yazılmışsa
 * bunu anlamanın yolu yoktu: elinizde "bir yedek" var görünüyor, geri
 * yüklemeye kalkınca eksik olduğu anlaşılıyordu. Yani en kötü anda.
 *
 * Manifest şu soruları kapatır: sette hangi parçalar var, her birinin boyutu
 * ve SHA-256'sı ne, hangi sürümden alındı, o anki migration defteri neydi.
 *
 * ATOMİK TAMAMLANMA: manifest EN SONDA yazılır. Yarıda kalan bir yedekte
 * manifest hiç oluşmaz ve set "tamamlanmamış" görünür. Önce yazılsaydı yarım
 * set TAM görünürdü — sessiz veri kaybının klasik biçimi.
 *
 * KISMİ ≠ BAŞARISIZ: medya arşivi boyut sınırını aşabilir. O hâlde set kısmi
 * olur ama başarısız olmaz; DB ve ayarlar geri yüklenebilir durumdadır.
 * İkisini aynı kefeye koymak, kullanılabilir bir yedeği çöpe attırırdı.
 */
final class YedekManifesti
{
    /**
     * Manifest biçim sürümü.
     *
     * SÜRÜMSÜZ MANİFEST OKUNMAZ: biçim ileride değişecek ve alanları yanlış
     * yorumlayıp "doğrulandı" demek, doğrulamamaktan kötüdür.
     */
    public const BICIM = 1;

    /** Bu türler olmadan set BAŞARISIZDIR. */
    private const ZORUNLU_TURLER = ['sql', 'config'];

    /**
     * @param array{
     *     set_id: string,
     *     olusturuldu: string,
     *     surum: string,
     *     sifreleme: string,
     *     parcalar: list<array{ad: string, tur: string, boyut: int, sha256: string}>,
     *     migration_defteri: list<string>,
     *     zorunlu_turler?: list<string>
     * } $veri
     */
    public function __construct(private readonly array $veri)
    {
    }

    public function setId(): string
    {
        return (string) $this->veri['set_id'];
    }

    /** @return list<string> */
    public function migrationDefteri(): array
    {
        return $this->veri['migration_defteri'];
    }

    /** @return list<array{ad: string, tur: string, boyut: int, sha256: string}> */
    public function parcalar(): array
    {
        return $this->veri['parcalar'];
    }

    /**
     * Set geri yüklenebilir mi?
     *
     * @return list<string> eksik ya da bozuk olanlar; boşsa set tamdır
     */
    public function eksikler(): array
    {
        $sorunlar = [];

        /** @var list<string> $zorunlu */
        $zorunlu = $this->veri['zorunlu_turler'] ?? self::ZORUNLU_TURLER;
        $mevcutTurler = array_column($this->parcalar(), 'tur');

        foreach ($zorunlu as $tur) {
            if (!in_array($tur, $mevcutTurler, true)) {
                $sorunlar[] = $tur;
            }
        }

        foreach ($this->parcalar() as $parca) {
            // 0 BAYT = yazım yarıda kalmış. "Dosya var" demek yetmez.
            if ((int) $parca['boyut'] <= 0) {
                $sorunlar[] = $parca['ad'] . ' (boş)';
            }
            // 64 hane dışı özet: hesaplanmamış ya da kırpılmış. Doğrulama
            // sırasında "eşleşmedi" demek yerine BURADA yakalanır — sebebi
            // orada anlaşılmaz olurdu.
            if (preg_match('/^[0-9a-f]{64}$/i', (string) $parca['sha256']) !== 1) {
                $sorunlar[] = $parca['ad'] . ' (özet geçersiz)';
            }
        }

        /** @var list<string> $tekil */
        $tekil = array_values(array_unique($sorunlar));

        return $tekil;
    }

    public function tamMi(): bool
    {
        return $this->eksikler() === [];
    }

    /** Medya parçası olmayan set KISMİ'dir — başarısız değil. */
    public function kismiMi(): bool
    {
        return !in_array('medya', array_column($this->parcalar(), 'tur'), true);
    }

    /** @return array{parca_sayisi: int, medya_parca_sayisi: int, toplam_bayt: int, tam: bool, kismi: bool} */
    public function ozet(): array
    {
        $parcalar = $this->parcalar();

        return [
            'parca_sayisi' => count($parcalar),
            'medya_parca_sayisi' => count(array_filter(
                $parcalar,
                static fn (array $p): bool => $p['tur'] === 'medya',
            )),
            'toplam_bayt' => array_sum(array_map(static fn (array $p): int => (int) $p['boyut'], $parcalar)),
            'tam' => $this->tamMi(),
            'kismi' => $this->kismiMi(),
        ];
    }

    public function jsonOlarak(): string
    {
        return json_encode(
            ['bicim' => self::BICIM] + $this->veri,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        );
    }

    /** @throws RuntimeException biçim tanınmıyorsa ya da JSON bozuksa */
    public static function jsondan(string $json): self
    {
        try {
            /** @var mixed $veri */
            $veri = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $hata) {
            throw new RuntimeException('Yedek manifesti okunamadı: ' . $hata->getMessage(), 0, $hata);
        }

        if (!is_array($veri) || ($veri['bicim'] ?? null) !== self::BICIM) {
            throw new RuntimeException(
                'Yedek manifesti tanınmayan biçimde (beklenen sürüm ' . self::BICIM . '). '
                . 'Sürümsüz bir manifesti okumak, alanları yanlış yorumlayıp "doğrulandı" demek olurdu.',
            );
        }

        unset($veri['bicim']);

        /** @var array{set_id: string, olusturuldu: string, surum: string, sifreleme: string, parcalar: list<array{ad: string, tur: string, boyut: int, sha256: string}>, migration_defteri: list<string>, zorunlu_turler?: list<string>} $veri */
        return new self($veri);
    }
}
