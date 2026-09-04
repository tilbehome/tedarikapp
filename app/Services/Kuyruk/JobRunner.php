<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use App\Core\Clock;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * KUYRUK İŞLEYİCİSİ (İE#20 C3) — cron tetikli, süre sınırlı.
 *
 * Paylaşımlı hostingde arka plan süreci yoktur; işleyici cron'dan koşar ve
 * KENDİ SÜRESİNİ KOLLAR. `max_execution_time` tarafından ortadan kesilmek,
 * işi "çalışıyor" durumunda bırakır (kilit zaman aşımıyla kurtarılır ama
 * bir tur boşa gider). Bu yüzden koşum, süre veya iş sayısı sınırına gelince
 * KENDİ İSTEĞİYLE durur — kesilmeyi beklemez.
 *
 * İşleyici, iş türlerini BİLMEZ: her tür kendi işleyicisini kaydeder
 * (`kaydet('ceviri', fn)`). Böylece yeni bir iş türü eklemek bu sınıfa
 * dokunmadan yapılır.
 */
final class JobRunner
{
    /** @var array<string, callable(array<string, mixed>, array<string, mixed>, IsBaglami): void> */
    private array $isleyiciler = [];

    public function __construct(
        private readonly JobQueue $kuyruk,
        private readonly LoggerInterface $logger,
        /** Koşum bütçesi (saniye): cron aralığından KISA olmalı. */
        private readonly int $sureSiniri = 50,
        private readonly int $isSiniri = 25,
        /**
         * v1.2.1 A3 — ZAMAN KAYNAĞI. Tur 50 saniye sürebilir; tek bir `$now`
         * bütün tura yayılırsa 48. saniyede alınan işin kirası 48 saniyesi
         * yanmış hâlde başlar ve iş KOŞARKEN devralınabilir. Saat her durum
         * geçişinde YENİDEN okunur. Verilmezse sistem saati.
         */
        private readonly ?Clock $saat = null,
    ) {
    }

    /**
     * O anki zaman — HER DURUM GEÇİŞİNDE yeniden okunur (A3).
     *
     * `$dondurulmus`: çağıran `kos()`a açık bir `$now` verdiyse ve saat
     * enjekte edilmemişse eski davranış korunur (donmuş zaman). Saat varsa
     * DAİMA saat kazanır — üretimde zaman gerçekten akar.
     */
    private function simdi(?DateTimeImmutable $dondurulmus = null): DateTimeImmutable
    {
        return $this->saat?->now() ?? $dondurulmus ?? new DateTimeImmutable();
    }

    /**
     * Uzun süren bir işin kirasını uzatır (İE#21 B11).
     *
     * İşleyiciler bunu iş satırıyla birlikte çağırır: `$kosucu->kalpAtisi($is, $now)`.
     * false dönerse iş ARTIK BİZİM DEĞİLDİR (kira devralınmış) ve işleyici durmalıdır.
     *
     * @param array<string, mixed> $is
     */
    public function kalpAtisi(array $is, DateTimeImmutable $now): bool
    {
        $token = is_string($is['kilit_token'] ?? null) ? (string) $is['kilit_token'] : '';

        return $token !== '' && $this->kuyruk->kalpAtisi((int) $is['id'], $token, $now);
    }

    /**
     * @param callable(array<string, mixed>, array<string, mixed>, IsBaglami): void $isleyici
     *        (yük, iş satırı, iş bağlamı) alır; hata fırlatırsa iş başarısız sayılır.
     *        Üçüncü parametre A2 ile geldi ve OPSİYONEL BİLDİRİLEBİLİR: PHP,
     *        daha az parametre bildiren kapanışlara fazladan argümanı sorunsuz
     *        geçirir; eski işleyiciler değişmeden çalışır.
     */
    public function kaydet(string $tur, callable $isleyici): void
    {
        $this->isleyiciler[$tur] = $isleyici;
    }

    /**
     * ŞU AN ELDE TUTULAN İŞ (D9-KESİN) — tur yarıda kesilirse serbest bırakmak için.
     *
     * `bin/kuyruk.php` bunu shutdown kancasında okur: süreç ölürken iş kirasıyla
     * birlikte asılı kalmasın, bir SONRAKİ TUR onu alabilsin.
     *
     * @return array{id: int, token: string}|null
     */
    public function askidakiIs(): ?array
    {
        return $this->askidaki;
    }

    /** @var array{id: int, token: string}|null */
    private ?array $askidaki = null;

    /**
     * İŞLEYİCİ KİMLİĞİ — süreç fonksiyonlarına GÜVENMEDEN üretilir (D7, 25 Ağu 2026).
     *
     * SAHA BULGUSU: MegaTR paylaşımlı hostingde `ea-php83` CLI'da `disable_functions`
     * `getmypid`'i kapatmış; `bin/kuyruk.php` her turda ölümcül hata veriyor ve kuyruk
     * cron'dan HİÇ işlemiyordu. Aynı risk `posix_getpid` ve `gethostname` için de
     * geçerlidir — paylaşımlı hostingde hangisinin açık olduğu VARSAYILAMAZ.
     *
     * Kimlikten beklenen tek şey KİRA SAHİPLİĞİNDE BENZERSİZLİKTİR: hangi sürecin işi
     * sahiplendiğini ayırt etmek. Gerçek PID olması şart değildir; bu yüzden süreç
     * fonksiyonu yoksa kriptografik rastgele bir ek kullanılır. Sıra bilinçlidir:
     * varsa PID okunur (aynı sürecin iki turu aynı kimliği kullansın, log okunur
     * kalsın), yoksa rastgeleye düşülür.
     */
    public static function surecKimligi(): string
    {
        $makine = function_exists('gethostname') ? (gethostname() ?: '') : '';
        if ($makine === '') {
            $makine = 'cron';
        }

        if (function_exists('getmypid')) {
            $pid = getmypid();
            if (is_int($pid) && $pid > 0) {
                return $makine . ':' . $pid;
            }
        }

        if (function_exists('posix_getpid')) {
            /** @var callable(): int $posix */
            $posix = 'posix_getpid';
            $pid = $posix();
            if ($pid > 0) {
                return $makine . ':' . $pid;
            }
        }

        // Süreç kimliği alınamıyor: benzersizlik rastgelelikten gelir. Kimlik
        // "pid yok" olduğunu SÖYLER — log okuyan kişi yanlış PID aramasın.
        return $makine . ':x' . bin2hex(random_bytes(8));
    }

    /**
     * İŞ ALINAMADIĞINDA SEBEP (D9).
     *
     * Kuyruk gerçekten boş olabilir; bekleyen iş olup zamanı gelmemiş de
     * olabilir. İkisi çok farklı durumlardır: ilki normal, ikincisi (saat
     * kayması ya da ileri tarihli yazım) bir arızadır ve sessiz kalırsa
     * kuyruk saatlerce çalışmaz.
     */
    private function neden(DateTimeImmutable $now): string
    {
        $saglik = $this->kuyruk->saglik($now);
        $bekleyen = (int) $saglik['bekleyen'];
        if ($bekleyen === 0) {
            return 'kuyruk boş';
        }

        $ileri = (int) $saglik['ileri_tarihli'];
        $dakika = $saglik['en_yakin_calisacak_dakika'];

        return sprintf(
            'ALINAMADI: %d iş bekliyor, %d tanesi ileri tarihli%s — işçi saati: %s',
            $bekleyen,
            $ileri,
            is_int($dakika) ? ' (en yakın ' . $dakika . ' dk sonra)' : '',
            $now->format('Y-m-d H:i:s P'),
        );
    }

    /**
     * Bir cron turu koşar.
     *
     * @return array{islenen: int, basarili: int, basarisiz: int, sure: float, durma_nedeni: string}
     */
    public function kos(?DateTimeImmutable $now = null, ?string $isleyiciKimligi = null, ?int $sureSiniri = null): array
    {
        // A3: `$now` GERİYE DÖNÜK UYUM İÇİNDİR. Saat enjekte edilmişse turun
        // her adımı KENDİ anını okur; edilmemişse verilen an dondurulmuş
        // sayılır ve eski davranış aynen sürer.
        $dondurulmus = $now;
        // D12: SÜRE BÜTÇESİ ÇAĞRIYA GÖRE DEĞİŞİR. Cron turu cömerttir (50 sn);
        // panel ziyaretinde tetiklenen tur, bağlantı kapatılamıyorsa kullanıcıyı
        // bekletmemek için saniyelerle sınırlıdır. İş sınırı ve kira mekanizması
        // AYNEN korunur — değişen yalnız turun ne kadar süreceğidir.
        $sure = $sureSiniri !== null ? max(1, $sureSiniri) : $this->sureSiniri;
        $kimlik = $isleyiciKimligi ?? self::surecKimligi();
        $baslangic = microtime(true);
        $islenen = 0;
        $basarili = 0;
        $basarisiz = 0;
        $durmaNedeni = 'kuyruk boş';

        while (true) {
            if ($islenen >= $this->isSiniri) {
                $durmaNedeni = 'iş sınırı (' . $this->isSiniri . ')';

                break;
            }
            if ((microtime(true) - $baslangic) >= $sure) {
                $durmaNedeni = 'süre sınırı (' . $sure . ' sn)';

                break;
            }

            // Her tur adımı KENDİ anını okur: kira, alınma anından başlar.
            $now = $this->simdi($dondurulmus);
            $is = $this->kuyruk->sahiplen($kimlik, $now);
            if ($is === null) {
                // D9: "kuyruk boş" YALNIZ gerçekten boşken söylenir. Sahada
                // panel "5 bekleyen" derken günlük her turda "kuyruk boş"
                // yazıyordu ve çelişkiyi kimse fark etmiyordu; işçi artık
                // neden hiçbir şey almadığını SÖYLER.
                //
                // v1.2.1 A7: ÇEKİŞME AYRI BİR SEBEPTİR. İş vardı ama başka bir
                // işleyici kaptı — bunu "boş" diye raporlamak, iki işleyicinin
                // birbirini yavaşlattığını görünmez kılardı.
                $durmaNedeni = $this->kuyruk->sonSecimNedeni() === JobQueue::SECIM_YARIS
                    ? 'çekişme: adaylar başka işleyicilerce alındı (kuyruk BOŞ değil)'
                    : $this->neden($now);

                break;
            }

            $islenen++;
            $tur = (string) $is['tur'];
            $isleyici = $this->isleyiciler[$tur] ?? null;

            // B11: sahiplenmede verilen kira token'ı sonuç yazarken KANIT olur.
            // Kirası dolup devralınan işin eski sahibi buraya geldiğinde token'ı
            // eşleşmez ve sonucu yazamaz — çift koşan işin sonuçları birbirini ezmez.
            $token = is_string($is['kilit_token'] ?? null) ? (string) $is['kilit_token'] : '';

            if ($isleyici === null) {
                // Tanınmayan tür: sessizce tekrar denemek sonsuz döngüdür.
                // Ölü rafına gönderilir ki panelde GÖRÜNSÜN — ama v1.2.1 A1
                // gereği SAHİPLİK DENETİMLİ yoldan: kirayı biz aldık, yazımı da
                // kiramızla yaparız. `yoneticiOldur()` iş akışından çağrılmaz.
                $this->sonucYaz((int) $is['id'], function () use ($is, $tur, $now, $token): void {
                    $this->kuyruk->basarisiz(
                        (int) $is['id'],
                        'Tanınmayan iş türü: ' . $tur,
                        $now,
                        HataSinifi::KALICI,
                        null,
                        $token,
                    );
                });
                $this->askidaki = null;
                $basarisiz++;
                $this->logger->warning('Kuyrukta tanınmayan iş türü', ['tur' => $tur, 'id' => (int) $is['id']]);

                continue;
            }

            /** @var array<string, mixed> $yuk */
            $yuk = is_string($is['yuk'] ?? null) ? (json_decode((string) $is['yuk'], true) ?: []) : [];
            // D9-KESİN: elimizdeki iş kayda geçer; süreç burada ölürse shutdown
            // kancası onu serbest bırakır.
            $this->askidaki = ['id' => (int) $is['id'], 'token' => $token];

            try {
                // A2: işleyici artık kirasını uzatabilir ve kaybını GÖREBİLİR.
                // Üçüncü parametre geriye dönük uyumludur — daha az parametre
                // bildiren eski işleyiciler fazladan argümanı görmezden gelir.
                $isleyici($yuk, $is, new IsBaglami($this->kuyruk, (int) $is['id'], $token));
                $bitisAni = $this->simdi($dondurulmus);
                $yazildi = $this->sonucYaz((int) $is['id'], function () use ($is, $bitisAni, $token): void {
                    $this->kuyruk->basarili((int) $is['id'], $bitisAni, $token);
                });
                $this->askidaki = null;
                // KİRA KAYBEDİLDİYSE BAŞARI SAYILMAZ: iş başka bir işleyicide
                // hâlâ çalışıyor; "bitti" saymak turun raporunu yalancı yapar.
                if ($yazildi) {
                    $basarili++;
                } else {
                    $basarisiz++;
                }
            } catch (Throwable $hata) {
                // B11: hata SINIFLANDIRILIR — kalıcı hata tekrar denenmez, hız
                // sınırında sağlayıcının istediği süre beklenir, geçici hatada
                // jitter'lı geri çekilme uygulanır (gerekçe: HataSinifi).
                ['sinif' => $sinif, 'bekleme' => $saglayiciBeklemesi] = HataSinifi::siniflandir($hata);

                $hataAni = $this->simdi($dondurulmus);
                $this->sonucYaz((int) $is['id'], function () use ($is, $hata, $hataAni, $sinif, $saglayiciBeklemesi, $token): void {
                    $this->kuyruk->basarisiz(
                        (int) $is['id'],
                        $hata->getMessage(),
                        $hataAni,
                        $sinif,
                        $saglayiciBeklemesi,
                        $token,
                    );
                });
                // İE22-D9-1 (rc6 denetim bulgusu): `basarisiz()` token'ı SİLER.
                // Askıdaki kaydı temizlemezsek ve tur bu işle biterse, shutdown
                // kancası eski token'la `birak()` çağırır; eşleşmez ve günlüğe
                // "YARIDA KESİLDİ: #N bırakılamadı (kira devralınmış olabilir)"
                // düşer. İş aslında doğru işlenmiştir — yalnız operatör olmayan
                // bir kira devralması arar. Yanlış teşhis, teşhissizlikten kötüdür.
                $this->askidaki = null;
                $basarisiz++;
                $this->logger->error('Kuyruk işi başarısız', [
                    'tur' => $tur,
                    'id' => (int) $is['id'],
                    'sinif' => $sinif,
                    'hata' => $hata->getMessage(),
                ]);
            }
        }

        return [
            'islenen' => $islenen,
            'basarili' => $basarili,
            'basarisiz' => $basarisiz,
            'sure' => round(microtime(true) - $baslangic, 2),
            'durma_nedeni' => $durmaNedeni,
        ];
    }

    /**
     * Sonuç yazımını KİRA KAYBI'na karşı sarar (v1.2.1 A1).
     *
     * `KiraKaybedildi` bir program hatası DEĞİLDİR: kira zaman aşımına uğramış,
     * iş başkasına geçmiştir; bu beklenen bir yarıştır. Turu düşürmemeli ama
     * SESSİZ de kalmamalı — sessiz kalırsa iki işleyicinin aynı işi koştuğu
     * hiçbir yerde görünmez ve kira süresinin yanlış ayarlandığı anlaşılmaz.
     *
     * AYRICA BU SARMALAYICI ŞART: `basarisiz()` çağrısı `catch` bloğunun
     * İÇİNDEDİR; oradan çıkan bir istisna turun tamamını düşürürdü.
     *
     * Başka her istisna yukarı verilir — onlar gerçekten arızadır.
     *
     * @param  callable(): void $yazim
     * @return bool yazım tuttu mu
     */
    private function sonucYaz(int $isId, callable $yazim): bool
    {
        try {
            $yazim();

            return true;
        } catch (KiraKaybedildi $kayip) {
            $this->logger->warning('Kuyruk sonucu yazılamadı: kira kaybedildi', [
                'id' => $isId,
                'yazilmak_istenen' => $kayip->yazilmakIstenen,
            ]);

            return false;
        }
    }
}
