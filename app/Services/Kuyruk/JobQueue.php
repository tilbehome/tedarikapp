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

    /**
     * SON SEÇİM NEDENİ (v1.2.1 A7) — "boş" ile "yarış kaybı" AYRI ŞEYLERDİR.
     *
     * Eskiden ikisi de `null` dönüyordu; çağıran turu bitirip günlüğe
     * "kuyruk boş" yazıyordu. Oysa kuyrukta iş VARDI, yalnız başkası kapmıştı.
     * İki işleyici aynı anda koştuğunda kuyruk yarı hızda ilerliyor ve
     * günlük yanlış sebep gösteriyordu.
     */
    public const SECIM_ALINDI = 'alindi';
    public const SECIM_BOS = 'bos';
    public const SECIM_YARIS = 'yaris';
    public const CALISIYOR = 'calisiyor';
    public const BITTI = 'bitti';
    public const OLU = 'olu';

    /**
     * Bir işleyicinin işi elinde tutabileceği azami süre; sonrasında iş geri alınır.
     *
     * D9-KESİN (25 Ağu 2026): 900 sn (15 dk) SAHADA ÇOK UZUNDU. Cron beş dakikada
     * bir koşuyor; süreç bir işi alıp ölürse (paylaşımlı hostingde CLI süre
     * sınırı, bellek, koparılan bağlantı) iş ÜÇ TUR boyunca kimseye görünmez.
     * O üç turda günlüğe "kuyruk boş" düşer — sahada tam olarak bu yaşandı:
     * beş işten ikisi bitti, kalan üçü 23 dakika boyunca "alınmıyor" göründü.
     *
     * Kira artık cron aralığına EŞİT: en kötü hâlde bir tur kaybedilir. Uzun
     * süren iş `kalpAtisi()` ile kirasını uzatır (B11); uzatamıyorsa zaten
     * ölmüştür ve işin başkasına geçmesi DOĞRU davranıştır.
     */
    public const KILIT_OMRU_SANIYE = 300;

    /**
     * Bir sahiplenme çağrısında kaç aday denenir (A7).
     *
     * Sınırsız denemek, yoğun çekişmede tek çağrıyı uzun bir döngüye çevirirdi.
     * Beş deneme, iki-üç işleyicili gerçek yükte fazlasıyla yeterli; dolarsa
     * cevap "boş" değil "çekişme" olur ve bir sonraki tur yeniden dener.
     */
    private const ADAY_DENEME_SINIRI = 5;

    /**
     * @param \App\Services\Bildirim\BildirimYayinci|null $bildirim V3-B A3 —
     *        kuyruk olayları (yeniden deneme, ölüm, toparlanma, diriltme) bildirim
     *        doğurur. OPSİYONELDİR: kuyruk bir HTTP kavramı değildir ve bakım
     *        betikleri onu yayıncısız kurar; null iken kuyruk aynen çalışır.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ?\App\Services\Bildirim\BildirimYayinci $bildirim = null,
    ) {
    }

    /**
     * Kuyruk olayını yayımlar — iş türü ve hata sınıfı birleştirme anahtarıdır.
     *
     * Kuyruk olaylarının hiçbiri denetim izine yazılmaz (activity_log kullanıcı
     * eylemlerinin izidir, arka plan işinin değil). Bu yüzden burada yayımlanan
     * olayların TAMAMI katalogda `birlestirme.izinli=true` olmalıdır; audit
     * bağlantısı isteyen bir olay buradan yayımlanırsa yayıncı PATLAR ve testte
     * görülür — sessizce audit'siz satır yazılmaz.
     *
     * @param array<string, scalar|null> $baglam
     */
    private function duyur(string $olayKodu, array $baglam): void
    {
        $this->bildirim?->guvenliYayimla($olayKodu, $baglam);
    }

    /**
     * DENETİMLİ kuyruk olayı: önce `activity_log`a SİSTEM aktörüyle satır yazar,
     * sonra bildirimi o satıra bağlar.
     *
     * Katalog, birleştirmesi kapalı olayların "değiştirilemez audit bağlantısı"
     * ile gösterilmesini şart koşuyor. Ölen iş, diriltilen iş ve durmuş kuyruk
     * tam olarak böyle olaylardır — ve bunlar bugüne kadar HİÇBİR denetim izi
     * bırakmıyordu. "Çeviri neden gelmedi?" sorusunun cevabı yalnız `jobs.hata`
     * kolonundaydı; iş temizlenince o da kayboluyordu. Artık kalıcı iz var.
     *
     * @param array<string, scalar|null> $baglam
     */
    private function duyurDenetimli(
        string $olayKodu,
        int $isId,
        string $eylem,
        string $ayrinti,
        DateTimeImmutable $now,
        array $baglam,
    ): void {
        if ($this->bildirim === null) {
            return;
        }

        $auditId = (new \App\Services\ActivityLog($this->connection))->record(
            'job',
            $isId,
            $eylem,
            mb_substr($ayrinti, 0, 500),
            null,
            $now,
            \App\Services\ActivityLog::ACTOR_SYSTEM,
            null,
        );

        $this->bildirim->guvenliYayimla($olayKodu, $baglam, $auditId);
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
     * ALINABİLİRLİK KOŞULU — TEK KAYNAK (D9 saha bulgusu, 25 Ağu 2026).
     *
     * BULGU: panel "Bekleyen 5 · kuyruk sağlıklı" derken cron günlüğü her turda
     * "0 iş · kuyruk boş" yazıyordu. Ölü 0, hata 0 — yani işler denenip
     * düşmüyor, HİÇ ALINMIYORDU. Sebep iki yüzeyin AYRI SORGU kullanmasıydı:
     *   · sayaç  → `durum = 'bekliyor'`            (zaman koşulu YOK)
     *   · işçi   → `durum = 'bekliyor' AND calisacak_at <= now`
     * Aradaki tek fark zaman koşuludur; `calisacak_at` ileri tarihliyse (ya da
     * işçinin saati sayacınkinden geriyse) sayaç "5 bekliyor" der, işçi hiçbir
     * şey görmez ve kimse çelişkiyi fark etmez.
     *
     * Koşul artık TEK YERDE yazılıdır ve hem sahiplenme hem sayım aynı metni
     * kullanır — D5'te sayfa içi panel ile popup için yapılanın kuyruk hâli.
     */
    private const ALINABILIR = '(durum = :bekliyor AND calisacak_at <= :simdi)
                OR (durum = :calisiyor AND kilit_bitis IS NOT NULL AND kilit_bitis <= :kira_bitti)';

    /** @return array<string, string> ALINABILIR koşulunun yer tutucuları */
    private function alinabilirParametreleri(string $zaman): array
    {
        return [
            'bekliyor' => self::BEKLIYOR,
            'calisiyor' => self::CALISIYOR,
            'simdi' => $zaman,
            'kira_bitti' => $zaman,
        ];
    }

    /**
     * ŞU AN alınabilir iş sayısı — işçinin GERÇEKTEN göreceği küme.
     *
     * Panel bunu `bekleyen` ile birlikte basar: ikisi ayrışıyorsa sorun görünür
     * hâle gelir ("5 bekliyor · 0 alınabilir" bir arıza cümlesidir).
     */
    public function alinabilirSayisi(DateTimeImmutable $now): int
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COUNT(*) FROM jobs WHERE ' . self::ALINABILIR,
        );
        $statement->execute($this->alinabilirParametreleri(Dates::toStorage($now)));

        return (int) $statement->fetchColumn();
    }

    /**
     * Bekleyen ama HENÜZ ZAMANI GELMEMİŞ işlerin sayısı ve en yakınının dakikası.
     *
     * @return array{sayi: int, en_yakin_dakika: int|null}
     */
    public function ileriTarihliler(DateTimeImmutable $now): array
    {
        $zaman = Dates::toStorage($now);
        $statement = $this->connection->pdo()->prepare(
            'SELECT COUNT(*) AS adet, MIN(calisacak_at) AS en_yakin
             FROM jobs WHERE durum = :durum AND calisacak_at > :simdi',
        );
        $statement->execute(['durum' => self::BEKLIYOR, 'simdi' => $zaman]);
        $satir = $statement->fetch();
        $sayi = is_array($satir) ? (int) $satir['adet'] : 0;
        $enYakin = is_array($satir) && is_string($satir['en_yakin'] ?? null) ? (string) $satir['en_yakin'] : null;

        $dakika = null;
        if ($enYakin !== null && $enYakin !== '') {
            try {
                $dakika = (int) ceil(
                    (Dates::fromStorage($enYakin, $now->getTimezone())->getTimestamp() - $now->getTimestamp()) / 60,
                );
            } catch (Throwable) {
                $dakika = null;
            }
        }

        return ['sayi' => $sayi, 'en_yakin_dakika' => $dakika];
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

        // ADAY SEÇİMİ — TÜR ADALETİ SQL'DE VE DETERMİNİSTİK (v1.2.1 A7).
        //
        // Eski sıra "öncelik, zaman, id" idi. 500 çeviri işi kuyruğa girdiğinde
        // aralarına düşen tek bir skor işi, 500 çeviri bitene kadar bekliyordu:
        // kuyruk teknik olarak çalışıyor ama bir iş TÜRÜ açlıktan ölüyordu.
        //
        // İlk çözüm PHP'de dönüşümlü bir sayaçtı ve SÜREÇ ÖMRÜNDEYDİ. Her cron
        // turu sayacı sıfırdan başlatıyordu; turlar kısa ve sık olduğu için
        // (5 dk) her turun ilk işi daima aynı türden geliyor, listenin
        // sonundaki tür pratikte hiç sıra alamıyordu. "Dönüşüm" tek süreç
        // içinde çalışıyor, süreçler arasında çalışmıyordu.
        //
        // YENİ KURAL: eşit öncelikli türler arasında EN ESKİ bekleyen işi olan
        // tür seçilir. Süreç ömründen bağımsızdır ve deterministiktir: açlıktan
        // ölen tür, bekledikçe kendiliğinden öne çıkar. Öncelik hâlâ üstündür —
        // adalet, önceliğin yerine geçmez, EŞİT öncelikler arasında paylaştırır.
        $sira = $pdo->prepare(
            'SELECT tur, MIN(oncelik) AS en_yuksek, MIN(calisacak_at) AS en_eski
             FROM jobs
             WHERE ' . self::ALINABILIR . '
             GROUP BY tur',
        );
        $sira->execute($this->alinabilirParametreleri($zaman));
        /** @var list<array<string, mixed>> $turler */
        $turler = $sira->fetchAll();
        if ($turler === []) {
            $this->sonSecimNedeni = self::SECIM_BOS;

            return null;
        }

        $turler = $this->adaletSirasi($pdo, $turler);

        // SINIRLI YENİDEN ADAY (A7): seçilen iş başkası tarafından kapılmışsa
        // tur BİTMEZ — sıradaki aday denenir. Eskiden tek kayıp `null` dönüyor
        // ve çağıran "kuyruk boş" diye turu kapatıyordu; iki işleyici aynı anda
        // koştuğunda kuyruk yarı hızda ilerliyordu.
        $denenen = [];
        for ($tekrar = 0; $tekrar < self::ADAY_DENEME_SINIRI; $tekrar++) {
            $id = $this->siradakiAday($pdo, $turler, $zaman, $denenen);
            if ($id === null) {
                // Aday kalmadı: türler vardı ama hepsinin işleri kapılmış.
                $this->sonSecimNedeni = $denenen === [] ? self::SECIM_BOS : self::SECIM_YARIS;

                return null;
            }
            $denenen[] = $id;

            $is = $this->sahiplenmeyiDene($pdo, $id, $isleyiciKimligi, $now, $zaman);
            if ($is !== null) {
                return $is;
            }
        }

        // Deneme sınırı doldu: kuyruk BOŞ DEĞİL, sadece çekişme var.
        $this->sonSecimNedeni = self::SECIM_YARIS;

        return null;
    }

    /**
     * TÜR SIRASI — DETERMİNİSTİK VE SÜREÇTEN BAĞIMSIZ (v1.2.1 A7).
     *
     * Ölçüt sırası:
     *   1. öncelik (küçük sayı önce) — adalet önceliğin yerine geçmez,
     *   2. O AN KOŞAN İŞ SAYISI (az olan önce) — hâlâ hizmet almakta olan tür
     *      geri çekilir; dönüşüm bundan doğar,
     *   3. en eski bekleyen iş — açlıktan ölen tür bekledikçe öne çıkar,
     *   4. tür adı — tam eşitlikte bile sonuç ÖNGÖRÜLEBİLİR olsun.
     *
     * (2) neden gerekli: eşit öncelikli ve AYNI ANDA eklenmiş işlerde yaş hiçbir
     * ayrım üretmez; o hâlde tek tür bütün turu alırdı. Eski çözüm PHP'de bir
     * sayaçtı ama SÜREÇ ÖMRÜNDEYDİ — her cron turu sıfırlanıyor ve daima aynı
     * tür öne geçiyordu. Koşan iş sayısı veritabanındadır: süreçler arasında da
     * çalışır.
     *
     * İki sorgu, tek sıralama: `ALINABILIR` koşulu CASE içine gömülseydi aynı
     * yer tutucu iki kez geçerdi ve MySQL yerel prepare bunu HY093 ile
     * reddederdi (SorguYerTutucuTest bunu zaten yasaklıyor).
     *
     * @param  list<array<string, mixed>> $turler
     * @return list<array<string, mixed>>
     */
    private function adaletSirasi(\PDO $pdo, array $turler): array
    {
        $kosan = $pdo->prepare('SELECT tur, COUNT(*) AS adet FROM jobs WHERE durum = :calisiyor GROUP BY tur');
        $kosan->execute(['calisiyor' => self::CALISIYOR]);

        $sayilar = [];
        foreach ($kosan->fetchAll() as $satir) {
            $sayilar[(string) $satir['tur']] = (int) $satir['adet'];
        }

        usort($turler, static function (array $a, array $b) use ($sayilar): int {
            return [(int) $a['en_yuksek'], $sayilar[(string) $a['tur']] ?? 0, (string) $a['en_eski'], (string) $a['tur']]
               <=> [(int) $b['en_yuksek'], $sayilar[(string) $b['tur']] ?? 0, (string) $b['en_eski'], (string) $b['tur']];
        });

        return $turler;
    }

    /**
     * Sıradaki adayı seçer: tür sırası SQL'den gelir, daha önce denenenler atlanır.
     *
     * @param list<array<string, mixed>> $turler öncelik+yaş sırasında
     * @param list<int>                  $denenen bu turda zaten kaybedilen kimlikler
     */
    private function siradakiAday(\PDO $pdo, array $turler, string $zaman, array $denenen): ?int
    {
        foreach ($turler as $satir) {
            $aday = $pdo->prepare(
                'SELECT id FROM jobs
                 WHERE tur = :tur AND (' . self::ALINABILIR . ')
                 ORDER BY oncelik ASC, calisacak_at ASC, id ASC
                 LIMIT 10',
            );
            $aday->execute(['tur' => (string) $satir['tur']] + $this->alinabilirParametreleri($zaman));

            foreach ($aday->fetchAll(\PDO::FETCH_COLUMN) as $id) {
                if (!in_array((int) $id, $denenen, true)) {
                    return (int) $id;
                }
            }
        }

        return null;
    }

    /**
     * Tek bir işi sahiplenmeyi dener; yarış kaybında `null`.
     *
     * @return array<string, mixed>|null
     */
    private function sahiplenmeyiDene(
        \PDO $pdo,
        int $id,
        string $isleyiciKimligi,
        DateTimeImmutable $now,
        string $zaman,
    ): ?array {
        // TERK EDİLMİŞ İŞ (D9-KESİN): kirası dolmuş bir işi devralıyorsak, önceki
        // sahibi sonuç YAZMADAN düşmüş demektir. Bu sessizce tekrarlanırsa iş
        // sonsuza kadar "alınıyor ama bitmiyor" döngüsüne girer ve ölü rafında
        // hiç görünmez — sahada 23 dakika boyunca yaşanan buydu.
        //
        // Deneme hakkı bitmişse iş ölü rafına gönderilir: arıza GÖRÜNÜR olur.
        $terk = $pdo->prepare(
            'SELECT durum, deneme, max_deneme FROM jobs WHERE id = :id',
        );
        $terk->execute(['id' => (int) $id]);
        $terkSatir = $terk->fetch();
        if (
            is_array($terkSatir)
            && (string) $terkSatir['durum'] === self::CALISIYOR
            && (int) $terkSatir['deneme'] >= (int) $terkSatir['max_deneme']
        ) {
            // TOKENSIZ YOL BİLİNÇLİ: bu bir işleyici sonucu değil, TOPARLAYICI
            // eylemidir. Ortada kirasını yazacak bir işleyici zaten yok — düşen
            // sürecin token'ı kimsenin elinde değil. Sahiplik denetimi istemek
            // burada işi sonsuza kadar rehin bırakırdı.
            $this->oluYaz(
                (int) $id,
                'İşleyici sonuç yazmadan düştü; kira ' . (int) $terkSatir['deneme']
                . ' kez devralındı. Süreç zaman/bellek sınırına takılıyor olabilir.',
                $now,
                HataSinifi::KALICI,
                null,
            );

            // Aynı turda sıradaki işe geçilir; tek bir bozuk iş kuyruğu tıkamaz.
            // Bu iş artık ÖLÜ; çağıran döngü onu "denenmiş" sayıp sıradakine geçer.
            return null;
        }

        // Koşullu sahiplenme: iki işleyici arasındaki yarışı burası çözer.
        //
        // YER TUTUCU DİSİPLİNİ (v0.11.3 dersi): aynı adlı yer tutucu bir SQL
        // deyiminde İKİ KEZ geçemez. Üretimde `ATTR_EMULATE_PREPARES=false`
        // olduğu için MySQL yerel prepare kullanır ve tekrar eden adı HY093 ile
        // reddeder — canlıda 500, testte (emülasyonlu) sessizce çalışır. Bu yüzden
        // her tekrar AYRI ADLA yazılır.
        // B11: her sahiplenme BENZERSİZ bir token üretir. Kirası dolup devralınan
        // işin eski sahibi uyandığında token'ı artık eşleşmez ve sonucu YAZAMAZ —
        // iki kez koşan bir işin sonucunu birinin diğerini ezerek yazması böyle
        // engellenir.
        $token = bin2hex(random_bytes(16));

        $al = $pdo->prepare(
            "UPDATE jobs
             SET durum = :calisiyor, kilit_sahibi = :sahip, kilit_token = :token,
                 kilitlendi_at = :kilit_at, kilit_bitis = :kira_bitis,
                 deneme = deneme + 1, updated_at = :guncelleme_at
             WHERE id = :id AND (
                   (durum = :bekliyor AND calisacak_at <= :simdi)
                OR (durum = :calisiyor2 AND kilit_bitis IS NOT NULL AND kilit_bitis <= :kira_bitti)
             )",
        );
        $al->execute([
            'calisiyor' => self::CALISIYOR,
            'calisiyor2' => self::CALISIYOR,
            'bekliyor' => self::BEKLIYOR,
            'sahip' => mb_substr($isleyiciKimligi, 0, 64),
            'token' => $token,
            'kilit_at' => $zaman,
            'kira_bitis' => Dates::toStorage($now->modify('+' . self::KILIT_OMRU_SANIYE . ' seconds')),
            'guncelleme_at' => $zaman,
            'simdi' => $zaman,
            'kira_bitti' => $zaman,
            'id' => (int) $id,
        ]);

        if ($al->rowCount() !== 1) {
            // YARIŞI KAYBETTİK — "boş" DEĞİL. Çağıran döngü sıradaki adayı dener.
            return null;
        }

        $satir = $pdo->prepare('SELECT * FROM jobs WHERE id = :id');
        $satir->execute(['id' => (int) $id]);
        $is = $satir->fetch();
        if (!is_array($is)) {
            return null;
        }

        $this->sonSecimNedeni = self::SECIM_ALINDI;

        return $is;
    }

    /** Son `sahiplen()` çağrısının sonucu — SECIM_* sabitlerinden biri. */
    private string $sonSecimNedeni = self::SECIM_BOS;

    /**
     * Son sahiplenme denemesi neden sonuçsuz kaldı? (A7)
     *
     * Çağıran bunu turu bitirip bitirmeyeceğine karar vermek ve günlüğe
     * DOĞRU sebebi yazmak için okur.
     */
    public function sonSecimNedeni(): string
    {
        return $this->sonSecimNedeni;
    }

    /**
     * İŞİ SERBEST BIRAK (D9-KESİN) — tur yarıda kesildiğinde çağrılır.
     *
     * Kira dolmasını beklemek, işi bir cron turu boyunca görünmez kılar. Süreç
     * düzgün kapanabiliyorsa (shutdown kancası) işi HEMEN geri bırakır: bir
     * sonraki tur onu normal biçimde alır.
     *
     * `deneme` sayacı GERİ ALINMAZ: iş her seferinde yeniden bırakılıyorsa bu
     * bir arızadır ve deneme hakkı bitince ölü rafında görünmelidir. Sonsuza
     * kadar sessizce dönen bir iş, hiç çalışmayan bir işten daha kötüdür.
     *
     * @return bool token eşleşmediyse false (iş artık bizim değil)
     */
    public function birak(int $id, string $token, DateTimeImmutable $now, string $sebep): bool
    {
        $statement = $this->connection->pdo()->prepare(
            // YER TUTUCU DİSİPLİNİ (v0.11.3 dersi, `SorguYerTutucuTest` kilitler):
            // aynı ad bir deyimde İKİ KEZ geçemez — MySQL native prepare HY093
            // ile reddeder, SQLite emülasyonu gizler. Her sütun AYRI ad alır.
            'UPDATE jobs SET durum = :bekliyor, kilit_sahibi = NULL, kilit_token = NULL,
                    kilitlendi_at = NULL, kilit_bitis = NULL, hata = :hata,
                    hata_sinifi = :sinif, calisacak_at = :calisacak_at, updated_at = :guncelleme_at
             WHERE id = :id AND kilit_token = :token AND durum = :calisiyor',
        );
        $statement->execute([
            'bekliyor' => self::BEKLIYOR,
            'calisiyor' => self::CALISIYOR,
            'hata' => mb_substr($sebep, 0, 2000),
            'sinif' => HataSinifi::GECICI,
            'calisacak_at' => Dates::toStorage($now),
            'guncelleme_at' => Dates::toStorage($now),
            'id' => $id,
            'token' => $token,
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * KİRA UZATMA (İE#21 B11) — uzun süren iş devralınmasın.
     *
     * İşleyici uzun bir turda (50 ürünlük çeviri) bunu periyodik çağırır. Token
     * eşleşmezse false döner: iş ARTIK BİZİM DEĞİLDİR ve işleyici durmalıdır.
     */
    public function kalpAtisi(int $id, string $token, DateTimeImmutable $now): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE jobs SET kilit_bitis = :kira_bitis, updated_at = :simdi
             WHERE id = :id AND kilit_token = :token AND durum = :calisiyor',
        );
        $statement->execute([
            'kira_bitis' => Dates::toStorage($now->modify('+' . self::KILIT_OMRU_SANIYE . ' seconds')),
            'simdi' => Dates::toStorage($now),
            'id' => $id,
            'token' => $token,
            'calisiyor' => self::CALISIYOR,
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * İŞ BAŞARIYLA BİTTİ — TEK CAS YAZIMI (v1.2.1 A1).
     *
     * WHERE üç koşul taşır: `id` + `durum = calisiyor` + `kilit_token`.
     * Üçü birden tutmuyorsa yazım YAPILMAZ ve `KiraKaybedildi` atılır.
     *
     * ESKİDEN token OPSİYONELDİ ve boş geçilince sahiplik hiç denetlenmiyordu;
     * `durum` da denetlenmiyordu. Kirası devralınmış yavaş bir işleyici,
     * ikinci işleyicinin ÇALIŞAN işini "bitti" damgalayıp kirasını siliyordu.
     *
     * @throws KiraKaybedildi kira artık bu işleyicinin değilse
     */
    public function basarili(int $id, DateTimeImmutable $now, string $token): void
    {
        // Toparlanma bildirimi için deneme sayısı UPDATE'ten ÖNCE okunur:
        // sonrasında iş "bitti" olur ama `deneme` kolonu korunur — yine de
        // sıralamayı okumaya bırakmak, kolonun ileride sıfırlanması hâlinde
        // sessizce yanlış davranırdı.
        $onceki = $this->denemeSayisi($id);

        $statement = $this->connection->pdo()->prepare(
            'UPDATE jobs SET durum = :durum, hata = NULL, hata_sinifi = NULL, kilit_sahibi = NULL,
                    kilit_token = NULL, kilitlendi_at = NULL, kilit_bitis = NULL,
                    bitti_at = :bitti_at, updated_at = :guncelleme_at
             WHERE id = :id AND durum = :calisiyor AND kilit_token = :token',
        );
        $zaman = Dates::toStorage($now);
        $statement->execute([
            'durum' => self::BITTI,
            'bitti_at' => $zaman,
            'guncelleme_at' => $zaman,
            'id' => $id,
            'calisiyor' => self::CALISIYOR,
            'token' => $token,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new KiraKaybedildi($id, 'basarili');
        }

        // Yalnız DAHA ÖNCE BAŞARISIZ OLMUŞ iş "toparlandı" sayılır; ilk denemede
        // biten her işi duyurmak bildirim merkezini gürültüye boğardı.
        if ($onceki > 0) {
            $this->duyur('NTF-JOB-RECOVERED', [
                'is_turu' => $this->isTuru($id),
                'is_id' => $id,
                'deneme' => $onceki,
            ]);
        }
    }

    /** İşin o ana kadarki deneme sayısı. */
    private function denemeSayisi(int $id): int
    {
        $statement = $this->connection->pdo()->prepare('SELECT deneme FROM jobs WHERE id = :id');
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn();
    }

    /**
     * BAŞARISIZ İŞ — TEK CAS YAZIMI (v1.2.1 A1).
     *
     * Deneme hakkı varsa GERİ BIRAKILIR (artan bekleme), yoksa ÖLÜ RAFINA.
     * Artan bekleme bilinçlidir: geçici bir ağ hatasında hemen tekrar denemek
     * aynı hatayı alır ve deneme haklarını saniyeler içinde tüketir.
     *
     * ESKİ KOD ÖNCE OKUYUP SONRA YAZIYORDU: `SELECT kilit_token` → PHP'de
     * karşılaştır → token'sız `UPDATE`. İki ifade arasında kira devralınabilir;
     * okuma anında geçerli olan token yazma anında geçersizdir (TOCTOU). Daha
     * kötüsü ölüm yolu `oldur()`a giriyordu ve o hiçbir denetim yapmıyordu —
     * eski sahip, ikinci işleyicinin ÇALIŞAN işini ölü rafına atabiliyordu.
     *
     * Karar için okuma hâlâ var (deneme/max), ama YAZIM tek CAS'tır: karar
     * yanlış satıra dayansa bile yazım tutmaz ve istisna atılır.
     *
     * @throws KiraKaybedildi kira artık bu işleyicinin değilse
     */
    public function basarisiz(
        int $id,
        string $hata,
        DateTimeImmutable $now,
        string $sinif = HataSinifi::GECICI,
        ?int $saglayiciBeklemesi = null,
        string $token = '',
    ): void {
        $pdo = $this->connection->pdo();
        $oku = $pdo->prepare('SELECT deneme, max_deneme FROM jobs WHERE id = :id');
        $oku->execute(['id' => $id]);
        $satir = $oku->fetch();
        if (!is_array($satir)) {
            throw new KiraKaybedildi($id, 'basarisiz');
        }

        $deneme = (int) $satir['deneme'];
        $max = (int) $satir['max_deneme'];
        $hata = mb_substr($hata, 0, 2000);

        // KALICI hata TEKRAR DENENMEZ: aynı sonucu üç kez üretmek kuyruğu meşgul
        // eder ve gerçek arızayı üç kat gecikmeyle görünür kılar.
        // Deneme hakkı bitmişse de aynı yol: ölü rafı — ama SAHİPLİK DENETİMİYLE.
        if ($sinif === HataSinifi::KALICI || $deneme >= $max) {
            $this->oluRafinaYaz($id, $hata, $now, $sinif, $token);

            return;
        }

        // JITTER'LI GERİ ÇEKİLME + 429'a SAYGI (gerekçe: HataSinifi).
        $bekleme = HataSinifi::bekleme($sinif, $deneme, $saglayiciBeklemesi);
        $statement = $pdo->prepare(
            'UPDATE jobs SET durum = :durum, hata = :hata, hata_sinifi = :sinif, kilit_sahibi = NULL,
                    kilit_token = NULL, kilitlendi_at = NULL, kilit_bitis = NULL,
                    calisacak_at = :sonra, updated_at = :simdi
             WHERE id = :id AND durum = :calisiyor AND kilit_token = :token',
        );
        $statement->execute([
            'durum' => self::BEKLIYOR,
            'hata' => $hata,
            'sinif' => $sinif,
            'sonra' => Dates::toStorage($now->modify('+' . $bekleme . ' seconds')),
            'simdi' => Dates::toStorage($now),
            'id' => $id,
            'calisiyor' => self::CALISIYOR,
            'token' => $token,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new KiraKaybedildi($id, 'basarisiz');
        }

        $this->duyur('NTF-JOB-RETRY-SCHEDULED', [
            'is_turu' => $this->isTuru($id),
            'hata_kodu' => $sinif,
            'is_id' => $id,
            'bekleme_saniye' => $bekleme,
        ]);
    }

    /**
     * YÖNETİCİ ÖLDÜRME — token İSTEMEZ, iş akışından çağrılMAZ.
     *
     * Yöneticinin elinde kira token'ı yoktur; "bu iş bir daha denenmesin"
     * diyebilmelidir. Bu yüzden bu yol denetimsizdir — ve ADI bunu açıkça
     * söyler. Eskiden `oldur()` hem yönetici hem iş akışı tarafından
     * kullanılıyordu; tek denetimsiz kapı iki amaca hizmet edince, iş akışı
     * sahipliği doğrulamadan yazabiliyordu.
     */
    public function yoneticiOldur(
        int $id,
        string $hata,
        DateTimeImmutable $now,
        string $sinif = HataSinifi::KALICI,
    ): void {
        $this->oluYaz($id, $hata, $now, $sinif, null);
    }

    /**
     * İş akışı ölüm yolu — SAHİPLİK DENETİMLİ.
     *
     * @throws KiraKaybedildi kira artık bu işleyicinin değilse
     */
    private function oluRafinaYaz(
        int $id,
        string $hata,
        DateTimeImmutable $now,
        string $sinif,
        string $token,
    ): void {
        if (!$this->oluYaz($id, $hata, $now, $sinif, $token)) {
            throw new KiraKaybedildi($id, 'olu');
        }
    }

    /**
     * Ölü rafına tek UPDATE. `$token` null ise sahiplik denetlenmez (yönetici).
     *
     * @return bool yazım tuttu mu (yönetici yolunda daima true sayılır)
     */
    private function oluYaz(
        int $id,
        string $hata,
        DateTimeImmutable $now,
        string $sinif,
        ?string $token,
    ): bool {
        $kosul = $token === null ? '' : ' AND durum = :calisiyor AND kilit_token = :token';
        $statement = $this->connection->pdo()->prepare(
            'UPDATE jobs SET durum = :durum, hata = :hata, hata_sinifi = :sinif, kilit_sahibi = NULL,
                    kilit_token = NULL, kilitlendi_at = NULL, kilit_bitis = NULL,
                    bitti_at = :bitti_at, updated_at = :guncelleme_at
             WHERE id = :id' . $kosul,
        );
        $zaman = Dates::toStorage($now);
        $parametreler = [
            'durum' => self::OLU,
            'hata' => mb_substr($hata, 0, 2000),
            'sinif' => $sinif,
            'bitti_at' => $zaman,
            'guncelleme_at' => $zaman,
            'id' => $id,
        ];
        if ($token !== null) {
            $parametreler['calisiyor'] = self::CALISIYOR;
            $parametreler['token'] = $token;
        }
        $statement->execute($parametreler);

        if ($statement->rowCount() !== 1) {
            return false;
        }

        $this->duyurDenetimli('NTF-JOB-DEAD', $id, 'job_dead', $hata, $now, [
            'is_turu' => $this->isTuru($id),
            'hata_kodu' => $sinif,
            'is_id' => $id,
        ]);

        return true;
    }

    /** İşin türü — bildirim bağlamı için; iş silinmişse null. */
    private function isTuru(int $id): ?string
    {
        $statement = $this->connection->pdo()->prepare('SELECT tur FROM jobs WHERE id = :id');
        $statement->execute(['id' => $id]);
        $deger = $statement->fetchColumn();

        return is_string($deger) ? $deger : null;
    }

    /**
     * ÖLÜ MEKTUP: "VAZGEÇ" (İE#21 B11).
     *
     * Ölü işi kuyruktan SİLER. "Yeniden dene" ile birlikte panelin iki eyleminden
     * biridir; üçüncüsü olan "düzelt", yükü değiştirip yeniden kuyruğa almaktır
     * (`yukuDuzelt`). Silme yalnız ÖLÜ işlere uygulanır: bekleyen ya da çalışan
     * bir işi silmek, kuyruğu sessizce eksiltmek olurdu.
     *
     * @return bool gerçekten silindi mi (olmayan/ölü olmayan iş için false)
     */
    public function vazgec(int $id): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'DELETE FROM jobs WHERE id = :id AND durum = :durum',
        );
        $statement->execute(['id' => $id, 'durum' => self::OLU]);

        return $statement->rowCount() === 1;
    }

    /**
     * ÖLÜ MEKTUP: "DÜZELT" — yükü değiştirip yeniden kuyruğa alır.
     *
     * Bazı başarısızlıklar veri hatasındandır (yanlış ürün kimliği, eksik alan).
     * Bunlar için tek çare işi silip elle yeniden yaratmaktı; yani denetim izi
     * kopuyordu. Düzeltme aynı satırda kalır: kaç kez denendiği, ne hata aldığı
     * ve kimin düzelttiği aynı kayıtta görünür.
     *
     * @param array<string, mixed> $yeniYuk
     */
    public function yukuDuzelt(int $id, array $yeniYuk, DateTimeImmutable $now): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE jobs SET yuk = :yuk, durum = :durum, deneme = 0, hata = NULL, hata_sinifi = NULL,
                    kilit_sahibi = NULL, kilit_token = NULL, kilitlendi_at = NULL, kilit_bitis = NULL,
                    bitti_at = NULL, calisacak_at = :calisacak_at, updated_at = :guncelleme_at
             WHERE id = :id AND durum = :olu',
        );
        $zaman = Dates::toStorage($now);
        $statement->execute([
            'yuk' => json_encode($yeniYuk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'durum' => self::BEKLIYOR,
            'calisacak_at' => $zaman,
            'guncelleme_at' => $zaman,
            'id' => $id,
            'olu' => self::OLU,
        ]);

        return $statement->rowCount() === 1;
    }

    /** Ölü rafındaki işi yeniden kuyruğa alır (panel "yeniden dene"). */
    public function dirilt(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE jobs SET durum = :durum, deneme = 0, hata = NULL, hata_sinifi = NULL, kilit_sahibi = NULL,
                    kilit_token = NULL, kilitlendi_at = NULL, kilit_bitis = NULL, bitti_at = NULL,
                    calisacak_at = :calisacak_at, updated_at = :guncelleme_at
             WHERE id = :id',
        );
        $zaman = Dates::toStorage($now);
        $statement->execute([
            'durum' => self::BEKLIYOR,
            'calisacak_at' => $zaman,
            'guncelleme_at' => $zaman,
            'id' => $id,
        ]);

        $this->duyurDenetimli('NTF-JOB-REPLAYED', $id, 'job_replayed', 'ölü iş yeniden çalıştırıldı', $now, [
            'is_turu' => $this->isTuru($id),
            'is_id' => $id,
        ]);
    }

    /**
     * Kuyruk sağlığı — panel "Sistem durumu" ekranının veri kaynağı.
     *
     * @return array{
     *     bekleyen: int, calisan: int, olu: int, en_eski_bekleyen_dakika: int|null,
     *     alinabilir: int, ileri_tarihli: int, en_yakin_calisacak_dakika: int|null,
     *     turler: array<string, int>, saatlik_biten: int, saatlik_olen: int,
     *     hata_orani_yuzde: int, yeniden_denenen: int
     * }
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

        // ── B11 METRİKLERİ ───────────────────────────────────────────────────
        // "Kuyruk çalışıyor mu?" sorusunun cevabı sayılarla verilir. Üçü de
        // SON 1 SAATE bakar: gün boyu ortalaması, yarım saattir devam eden bir
        // arızayı gizler.
        $birSaatOnce = Dates::toStorage($now->modify('-1 hour'));

        $sonSaat = static function (string $durum) use ($pdo, $birSaatOnce): int {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM jobs WHERE durum = :durum AND bitti_at IS NOT NULL AND bitti_at >= :esik',
            );
            $statement->execute(['durum' => $durum, 'esik' => $birSaatOnce]);

            return (int) $statement->fetchColumn();
        };

        $bitenSaatlik = $sonSaat(self::BITTI);
        $olenSaatlik = $sonSaat(self::OLU);
        $toplamSaatlik = $bitenSaatlik + $olenSaatlik;

        // Yeniden denemeye düşmüş (hatalı ama hâlâ hayatta) işler de hata oranına
        // girer: yalnız ölenlere bakmak, üç kez patlayıp dördüncüde tutan bir
        // sağlayıcıyı "sağlıklı" gösterirdi.
        $bekleyenHatali = $pdo->prepare(
            'SELECT COUNT(*) FROM jobs WHERE durum = :durum AND hata IS NOT NULL',
        );
        $bekleyenHatali->execute(['durum' => self::BEKLIYOR]);

        $ileri = $this->ileriTarihliler($now);

        return [
            'bekleyen' => $say(self::BEKLIYOR),
            'calisan' => $say(self::CALISIYOR),
            'olu' => $say(self::OLU),
            'en_eski_bekleyen_dakika' => $enEski,
            // D9: "bekleyen" ile "alınabilir" AYRI sayılardır. Eşit değillerse
            // işçi o işleri göremiyor demektir; panel bunu susarak geçmez.
            'alinabilir' => $this->alinabilirSayisi($now),
            'ileri_tarihli' => $ileri['sayi'],
            'en_yakin_calisacak_dakika' => $ileri['en_yakin_dakika'],
            'turler' => $turler,
            // B11 metrikleri — Ayarlar > Kuyruk durumu bunları basar.
            'saatlik_biten' => $bitenSaatlik,
            'saatlik_olen' => $olenSaatlik,
            'hata_orani_yuzde' => $toplamSaatlik > 0
                ? (int) round($olenSaatlik / $toplamSaatlik * 100)
                : 0,
            'yeniden_denenen' => (int) $bekleyenHatali->fetchColumn(),
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
            // İE#21 B11: `yuk` ve `hata_sinifi` da döner — panel "Düzelt" eylemi
            // mevcut yükü göstermeden düzeltme isteyemez, kullanıcı neyi
            // değiştireceğini bilemezdi.
            'SELECT id, tur, anahtar, yuk, hata, hata_sinifi, deneme, updated_at FROM jobs
             WHERE durum = :durum ORDER BY updated_at DESC LIMIT :limit',
        );
        $statement->bindValue('durum', self::OLU);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> */
        return $statement->fetchAll() ?: [];
    }
}
