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
        'satis' => 35,
        'degerlendirme' => 25,
        'satici' => 20,
        'tazelik' => 10,
        'veri_tamligi' => 10,
    ];

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
            'satis' => $this->sayi($ilan['satis_adedi'] ?? null),
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
            $puan = in_array($ad, ['tazelik', 'veri_tamligi'], true)
                ? max(0.0, min(1.0, $ham))
                : $this->yuzdelikDilim($platform, $this->kolonAdi($ad), $ham, (int) $ilan['id']);

            $bilesenler[$ad] = ['ham' => $ham, 'puan' => $puan, 'agirlik' => $agirlik];
            $toplamPuan += $puan * $agirlik;
            $toplamAgirlik += $agirlik;
            $mevcut++;
        }

        if ($mevcut < self::ASGARI_BILESEN || $toplamAgirlik === 0) {
            return [
                'skor' => null,
                'bilesenler' => $bilesenler,
                'neden' => 'Yeterli sinyal yok (' . $mevcut . '/' . count($hamDegerler) . ' bileşen) — skor GİZLİ.',
            ];
        }

        return [
            'skor' => (int) round(($toplamPuan / $toplamAgirlik) * 100),
            'bilesenler' => $bilesenler,
            'neden' => null,
        ];
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
    private function degerlendirmeHam(array $ilan): ?float
    {
        $adet = $this->sayi($ilan['degerlendirme_adedi'] ?? null);
        $puan = $this->sayi($ilan['degerlendirme_puani'] ?? null);
        if ($adet === null && $puan === null) {
            return null;
        }

        // Adet tek başına yeterli sinyaldir; puan varsa adedi ağırlıklandırır.
        // 5 üzerinden 4.5 → 0.9 çarpanı: az ama iyi değerlendirilmiş ilan cezalanmaz.
        $carpan = $puan === null ? 1.0 : max(0.2, min(1.0, $puan / 5));

        return ($adet ?? 0.0) * $carpan;
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

        // Karne: yıl (kıdem) temel; puan ve yanıt oranı çarpan olarak biner.
        $temel = $yil ?? 1.0;
        if ($puan !== null) {
            $temel *= max(0.2, min(1.0, $puan / 5));
        }
        if ($yanit !== null) {
            $temel *= max(0.2, min(1.0, $yanit / 100));
        }

        return $temel;
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
