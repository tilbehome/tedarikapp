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

            $glossary = new Glossary($basePath);
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

            $cevirmen->translateProduct([
                // D12: kuyruk yolu da ORİJİNAL metni çevirir. `name` alanı çoğu
                // kayıtta makine çevirisi bir Türkçe addır ve kalıcılık ölçütü
                // `name_original` üzerinden bakar; ekrandaki adı göndermek,
                // üretilen satırın hiç aranmayan bir anahtara yazılması demekti.
                // İki yol (senkron ve kuyruk) aynı metni çevirir — tek kaynak.
                'name' => (string) ($urun['name_original'] ?? $urun['name']),
                'category' => null,
                'source_lang' => Glossary::detect((string) ($urun['name_original'] ?? $urun['name'])),
                'attributes' => $degerler,
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
        $kosucu->kaydet(self::TUR_MEDYA, static function (array $yuk) use ($config, $connection, $basePath): void {
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

            (new MediaMigrator($connection, $medya))->urununMedyasi($urunId);
        });
    }
}
