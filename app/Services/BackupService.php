<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Ifsnop\Mysqldump\Mysqldump;
use RuntimeException;
use SensitiveParameter;

/**
 * Veritabanı yedekleme (İE#10.5 Blok 1 — dış inceleme, PM onaylı).
 *
 * Dump PHP İÇİNDEN üretilir (ifsnop/mysqldump-php — `exec`/`mysqldump` paylaşımlı
 * hostta YASAK, docs/04 §7). Çıktı AES-256-GCM ile şifrelenir: anahtar APP_KEY'den
 * HKDF ile türetilmiş AYRI yedek anahtarıdır (K39 OpenSSL hattı) — APP_KEY'in
 * kendisi asla doğrudan kullanılmaz ve hiçbir yerde loglanmaz. Dosya web'den
 * erişilemeyen `storage/backups/` altına yazılır (storage/.htaccess deny + ayrıca
 * kendi .htaccess'i); SHA-256 özeti kayda eşlik eder.
 *
 * "Geri yüklenemeyen yedek yedek değildir": CI, ürettiği dump'ı boş MySQL'e geri
 * yükleyip smoke koşar (uretim-profili job'ı).
 */
final class BackupService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const MAGIC = 'TDKBK1'; // dosya başı imzası + sürüm
    private const KEY_INFO = 'tedarikapp:backup:v1';
    /** Sunucu-üretimi yedek adı deseni — indirme/silme yalnız bu desenle çalışır. */
    private const NAME_PATTERN = '/^yedek-\d{8}-\d{6}\.sql\.enc$/';

    public function __construct(
        private readonly Config $config,
        private readonly string $basePath,
    ) {
    }

    public function directory(): string
    {
        return $this->basePath . '/storage/backups';
    }

    /** storage kökü zaten deny'lidir; yedek klasörü kendi .htaccess'ini AYRICA taşır (derinlik savunması). */
    private function ensureHtaccess(): void
    {
        $file = $this->directory() . '/.htaccess';
        if (is_dir($this->directory()) && !is_file($file)) {
            @file_put_contents($file, "Require all denied\n");
        }
    }

    public function isWritable(): bool
    {
        $dir = $this->directory();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            $this->ensureHtaccess();
        }

        return is_dir($dir) && is_writable($dir);
    }

    /**
     * Yedek üretir: dump → şifrele → storage/backups'a yaz.
     *
     * @return array{name: string, size: int, sha256: string, created_at: string}
     */
    public function create(): array
    {
        if (!$this->isWritable()) {
            throw new RuntimeException(
                'Yedek klasörü yazılamıyor (storage/backups). cPanel > Dosya Yöneticisi\'nde '
                . 'storage klasörüne yazma izni (775) verin.',
            );
        }
        $this->ensureHtaccess();

        $dump = $this->dumpDatabase();
        $encrypted = $this->encrypt($dump);
        // Dump düz metni bellekte gereksiz tutulmasın.
        unset($dump);

        $name = 'yedek-' . date('Ymd-His') . '.sql.enc';
        $path = $this->directory() . '/' . $name;
        if (@file_put_contents($path, $encrypted) === false) {
            throw new RuntimeException('Yedek dosyası yazılamadı: storage/backups izinlerini denetleyin.');
        }
        @chmod($path, 0600);

        return [
            'name' => $name,
            'size' => strlen($encrypted),
            'sha256' => hash('sha256', $encrypted),
            'created_at' => date(DATE_ATOM),
        ];
    }

    /**
     * Var olan yedekler — yeniden eskiye.
     *
     * @return list<array{name: string, size: int, created_at: string}>
     */
    public function list(): array
    {
        $entries = [];
        foreach (glob($this->directory() . '/yedek-*.sql.enc') ?: [] as $file) {
            $name = basename($file);
            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                continue;
            }
            $entries[] = [
                'name' => $name,
                'size' => (int) filesize($file),
                'created_at' => date(DATE_ATOM, (int) filemtime($file)),
            ];
        }
        usort($entries, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $entries;
    }

    /** Son yedeğin yaşı (saniye) — panel 24 saat uyarı rozeti için; hiç yedek yoksa null. */
    public function lastBackupAgeSeconds(): ?int
    {
        $entries = $this->list();
        if ($entries === []) {
            return null;
        }

        return max(0, time() - (int) strtotime($entries[0]['created_at']));
    }

    /** İndirme için doğrulanmış tam yol — desen dışı ad reddedilir (path traversal kalkanı). */
    public function pathFor(string $name): ?string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return null;
        }
        $path = $this->directory() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * Şifreli yedeği çözer (CI restore kanıtı + olağanüstü durum kurtarması).
     * Panelden ÇAĞRILMAZ — düz dump yalnız geri yükleme anında var olur.
     */
    public function decrypt(#[SensitiveParameter] string $encrypted): string
    {
        $magicLength = strlen(self::MAGIC);
        if (!str_starts_with($encrypted, self::MAGIC)) {
            throw new RuntimeException('Bu dosya bir tedarikapp yedeği değil (imza uyuşmadı).');
        }
        $iv = substr($encrypted, $magicLength, self::IV_LENGTH);
        $tag = substr($encrypted, $magicLength + self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($encrypted, $magicLength + self::IV_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt($ciphertext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, self::MAGIC);
        if ($plain === false) {
            throw new RuntimeException('Yedek çözülemedi: APP_KEY farklı veya dosya bozuk.');
        }

        return $plain;
    }

    private function encrypt(#[SensitiveParameter] string $plain): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, self::MAGIC, self::TAG_LENGTH);
        if ($ciphertext === false) {
            throw new RuntimeException('Yedek şifrelenemedi (OpenSSL).');
        }

        return self::MAGIC . $iv . $tag . $ciphertext;
    }

    /** APP_KEY'den HKDF ile türetilmiş AYRI yedek anahtarı — APP_KEY doğrudan kullanılmaz. */
    private function key(): string
    {
        $appKey = $this->config->get('APP_KEY', '');
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY yapılandırılmamış — yedek şifrelenemez.');
        }

        return hash_hkdf('sha256', (string) hex2bin($appKey) ?: $appKey, 32, self::KEY_INFO);
    }

    /**
     * Dump'ı üretir. mysqldump-php dosya YOLU ister (akış veremeyiz); düz metin dump
     * web'den erişilemeyen deny'li klasöre GEÇİCİ rastgele adla yazılır, okunur ve
     * şifreleme öncesi ANINDA silinir — diskte düz metin kalıcı olmaz.
     */
    private function dumpDatabase(): string
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->config->get('DB_HOST', '127.0.0.1'),
            $this->config->get('DB_PORT', '3306'),
            $this->config->get('DB_NAME', ''),
        );

        $tempPath = $this->directory() . '/.tmp-' . bin2hex(random_bytes(8)) . '.sql';

        try {
            $dump = new Mysqldump($dsn, $this->config->get('DB_USER', ''), $this->config->get('DB_PASS', ''), [
                'add-drop-table' => true,
                'single-transaction' => true,
                'lock-tables' => false,
                'default-character-set' => Mysqldump::UTF8MB4,
            ]);
            $dump->start($tempPath);

            $sql = (string) file_get_contents($tempPath);
        } catch (\Exception $e) {
            // Kütüphane mesajı kimlik bilgisi içerebilir — şifre maskelenir, ayrıntı loga (çağıran loglar).
            throw new RuntimeException('Veritabanı dökümü üretilemedi: ' . $this->sanitize($e->getMessage()), 0, $e);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        if (trim($sql) === '') {
            throw new RuntimeException('Veritabanı dökümü boş çıktı — yedek üretilmedi.');
        }

        return $sql;
    }

    private function sanitize(string $message): string
    {
        $pass = $this->config->get('DB_PASS', '');

        return $pass === '' ? $message : str_replace($pass, '***', $message);
    }
}
