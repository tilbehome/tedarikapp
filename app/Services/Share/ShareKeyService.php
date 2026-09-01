<?php

declare(strict_types=1);

namespace App\Services\Share;

use App\Models\ListRepository;
use DateTimeImmutable;

/**
 * ERİŞİM ANAHTARI (İE#18 Görev 6 · K62).
 *
 * Ürün Sahibi kararı: paylaşım sayfası artık "linki bilen görür" DEĞİLDİR.
 * Firma 6 haneli bir anahtar girer; doğrulanana kadar yanıtta LİSTE VERİSİ YOKTUR.
 *
 * ANAHTAR BİÇİMİ: 6 hane, KARIŞAN KARAKTERLER YOK — `0/O` ve `1/I` alfabeden
 * çıkarıldı. Anahtar telefonda okunup elle yazılacak; "sıfır mı O mu?" sorusu
 * kullanıcıyı yorar ve yanlış denemeye yol açar.
 *
 * DOĞRULAMA: karşılaştırma HASH üzerinden yapılır (HMAC-SHA256, APP_KEY) ve
 * `hash_equals` ile — zamanlama sızıntısı olmasın. Anahtar KÜÇÜK/BÜYÜK HARF
 * duyarsızdır: kullanıcı küçük yazarsa reddetmek gereksiz sürtünmedir.
 *
 * ÇEREZ: doğrulama sonrası imzalı bir çerez yazılır (kapsam: O TOKEN, ömür 12
 * saat). Çerez K58 imza modelinin YERİNE GEÇMEZ, üstüne EKLENİR: indirme
 * bağlantısı hâlâ kendi imzasını taşır, çerez yalnız "bu kişi anahtarı bildi"
 * bilgisini taşır.
 */
final class ShareKeyService
{
    /** Karışması kolay karakterler (0/O, 1/I) alfabede YOK. */
    private const ALFABE = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const UZUNLUK = 6;

    /** Çerez ömrü: bir iş günü boyunca tekrar tekrar anahtar sorulmasın. */
    public const CEREZ_OMRU_SANIYE = 43200; // 12 saat
    public const CEREZ_ADI = 'tdk_liste_anahtar';

    public function __construct(
        private readonly ListRepository $lists,
        private readonly string $appKey,
        /**
         * v1.2.1 D8 (TDR-034) — anahtarın DİNLENMEDE şifrelenmesi.
         *
         * `share_key_plain` düz saklanıyordu ve bu bilinçli bir istisnaydı
         * (K62): anahtar 6 hanedir ve panelin onu KULLANICIYA GÖSTERMESİ
         * gerekir. Ama "gösterilebilir olması" ile "düz saklanması" aynı şey
         * değildir: veritabanı yedeği sızarsa (off-site yedek, çalınan dump)
         * bütün paylaşım anahtarları okunur hâlde çıkar. Geri döndürülebilir
         * şifreleme gösterilebilirliği korur, yedeği tek başına yetersiz kılar.
         *
         * OPSİYONEL: verilmezse davranış eskisi gibidir (düz). Bakım betikleri
         * ve eski çağrılar kırılmaz.
         */
        private readonly ?\App\Core\Encrypter $sifreleyici = null,
    ) {
    }

    /**
     * Saklanacak biçim: şifreleyici varsa şifreli, yoksa düz.
     */
    private function saklanacak(string $anahtar): string
    {
        return $this->sifreleyici?->encrypt($anahtar) ?? $anahtar;
    }

    /**
     * Saklanan değeri okur — TEMBEL GÖÇ.
     *
     * Migration YOK: mevcut satırlar DÜZ metin taşıyor ve onları toplu
     * dönüştürmek, kurulu her sistemde bir veri göçü demek olurdu. Çözme
     * başarısızsa değer düz kabul edilir; satır bir sonraki yenilemede
     * kendiliğinden şifreliye döner.
     *
     * Bu tolerans bir açık DEĞİL: şifreli metin ancak doğru anahtarla çözülür,
     * çözülemeyen değer zaten şifreli olamaz.
     */
    private function okunan(?string $saklanan): string
    {
        if (!is_string($saklanan) || $saklanan === '' || $this->sifreleyici === null) {
            return (string) $saklanan;
        }

        try {
            return $this->sifreleyici->decrypt($saklanan);
        } catch (\Throwable) {
            return $saklanan; // göç öncesi düz değer
        }
    }

    /**
     * Panelde GÖSTERİLECEK anahtar — çözülmüş hâli.
     *
     * @param array<string, mixed> $row
     */
    public function gosterilecek(array $row): string
    {
        $saklanan = $row['share_key_plain'] ?? null;

        return $this->okunan(is_string($saklanan) ? $saklanan : null);
    }

    /** Yeni anahtar üretir — kriptografik rastgelelik (tahmin edilemez olmalı). */
    public function uret(): string
    {
        $anahtar = '';
        $sinir = strlen(self::ALFABE) - 1;
        for ($i = 0; $i < self::UZUNLUK; $i++) {
            $anahtar .= self::ALFABE[random_int(0, $sinir)];
        }

        return $anahtar;
    }

    public function hash(string $anahtar): string
    {
        return hash_hmac('sha256', mb_strtoupper(trim($anahtar), 'UTF-8'), $this->appKey);
    }

    /**
     * Listenin anahtarını üretip kaydeder (yenileme dahil).
     *
     * Yenileme ESKİYİ ANINDA ÖLDÜRÜR (K51 iptal ruhu): hash değişir, eski
     * anahtarla yazılmış çerezler de geçersizleşir çünkü çerez imzası hash'i
     * kapsar.
     *
     * @return string düz metin anahtar (yalnız panelde gösterilir)
     */
    public function yenile(int $listId, DateTimeImmutable $now): string
    {
        $anahtar = $this->uret();
        $this->lists->update($listId, [
            'share_key_hash' => $this->hash($anahtar),
            'share_key_plain' => $this->saklanacak($anahtar),
        ], $now);

        return $anahtar;
    }

    /**
     * Liste satırında anahtar yoksa üretir (migration sonrası ilk erişim).
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed> güncel satır
     */
    public function hazirla(array $row, DateTimeImmutable $now): array
    {
        if (is_string($row['share_key_hash'] ?? null) && $row['share_key_hash'] !== '') {
            return $row;
        }

        $anahtar = $this->uret();
        $this->lists->update((int) $row['id'], [
            'share_key_hash' => $this->hash($anahtar),
            'share_key_plain' => $this->saklanacak($anahtar),
        ], $now);

        $row['share_key_hash'] = $this->hash($anahtar);
        // DÖNEN SATIR DÜZ TAŞIR: çağıran onu kullanıcıya gösterir. Saklanan
        // biçim şifrelidir; ikisi bilinçli olarak farklıdır.
        $row['share_key_plain'] = $anahtar;

        return $row;
    }

    /**
     * Bu listede kapı AÇIK mı? (kapalıysa davranış eski hâlidir: token yeter)
     *
     * @param array<string, mixed> $row
     */
    public function kapiAcik(array $row): bool
    {
        return (int) ($row['share_key_enabled'] ?? 1) === 1;
    }

    /**
     * Girilen anahtar doğru mu? Karşılaştırma sabit zamanlı.
     *
     * @param array<string, mixed> $row
     */
    public function dogru(array $row, string $girilen): bool
    {
        $kayitli = $row['share_key_hash'] ?? null;
        if (!is_string($kayitli) || $kayitli === '') {
            return false;
        }
        if (preg_match('/^[0-9A-Za-z]{6}$/', trim($girilen)) !== 1) {
            return false;
        }

        return hash_equals($kayitli, $this->hash($girilen));
    }

    /**
     * Çerez değeri: token + anahtar hash'i + son kullanma üzerinden imzalanır.
     *
     * Anahtar hash'i imzanın İÇİNDE olduğu için anahtar yenilendiğinde eski
     * çerez kendiliğinden geçersizleşir — ayrıca bir iptal listesi tutmaya
     * gerek kalmaz.
     */
    /** @param array<string, mixed> $row */
    public function cerezDegeri(string $token, array $row, DateTimeImmutable $now): string
    {
        $sonKullanma = $now->getTimestamp() + self::CEREZ_OMRU_SANIYE;

        return $sonKullanma . '.' . $this->cerezImzasi($token, $row, $sonKullanma);
    }

    /**
     * Çerez geçerli mi? (biçim · süre · imza)
     *
     * @param array<string, mixed> $row
     */
    public function cerezGecerli(string $token, array $row, ?string $cerez, DateTimeImmutable $now): bool
    {
        if ($cerez === null || $cerez === '') {
            return false;
        }
        $parcalar = explode('.', $cerez, 2);
        if (count($parcalar) !== 2 || preg_match('/^\d{10,11}$/', $parcalar[0]) !== 1) {
            return false;
        }
        $sonKullanma = (int) $parcalar[0];
        if ($sonKullanma <= $now->getTimestamp()) {
            return false;
        }
        // Aşırı uzun ömürlü çerez kabul edilmez (üretilmiş olamaz).
        if ($sonKullanma > $now->getTimestamp() + self::CEREZ_OMRU_SANIYE + 60) {
            return false;
        }

        return hash_equals($this->cerezImzasi($token, $row, $sonKullanma), $parcalar[1]);
    }

    /** @param array<string, mixed> $row */
    private function cerezImzasi(string $token, array $row, int $sonKullanma): string
    {
        $kapsam = implode("\n", [
            'tdk-liste-anahtar-v1',
            $token,
            (string) ($row['share_key_hash'] ?? ''),
            (string) $sonKullanma,
        ]);

        return substr(rtrim(strtr(base64_encode(hash_hmac('sha256', $kapsam, $this->appKey, true)), '+/', '-_'), '='), 0, 32);
    }
}
