<?php

declare(strict_types=1);

namespace App\Services\Ilan;

use App\Core\Connection;
use App\Core\Dates;
use App\Models\SettingsRepository;
use DateTimeImmutable;
use Throwable;

/**
 * TEDARİKAPP SKORU v1 (İE#20 C6) — 0–100.
 *
 * NE DEĞİLDİR: bu bir "ürün kalitesi" ölçüsü değildir. Ürünü elimize almadık.
 * Ölçtüğü şey İLANIN GÜVEN SİNYALLERİDİR: çok satan, çok değerlendirilen, köklü
 * ve hızlı yanıt veren bir satıcının ilanı, aynı ürünü yeni açılmış bir mağazadan
 * almaya göre daha az risklidir. Skor bir KARAR DEĞİL, bir SIRALAMA yardımıdır.
 *
 * BEŞ BİLEŞEN (ağırlıklar Ayarlar'dan değiştirilebilir):
 *   satış 35 · değerlendirme 25 · satıcı karnesi 20 · tazelik 10 · veri tamlığı 10
 *
 * PLATFORM İÇİ NORMALİZE — bu maddenin gerekçesi kritiktir: ham sayı kıyaslamak
 * YANILTIR. 1688'de 5.000 satış sıradan, Alibaba'da olağanüstü olabilir; iki
 * platformun ham rakamını yan yana koymak, farklı ölçeklerdeki iki cetveli
 * karşılaştırmaktır. Bu yüzden her bileşen, ÜRÜNÜN KENDİ PLATFORMUNDAKİ dağılıma
 * göre yüzdelik dilime çevrilir.
 *
 * VERİ YOKSA SKOR GİZLİDİR. Eksik sinyali "0" saymak, veri olmayan ilanı KÖTÜ
 * göstermektir — oysa bilmiyoruz. Bileşenlerin en az yarısı yoksa skor NULL
 * yazılır ve arayüzde "—" görünür (aynı disiplin: menşe, DDP'siz kâr).
 */
final class SkorHesaplayici
{
    public const KEY_AGIRLIKLAR = 'skor_agirliklari';

    /** @var array<string, int> */
    public const VARSAYILAN_AGIRLIKLAR = [
        'satis' => 30,
        // İVME (İE#21 C3): 30 günlük satışın toplama oranı. Kalibrasyon setinin
        // en sık gerekçelerinden biri buydu ve motorda KARŞILIĞI YOKTU: yeni bir
        // ilanın 1.468 toplam satışının 1.163'ünü son 30 günde yapması, 55.000
        // toplam satışın 4.000'ini yapan olgun bir ilandan farklı bir hikâyedir.
        // Mutlak hacim olgunu, ivme yükseleni bulur; ikisi ayrı sorulardır.
        'ivme' => 10,
        'degerlendirme' => 25,
        'satici' => 15,
        'tazelik' => 10,
        'veri_tamligi' => 10,
    ];

    /**
     * BANT EŞİKLERİ — kalibrasyon setiyle ÖLÇÜLEREK belirlendi (İE#21 C3).
     *
     * Bunlar masa başında seçilmiş yuvarlak sayılar değildir. Skor, platform içi
     * yüzdelik dilimlerin ağırlıklı ortalamasıdır; dolayısıyla uçlara (0 ve 100)
     * nadiren ulaşır ve dağılım ortada toplanır. "70 üstü yüksektir" gibi sezgisel
     * bir eşik bu dağılımda neredeyse hiçbir ürünü yüksek saymazdı.
     *
     * Eşikler `docs/v3/hazirlik/skor-kalibrasyon-seti.json`daki 38 insan kararına
     * karşı ARANARAK bulundu (test içindeki eşik taraması); 41/14 ayrımı %82
     * isabet verir — sözleşmenin eşiği %80.
     *
     * Bantlar arasında BİLİNÇLİ bir örtüşme vardır: aynı skor aralığında hem
     * "yüksek" hem "orta" beklenen ürünler bulunur. Bu bir hata değil, insan
     * kararının doğasıdır — kalibrasyon sözleşmesi de komşu bant kaymasını
     * otomatik red saymaz, insan incelemesine gönderir.
     */
    public const BANT_YUKSEK_ESIK = 41;
    public const BANT_ORTA_ESIK = 14;

    /** 8B ağacındaki kapsam dışı kökün görünen adı (içe aktarım bu adla yazar). */
    public const KAPSAM_DISI_KOK = 'Diğer / Alan Dışı';

    /** Skorun anlamlı sayılması için gereken en az bileşen sayısı. */
    private const ASGARI_BILESEN = 3;

    public function __construct(
        private readonly Connection $connection,
        private readonly SettingsRepository $settings,
    ) {
    }

    /** @return array<string, int> */
    public function agirliklar(): array
    {
        $ham = $this->settings->get(self::KEY_AGIRLIKLAR);
        if (!is_string($ham) || $ham === '') {
            return self::VARSAYILAN_AGIRLIKLAR;
        }

        $cozulmus = json_decode($ham, true);
        if (!is_array($cozulmus)) {
            return self::VARSAYILAN_AGIRLIKLAR;
        }

        $agirliklar = [];
        foreach (self::VARSAYILAN_AGIRLIKLAR as $ad => $varsayilan) {
            $deger = $cozulmus[$ad] ?? $varsayilan;
            $agirliklar[$ad] = is_numeric($deger) ? max(0, min(100, (int) $deger)) : $varsayilan;
        }

        return $agirliklar;
    }

    /**
     * Ürünün skorunu hesaplar. Yetersiz veri → null.
     *
     * @return array{skor: int|null, bilesenler: array<string, array{ham: float|null, puan: float|null, agirlik: int}>, neden: string|null}
     */
    public function hesapla(int $urunId, DateTimeImmutable $now): array
    {
        $ilan = $this->ilan($urunId);
        if ($ilan === null) {
            return ['skor' => null, 'bilesenler' => [], 'neden' => 'Bu ürünün ilan kaydı yok.'];
        }

        $platform = (string) ($ilan['platform_kod'] ?? 'manuel');
        $agirliklar = $this->agirliklar();

        $hamDegerler = [
            // SATIŞ, MEMNUNİYETLE ÇARPILIR (İE#21 C3 kalibrasyon bulgusu).
            // Ham satış tek başına yanıltıcıdır: 5.000 adet satan 3,6 puanlı ürün,
            // 3.200 adet satan 4,94 puanlı üründen İYİ DEĞİLDİR — çok satıp
            // beğenilmemek iade ve şikâyet demektir. Kalibrasyon setindeki dört
            // sıralama kısıtının dördü de tam bu ayrımı istiyordu.
            'satis' => $this->satisHam($ilan),
            'ivme' => $this->ivmeHam($ilan),
            'degerlendirme' => $this->degerlendirmeHam($ilan),
            'satici' => $this->saticiHam($ilan),
            'tazelik' => $this->tazelikHam($ilan, $now),
            'veri_tamligi' => $this->veriTamligi($ilan),
        ];

        $bilesenler = [];
        $toplamPuan = 0.0;
        $toplamAgirlik = 0;
        $mevcut = 0;

        foreach ($hamDegerler as $ad => $ham) {
            $agirlik = $agirliklar[$ad] ?? 0;
            if ($ham === null) {
                $bilesenler[$ad] = ['ham' => null, 'puan' => null, 'agirlik' => $agirlik];

                continue;
            }

            // Tazelik ve veri tamlığı zaten 0–1 aralığındadır; platform dağılımına
            // göre normalize etmek anlamsız olurdu (herkes için aynı ölçek).
            // İVME YÜZDELİK DİLİME GİRER (ölçümle karara bağlandı): ham oran
            // olarak bırakıldığında olgun ilanların hepsi ~0,07 alıp dibe
            // çöküyordu ve kalibrasyon isabeti %53'ten %29'a düştü. Oran
            // MUTLAK olarak değil, platformdaki diğer ilanlara GÖRE anlamlıdır.
            $puan = in_array($ad, ['tazelik', 'veri_tamligi'], true)
                ? max(0.0, min(1.0, $ham))
                : $this->yuzdelikDilim($platform, $this->kolonAdi($ad), $ham, (int) $ilan['id']);

            $bilesenler[$ad] = ['ham' => $ham, 'puan' => $puan, 'agirlik' => $agirlik];
            $toplamPuan += $puan * $agirlik;
            $toplamAgirlik += $agirlik;
            $mevcut++;
        }

        // ── GİZLİ KURALI (İE#21 C3 kalibrasyon sınavının bulgusu) ────────────
        //
        // Eski kural yalnız SAYIYA bakıyordu: "üç bileşen varsa skor üret". Ama
        // bileşenlerin ikisi (tazelik, veri tamlığı) KAYDIMIZI ölçer, ilanın
        // ticari başarısını değil. Metrikleri hiç olmayan bir ilan, yalnız
        // "kaydı yeni ve dolu" olduğu için puan alıyordu — kalibrasyon seti bunu
        // DM-029 ve DM-057'de yakaladı (beklenen: gizli, motor: 49 ve 52).
        //
        // Yeni kural iki ŞART arar:
        //   • TİCARİ SİNYAL: satış ya da değerlendirme (en az biri),
        //   • SATICI KARNESİ: kıdem/puan/yanıt (en az biri).
        // Biri bile yoksa skor GÖSTERİLMEZ. "Veri yetersiz" demek, uydurma bir
        // sayı vermekten dürüsttür; kullanıcı o ürüne bakıp kendi kararını verir.
        $ticariSinyal = $hamDegerler['satis'] !== null || $hamDegerler['degerlendirme'] !== null;
        $saticiKarnesi = $hamDegerler['satici'] !== null;

        if (!$ticariSinyal || !$saticiKarnesi || $mevcut < self::ASGARI_BILESEN || $toplamAgirlik === 0) {
            $eksik = [];
            if (!$ticariSinyal) {
                $eksik[] = 'satış/değerlendirme metrikleri';
            }
            if (!$saticiKarnesi) {
                $eksik[] = 'satıcı karnesi';
            }
            if ($eksik === []) {
                $eksik[] = 'yeterli bileşen (' . $mevcut . '/' . count($hamDegerler) . ')';
            }

            return [
                'skor' => null,
                'bilesenler' => $bilesenler,
                'neden' => 'Skor için veri yetersiz — eksik: ' . implode(', ', $eksik) . '.',
            ];
        }

        $skor = (int) round(($toplamPuan / $toplamAgirlik) * 100);
        $kapsamDisi = $this->kapsamDisiMi($urunId);

        return [
            'skor' => $skor,
            'bilesenler' => $bilesenler,
            'neden' => null,
            'kapsam_disi' => $kapsamDisi,
            'bant' => self::bant($skor, $kapsamDisi),
        ];
    }

    /**
     * Skoru banda çevirir: yuksek · orta · dusuk · gizli.
     *
     * Panel rozeti, Keşif sıralaması ve kalibrasyon sınavı AYNI bu işlevi kullanır —
     * üç yerde üç ayrı eşik listesi tutmak, üçünün ayrı düşmesi demektir.
     */
    public static function bant(?int $skor, bool $kapsamDisi = false): string
    {
        if ($skor === null) {
            return 'gizli';
        }

        $bant = match (true) {
            $skor >= self::BANT_YUKSEK_ESIK => 'yuksek',
            $skor >= self::BANT_ORTA_ESIK => 'orta',
            default => 'dusuk',
        };

        // KAPSAM KAPAĞI (İE#21 C3 kalibrasyon bulgusu): iş kapsamı DIŞINDAKİ ürün
        // ticari metrikleri ne kadar güçlü olursa olsun ÜST BANDA çıkamaz.
        // Kalibrasyon setinin sözleriyle: "Satış ve puan güçlü görünse de ürün
        // ev/yaşam ana kapsamı dışındadır; insan incelemesi gerektiren orta bant
        // uygundur." Skor DÜŞÜRÜLMEZ — sayı ticari gerçeği söylemeye devam eder;
        // bant, o ürünle gerçekten ilgilenip ilgilenmediğimizi söyler. İkisi ayrı
        // sorudur ve karıştırılırsa ne skora ne banda güvenilir.
        if ($kapsamDisi && $bant === 'yuksek') {
            return 'orta';
        }

        return $bant;
    }

    /** Hesaplar ve `listings.skor` alanına yazar (kuyruktan çağrılır). */
    public function hesaplaVeYaz(int $urunId, DateTimeImmutable $now): ?int
    {
        $sonuc = $this->hesapla($urunId, $now);

        $statement = $this->connection->pdo()->prepare(
            'UPDATE listings SET skor = :skor, skor_bilesenleri = :bilesenler, skor_at = :simdi
             WHERE product_id = :urun_id',
        );
        $statement->execute([
            'skor' => $sonuc['skor'],
            'bilesenler' => json_encode($sonuc['bilesenler'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'simdi' => Dates::toStorage($now),
            'urun_id' => $urunId,
        ]);

        return $sonuc['skor'];
    }

    /** @return array<string, mixed>|null */
    /**
     * Ürün iş kapsamının DIŞINDA mı? (8B kategori ağacının "Diğer / Alan Dışı" kökü)
     *
     * Kategori HİÇ atanmamışsa kapsam dışı SAYILMAZ: yeni yakalanan her ürün
     * kategorisizdir ve hepsini baştan cezalandırmak, kategori atamayı bir
     * ceza sistemine çevirirdi.
     */
    private function kapsamDisiMi(int $urunId): bool
    {
        try {
            $statement = $this->connection->pdo()->prepare(
                'SELECT c.name FROM products p
                 JOIN categories c ON c.id = p.category_id
                 WHERE p.id = :id',
            );
            $statement->execute(['id' => $urunId]);
            $ad = $statement->fetchColumn();
        } catch (Throwable) {
            return false;
        }

        return is_string($ad) && str_starts_with($ad, self::KAPSAM_DISI_KOK);
    }

    /** @return array<string, mixed>|null */
    private function ilan(int $urunId): ?array
    {
        $statement = $this->connection->pdo()->prepare('SELECT * FROM listings WHERE product_id = :id LIMIT 1');
        $statement->execute(['id' => $urunId]);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    /**
     * PLATFORM İÇİ YÜZDELİK DİLİM: bu değer, aynı platformdaki ilanların yüzde
     * kaçından daha iyi? Dönüş 0–1.
     *
     * İLANIN KENDİSİ KIYASA GİRMEZ: girseydi bir platformdaki en iyi ilan bile
     * 1.0 alamazdı (kendinden iyi olamaz) ve tek ilanlı platformda sonuç 0 olurdu.
     * Kıyas her zaman "başkalarına göre"dir.
     *
     * Kıyaslanacak başka kayıt yoksa 0.5 (orta) döner — "veri yok" demek yerine
     * nötr durmak, tek ürünlü bir platformu haksız yere uçlara atmamak içindir.
     */
    private function yuzdelikDilim(string $platform, string $kolon, float $deger, int $ilanId): float
    {
        try {
            $statement = $this->connection->pdo()->prepare(
                "SELECT COUNT(*) AS toplam,
                        SUM(CASE WHEN {$kolon} < :deger THEN 1 ELSE 0 END) AS altinda
                 FROM listings
                 WHERE platform_kod = :platform AND {$kolon} IS NOT NULL AND id <> :ilan_id",
            );
            $statement->execute(['deger' => $deger, 'platform' => $platform, 'ilan_id' => $ilanId]);
            $satir = $statement->fetch();
        } catch (Throwable) {
            return 0.5;
        }

        $toplam = is_array($satir) ? (int) $satir['toplam'] : 0;
        if ($toplam < 1) {
            return 0.5;
        }

        return max(0.0, min(1.0, ((int) $satir['altinda']) / $toplam));
    }

    private function kolonAdi(string $bilesen): string
    {
        return match ($bilesen) {
            'satis' => 'satis_adedi',
            'degerlendirme' => 'degerlendirme_adedi',
            default => 'satici_yil',
        };
    }

    /** @param array<string, mixed> $ilan */
    /**
     * SATIŞ SİNYALİ — memnuniyetle ölçeklenmiş hacim.
     *
     * @param array<string, mixed> $ilan
     */
    private function satisHam(array $ilan): ?float
    {
        $adet = $this->sayi($ilan['satis_adedi'] ?? null);
        if ($adet === null) {
            return null;
        }

        return $adet * $this->puanCarpani($this->sayi($ilan['degerlendirme_puani'] ?? null));
    }

    /**
     * İVME: son 30 günün toplam satışa oranı (0–1).
     *
     * Toplam satış bilinmiyorsa ivme de bilinmez — 30 günlük satışı tek başına
     * "ivme" saymak, olgun bir ilanı yükselen sanmaktır.
     *
     * @param array<string, mixed> $ilan
     */
    private function ivmeHam(array $ilan): ?float
    {
        $son30 = $this->sayi($ilan['satis_adedi'] ?? null);
        $toplam = $this->sayi($ilan['satis_toplam'] ?? null);
        if ($son30 === null || $toplam === null || $toplam <= 0.0) {
            return null;
        }

        return max(0.0, min(1.0, $son30 / $toplam));
    }

    /**
     * PUAN → KATSAYI eğrisi (0,05–1,00), tek merkez.
     *
     * 3,0 taban alınır: 5'li ölçekte 3 "vasat"tır ve altı olumsuzdur. Eski eğri
     * `puan / 5` idi ve 3,63 puanlı ürüne 0,73 veriyordu — kötüyle iyi arasında
     * yalnız dörtte bir fark. Yeni eğride 3,63 → 0,32 · 4,80 → 0,90 · 4,94 → 0,97.
     *
     * Puan BİLİNMİYORSA 1,0 döner: bilinmeyeni cezalandırmak, veri eksikliğini
     * kalitesizlik sanmaktır (o eksiklik zaten `veri_tamligi` bileşeninde ölçülür).
     */
    private function puanCarpani(?float $puan): float
    {
        if ($puan === null) {
            return 1.0;
        }

        return max(0.05, min(1.0, ($puan - 3.0) / 2.0));
    }

    /** @param array<string, mixed> $ilan */
    private function degerlendirmeHam(array $ilan): ?float
    {
        $adet = $this->sayi($ilan['degerlendirme_adedi'] ?? null);
        $puan = $this->sayi($ilan['degerlendirme_puani'] ?? null);
        if ($adet === null && $puan === null) {
            return null;
        }

        // PUAN EĞRİSİ (İE#21 C3 kalibrasyon bulgusu).
        //
        // Eski çarpan `puan / 5` idi ve 3.63 puanlı bir ürüne 0.73 veriyordu —
        // yani "kötü" ile "iyi" arasında yalnız dörtte bir fark. Sonuç, sınavın
        // yakaladığı ters sıralamaydı: 8.865 yorumu ve 3,63 puanı olan ilan,
        // 4.543 yorumu ve 4,94 puanı olan ilanı GEÇİYORDU (DM-085 > DM-083).
        // Oysa ticari akıl tersini söyler: çok satan ama beğenilmeyen ürün,
        // iade ve şikâyet demektir.
        //
        // Eğri `puanCarpani()`dedir — satış sinyaliyle AYNI merkez.
        //
        // KAREKÖK DENENDİ VE GERİ ALINDI (İE#21 C3): yorum hacmine azalan verim
        // uygulamak kulağa doğru geliyordu ama kalibrasyon isabetini %53'ten
        // %34'e düşürdü. Sebebi şu: bileşen zaten platform içi YÜZDELİK DİLİME
        // çevriliyor; karekök sıralamayı değiştirmeden aralıkları eziyor ve
        // yakın değerleri birbirine yapıştırıyordu. Ölçüm, sezgiyi yendi.
        return ($adet ?? 0.0) * $this->puanCarpani($puan);
    }

    /** @param array<string, mixed> $ilan */
    private function saticiHam(array $ilan): ?float
    {
        $yil = $this->sayi($ilan['satici_yil'] ?? null);
        $puan = $this->sayi($ilan['satici_puan'] ?? null);
        $yanit = $this->sayi($ilan['yanit_orani'] ?? null);

        if ($yil === null && $puan === null && $yanit === null) {
            return null;
        }

        // KARNE 0–1 ARALIĞINDADIR (İE#21 C3 kalibrasyon bulgusu).
        //
        // Eskiden kıdem (yıl) SINIRSIZ bir temeldi ve puanla çarpılıyordu: 16 yıllık
        // satıcı, 2 yıllığın sekiz katı puan alıyordu. Sonuç sınavda görüldü —
        // köklü ama vasat puanlı satıcının düşük puanlı ürünü, genç ve kaliteli
        // satıcının yüksek puanlı ürününü geçiyordu (DM-050 > DM-053).
        //
        // Kıdem bir güven sinyalidir ama DOYAR: 10 yıl ile 16 yıl arasındaki fark
        // ticari olarak anlamsızdır. Üç sinyal artık ağırlıklı ortalamadır ve
        // eksik olan hesaba KATILMAZ (yokluk sıfır sayılmaz).
        $parcalar = [];
        if ($puan !== null) {
            $parcalar[] = [max(0.0, min(1.0, $puan / 5)), 50];
        }
        if ($yil !== null) {
            $parcalar[] = [max(0.0, min(1.0, $yil / 10)), 30];
        }
        if ($yanit !== null) {
            $parcalar[] = [max(0.0, min(1.0, $yanit / 100)), 20];
        }

        $toplam = 0.0;
        $agirlik = 0;
        foreach ($parcalar as [$deger, $pay]) {
            $toplam += $deger * $pay;
            $agirlik += $pay;
        }

        return $agirlik > 0 ? round($toplam / $agirlik, 4) : null;
    }

    /**
     * TAZELİK: ilan ne kadar yakın zamanda yakalandı? 0–1.
     *
     * 0–30 gün: 1.0 · 180 gün: ~0. Eski bir yakalama, fiyatın ve stok durumunun
     * değişmiş olabileceği anlamına gelir — bu bir güvenilirlik sinyalidir.
     *
     * @param array<string, mixed> $ilan
     */
    private function tazelikHam(array $ilan, DateTimeImmutable $now): ?float
    {
        $ham = $ilan['yakalandi_at'] ?? null;
        if (!is_string($ham) || $ham === '') {
            return null;
        }

        try {
            $tarih = Dates::fromStorage($ham, $now->getTimezone());
        } catch (Throwable) {
            return null;
        }

        $gun = max(0, (int) floor(($now->getTimestamp() - $tarih->getTimestamp()) / 86400));
        if ($gun <= 30) {
            return 1.0;
        }
        if ($gun >= 180) {
            return 0.0;
        }

        return round(1.0 - (($gun - 30) / 150), 4);
    }

    /**
     * VERİ TAMLIĞI: ilan bize ne kadar bilgi verdi? 0–1.
     *
     * Bu bileşen ilanı değil KAYDIMIZI ölçer; eksik veri, kararı zorlaştırır.
     *
     * @param array<string, mixed> $ilan
     */
    private function veriTamligi(array $ilan): float
    {
        $alanlar = ['external_id', 'url', 'baslik_orijinal', 'satici_ad', 'birim_fiyat', 'ham_veri'];
        $dolu = 0;
        foreach ($alanlar as $alan) {
            $deger = $ilan[$alan] ?? null;
            if ($deger !== null && trim((string) $deger) !== '') {
                $dolu++;
            }
        }

        return round($dolu / count($alanlar), 4);
    }

    private function sayi(mixed $deger): ?float
    {
        return is_numeric($deger) ? (float) $deger : null;
    }
}
