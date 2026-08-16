<?php

declare(strict_types=1);

namespace App\Setup;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Kurulum gereksinim denetimi (İE#5 §11a).
 *
 * Kural: eksikleri İSİM İSİM ve **ne yapılacağını söyleyerek** listeler.
 * "Gereksinimler karşılanmıyor" gibi bir mesaj kullanıcıyı sunucu yöneticisine
 * gitmek zorunda bırakır; burada hangi eklentinin açılacağı, hangi klasörün
 * yazılabilir yapılacağı tek tek yazılır.
 */
final class RequirementChecker
{
    public const string MIN_PHP_VERSION = '8.4.0';

    /** Uygulamanın çalışması için zorunlu eklentiler (docs/04 §7 + K19 paketlerinin ihtiyaçları). */
    private const array REQUIRED_EXTENSIONS = [
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

    /** Zorunlu değil ama varsa tercih edilenler. */
    private const array OPTIONAL_EXTENSIONS = [
        'sodium' => 'TOTP secret şifrelemesinde tercih edilen algoritma (yoksa AES-256-GCM kullanılır — K27).',
    ];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @return array{
     *     ok: bool,
     *     php: array{ok: bool, required: string, current: string},
     *     extensions: list<array{name: string, ok: bool, required: bool, reason: string}>,
     *     writable: list<array{path: string, ok: bool, hint: string}>,
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

        $warnings = [];
        if (!$this->isHttps($request)) {
            $warnings[] = 'Bağlantı HTTPS değil. Oturum çerezi "Secure" işaretlenemez ve şifreniz '
                . 'ağda açık gider. Kuruluma devam edebilirsiniz ama canlıya almadan önce SSL kurun.';
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
        $writableOk = true;
        foreach ($writable as $directory) {
            if (!$directory['ok']) {
                $writableOk = false;
            }
        }

        return [
            'ok' => $php['ok'] && $extensionsOk && $writableOk,
            'php' => $php,
            'extensions' => $extensions,
            'writable' => $writable,
            'warnings' => $warnings,
        ];
    }

    /** @return array{path: string, ok: bool, hint: string} */
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
