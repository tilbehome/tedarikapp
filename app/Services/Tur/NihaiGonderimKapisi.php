<?php

declare(strict_types=1);

namespace App\Services\Tur;

/**
 * NİHAİ GÖNDERİM KAPISI (V3-C Blok B · #15 §4).
 *
 * KORUNAN FELAKET: firma "gönder"e basar, tur `RESPONDED` olur ve Ürün Sahibi
 * teklifi değerlendirmeye alır — ama satırların yarısında fiyat yok, birinde
 * MOQ boş, kademeler çakışıyor. Eksiklik ancak sipariş aşamasında görülür ve o
 * noktada firmaya geri dönmek BİR TUR DAHA demektir: günler.
 *
 * KAPI SUNUCUDADIR (K37 / CLAUDE.md §4). Portal isteği panelden gelmez;
 * arayüzde gizlenmiş bir düğme, gönderilemeyen bir istek anlamına gelmez.
 *
 * EKSİKLER SATIR BAZINDA RAPORLANIR. Tek bir "geçersiz" cevabı firmayı boş
 * yere dolaştırır: hangi satırda neyin eksik olduğunu görmeden düzeltemez.
 * Bu, nezaket değil TUR SAYISINI AZALTAN bir tasarım kararıdır.
 *
 * PARA KARŞILAŞTIRMASI STRING ÜZERİNDEN (K14): fiyat float'a çevrilmez.
 * Yalnız "dolu mu ve pozitif mi" sorulur; aritmetik yapılmaz.
 */
final class NihaiGonderimKapisi
{
    /** Nihai gönderimin başlayabileceği durumlar (#15 koşul 1). */
    private const IZINLI_DURUMLAR = ['SENT', 'VIEWED', 'PRICING'];

    /** `found` satırında doldurulması ZORUNLU alanlar (#15 koşul 3). */
    private const BULUNAN_ZORUNLU = ['fiyat', 'para_birimi', 'moq', 'termin_baslangic', 'termin_gun', 'birim'];

    /**
     * @param array{
     *     durum: string,
     *     erisim_iptal: bool,
     *     rfq_satir_idler: list<string>,
     *     satirlar: list<array<string, mixed>>,
     *     gecerlilik_onayi: bool,
     *     ddp_kdv_onayi: bool,
     *     istemci_surumu: int,
     *     sunucu_surumu: int
     * } $istek
     * @return array{gecerli: bool, cakisma: bool, eksikler: list<array{satir: ?string, alan: string, sebep: string}>}
     */
    public function degerlendir(array $istek): array
    {
        $eksikler = [];
        $cakisma = false;

        // 1) Durum ve erişim.
        if (!in_array($istek['durum'], self::IZINLI_DURUMLAR, true)) {
            $eksikler[] = $this->eksik(null, 'durum', 'Bu tur artık yanıt kabul etmiyor.');
        }
        if ($istek['erisim_iptal']) {
            $eksikler[] = $this->eksik(null, 'erisim', 'Paylaşım erişimi iptal edilmiş.');
        }

        // 8) SÜRÜM ÇAKIŞMASI — ayrı işaretlenir çünkü çözümü farklıdır: firma
        // bir şey düzeltmez, ÇAKIŞMA EKRANI açılır ve güncel RFQ gösterilir.
        // Sessizce kabul etmek, firmanın FARKLI bir şeye fiyat vermesi demektir.
        if ($istek['istemci_surumu'] !== $istek['sunucu_surumu']) {
            $cakisma = true;
            $eksikler[] = $this->eksik(null, 'round_version', 'Teklif formu güncellendi; yenileyip tekrar gönderin.');
        }

        // 7) Onay kutuları.
        if (!$istek['gecerlilik_onayi']) {
            $eksikler[] = $this->eksik(null, 'gecerlilik_onayi', 'Fiyat geçerlilik süresi onaylanmalı.');
        }
        if (!$istek['ddp_kdv_onayi']) {
            $eksikler[] = $this->eksik(null, 'ddp_kdv_onayi', 'DDP Türkiye KDV dahil onayı işaretlenmeli.');
        }

        // 2) HER RFQ satırında nihai bir yanıt olmalı.
        //
        // "Yanıtlanmayan" ile "bulunamadı" AYRI şeylerdir (K67: bilinmeyen ≠
        // sıfır). Yanıtlanmayan satır, teklifi eksik bırakır ve o eksik ancak
        // sipariş anında görülür.
        $yanitlanan = [];
        foreach ($istek['satirlar'] as $satir) {
            $yanitlanan[(string) ($satir['rfq_satir_id'] ?? '')] = true;
        }
        foreach ($istek['rfq_satir_idler'] as $satirId) {
            if (!isset($yanitlanan[$satirId])) {
                $eksikler[] = $this->eksik($satirId, 'yanit_durumu', 'Bu satır yanıtlanmamış.');
            }
        }

        foreach ($istek['satirlar'] as $satir) {
            foreach ($this->satirEksikleri($satir) as $eksik) {
                $eksikler[] = $eksik;
            }
        }

        return [
            'gecerli' => $eksikler === [],
            'cakisma' => $cakisma,
            'eksikler' => $eksikler,
        ];
    }

    /**
     * @param  array<string, mixed> $satir
     * @return list<array{satir: ?string, alan: string, sebep: string}>
     */
    private function satirEksikleri(array $satir): array
    {
        $id = (string) ($satir['rfq_satir_id'] ?? '');
        $durum = (string) ($satir['yanit_durumu'] ?? '');

        return match ($durum) {
            'found' => $this->bulunanEksikleri($id, $satir),
            'not_found' => $this->dolu($satir['aciklama'] ?? null)
                ? []
                : [$this->eksik($id, 'aciklama', 'Bulunamadı satırında kısa açıklama zorunlu.')],
            'alternative_available' => $this->alternatifEksikleri($id, $satir),
            default => [$this->eksik($id, 'yanit_durumu', 'Geçerli bir yanıt durumu seçilmemiş.')],
        };
    }

    /**
     * @param  array<string, mixed> $satir
     * @return list<array{satir: ?string, alan: string, sebep: string}>
     */
    private function bulunanEksikleri(string $id, array $satir): array
    {
        $eksikler = [];

        foreach (self::BULUNAN_ZORUNLU as $alan) {
            if (!$this->dolu($satir[$alan] ?? null)) {
                $eksikler[] = $this->eksik($id, $alan, 'Zorunlu alan boş.');
            }
        }

        // SIFIR FİYAT "BEDAVA" DEĞİL, DOLDURULMAMIŞ demektir. Geçirmek,
        // toplamı sessizce yanlış hesaplatır.
        if (isset($satir['fiyat']) && !$this->pozitifSayi((string) $satir['fiyat'])) {
            $eksikler[] = $this->eksik($id, 'fiyat', 'Fiyat sıfırdan büyük olmalı.');
        }

        if (($satir['ddp_kdv_dahil'] ?? false) !== true) {
            $eksikler[] = $this->eksik($id, 'ddp_kdv_dahil', 'DDP Türkiye KDV dahil onayı gerekli.');
        }

        $kademeHatasi = $this->kademeHatasi($satir['kademeler'] ?? []);
        if ($kademeHatasi !== null) {
            $eksikler[] = $this->eksik($id, 'kademeler', $kademeHatasi);
        }

        return $eksikler;
    }

    /**
     * @param  array<string, mixed> $satir
     * @return list<array{satir: ?string, alan: string, sebep: string}>
     */
    private function alternatifEksikleri(string $id, array $satir): array
    {
        $eksikler = [];

        // Bağlantı ya da açıklama: ikisinden BİRİ yeterli — firma her zaman
        // bağlantı veremeyebilir ama neyi önerdiğini anlatabilmeli.
        if (!$this->dolu($satir['alternatif_baglanti'] ?? null) && !$this->dolu($satir['aciklama'] ?? null)) {
            $eksikler[] = $this->eksik($id, 'alternatif_baglanti', 'Alternatif için bağlantı ya da açıklama gerekli.');
        }

        // ALTERNATİF, ÖNERİLDİĞİ İÇİN DEĞİL FİYATLANDIĞI İÇİN İŞE YARAR.
        // Fiyatsız alternatif bir sonraki turu doğurur.
        foreach (['fiyat', 'moq', 'termin_gun'] as $alan) {
            if (!$this->dolu($satir[$alan] ?? null)) {
                $eksikler[] = $this->eksik($id, $alan, 'Alternatif için zorunlu alan boş.');
            }
        }

        return $eksikler;
    }

    /**
     * Kademeler sıralı, pozitif ve ÇAKIŞMASIZ mı? (#15 koşul 6)
     *
     * Çakışan kademe, aynı miktar için İKİ fiyat demektir ve hangisinin
     * geçerli olduğu bilinemez. Belirsizlik hesaba giremez.
     *
     * @param mixed $kademeler
     */
    private function kademeHatasi(mixed $kademeler): ?string
    {
        if (!is_array($kademeler) || $kademeler === []) {
            return null;
        }

        $oncekiAdet = 0;
        foreach ($kademeler as $kademe) {
            if (!is_array($kademe)) {
                return 'Kademe biçimi geçersiz.';
            }
            $adet = (int) ($kademe['adet'] ?? 0);
            if ($adet <= 0) {
                return 'Kademe adedi sıfırdan büyük olmalı.';
            }
            if ($adet <= $oncekiAdet) {
                return 'Kademeler artan sırada ve çakışmasız olmalı.';
            }
            if (!$this->pozitifSayi((string) ($kademe['fiyat'] ?? ''))) {
                return 'Kademe fiyatı sıfırdan büyük olmalı.';
            }
            $oncekiAdet = $adet;
        }

        return null;
    }

    /** Değer dolu mu? (0 sayısı DOLUDUR, boş dize değildir) */
    private function dolu(mixed $deger): bool
    {
        if ($deger === null) {
            return false;
        }
        if (is_string($deger)) {
            return trim($deger) !== '';
        }

        return true;
    }

    /**
     * String para/sayı pozitif mi? — FLOAT'A ÇEVİRMEDEN (K14).
     *
     * `bccomp` bcmath ile string karşılaştırır; float'a çevirmek 0.1+0.2
     * sınıfı hataların kapısını açardı ve para burada tam da o yüzden string
     * taşınıyor.
     */
    private function pozitifSayi(string $deger): bool
    {
        $deger = trim($deger);
        if ($deger === '' || preg_match('/^\d+(\.\d+)?$/', $deger) !== 1) {
            return false;
        }

        return bccomp($deger, '0', 4) === 1;
    }

    /** @return array{satir: ?string, alan: string, sebep: string} */
    private function eksik(?string $satir, string $alan, string $sebep): array
    {
        return ['satir' => $satir, 'alan' => $alan, 'sebep' => $sebep];
    }
}
