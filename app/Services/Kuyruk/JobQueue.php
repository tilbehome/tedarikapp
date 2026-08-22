<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;
use PDOException;
use Throwable;

/**
 * İŞ KUYRUĞU (İE#20 C3) — paylaşımlı hosting gerçeğine göre tasarlanmıştır.
 *
 * Sözleşme dört kuralda özetlenir:
 *
 *  1. **Aynı iş iki kez kuyruğa girmez.** `tur + anahtar` çifti UNIQUE'tir:
 *     "42 numaralı ürünü çevir" işi on kez istense de kuyrukta bir satırdır.
 *     Bu, kullanıcının düğmeye üst üste basmasını zararsız kılar.
 *  2. **Sahiplenme yarışa dayanıklıdır.** İş, koşullu UPDATE ile alınır; iki
 *     işleyici aynı işi alamaz. Kaybeden bir sonraki işe geçer.
 *  3. **Ölen işleyici işi rehin almaz.** Kilit zaman aşımına uğrarsa iş yeniden
 *     alınabilir hâle gelir; PHP `max_execution_time` ile kesilen bir koşum
 *     kuyruğu kilitlemez.
 *  4. **Başarısız iş SESSİZCE KAYBOLMAZ.** Deneme hakkı biten iş `olu` rafına
 *     düşer, hatası saklanır ve panelde görünür. Kaybolan iş, hiç başlamamış
 *     işten daha tehlikelidir — kimse eksikliğini fark etmez.
 */
final class JobQueue
{
    public const BEKLIYOR = 'bekliyor';
    public const CALISIYOR = 'calisiyor';
    public const BITTI = 'bitti';
    public const OLU = 'olu';

    /** Bir işleyicinin işi elinde tutabileceği azami süre; sonrasında iş geri alınır. */
    public const KILIT_OMRU_SANIYE = 900;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * İşi kuyruğa alır. Aynı `tur+anahtar` zaten kuyruktaysa YENİ SATIR AÇILMAZ.
     *
     * @param array<string, mixed> $yuk
     *
     * @return int kuyruktaki satırın kimliği (mevcut ya da yeni)
     */
    public function ekle(
        string $tur,
        ?string $anahtar,
        array $yuk,
        DateTimeImmutable $now,
        int $oncelik = 100,
        int $maxDeneme = 3,
    ): int {
        $pdo = $this->connection->pdo();
        $json = json_encode($yuk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $zaman = Dates::toStorage($now);

        try {
            $statement = $pdo->prepare(
                'INSERT INTO jobs (tur, anahtar, yuk, durum, oncelik, max_deneme, calisacak_at, created_at, updated_at)
                 VALUES (:tur, :anahtar, :yuk, :durum, :oncelik, :max_deneme, :calisacak_at, :created_at, :updated_at)',
            );
            $statement->execute([
                'tur' => $tur,
                'anahtar' => $anahtar,
                'yuk' => $json,
                'durum' => self::BEKLIYOR,
                'oncelik' => $oncelik,
                'max_deneme' => $maxDeneme,
                'calisacak_at' => $zaman,
                'created_at' => $zaman,
                'updated_at' => $zaman,
            ]);

            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            // UNIQUE ihlali = iş zaten kuyrukta. Bu bir HATA DEĞİL, istenen davranıştır.
            $mevcut = $this->bul($tur, $anahtar);
            if ($mevcut === null) {
                throw $e;
            }

            // Ölü rafındaki bir iş yeniden istenirse CANLANIR: kullanıcı "yeniden dene"
            // dediğinde ona "zaten kuyrukta" demek, hiçbir şey yapmamaktır.
            if ((string) $mevcut['durum'] === self::OLU) {
                $this->dirilt((int) $mevcut['id'], $now);
            }

            return (int) $mevcut['id'];
        }
    }

    /**
     * Sıradaki işi SAHİPLENİR. Yoksa null.
     *
     * @return array<string, mixed>|null
     */
    public function sahiplen(string $isleyiciKimligi, DateTimeImmutable $now): ?array
    {
        $pdo = $this->connection->pdo();
        $zaman = Dates::toStorage($now);
        $kilitEskisi = Dates::toStorage($now->modify('-' . self::KILIT_OMRU_SANIYE . ' seconds'));

        // Aday: çalışma zamanı gelmiş bekleyen iş VEYA kilidi eskimiş "çalışıyor" iş.
        $aday = $pdo->prepare(
            "SELECT id FROM jobs
             WHERE (durum = :bekliyor AND calisacak_at <= :simdi)
                OR (durum = :calisiyor AND kilitlendi_at IS NOT NULL AND kilitlendi_at <= :kilit_eskisi)
             ORDER BY oncelik ASC, calisacak_at ASC, id ASC
             LIMIT 1",
        );
        $aday->execute([
            'bekliyor' => self::BEKLIYOR,
            'calisiyor' => self::CALISIYOR,
            'simdi' => $zaman,
            'kilit_eskisi' => $kilitEskisi,
        ]);
        $id = $aday->fetchColumn();
        if ($id === false) {
            return null;
        }

        // Koşullu sahiplenme: iki işleyici arasındaki yarışı burası çözer.
        //
        // YER TUTUCU DİSİPLİNİ (v0.11.3 dersi): aynı adlı yer tutucu bir SQL
        // deyiminde İKİ KEZ geçemez. Üretimde `ATTR_EMULATE_PREPARES=false`
        // olduğu için MySQL yerel prepare kullanır ve tekrar eden adı HY093 ile
        // reddeder — canlıda 500, testte (emülasyonlu) sessizce çalışır. Bu yüzden
        // her tekrar AYRI ADLA yazılır.
        $al = $pdo->prepare(
            "UPDATE jobs
             SET durum = :calisiyor, kilit_sahibi = :sahip, kilitlendi_at = :kilit_at,
                 deneme = deneme + 1, updated_at = :guncelleme_at
             WHERE id = :id AND (
                   (durum = :bekliyor AND calisacak_at <= :simdi)
                OR (durum = :calisiyor2 AND kilitlendi_at IS NOT NULL AND kilitlendi_at <= :kilit_eskisi)
             )",
        );
        $al->execute([
            'calisiyor' => self::CALISIYOR,
            'calisiyor2' => self::CALISIYOR,
            'bekliyor' => self::BEKLIYOR,
            'sahip' => mb_substr($isleyiciKimligi, 0, 64),
            'kilit_at' => $zaman,
            'guncelleme_at' => $zaman,
            'simdi' => $zaman,
            'kilit_eskisi' => $kilitEskisi,
            'id' => (int) $id,
        ]);

        if ($al->rowCount() !== 1) {
            return null; // yarışı kaybettik; bir sonraki turda başka iş alınır
        }

        $satir = $pdo->prepare('SELECT * FROM jobs WHERE id = :id');
        $satir->execute(['id' => (int) $id]);
        $is = $satir->fetch();

        return is_array($is) ? $is : null;
    }

    public function basarili(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE jobs SET durum = :durum, hata = NULL, kilit_sahibi = NULL, kilitlendi_at = NULL,
                    bitti_at = :bitti_at, updated_at = :guncelleme_at
             WHERE id = :id',
        );
        $zaman = Dates::toStorage($now);
        $statement->execute([
            'durum' => self::BITTI,
            'bitti_at' => $zaman,
            'guncelleme_at' => $zaman,
            'id' => $id,
        ]);
    }

    /**
     * Başarısız iş: deneme hakkı varsa GERİ BIRAKILIR (artan bekleme), yoksa ÖLÜ RAFINA.
     *
     * Artan bekleme (backoff) bilinçlidir: geçici bir ağ hatasında hemen tekrar
     * denemek aynı hatayı alır ve deneme haklarını saniyeler içinde tüketir.
     */
    public function basarisiz(int $id, string $hata, DateTimeImmutable $now): void
    {
        $pdo = $this->connection->pdo();
        $oku = $pdo->prepare('SELECT deneme, max_deneme FROM jobs WHERE id = :id');
        $oku->execute(['id' => $id]);
        $satir = $oku->fetch();
        if (!is_array($satir)) {
            return;
        }

        $deneme = (int) $satir['deneme'];
        $max = (int) $satir['max_deneme'];
        $hata = mb_substr($hata, 0, 2000);

        if ($deneme >= $max) {
            $statement = $pdo->prepare(
                'UPDATE jobs SET durum = :durum, hata = :hata, kilit_sahibi = NULL, kilitlendi_at = NULL,
                        bitti_at = :bitti_at, updated_at = :guncelleme_at
                 WHERE id = :id',
            );
            $zaman = Dates::toStorage($now);
            $statement->execute([
                'durum' => self::OLU,
                'hata' => $hata,
                'bitti_at' => $zaman,
                'guncelleme_at' => $zaman,
                'id' => $id,
            ]);

            return;
        }

        $bekleme = min(3600, 60 * (2 ** max(0, $deneme - 1)));
        $statement = $pdo->prepare(
            'UPDATE jobs SET durum = :durum, hata = :hata, kilit_sahibi = NULL, kilitlendi_at = NULL,
                    calisacak_at = :sonra, updated_at = :simdi
             WHERE id = :id',
        );
        $statement->execute([
            'durum' => self::BEKLIYOR,
            'hata' => $hata,
            'sonra' => Dates::toStorage($now->modify('+' . $bekleme . ' seconds')),
            'simdi' => Dates::toStorage($now),
            'id' => $id,
        ]);
    }

    /**
     * İşi DOĞRUDAN ölü rafına gönderir — tekrar denemenin ANLAMSIZ olduğu hâller.
     *
     * `basarisiz()` geçici hatalar içindir (ağ, kota) ve deneme hakkı bırakır.
     * Ama "tanınmayan iş türü" ya da "ürün silinmiş" gibi bir hata tekrar
     * denemekle düzelmez: aynı sonucu üç kez üretir, kuyruğu meşgul eder ve
     * gerçek arızayı üç kat gecikmeyle görünür kılar.
     */
    public function oldur(int $id, string $hata, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE jobs SET durum = :durum, hata = :hata, kilit_sahibi = NULL, kilitlendi_at = NULL,
                    bitti_at = :bitti_at, updated_at = :guncelleme_at
             WHERE id = :id',
        );
        $zaman = Dates::toStorage($now);
        $statement->execute([
            'durum' => self::OLU,
            'hata' => mb_substr($hata, 0, 2000),
            'bitti_at' => $zaman,
            'guncelleme_at' => $zaman,
            'id' => $id,
        ]);
    }

    /** Ölü rafındaki işi yeniden kuyruğa alır (panel "yeniden dene"). */
    public function dirilt(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE jobs SET durum = :durum, deneme = 0, hata = NULL, kilit_sahibi = NULL,
                    kilitlendi_at = NULL, bitti_at = NULL, calisacak_at = :calisacak_at, updated_at = :guncelleme_at
             WHERE id = :id',
        );
        $zaman = Dates::toStorage($now);
        $statement->execute([
            'durum' => self::BEKLIYOR,
            'calisacak_at' => $zaman,
            'guncelleme_at' => $zaman,
            'id' => $id,
        ]);
    }

    /**
     * Kuyruk sağlığı — panel "Sistem durumu" ekranının veri kaynağı.
     *
     * @return array{bekleyen: int, calisan: int, olu: int, en_eski_bekleyen_dakika: int|null, turler: array<string, int>}
     */
    public function saglik(DateTimeImmutable $now): array
    {
        $pdo = $this->connection->pdo();

        $say = static function (string $durum) use ($pdo): int {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM jobs WHERE durum = :durum');
            $statement->execute(['durum' => $durum]);

            return (int) $statement->fetchColumn();
        };

        $enEski = null;
        $statement = $pdo->prepare(
            'SELECT MIN(calisacak_at) FROM jobs WHERE durum = :durum',
        );
        $statement->execute(['durum' => self::BEKLIYOR]);
        $deger = $statement->fetchColumn();
        if (is_string($deger) && $deger !== '') {
            try {
                $enEski = max(0, (int) round(
                    ($now->getTimestamp() - Dates::fromStorage($deger, $now->getTimezone())->getTimestamp()) / 60,
                ));
            } catch (Throwable) {
                $enEski = null;
            }
        }

        $turler = [];
        $tur = $pdo->prepare("SELECT tur, COUNT(*) AS adet FROM jobs WHERE durum IN (:b, :c) GROUP BY tur");
        $tur->execute(['b' => self::BEKLIYOR, 'c' => self::CALISIYOR]);
        foreach ($tur->fetchAll() ?: [] as $satir) {
            $turler[(string) $satir['tur']] = (int) $satir['adet'];
        }

        return [
            'bekleyen' => $say(self::BEKLIYOR),
            'calisan' => $say(self::CALISIYOR),
            'olu' => $say(self::OLU),
            'en_eski_bekleyen_dakika' => $enEski,
            'turler' => $turler,
        ];
    }

    /**
     * Biten işlerin temizliği — kuyruk tablosu sonsuza dek büyümemeli.
     *
     * Ölü işler SİLİNMEZ: onlar bir arıza kaydıdır ve elle incelenmelidir.
     */
    public function temizle(DateTimeImmutable $now, int $gun = 7): int
    {
        $statement = $this->connection->pdo()->prepare(
            'DELETE FROM jobs WHERE durum = :durum AND bitti_at IS NOT NULL AND bitti_at <= :esik',
        );
        $statement->execute([
            'durum' => self::BITTI,
            'esik' => Dates::toStorage($now->modify('-' . max(1, $gun) . ' days')),
        ]);

        return $statement->rowCount();
    }

    /** @return array<string, mixed>|null */
    public function bul(string $tur, ?string $anahtar): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            $anahtar === null
                ? 'SELECT * FROM jobs WHERE tur = :tur AND anahtar IS NULL LIMIT 1'
                : 'SELECT * FROM jobs WHERE tur = :tur AND anahtar = :anahtar LIMIT 1',
        );
        $statement->execute($anahtar === null ? ['tur' => $tur] : ['tur' => $tur, 'anahtar' => $anahtar]);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    /**
     * Ölü rafı — panelde listelenir.
     *
     * @return list<array<string, mixed>>
     */
    public function oluIsler(int $limit = 50): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, tur, anahtar, hata, deneme, updated_at FROM jobs
             WHERE durum = :durum ORDER BY updated_at DESC LIMIT :limit',
        );
        $statement->bindValue('durum', self::OLU);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> */
        return $statement->fetchAll() ?: [];
    }
}
