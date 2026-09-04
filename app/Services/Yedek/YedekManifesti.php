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
 *
 * PARÇALAR BİRBİRİNE BAĞLIDIR (PM ara hükmü, 3 Eyl): tam seti tek zip olarak
 * indirmek YOK — panel parçaları tek tek sunuyor. O hâlde parçaları bir arada
 * TUTAN şey manifest olmak zorundadır, yoksa kullanıcı beş parçanın üçünü
 * indirir, geri yükler ve eksikliği ancak veriye baktığında fark eder. Bağlar:
 *
 *   • `sira`         — parçanın açılma sırası (medya 002, 001'den sonra),
 *   • `toplam_parca` — sette KAÇ parça olduğu,
 *   • `sha256`       — her parçanın kimliği.
 *
 * `toplam_parca` neden ayrı yazılır, elde olanı saymak niye yetmez: eksik olan
 * parça manifestteki LİSTEDEN de düşmüş olabilir (manifest yazılırken hata,
 * elle düzenleme, aktarımda bozulma). Beklenen sayı bağımsız bir tanık olarak
 * durur; listeyi saymak yalnız listenin kendi kendisiyle tutarlılığını ölçer.
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

    /**
     * Bu türler olmadan set BAŞARISIZDIR.
     *
     * H1 (PM hükmü, 4 Eyl): yalnız `sql`. Eskiden `config` de zorunluydu ve
     * `config.php` üstünde bir gecelik izin kazası, o gece VERİTABANI
     * yedeğinin de alınmaması demekti. Ayarlar yeniden girilebilir,
     * veritabanı girilemez — kaybı en büyük parçayı, kaybı en küçük parça
     * yüzünden düşürmek orantısızdı. Config eksikse set KISMİ olur (aşağıda),
     * reddedilmez.
     */
    private const ZORUNLU_TURLER = ['sql'];

    /** Set durumu: bütün bileşenler alındı. */
    public const DURUM_TAM = 'TAM';

    /**
     * Set durumu: bir bileşen BİLEREK alınamadı (`eksik` listesi söyler,
     * `sebep` neden olduğunu söyler). KISMİ ≠ eksik parça: kısmi set "daha
     * az parçayla bilinçli tamamlanmış" settir; parçası kaybolmuş set ise
     * GEÇERSİZDİR ve parça bağı (sira / toplam_parca) onu kısmi sette de
     * yakalar.
     */
    public const DURUM_KISMI = 'KISMI';

    /**
     * @param array{
     *     set_id: string,
     *     olusturuldu: string,
     *     surum: string,
     *     sifreleme: string,
     *     parcalar: list<array{ad: string, tur: string, sira?: int, boyut: int, sha256: string}>,
     *     migration_defteri: list<string>,
     *     toplam_parca?: int,
     *     eksik?: list<string>,
     *     sebep?: string|null,
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

    /** @return list<array{ad: string, tur: string, sira?: int, boyut: int, sha256: string}> */
    public function parcalar(): array
    {
        return $this->veri['parcalar'];
    }

    /**
     * Sette OLMASI GEREKEN parça sayısı.
     *
     * Manifest bunu yazmıyorsa 0 döner ve `eksikler()` seti reddeder — sayıyı
     * listeden türetmek, bu alanın var oluş sebebini yok ederdi.
     */
    public function toplamParca(): int
    {
        return (int) ($this->veri['toplam_parca'] ?? 0);
    }

    /**
     * Parçalar AÇILMA SIRASINDA.
     *
     * Geri yükleme bu sırayı izler: önce şema/veri (sql), sonra ayarlar, sonra
     * medya arşivleri 001'den itibaren. Medyada sıra önemlidir — parçalar aynı
     * ada sahip bir dosyanın farklı sürümlerini taşıyabilir ve sonuncusu
     * kazanmalıdır.
     *
     * @return list<array{ad: string, tur: string, sira?: int, boyut: int, sha256: string}>
     */
    public function siraliParcalar(): array
    {
        $parcalar = $this->parcalar();
        usort($parcalar, static fn (array $a, array $b): int => ($a['sira'] ?? 0) <=> ($b['sira'] ?? 0));

        return $parcalar;
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

        foreach ($this->parcaBaglari() as $sorun) {
            $sorunlar[] = $sorun;
        }

        /** @var list<string> $tekil */
        $tekil = array_values(array_unique($sorunlar));

        return $tekil;
    }

    /**
     * PARÇALARI BAĞLAYAN denetimler: sayı, sıra, boşluk.
     *
     * Hepsi FAIL-CLOSED'dur. Bir sette bu bağlardan biri kurulamıyorsa set
     * "belki tamdır" değil, GEÇERSİZDİR: geri yüklenebilirliğinden emin
     * olamadığımız bir yedeği geri yüklenebilir saymak, yedeğin olmamasından
     * daha tehlikelidir — çünkü ona güvenip başka önlem almazsınız.
     *
     * @return list<string>
     */
    private function parcaBaglari(): array
    {
        $sorunlar = [];
        $parcalar = $this->parcalar();
        $beklenen = $this->toplamParca();

        if ($beklenen <= 0) {
            return ['toplam parça sayısı manifestte yok'];
        }
        if ($beklenen !== count($parcalar)) {
            $sorunlar[] = sprintf(
                'toplam parça uyuşmuyor (manifest %d diyor, listede %d parça var)',
                $beklenen,
                count($parcalar),
            );
        }

        $siralar = [];
        foreach ($parcalar as $parca) {
            if (!isset($parca['sira'])) {
                // Sırasız parça, bu bağdan ÖNCE yazılmış bir setten gelir.
                // Sessizce kabul etmek, sıralamanın hiç garanti edilmediği bir
                // seti geri yüklenebilir saymak olurdu.
                $sorunlar[] = $parca['ad'] . ' (sıra numarası yok)';

                continue;
            }
            $siralar[] = (int) $parca['sira'];
        }

        if (count($siralar) !== count(array_unique($siralar))) {
            $sorunlar[] = 'sıra numarası tekrarlanıyor';
        }
        // 1..N kesintisiz olmalı: 1,2,3,5 dizisi dört dosyayla da oluşabilir
        // ve dosya SAYMAK bunu yakalayamaz — dördüncü parça eksiktir.
        if ($siralar !== [] && $this->kesintisizDegil($siralar, $beklenen)) {
            $sorunlar[] = 'parça sıraları 1..' . $beklenen . ' aralığında kesintisiz değil';
        }

        return $sorunlar;
    }

    /** @param list<int> $siralar */
    private function kesintisizDegil(array $siralar, int $beklenen): bool
    {
        $benzersiz = array_values(array_unique($siralar));
        sort($benzersiz);

        return $benzersiz !== range(1, count($benzersiz)) || count($benzersiz) !== $beklenen;
    }

    public function tamMi(): bool
    {
        return $this->eksikler() === [];
    }

    /**
     * BİLEREK alınamayan bileşenler (H1): örn. `['config']`.
     *
     * Yazıcı bunu, bileşeni yakalayamadığı anda kaydeder. Listeden türetilmez:
     * "config parçası yok" ile "config parçası alınamadı" aynı şey değildir
     * ve ikisini ayıran tek tanık, yazıcının o an yazdığı bu alandır.
     *
     * @return list<string>
     */
    public function eksikBilesenler(): array
    {
        return array_map('strval', $this->veri['eksik'] ?? []);
    }

    /** Eksik bileşenin KISA sebebi — insan okur, karar verir. */
    public function sebep(): ?string
    {
        $sebep = $this->veri['sebep'] ?? null;

        return is_string($sebep) && $sebep !== '' ? $sebep : null;
    }

    /** TAM ya da KISMI — geçerlilikten bağımsız bir eksen (bkz. DURUM_KISMI). */
    public function durum(): string
    {
        return $this->eksikBilesenler() === [] ? self::DURUM_TAM : self::DURUM_KISMI;
    }

    /** Medya parçası olmayan set — panel "görselsiz" rozeti için. */
    public function medyasizMi(): bool
    {
        return !in_array('medya', array_column($this->parcalar(), 'tur'), true);
    }

    /** @return array{parca_sayisi: int, medya_parca_sayisi: int, toplam_bayt: int, tam: bool, durum: string, eksik: list<string>, sebep: string|null, medyasiz: bool} */
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
            'durum' => $this->durum(),
            'eksik' => $this->eksikBilesenler(),
            'sebep' => $this->sebep(),
            'medyasiz' => $this->medyasizMi(),
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

        /** @var array{set_id: string, olusturuldu: string, surum: string, sifreleme: string, parcalar: list<array{ad: string, tur: string, sira?: int, boyut: int, sha256: string}>, migration_defteri: list<string>, toplam_parca?: int, eksik?: list<string>, sebep?: string|null, zorunlu_turler?: list<string>} $veri */
        return new self($veri);
    }
}
