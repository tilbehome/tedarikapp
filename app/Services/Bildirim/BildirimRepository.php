<?php

declare(strict_types=1);

namespace App\Services\Bildirim;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;
use PDOException;

/**
 * BİLDİRİM DEPOSU (V3-B A2) — `notifications` tablosunun tek erişim noktası.
 *
 * Katman bağımsızdır: yalnız `Connection` bilir. Denetleyiciden de,
 * `JobQueue`dan da, gece süpürmesinden de çağrılabilir — bildirim üretimi bir
 * HTTP kavramı değildir, kuyrukta ölen iş de bildirim doğurur.
 *
 * BİRLEŞTİRME: `yaz()` önce UPDATE dener, satır yoksa INSERT eder. Sıra
 * bilinçlidir ve `RateSnapshotRepository::yeniSurum()` ile aynı derstir
 * (İE#22 A3): önce SELECT sonra INSERT deseni, iki eşzamanlı istek arasında
 * yarış bırakır ve UNIQUE ihlaliyle olayı KAYBETTİRİR. UPDATE-önce deseninde
 * kaybolan hiçbir olay yoktur; en kötü ihtimalle iki istek de UPDATE'e düşer
 * ve sayaç iki kez artar — istenen davranış zaten budur.
 */
final class BildirimRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Bildirimi yazar veya açık penceredeki satırla birleştirir.
     *
     * @param  array{olay_kodu: string, onem: string, grup: string, baslik: string,
     *               govde: string, eylem_linki?: string|null, kullanici_id?: int|null,
     *               grup_anahtari: string, audit_id?: int|null} $bildirim
     * @param  int  $pencereDakika 0 ise birleştirme kapalıdır (her olay ayrı satır)
     * @return array{id: int, birlesti: bool, birlesen_sayi: int}
     */
    public function yaz(array $bildirim, DateTimeImmutable $now, int $pencereDakika): array
    {
        $pencere = $this->pencereBaslangici($bildirim, $now, $pencereDakika);
        $pdo = $this->connection->pdo();

        if ($pencereDakika > 0) {
            // 1) AÇIK PENCEREYİ ARTIR. `okundu_at = NULL` bilinçlidir: birleşen
            //    yeni olay, kullanıcının okuduğu satırı yeniden okunmamış yapar
            //    — "aynı şey bir daha oldu" görülmelidir.
            $guncelle = $pdo->prepare(
                'UPDATE notifications
                 SET birlesen_sayi = birlesen_sayi + 1,
                     govde = :govde,
                     okundu_at = NULL,
                     updated_at = :updated_at
                 WHERE olay_kodu = :olay_kodu
                   AND grup_anahtari = :grup_anahtari
                   AND pencere_baslangic = :pencere_baslangic',
            );
            $guncelle->execute([
                'govde' => $bildirim['govde'],
                'updated_at' => Dates::toStorage($now),
                'olay_kodu' => $bildirim['olay_kodu'],
                'grup_anahtari' => $bildirim['grup_anahtari'],
                'pencere_baslangic' => Dates::toStorage($pencere),
            ]);

            if ($guncelle->rowCount() > 0) {
                return $this->birlesmisSatir($bildirim, $pencere);
            }
        }

        // 2) YENİ SATIR. UNIQUE ihlali yalnız aynı anda iki istek gelirse olur;
        //    o durumda UPDATE yoluna DÖNÜLÜR — olay kaybolmaz.
        try {
            $this->ekle($bildirim, $pencere, $now);
        } catch (PDOException $hata) {
            if (!$this->benzersizlikIhlaliMi($hata)) {
                throw $hata;
            }

            $tekrar = $pdo->prepare(
                'UPDATE notifications
                 SET birlesen_sayi = birlesen_sayi + 1, okundu_at = NULL, updated_at = :updated_at
                 WHERE olay_kodu = :olay_kodu AND grup_anahtari = :grup_anahtari
                   AND pencere_baslangic = :pencere_baslangic',
            );
            $tekrar->execute([
                'updated_at' => Dates::toStorage($now),
                'olay_kodu' => $bildirim['olay_kodu'],
                'grup_anahtari' => $bildirim['grup_anahtari'],
                'pencere_baslangic' => Dates::toStorage($pencere),
            ]);

            return $this->birlesmisSatir($bildirim, $pencere);
        }

        return ['id' => (int) $pdo->lastInsertId(), 'birlesti' => false, 'birlesen_sayi' => 1];
    }

    /**
     * Şu an açık bir transaction var mı? (K102)
     *
     * Yayıncı bu bilgiyle karar veriyor: transaction İÇİNDEYSE bildirim hatası
     * yukarı verilir ve birincil kayıt da geri alınır (ya ikisi de olur ya
     * hiçbiri). DIŞINDAYSA birincil kayıt ZATEN COMMIT OLMUŞTUR; istisnayı
     * yukarı vermek, başarılı bir işlemi 500'e çevirmek olurdu.
     */
    public function islemIcindeMi(): bool
    {
        return $this->connection->pdo()->inTransaction();
    }

    /**
     * Okunmamış bildirim sayısı — üst çubuk rozeti bunu okur.
     */
    public function okunmamisSayisi(): int
    {
        $statement = $this->connection->pdo()->query(
            'SELECT COUNT(*) FROM notifications WHERE okundu_at IS NULL',
        );

        return $statement === false ? 0 : (int) $statement->fetchColumn();
    }

    /**
     * Bildirim merkezi listesi — en yeni önce.
     *
     * @return list<array<string, mixed>>
     */
    public function listele(int $limit = 50, bool $yalnizOkunmamis = false): array
    {
        $limit = max(1, min(200, $limit));
        $kosul = $yalnizOkunmamis ? 'WHERE okundu_at IS NULL' : '';

        $statement = $this->connection->pdo()->prepare(
            'SELECT id, olay_kodu, onem, grup, baslik, govde, eylem_linki,
                    birlesen_sayi, audit_id, okundu_at, created_at, updated_at
             FROM notifications ' . $kosul . '
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit,
        );
        $statement->execute();

        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $statement->fetchAll() ?: [];

        return array_map(static function (array $satir): array {
            $satir['id'] = (int) $satir['id'];
            $satir['birlesen_sayi'] = (int) $satir['birlesen_sayi'];
            $satir['audit_id'] = $satir['audit_id'] === null ? null : (int) $satir['audit_id'];
            $satir['okundu'] = $satir['okundu_at'] !== null;

            return $satir;
        }, $satirlar);
    }

    /** Tek bildirimi okundu işaretler; zaten okunmuşsa false döner. */
    public function okunduIsaretle(int $id, DateTimeImmutable $now): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE notifications SET okundu_at = :okundu_at, updated_at = :updated_at
             WHERE id = :id AND okundu_at IS NULL',
        );
        $statement->execute([
            'okundu_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
            'id' => $id,
        ]);

        return $statement->rowCount() > 0;
    }

    /** Hepsini okundu işaretler; işaretlenen satır sayısını döner. */
    public function hepsiniOkunduIsaretle(DateTimeImmutable $now): int
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE notifications SET okundu_at = :okundu_at, updated_at = :updated_at
             WHERE okundu_at IS NULL',
        );
        $statement->execute([
            'okundu_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
        ]);

        return $statement->rowCount();
    }

    /**
     * Bu olay kodu bugün (verilen andan geriye `saat` içinde) üretilmiş mi?
     *
     * A5 görünüm deseni "aynı olay oturum başına bir kez anlık kart" diyor;
     * anlık kart kararı bu sorguya dayanır.
     */
    public function sonUretim(string $olayKodu, DateTimeImmutable $now, int $saat = 24): ?string
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT created_at FROM notifications
             WHERE olay_kodu = :olay_kodu AND created_at >= :sinir
             ORDER BY created_at DESC LIMIT 1',
        );
        $statement->execute([
            'olay_kodu' => $olayKodu,
            'sinir' => Dates::toStorage($now->modify(sprintf('-%d hours', max(1, $saat)))),
        ]);
        $deger = $statement->fetchColumn();

        return is_string($deger) ? $deger : null;
    }

    /** Eski okunmuş bildirimleri siler (bakım). */
    public function temizle(DateTimeImmutable $now, int $gun = 60): int
    {
        $statement = $this->connection->pdo()->prepare(
            'DELETE FROM notifications
             WHERE okundu_at IS NOT NULL AND created_at < :sinir',
        );
        $statement->execute([
            'sinir' => Dates::toStorage($now->modify(sprintf('-%d days', max(1, $gun)))),
        ]);

        return $statement->rowCount();
    }

    /**
     * Pencerenin başlangıç anı: zaman ekseni `pencereDakika` uzunluğunda
     * sabit dilimlere bölünür. Kayan pencere KULLANILMAZ — kayan pencerede
     * "hangi satıra birleşeceğim" sorusunun cevabı okuma anına bağlı olur ve
     * UNIQUE kısıtı anlamını yitirir.
     *
     * @param array<string, mixed> $bildirim
     */
    private function pencereBaslangici(array $bildirim, DateTimeImmutable $now, int $pencereDakika): DateTimeImmutable
    {
        if ($pencereDakika <= 0) {
            // Birleştirme kapalı: her olay kendi penceresindedir. Anı mikro
            // saniyeye kadar korumak yerine olay kodunu anahtara katmak
            // gerekmiyor — grup_anahtari zaten tekil kimlik taşıyor.
            unset($bildirim);

            return $now;
        }

        $dakika = (int) $now->format('i');
        $dilim = intdiv($dakika, $pencereDakika) * $pencereDakika;

        return $now->setTime((int) $now->format('H'), $dilim, 0);
    }

    /** @param array<string, mixed> $bildirim */
    private function ekle(array $bildirim, DateTimeImmutable $pencere, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO notifications
                (olay_kodu, onem, grup, baslik, govde, eylem_linki, kullanici_id,
                 grup_anahtari, pencere_baslangic, birlesen_sayi, audit_id, okundu_at,
                 created_at, updated_at)
             VALUES
                (:olay_kodu, :onem, :grup, :baslik, :govde, :eylem_linki, :kullanici_id,
                 :grup_anahtari, :pencere_baslangic, 1, :audit_id, NULL,
                 :created_at, :updated_at)',
        );
        $statement->execute([
            'olay_kodu' => $bildirim['olay_kodu'],
            'onem' => $bildirim['onem'],
            'grup' => $bildirim['grup'],
            'baslik' => $bildirim['baslik'],
            'govde' => $bildirim['govde'],
            'eylem_linki' => $bildirim['eylem_linki'] ?? null,
            'kullanici_id' => $bildirim['kullanici_id'] ?? null,
            'grup_anahtari' => $bildirim['grup_anahtari'],
            'pencere_baslangic' => Dates::toStorage($pencere),
            'audit_id' => $bildirim['audit_id'] ?? null,
            'created_at' => Dates::toStorage($now),
            'updated_at' => Dates::toStorage($now),
        ]);
    }

    /**
     * Birleşilen satırın kimliği VE güncel sayacı.
     *
     * Sayaç burada okunur çünkü çağıran (yayıncı) katalogdaki toplu gövdeyi
     * "{n} ürün kabul edildi" diye dolduracaktır; sayıyı ayrıca sorgulatmak
     * aynı satırı iki kez okumak olurdu.
     *
     * @param  array<string, mixed> $bildirim
     * @return array{id: int, birlesti: bool, birlesen_sayi: int}
     */
    private function birlesmisSatir(array $bildirim, DateTimeImmutable $pencere): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, birlesen_sayi FROM notifications
             WHERE olay_kodu = :olay_kodu AND grup_anahtari = :grup_anahtari
               AND pencere_baslangic = :pencere_baslangic',
        );
        $statement->execute([
            'olay_kodu' => $bildirim['olay_kodu'],
            'grup_anahtari' => $bildirim['grup_anahtari'],
            'pencere_baslangic' => Dates::toStorage($pencere),
        ]);
        /** @var array{id: int|string, birlesen_sayi: int|string}|false $satir */
        $satir = $statement->fetch();

        return [
            'id' => $satir === false ? 0 : (int) $satir['id'],
            'birlesti' => true,
            'birlesen_sayi' => $satir === false ? 1 : (int) $satir['birlesen_sayi'],
        ];
    }

    /** Birleşme sonrası toplu gövdeyi yazar (yalnız yayıncı çağırır). */
    public function govdeyiYaz(int $id, string $govde): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE notifications SET govde = :govde WHERE id = :id',
        );
        $statement->execute(['govde' => $govde, 'id' => $id]);
    }

    private function benzersizlikIhlaliMi(PDOException $hata): bool
    {
        $kod = (string) $hata->getCode();

        return $kod === '23000' || $kod === '23505';
    }
}
