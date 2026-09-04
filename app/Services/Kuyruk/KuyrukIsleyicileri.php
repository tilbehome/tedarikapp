<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Connection;
use App\Core\Encrypter;
use App\Core\SystemClock;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use App\Models\TranslationCacheRepository;
use App\Services\CurlMediaFetcher;
use App\Services\Ilan\SkorHesaplayici;
use App\Services\MediaMigrator;
use App\Services\MediaService;
use App\Services\Translation\CeviriAyarlari;
use App\Services\Translation\Glossary;
use App\Services\Translation\LayeredTranslator;
use App\Services\Translation\LlmIstemci;
use App\Services\Translation\LlmTranslator;
use App\Services\Translation\MyMemoryTranslator;
use App\Services\Translation\SozlukFabrikasi;
use App\Services\Translation\TranslationService;
use App\Services\UrlGuard;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * İŞ TÜRLERİNİN KAYDI (İE#20 C3/C4/C6).
 *
 * Kuyruk işleyicisi (`JobRunner`) iş türlerini BİLMEZ; burada bağlanırlar.
 * Böylece yeni bir arka plan işi eklemek, koşucuya dokunmadan bu dosyaya bir
 * `kaydet()` satırı eklemektir.
 *
 * Kompozisyon burada yapılır (CLI bağlamı) çünkü kuyruk cron'dan koşar ve
 * AppBuilder'ın HTTP bağlamına ihtiyacı yoktur.
 */
final class KuyrukIsleyicileri
{
    public const TUR_CEVIRI = 'ceviri';
    public const TUR_SKOR = 'skor';
    /** D11a: yakalanan galeri görsellerini arşive indirir. */
    public const TUR_MEDYA = 'medya';

    public static function kaydet(
        JobRunner $kosucu,
        Config $config,
        Connection $connection,
        LoggerInterface $logger,
        string $basePath,
        ?Clock $clock = null,
        // V3-B A3: çeviri işi bitince/patlayınca bildirim doğar.
        ?\App\Services\Bildirim\BildirimYayinci $bildirim = null,
    ): void {
        $clock ??= SystemClock::fromConfig($config);
        $urunler = new ProductRepository($connection);
        $ayarlarDeposu = new SettingsRepository($connection);

        // ── ÇEVİRİ (C4) ──────────────────────────────────────────────────────
        $kosucu->kaydet(self::TUR_CEVIRI, static function (array $yuk) use (
            $config,
            $connection,
            $logger,
            $basePath,
            $clock,
            $urunler,
            $ayarlarDeposu,
            $bildirim,
        ): void {
            $urunId = (int) ($yuk['urun_id'] ?? 0);
            if ($urunId <= 0) {
                throw new RuntimeException('Çeviri işi ürün kimliği taşımıyor.');
            }

            $urun = $urunler->find($urunId);
            if ($urun === null) {
                // Ürün silinmiş: tekrar denemek düzeltmez.
                throw new RuntimeException('Ürün bulunamadı (silinmiş olabilir): #' . $urunId);
            }

            // v1.2.1 A6: sözlük TEK FABRİKADAN. Burada elle kurulmuştu ve
            // `config`/`storage` eklerini kaçırıyordu: kuyrukla çevrilen her
            // ürün BOŞ sözlükle çevriliyordu, üstelik sessizce.
            $glossary = SozlukFabrikasi::kur($basePath);
            $makine = new TranslationService(
                new TranslationCacheRepository($connection),
                new MyMemoryTranslator(
                    new UrlGuard(array_map('trim', explode(',', $config->get('TRANSLATE_ALLOWED_HOSTS', 'api.mymemory.translated.net')))),
                    $config->getPositiveInt('TRANSLATE_TIMEOUT', 5),
                ),
                $clock,
                $logger,
                $config->get('TRANSLATE_ENABLED', '1') !== '0',
                'zh',
                'tr',
                $glossary,
            );

            $cevirmen = new LlmTranslator(
                $glossary,
                new CeviriAyarlari($ayarlarDeposu, new Encrypter($config)),
                new LlmIstemci($config->getPositiveInt('TRANSLATE_LLM_TIMEOUT', 45)),
                new TranslationCacheRepository($connection),
                $clock,
                $logger,
                new LayeredTranslator($glossary, $makine),
            );

            // K54: sonuç ÖNERİDİR. Kuyruk işi de ürün alanlarına YAZMAZ; yalnız
            // önbelleği doldurur, böylece panel/belge anında ve AĞSIZ okur (K61).
            //
            // İE#21 B9 — DEĞERLER DE SORULUR. Burası `attributes: []` gönderiyordu:
            // yani LLM'e yalnız ürün ADI soruluyor, marka/renk/malzeme/varyasyon
            // hiç çevrilmiyordu. Sayfada ham Çince kalmasının kök nedeni buydu.
            $degerler = \App\Services\Translation\CevrilecekDegerler::topla($urun);

            $kaynakDil = Glossary::detect((string) ($urun['name_original'] ?? $urun['name']));

            try {
                $cevirmen->translateProduct([
                    // D12: kuyruk yolu da ORİJİNAL metni çevirir. `name` alanı çoğu
                    // kayıtta makine çevirisi bir Türkçe addır ve kalıcılık ölçütü
                    // `name_original` üzerinden bakar; ekrandaki adı göndermek,
                    // üretilen satırın hiç aranmayan bir anahtara yazılması demekti.
                    // İki yol (senkron ve kuyruk) aynı metni çevirir — tek kaynak.
                    'name' => (string) ($urun['name_original'] ?? $urun['name']),
                    'category' => null,
                    'source_lang' => $kaynakDil,
                    'attributes' => $degerler,
                ]);
            } catch (\Throwable $hata) {
                // Bildirim önce yazılır, istisna SONRA yukarı verilir: kuyruk
                // yeniden deneme/ölüm kararını kendi verir, biz yalnız haber
                // veririz. Sırayı ters çevirmek, ölen işin hiç duyulmaması
                // demekti — D12'nin "sessiz başarısızlık" dersi.
                $bildirim?->guvenliYayimla('NTF-TRANSLATION-JOB-FAILED', [
                    'dil' => $kaynakDil,
                    'hata_kodu' => \App\Services\Kuyruk\HataSinifi::siniflandir($hata)['sinif'],
                    'urun_id' => $urunId,
                    'urun_adi' => (string) $urun['name'],
                ]);

                throw $hata;
            }

            $bildirim?->guvenliYayimla('NTF-TRANSLATION-BATCH-COMPLETE', [
                'dil' => $kaynakDil,
                'urun_id' => $urunId,
                'urun_adi' => (string) $urun['name'],
                'is_turu' => self::TUR_CEVIRI,
            ]);
        });

        // ── SKOR (C6) ────────────────────────────────────────────────────────
        $kosucu->kaydet(self::TUR_SKOR, static function (array $yuk) use ($connection, $ayarlarDeposu, $clock): void {
            $urunId = (int) ($yuk['urun_id'] ?? 0);
            if ($urunId <= 0) {
                throw new RuntimeException('Skor işi ürün kimliği taşımıyor.');
            }

            (new SkorHesaplayici($connection, $ayarlarDeposu))->hesaplaVeYaz($urunId, $clock->now());
        });

        // ── MEDYA (D11a) ─────────────────────────────────────────────────────
        //
        // SAHA BULGUSU (25 Ağu 2026): çekmece "5 görsel" diyor ama dördü BOŞ
        // kare çıkıyordu. Sebep: yakalamada yalnız ANA GÖRSEL indiriliyor,
        // galeri satırları `storage_mode='remote'` olarak alicdn adresiyle
        // kalıyordu. alicdn Referer ACL'i yüzünden tarayıcı o adresleri
        // çizemiyor (MediaService'in kendi yorumunda yazılı olan sebep).
        //
        // Taşıma hattı (`MediaMigrator`) zaten vardı ama YALNIZ ELLE koşulan bir
        // CLI'dan çağrılıyordu; yani pratikte hiç koşmuyordu. Artık her yakalama
        // bir medya işi yazar ve galeri kendiliğinden yerele iner.
        //
        // Neden kuyrukta: 20 görsel indirmek yakalamayı dakikalarca bekletirdi;
        // eklenti "gönderildi" diyemezdi. Kuyruk bunu arka planda yapar.
        $kosucu->kaydet(self::TUR_MEDYA, static function (
            array $yuk,
            array $is,
            ?IsBaglami $baglam = null,
        ) use ($config, $connection, $basePath, $clock): void {
            $urunId = (int) ($yuk['urun_id'] ?? 0);
            if ($urunId <= 0) {
                throw new RuntimeException('Medya işi ürün kimliği taşımıyor.');
            }

            $urlGuard = new UrlGuard(array_map(
                'trim',
                explode(',', $config->get('MEDIA_ALLOWED_HOSTS', '')),
            ));
            $medya = new MediaService(
                $basePath,
                $urlGuard,
                new CurlMediaFetcher($urlGuard, $config->getPositiveInt('MEDIA_DOWNLOAD_TIMEOUT', 25)),
                new SettingsRepository($connection),
                $config->getPositiveInt('MEDIA_MAX_MB', 8) * 1024 * 1024,
                $config->get('MEDIA_PATH', 'public/media'),
            );

            // Arşiv modu kapalıysa (klasör yazılamıyor) iş BAŞARISIZ sayılmaz:
            // indirme mümkün değildir, tekrar denemek de düzeltmez. Kayıtlar
            // remote kalır ve arayüz bunu "uzak görsel" olarak işaretler.
            if ($medya->mode() !== MediaService::MODE_DOWNLOAD) {
                return;
            }

            // A2: HER GÖRSELDEN ÖNCE VE SONRA KALP ATIŞI. On görselli bir ürün
            // (görsel başına 25 sn zaman aşımı) 300 sn'lik kirayı aşabilir; kira
            // dolarsa iş devralınır ve İKİ işleyici aynı dosyaları indirir.
            // Kira kaybedilirse `kontrolNoktasi()` istisna atar ve indirme
            // döngüsü ORADA durur — yan etki sahiplik kaybından sonra sürmez.
            //
            // A4: eksik görsel kalırsa `urununMedyasi()` MedyaEksik atar; iş
            // BİTMİŞ sayılmaz. Geçici hatada kuyruk yeniden dener ve ikinci tur
            // yalnız eksikleri indirir (inenler artık `local`).
            // D6: İŞÇİ BAŞINA BELLEK BÜTÇESİ. Bütçe dolunca kalan görseller sonraki
            // tura kalır ve iş ERTELENİR (hata değil, deneme hakkı yakmaz).
            // Sınıra çarpıp ölmek yerine sınırdan önce durmak: ölen süreç iz
            // bırakmaz, duran süreç "ertelendi" der.
            (new MediaMigrator($connection, $medya))->urununMedyasi(
                $urunId,
                kontrol: $baglam === null ? null : static function () use ($baglam, $clock): void {
                    $baglam->kontrolNoktasi($clock->now());
                },
                butce: BellekButcesi::megabayttan($config->getPositiveInt('KUYRUK_BELLEK_BUTCESI_MB', 64)),
            );
        });
    }
}
