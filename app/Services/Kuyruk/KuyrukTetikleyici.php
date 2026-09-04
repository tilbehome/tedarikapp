<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use App\Core\Clock;
use App\Models\SettingsRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * KUYRUK TETİKLEYİCİSİ (D12) — CRON OLMADAN DA İŞLER AKAR.
 *
 * SAHA GERÇEĞİ (28 Ağu): kuyruk cron'u iki kez kurulmadı; işler 1432 dakika
 * bekledi. Ürün Sahibi kararı kesin: kullanıcı HİÇBİR cron kurmadan sistem
 * uçtan uca çalışacak, kuyruk/cron kavramı kullanıcıya görünmeyecek.
 *
 * ÇÖZÜM TEK BİR İŞÇİ DEĞİL, ÇOK TETİKLEYİCİDİR:
 *   · panel ziyareti  — oturumlu her girişte (bu sınıf),
 *   · yakalama sonrası — fırsatçı tur (bu sınıf),
 *   · cron            — varsa fazlalık, yoksa eksik değil.
 * Üçü de AYNI `JobRunner`ı çağırır; claim/kira mekanizması aynen korunur
 * (B11) — yani iki tetikleyici aynı anda koşsa bile bir iş iki kez işlenmez.
 *
 * KULLANICI BEKLETİLMEZ: yanıt önce gönderilir (`fastcgi_finish_request` ya da
 * LiteSpeed karşılığı), tur ondan sonra koşar. Bu işlevler yoksa tur çok kısa
 * bir bütçeyle koşar — kullanıcı gecikmeyi hissetmesin diye saniyeler değil,
 * bir-iki iş kadar.
 *
 * ÜSTÜSTE BİNME KORUMASI: aynı anda birden çok istek tur açmasın diye ayarlarda
 * bir zaman damgası tutulur. Bu bir KİLİT DEĞİL, gürültü kesicidir; gerçek
 * kilit her zaman `JobQueue::sahiplen()`in kira token'ıdır.
 */
final class KuyrukTetikleyici
{
    /** Ayar anahtarı: son turun başlangıç zamanı (ISO-8601). */
    public const KEY_SON_TUR = 'kuyruk_son_tur';

    /** Ayar anahtarı: son turun SONUÇ özeti — kuyruk kartı bunu okur. */
    public const KEY_SON_TUR_OZET = 'kuyruk_son_tur_ozet';

    /** İki tur arasındaki en kısa süre (sn): panelde onlarca istek tur açmasın. */
    private const SOGUMA_SANIYE = 20;

    public function __construct(
        private readonly JobRunner $kosucu,
        private readonly SettingsRepository $ayarlar,
        private readonly Clock $saat,
        private readonly LoggerInterface $kayitci,
        // V3-B A3: eşik türevli bildirimler (kuyruk durgunluğu, kur sapması)
        // cron'a değil bu tura takılır — K86: kullanıcı cron kurmaz.
        private readonly ?\App\Services\Bildirim\EsikSupurmesi $supurme = null,
    ) {
    }

    /**
     * Yanıt gönderildikten SONRA bir tur dener.
     *
     * @param bool $zorla soğuma süresini yok say (toplu çeviri gibi açık istekler)
     */
    public function yanittanSonraDene(bool $zorla = false): void
    {
        $now = $this->saat->now();
        if (!$zorla && !$this->soguduMu($now)) {
            return;
        }

        $this->turuIsaretle($now);

        // TUR KAPANIŞ KANCASINDA KOŞAR — ÖNCE değil.
        //
        // Bu çağrı ara katmandan gelir; yanıt henüz gönderilmemiştir. Bağlantıyı
        // burada kapatmak, gövdesi yazılmamış bir yanıtı kesmek olurdu. Kanca
        // ise Slim yanıtı yazdıktan sonra çalışır: orada kapatmak güvenlidir.
        register_shutdown_function(function () use ($now): void {
            // Bağlantı kapatılabiliyorsa kullanıcı BEKLEMEZ: yanıt gitmiştir,
            // tur arkada koşar. Kapatılamıyorsa (mod_php) tur yine koşar ama çok
            // kısa bütçeyle — sessizce atlamak, cron'suz kurulumda hiçbir şeyin
            // işlenmemesi demekti.
            $kapatildi = $this->baglantiyiKapat();
            $this->turuKos($now, $kapatildi);
        });
    }

    /**
     * Turu ŞİMDİ koşar (kuyruk işçisi ve testler için); sonucu döndürür.
     *
     * @return array{islenen: int, basarili: int, basarisiz: int, sure: float, durma_nedeni: string}
     */
    public function simdiKos(DateTimeImmutable $now, ?int $sureSiniri = null): array
    {
        return $this->kosucu->kos($now, null, $sureSiniri);
    }

    private function turuKos(DateTimeImmutable $now, bool $baglantiKapali): void
    {
        try {
            // Bağlantı kapalıysa cömert bütçe (kullanıcı beklemiyor), açıksa
            // sıkı bütçe (yanıt gecikmesin).
            $ozet = $this->kosucu->kos($now, null, $baglantiKapali ? null : 3);
            $this->ayarlar->set(self::KEY_SON_TUR_OZET, json_encode([
                'zaman' => $now->format(DATE_ATOM),
                'islenen' => $ozet['islenen'],
                'basarili' => $ozet['basarili'],
                'basarisiz' => $ozet['basarisiz'],
                'ertelenen' => $ozet['ertelenen'],
                'bellek_zirve_mb' => $ozet['bellek_zirve_mb'],
                'neden' => $ozet['durma_nedeni'],
                'arkaplan' => $baglantiKapali,
            ], JSON_UNESCAPED_UNICODE) ?: '');

            // Tur bitti; eşikleri de tara. Kendi aralığı vardır, her turda
            // koşmaz — bu çağrı yalnız "fırsat" sağlar.
            $this->supurme?->supur();
        } catch (Throwable $hata) {
            // Tetikleyici hatası KULLANICIYA YANSIMAZ: yanıt zaten gitti ve
            // madde 3 (panel ziyareti) bir sonraki turda yeniden dener.
            $this->kayitci->warning('Kuyruk tetikleyici turu başarısız', ['hata' => $hata->getMessage()]);
        }
    }

    /** Son turun üstünden yeterli süre geçti mi? */
    private function soguduMu(DateTimeImmutable $now): bool
    {
        $son = $this->ayarlar->get(self::KEY_SON_TUR);
        if (!is_string($son) || $son === '') {
            return true;
        }

        try {
            $sonZaman = new DateTimeImmutable($son);
        } catch (Throwable) {
            return true;
        }

        return ($now->getTimestamp() - $sonZaman->getTimestamp()) >= self::SOGUMA_SANIYE;
    }

    private function turuIsaretle(DateTimeImmutable $now): void
    {
        try {
            $this->ayarlar->set(self::KEY_SON_TUR, $now->format(DATE_ATOM));
        } catch (Throwable) {
            // Ayar yazılamıyorsa tur yine koşar; yalnız soğuma korumasız kalır.
        }
    }

    /**
     * Yanıtı kullanıcıya gönderip bağlantıyı kapatmayı dener.
     *
     * PHP-FPM'de `fastcgi_finish_request`, LiteSpeed'de `litespeed_finish_request`
     * vardır; mod_php'de ikisi de yoktur ve güvenilir bir karşılığı da yoktur
     * (çıktı tamponunu boşaltmak bağlantıyı KAPATMAZ). Böyle bir sunucuda
     * kullanıcıyı bekletmemek için tur bütçesi kısılır.
     */
    private function baglantiyiKapat(): bool
    {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();

            return true;
        }
        if (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();

            return true;
        }

        return false;
    }
}
