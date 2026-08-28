<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\AppUrl;
use App\Core\AppVersion;
use App\Core\Connection;
use App\Models\SettingsRepository;
use Throwable;

/**
 * Kurulum/açılış tanılaması (K42): "site 500 verdi" ASLA tek bilgi olmamalı.
 *
 * Ürettiği veri, sihirbazın "TANILAMA RAPORUNU KOPYALA" düğmesinin ve hata
 * yanıtlarındaki teknik detay bölümünün kaynağıdır: uygulama/PHP sürümü, SAPI,
 * eklenti VAR/YOK listesi, sunucu özeti, MySQL sürümü (alınabildiyse), hata
 * detayı (sınıf, mesaj, konum, kısa iz), zaman damgası.
 *
 * SIR KURALI (pazarlıksız): çıktıda DB şifresi, APP_KEY, token, TOTP secret,
 * .env içeriği OLMAZ. Mesaj/iz metinleri redaksiyondan geçer; dosya yolları
 * hesap kullanıcı adını sızdırmasın diye uygulama köküne göre kısaltılır.
 */
final class SetupDiagnostics
{
    /** docs/SUNUCU-PROFILI.md ile aynı liste + önerilen sodium (K39/K41). */
    private const PROFILE_EXTENSIONS = [
        'pdo_mysql', 'curl', 'gd', 'mbstring', 'zip', 'intl', 'bcmath', 'fileinfo', 'openssl', 'sodium',
    ];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Ortam özeti — hata olsun olmasın aynı biçim.
     *
     * @return array<string, mixed>
     */
    public function environment(?Connection $connection = null): array
    {
        $extensions = [];
        foreach (self::PROFILE_EXTENSIONS as $extension) {
            $extensions[$extension] = extension_loaded($extension) ? 'VAR' : 'YOK';
        }

        $appUrl = $this->appUrl($connection);

        return [
            'app_version' => AppVersion::VALUE,
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')',
            'extensions' => $extensions,
            'mysql_version' => $this->mysqlVersion($connection),
            // rc8/K4 (F-08): paylaşım bağlantısının tabanı. RAPOR SATIRIDIR —
            // kurulumu DURDURMAZ; eksikliği burada görünür olsun ki destek
            // "linkler neden yanlış adrese gidiyor" sorusunu tek bakışta
            // cevaplayabilsin.
            'app_url' => $appUrl,
            'app_url_kanonik' => AppUrl::kanonik($appUrl) !== null,
            // D12 (K91): LLM anahtarı yoksa çeviriler GEÇİCİ katmanda kalır ve
            // üç dil garantisi yoktur. Kurulumu DURDURMAZ — ama destek, "çeviri
            // neden yarım" sorusunu bu satıra bakarak yanıtlayabilmeli.
            'llm_anahtari' => $this->llmAnahtariVarMi($connection),
            'timestamp' => date(DATE_ATOM),
        ];
    }

    /** Çeviri sağlayıcı anahtarı tanımlı mı? (değeri OKUNMAZ, yalnız varlığı) */
    private function llmAnahtariVarMi(?Connection $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        try {
            $deger = (new SettingsRepository($connection))->get('ceviri_api_anahtari');

            return is_string($deger) && $deger !== '';
        } catch (Throwable) {
            return false;
        }
    }

    /** `settings.APP_URL` — tablo/bağlantı yoksa null (teşhis üretimi hata üretemez). */
    private function appUrl(?Connection $connection): ?string
    {
        if ($connection === null) {
            return null;
        }

        try {
            return (new SettingsRepository($connection))->get('APP_URL');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Başarısız adımın teşhis bloğu.
     *
     * @return array<string, mixed>
     */
    public function failure(string $step, Throwable $exception): array
    {
        $trace = array_slice(explode("\n", $exception->getTraceAsString()), 0, 6);

        return [
            'step' => $step,
            'exception' => $exception::class,
            'message' => $this->redact($exception->getMessage()),
            'location' => $this->shortPath($exception->getFile()) . ':' . $exception->getLine(),
            'trace' => array_map(fn (string $line): string => $this->redact($this->shortPath($line)), $trace),
        ];
    }

    /** Metin içinden sır olabilecek kalıpları maskeler. */
    public function redact(string $text): string
    {
        // APP_KEY / token biçimi: uzun hex dizileri.
        $text = (string) preg_replace('/[0-9a-f]{32,}/i', '[gizlendi]', $text);

        // "password=...", "pass: ...", "secret=..." kalıpları (DSN, config dökümleri).
        return (string) preg_replace(
            '/(pass(?:word)?|pwd|secret|token|app_key)(\s*[=:]\s*)[^\s;,\'"]+/i',
            '$1$2[gizlendi]',
            $text,
        );
    }

    /** Mutlak yol hesap adını sızdırır (/home/<kullanıcı>/...); köke göre kısalt. */
    private function shortPath(string $path): string
    {
        return str_replace(
            [rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR, rtrim($this->basePath, '/\\') . '/'],
            '',
            $path,
        );
    }

    private function mysqlVersion(?Connection $connection): ?string
    {
        if ($connection === null) {
            return null;
        }

        try {
            $statement = $connection->pdo()->query('SELECT VERSION() AS v');
            $row = $statement === false ? false : $statement->fetch();

            return is_array($row) ? (string) $row['v'] : null;
        } catch (Throwable) {
            return null; // sürüm alınamadıysa rapor yine üretilir
        }
    }
}
