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
use App\Services\Ilan\SkorHesaplayici;
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
            $cevirmen->translateProduct([
                'name' => (string) $urun['name'],
                'category' => null,
                'source_lang' => Glossary::detect((string) ($urun['name_original'] ?? $urun['name'])),
                'attributes' => [],
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
    }
}
