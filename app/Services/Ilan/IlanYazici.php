<?php

declare(strict_types=1);

namespace App\Services\Ilan;

use App\Core\Connection;
use DateTimeImmutable;
use PDO;

/**
 * İLAN KAYDI YAZICISI (İE#21 B3 — saha bulgusu, 23 Ağu 2026).
 *
 * BULGU: `listings` tablosunu (İE#20 C2) YALNIZCA tek seferlik göç betiği
 * (`bin/goc-ilan.php`) dolduruyordu. Yakalama yolu ürünü yazıyor ama ilanı
 * AÇMIYORDU. Sonuç: canlı kurulum sıfırlandıktan (K73) sonra gelen her yeni
 * ürünün ilan kaydı YOKTU — Keşif'te skor "—", ürün çekmecesinde "kaynak ve
 * satıcı" boş, fiyat kademeleri hiç görünmüyordu. Ürün≠ilan ayrımı (K67) şemada
 * duruyor ama veri akışında yaşamıyordu.
 *
 * BU SINIF O BOŞLUĞU KAPATIR: her yakalama, ürünün yanında bir ilan satırı ve
 * varsa fiyat kademelerini açar. Eşleme `bin/goc-ilan.php` ile BİREBİR aynıdır;
 * göçle gelen kayıtla yeni gelen kayıt aynı biçimde durur, yoksa Keşif iki farklı
 * veri kuşağını yan yana gösterirdi.
 *
 * ALAN YOKSA NULL (K67 disiplini): satış adedi, değerlendirme, satıcı karnesi
 * gibi sinyaller bugünün yakalama sözleşmesinde YOKTUR. Sıfır yazmak "sıfır
 * satmış" demek olurdu; NULL "bilinmiyor" der ve skor motoru bilinmeyeni
 * kendi kuralıyla (ağırlığı düşürerek) ele alır. Sinyaller eklenti v2 ile
 * gelince buraya eklenir; şema hazırdır.
 *
 * İDEMPOTANS: aynı ürün için ilan bir kez açılır. Yakalama tekrarı (ağ hatası
 * sonrası retry) ürünü çoğaltmadığı gibi ilanı da çoğaltmaz; var olan satır
 * GÜNCELLENİR, çünkü ikinci yakalama daha taze fiyat/satıcı bilgisi taşıyabilir.
 */
final class IlanYazici
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Ürünün ilan kaydını açar veya günceller; ilan kimliğini döner.
     *
     * Çağıran TRANSACTION İÇİNDE olmalıdır: ürün ile ilanı ayrı işlemlere bölmek,
     * yarıda kalan bir yakalamada ilansız ürün (ya da ürünsüz ilan) bırakırdı.
     *
     * @param list<array{min_adet?: mixed, min_qty?: mixed, birim_fiyat?: mixed, price_yuan?: mixed}>|null $kademeler
     *        yakalamanın `normalized.price_tiers` bloğu. Bu blok ürüne YAZILMAZ
     *        (K32: ürüne yalnız RAW gider), dolayısıyla veritabanından okunamaz —
     *        kademeler kaybolmasın diye çağıran doğrudan geçirir.
     */
    public function yaz(int $urunId, DateTimeImmutable $now, ?array $kademeler = null): ?int
    {
        $pdo = $this->connection->pdo();
        $urun = $this->urun($pdo, $urunId);
        if ($urun === null) {
            return null;
        }

        $zaman = $now->format('Y-m-d H:i:s');
        $ham = is_string($urun['raw_attributes'] ?? null) ? (string) $urun['raw_attributes'] : null;

        // Elle girilmiş ürünün de ilanı açılır; platformu "manuel"dir. İlan
        // açmamak, "bu ürünün kaynağı yok" bilgisini kaybettirirdi (göç betiğiyle
        // aynı karar).
        $platformKod = trim((string) ($urun['platform'] ?? ''));
        if ($platformKod === '') {
            $platformKod = 'manuel';
        }

        $alanlar = [
            'platform_id' => $this->platformId($pdo, $platformKod),
            'platform_kod' => $platformKod,
            'external_id' => $this->kirp($urun['external_id'] ?? null, 100),
            'url' => $this->kirp($urun['url'] ?? null, 1000),
            'baslik_orijinal' => $this->kirp($urun['name_original'] ?? null, 500),
            'satici_ad' => $this->kirp($urun['vendor_name'] ?? null, 200),
            'satici_url' => $this->kirp($urun['vendor_url'] ?? null, 1000),
            'birim_fiyat' => (string) $urun['price_yuan'],
            'para_birimi' => 'CNY',
            'ham_veri' => $ham,
            'yakalandi_at' => (string) $urun['created_at'],
        ];

        $mevcut = $this->mevcutIlan($pdo, $urunId);
        if ($mevcut === null) {
            $ekle = $pdo->prepare(
                'INSERT INTO listings (product_id, platform_id, platform_kod, external_id, url, baslik_orijinal,
                     satici_ad, satici_url, birim_fiyat, para_birimi, ham_veri, yakalandi_at, created_at, updated_at)
                 VALUES (:product_id, :platform_id, :platform_kod, :external_id, :url, :baslik_orijinal,
                     :satici_ad, :satici_url, :birim_fiyat, :para_birimi, :ham_veri, :yakalandi_at, :created_at, :updated_at)',
            );
            $ekle->execute($alanlar + [
                'product_id' => $urunId,
                'created_at' => $zaman,
                'updated_at' => $zaman,
            ]);
            $ilanId = (int) $pdo->lastInsertId();
        } else {
            $ilanId = $mevcut;
            $guncelle = $pdo->prepare(
                'UPDATE listings SET platform_id = :platform_id, platform_kod = :platform_kod,
                     external_id = :external_id, url = :url, baslik_orijinal = :baslik_orijinal,
                     satici_ad = :satici_ad, satici_url = :satici_url, birim_fiyat = :birim_fiyat,
                     para_birimi = :para_birimi, ham_veri = :ham_veri, yakalandi_at = :yakalandi_at,
                     updated_at = :updated_at
                 WHERE id = :id',
            );
            $guncelle->execute($alanlar + ['id' => $ilanId, 'updated_at' => $zaman]);
        }

        $this->kademeleriYaz($pdo, $ilanId, $ham, $kademeler);

        return $ilanId;
    }

    /**
     * Fiyat kademeleri: ham veriden yeniden üretilir ve TAMAMEN değiştirilir.
     *
     * Kademe eklemek yerine "sil-yaz" yapılır: ikinci yakalamada satıcı kademeyi
     * kaldırmış olabilir ve eski kademe orada kalırsa kullanıcı artık geçerli
     * olmayan bir fiyata bakar.
     */
    /** @param list<array<string, mixed>>|null $yakalamadan */
    private function kademeleriYaz(PDO $pdo, int $ilanId, ?string $ham, ?array $yakalamadan): void
    {
        // Önce ham veri (platformun kendi bloğu), yoksa yakalamanın normalize
        // ettiği kademeler. Ham veri üstündür: normalize katmanı bir gün değişse
        // bile ham blok platformun söylediğidir.
        $kademeler = FiyatKademeAyristirici::ayristir($ham);
        if ($kademeler === [] && $yakalamadan !== null) {
            $kademeler = $this->yakalamaKademeleri($yakalamadan);
        }

        $sil = $pdo->prepare('DELETE FROM listing_price_tiers WHERE listing_id = :id');
        $sil->execute(['id' => $ilanId]);

        if ($kademeler === []) {
            return;
        }

        $ekle = $pdo->prepare(
            'INSERT INTO listing_price_tiers (listing_id, min_adet, birim_fiyat, para_birimi) VALUES (?, ?, ?, ?)',
        );
        foreach ($kademeler as $kademe) {
            $ekle->execute([$ilanId, $kademe['min_adet'], $kademe['birim_fiyat'], 'CNY']);
        }
    }

    /**
     * Yakalama gövdesindeki kademeleri ayrıştırıcının biçimine çevirir.
     *
     * Sözleşme (docs/04): `[{min_qty, price_yuan}]`. Eksik/bozuk satır ATILIR —
     * yanlış bir kademe, yanlış fiyata sipariş demektir.
     *
     * @param list<array<string, mixed>> $ham
     *
     * @return list<array{min_adet: int, birim_fiyat: string}>
     */
    private function yakalamaKademeleri(array $ham): array
    {
        $kademeler = [];
        foreach ($ham as $satir) {
            // Çağıran yalnız dizi satırları geçirir (CaptureApplier süzer).
            $min = $satir['min_qty'] ?? $satir['min_adet'] ?? null;
            $fiyat = $satir['price_yuan'] ?? $satir['birim_fiyat'] ?? null;
            if (!is_numeric($min) || (int) $min < 1 || !is_numeric($fiyat)) {
                continue;
            }
            $kademeler[] = ['min_adet' => (int) $min, 'birim_fiyat' => (string) $fiyat];
        }

        usort($kademeler, static fn (array $a, array $b): int => $a['min_adet'] <=> $b['min_adet']);

        return $kademeler;
    }

    /** @return array<string, mixed>|null */
    private function urun(PDO $pdo, int $urunId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT id, platform, external_id, url, name_original, vendor_name, vendor_url,
                    price_yuan, raw_attributes, created_at
             FROM products WHERE id = :id',
        );
        $statement->execute(['id' => $urunId]);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    private function mevcutIlan(PDO $pdo, int $urunId): ?int
    {
        $statement = $pdo->prepare('SELECT id FROM listings WHERE product_id = :id ORDER BY id LIMIT 1');
        $statement->execute(['id' => $urunId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function platformId(PDO $pdo, string $kod): ?int
    {
        $statement = $pdo->prepare('SELECT id FROM platforms WHERE kod = :kod LIMIT 1');
        $statement->execute(['kod' => $kod]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function kirp(mixed $deger, int $uzunluk): ?string
    {
        if (!is_string($deger) || trim($deger) === '') {
            return null;
        }

        return mb_substr(trim($deger), 0, $uzunluk);
    }
}
