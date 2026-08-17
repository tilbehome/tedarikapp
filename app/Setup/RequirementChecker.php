<?php

declare(strict_types=1);

namespace App\Setup;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Kurulum gereksinim denetimi (İE#5 §11a, K37 §D10).
 *
 * Kural: eksikleri İSİM İSİM ve **ne yapılacağını söyleyerek** listeler.
 * "Gereksinimler karşılanmıyor" gibi bir mesaj kullanıcıyı sunucu yöneticisine
 * gitmek zorunda bırakır; burada hangi eklentinin açılacağı, hangi klasörün
 * yazılabilir yapılacağı tek tek yazılır.
 *
 * ZORUNLU / UYARI ayrımı (K37 — K33 ile barışma):
 *  • PHP sürümü + zorunlu eklentiler + (production'da) HTTPS → her modda ZORUNLU.
 *  • `storage/` ve `public/media/` yazılabilirliği → yalnızca arşiv (download) modu
 *    için gerekir; yazılamıyorsa kurulum BLOKLANMAZ — hotlink + DB-log moduyla
 *    devam edilir ve uyarı kartı gösterilir (K33 zaten bu modu destekler).
 */
final class RequirementChecker
{
    public const MIN_PHP_VERSION = '8.1.0';

    public function __construct(
        private readonly string $basePath,
        private readonly string $appEnv = 'production',
    ) {
    }

    /** Uygulamanın çalışması için zorunlu eklentiler (docs/04 §7 + K19 paketlerinin ihtiyaçları). */
    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql' => 'Veritabanı bağlantısı (MySQL) bu eklenti olmadan kurulamaz.',
        'curl' => 'Dış istekler yalnızca cURL ile yapılır (K8): 1688 görsellerinin indirilmesi buna bağlı.',
        'gd' => 'Ürün görsellerinin yeniden boyutlandırılması ve Excel içine gömülmesi GD ile yapılır.',
        'mbstring' => 'Çince başlıklar ve Türkçe karakterler için çok baytlı dize işlemleri.',
        'zip' => 'Excel (xlsx) çıktısı üretimi zip gerektirir.',
        'intl' => 'Tarih/sayı biçimleme.',
        'bcmath' => 'Para hesapları float ile YAPILMAZ (K14/K24); bcmath zorunludur.',
        'fileinfo' => 'İndirilen dosyaların gerçek türünün doğrulanması (SSRF/MIME koruması).',
        'openssl' => 'HTTPS istekleri ve TOTP secret şifrelemesi.',
    ];

    /** Zorunlu değil ama varsa tercih edilenler — kurulum BLOKLANMAZ (K39). */
    private const OPTIONAL_EXTENSIONS = [
        'sodium' => 'TOTP secret şifrelemesinde tercih edilen arka uç. Yok: OpenSSL AES-256-GCM kullanılacak '
            . '(eşdeğer güvenlikte AEAD — K27/K39); kurulum bloklanmaz.',
    ];

    /**
     * @return array{
     *     ok: bool,
     *     php: array{ok: bool, required: string, current: string},
     *     extensions: list<array{name: string, ok: bool, required: bool, reason: string}>,
     *     writable: list<array{path: string, ok: bool, required: bool, hint: string}>,
     *     https: array{ok: bool, required: bool, hint: string},
     *     warnings: list<string>
     * }
     */
    public function check(ServerRequestInterface $request): array
    {
        $php = [
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>='),
            'required' => self::MIN_PHP_VERSION,
            'current' => PHP_VERSION,
        ];

        $extensions = [];
        foreach (self::REQUIRED_EXTENSIONS as $name => $reason) {
            $extensions[] = [
                'name' => $name,
                'ok' => extension_loaded($name),
                'required' => true,
                'reason' => $reason,
            ];
        }
        foreach (self::OPTIONAL_EXTENSIONS as $name => $reason) {
            $extensions[] = [
                'name' => $name,
                'ok' => extension_loaded($name),
                'required' => false,
                'reason' => $reason,
            ];
        }

        $writable = [
            $this->writableCheck('storage', 'Loglar, geçici export dosyaları ve kurulum kilidi buraya yazılır.'),
            $this->writableCheck('public/media', 'İndirilen ürün görselleri buraya kaydedilir (K6).'),
        ];

        // K37 §D10: HTTPS production'da ZORUNLUDUR (sırlar bu kanaldan geçecek);
        // geliştirme ortamında yalnızca uyarıdır.
        $httpsOk = $this->isHttps($request);
        // K45 (Ürün Sahibi talimatı): HTTPS hiçbir modda BLOKLAMAZ — yalnız uyarı.
        $httpsRequired = false;
        $https = [
            'ok' => $httpsOk,
            'required' => $httpsRequired,
            'hint' => $httpsOk
                ? 'Bağlantı HTTPS — kurulum sırları güvenli kanaldan geçecek.'
                : 'Bağlantı HTTPS değil. Şifreler ve gizli anahtarlar ağda açık gider. '
                    . 'Çözüm: cPanel > SSL/TLS ile sertifika kurun ve sihirbazı https:// adresinden açın.',
        ];

        $warnings = [];
        if (!$httpsOk && !$httpsRequired) {
            $warnings[] = 'Bağlantı HTTPS değil. Oturum çerezi "Secure" işaretlenemez ve şifreniz '
                . 'ağda açık gider. Kuruluma devam edebilirsiniz ama canlıya almadan önce SSL kurun.';
        }
        foreach ($writable as $directory) {
            if (!$directory['ok']) {
                $warnings[] = sprintf(
                    '"%s" yazılabilir değil. Kurulum ENGELLENMEZ: görseller indirilmek yerine '
                    . 'orijinal adresinden gösterilir (hotlink modu) ve loglar veritabanına yazılır (K33). '
                    . 'Arşiv modu isterseniz klasöre yazma izni verip yeniden denetleyin.',
                    $directory['path'],
                );
            }
        }
        if (!extension_loaded('sodium')) {
            $warnings[] = 'libsodium eklentisi yok. TOTP secret\'ları AES-256-GCM ile şifrelenecek '
                . '(desteklenen yedek yol, K27) — kurulum engellenmez.';
        }

        $extensionsOk = true;
        foreach ($extensions as $extension) {
            if ($extension['required'] && !$extension['ok']) {
                $extensionsOk = false;
            }
        }

        return [
            // Yazılabilirlik bilerek DAHİL DEĞİL (K37 §D10): hotlink/DB modu meşru bir kurulumdur.
            'ok' => $php['ok'] && $extensionsOk && ($httpsOk || !$httpsRequired),
            'php' => $php,
            'extensions' => $extensions,
            'writable' => $writable,
            'https' => $https,
            'warnings' => $warnings,
        ];
    }

    /** @return array{path: string, ok: bool, required: bool, hint: string} */
    private function writableCheck(string $relative, string $purpose): array
    {
        $absolute = $this->basePath . '/' . $relative;

        // Klasör yoksa oluşturmayı dene: kurulumun ilk adımında olmaması normaldir.
        if (!is_dir($absolute)) {
            @mkdir($absolute, 0775, true);
        }

        $ok = is_dir($absolute) && is_writable($absolute);

        return [
            'path' => $relative . '/',
            'ok' => $ok,
            // K37 §D10: yazılabilirlik kurulumu bloklamaz — hotlink/DB modu ile devam edilir.
            'required' => false,
            'hint' => $ok
                ? $purpose
                : $purpose . ' Çözüm: cPanel > Dosya Yöneticisi\'nde "' . $relative
                    . '" klasörünü oluşturun ve yazma izni (755 veya 775) verin.',
        ];
    }

    private function isHttps(ServerRequestInterface $request): bool
    {
        $server = $request->getServerParams();
        $https = $server['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return $request->getUri()->getScheme() === 'https';
    }
}
