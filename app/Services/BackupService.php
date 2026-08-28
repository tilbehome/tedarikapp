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
     * @return array{name: string, size: int, sha256: string, created_at: string, files_name: string|null, files_included: list<string>, media_manifest: string|null, media_archive: string|null, media_files: int, media_bytes: int, media_skipped: bool}
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

        $damga = date('Ymd-His');
        $name = 'yedek-' . $damga . '.sql.enc';
        $path = $this->directory() . '/' . $name;
        if (@file_put_contents($path, $encrypted) === false) {
            throw new RuntimeException('Yedek dosyası yazılamadı: storage/backups izinlerini denetleyin.');
        }
        @chmod($path, 0600);

        $dosyalar = $this->dosyaYedegiYaz($damga);
        // İE#22 E4 (F-03): görseller de yedeğe girer.
        $medya = $this->medyaYedegiYaz($damga);

        return [
            'name' => $name,
            'size' => strlen($encrypted),
            'sha256' => hash('sha256', $encrypted),
            'created_at' => date(DATE_ATOM),
            'files_name' => $dosyalar['name'],
            'files_included' => $dosyalar['included'],
            // İE#22 E4: medya yedeğinin sonucu ÇAĞIRANA taşınır — gece koşusu
            // özetinde görünsün diye. Arşiv atlandıysa bu da raporlanır.
            'media_manifest' => $medya['manifest'],
            'media_archive' => $medya['arsiv'],
            'media_files' => $medya['dosya_sayisi'],
            'media_bytes' => $medya['toplam_bayt'],
            'media_skipped' => $medya['atlandi'],
        ];
    }

    /**
     * DOSYA YEDEĞİ (İE#19 G8) — veritabanı tek başına yeterli DEĞİLDİR.
     *
     * Yedek yalnız SQL dökümüydü. Sunucu tamamen kaybedilirse dökümü geri yüklemek
     * için `config.php` gerekir (DB bilgileri + APP_KEY) ve APP_KEY olmadan dökümün
     * kendisi de ÇÖZÜLEMEZ — yani "yedeğim var" diyen kullanıcı, elindeki tek
     * dosyayla hiçbir şey yapamıyordu. Ayrıca kullanıcının kendi terminolojisi
     * (`storage/sozluk-*.php`, K44) hiçbir yedeğe girmiyordu; sunucu gidince
     * aylarca biriken sözlük düzeltmeleri de gidiyordu.
     *
     * KAPSAM: `config.php` (yoksa legacy `.env`) + `storage/sozluk-*.php`.
     * Şifreleme SQL yedeğiyle aynı anahtarladır. Bu, APP_KEY'i kaybeden birinin
     * config.php'yi de kurtaramayacağı anlamına gelir — bu yüzden APP_KEY EMANET
     * PROSEDÜRÜ zorunludur ve runbook'ta (docs/07 §5b) tarif edilir: anahtar,
     * yedeklerden AYRI bir yerde (parola yöneticisi / kapalı zarf) saklanır.
     *
     * @return array{name: string|null, included: list<string>}
     */
    /**
     * MEDYA YEDEĞİ (İE#22 E4 · dış denetim F-03).
     *
     * BULGU: runbook "yedek alınıyor" diyordu ama betik YALNIZ veritabanını ve
     * ayar dosyalarını alıyordu. Sunucu kaybında ürün görselleri geri gelmez;
     * kaynak adresler süreli olduğu için hepsi yeniden indirilemez.
     *
     * İKİ PARÇA, İKİ AYRI DEĞER:
     *   · MANİFEST (her zaman): dosya adı + boyut + sha256 listesi. Küçüktür,
     *     her gece yazılır ve "hangi görsel vardı, bozuldu mu" sorusunu
     *     yanıtlar. Arşiv alınamasa bile bu liste kayıp tespitini sağlar.
     *   · ARŞİV (boyut sınırlı): `BACKUP_MEDIA_MAX_MB` (varsayılan 200) altında
     *     kalıyorsa ZipArchive ile paketlenir. Sınır bilinçlidir: paylaşımlı
     *     hostingde gecelik cron'u gigabaytlarca dosyayla boğmak, yedeğin
     *     kendisini başarısız kılar. Sınır aşılırsa manifest yine yazılır ve
     *     durum RAPORLANIR — sessizce atlanmaz.
     *
     * @return array{manifest: string|null, arsiv: string|null, dosya_sayisi: int, toplam_bayt: int, atlandi: bool}
     */
    private function medyaYedegiYaz(string $damga): array
    {
        $medyaKok = $this->basePath . '/public/media';
        $bos = ['manifest' => null, 'arsiv' => null, 'dosya_sayisi' => 0, 'toplam_bayt' => 0, 'atlandi' => false];
        if (!is_dir($medyaKok)) {
            return $bos;
        }

        $dosyalar = array_values(array_filter(
            glob($medyaKok . '/*') ?: [],
            static fn (string $yol): bool => is_file($yol) && !str_ends_with($yol, '.htaccess'),
        ));
        if ($dosyalar === []) {
            return $bos;
        }

        $satirlar = [];
        $toplam = 0;
        foreach ($dosyalar as $yol) {
            $boyut = (int) @filesize($yol);
            $toplam += $boyut;
            $satirlar[] = sprintf('%s  %d  %s', hash_file('sha256', $yol) ?: '-', $boyut, basename($yol));
        }

        $manifestAdi = 'yedek-' . $damga . '.media-manifest.txt';
        @file_put_contents(
            $this->directory() . '/' . $manifestAdi,
            "# tedarikapp medya manifesti
# sha256  bayt  dosya
" . implode("
", $satirlar) . "
",
        );

        $sinirMb = $this->config->getPositiveInt('BACKUP_MEDIA_MAX_MB', 200);
        if ($toplam > $sinirMb * 1024 * 1024 || !class_exists(\ZipArchive::class)) {
            return [
                'manifest' => $manifestAdi,
                'arsiv' => null,
                'dosya_sayisi' => count($dosyalar),
                'toplam_bayt' => $toplam,
                'atlandi' => true,
            ];
        }

        $arsivAdi = 'yedek-' . $damga . '.media.zip';
        $zip = new \ZipArchive();
        if ($zip->open($this->directory() . '/' . $arsivAdi, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return [
                'manifest' => $manifestAdi,
                'arsiv' => null,
                'dosya_sayisi' => count($dosyalar),
                'toplam_bayt' => $toplam,
                'atlandi' => true,
            ];
        }
        foreach ($dosyalar as $yol) {
            $zip->addFile($yol, 'media/' . basename($yol));
        }
        $zip->close();
        @chmod($this->directory() . '/' . $arsivAdi, 0600);

        return [
            'manifest' => $manifestAdi,
            'arsiv' => $arsivAdi,
            'dosya_sayisi' => count($dosyalar),
            'toplam_bayt' => $toplam,
            'atlandi' => false,
        ];
    }

    /** @return array{name: string|null, included: list<string>} */
    private function dosyaYedegiYaz(string $damga): array
    {
        $adaylar = [];
        foreach (['config.php', '.env'] as $ayar) {
            if (is_file($this->basePath . '/' . $ayar)) {
                $adaylar[] = $ayar;

                break; // config.php varsa legacy .env'e gerek yok
            }
        }
        foreach (glob($this->basePath . '/storage/sozluk-*.php') ?: [] as $sozluk) {
            $adaylar[] = 'storage/' . basename($sozluk);
        }

        if ($adaylar === []) {
            return ['name' => null, 'included' => []];
        }

        $paket = [];
        $iceren = [];
        foreach ($adaylar as $goreli) {
            $icerik = @file_get_contents($this->basePath . '/' . $goreli);
            if ($icerik === false) {
                continue;
            }
            $paket[$goreli] = base64_encode($icerik);
            $iceren[] = $goreli;
        }
        if ($paket === []) {
            return ['name' => null, 'included' => []];
        }

        $govde = json_encode(
            ['surum' => 1, 'uretim' => date(DATE_ATOM), 'dosyalar' => $paket],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $ad = 'yedek-' . $damga . '.files.enc';
        $yol = $this->directory() . '/' . $ad;
        if (@file_put_contents($yol, $this->encrypt($govde)) === false) {
            return ['name' => null, 'included' => []];
        }
        @chmod($yol, 0600);

        return ['name' => $ad, 'included' => $iceren];
    }

    /**
     * Dosya yedeğini çözer: göreli yol → içerik (geri yükleme ve tatbikat için).
     *
     * @return array<string, string>
     */
    public function decryptFiles(#[SensitiveParameter] string $encrypted): array
    {
        $decoded = json_decode($this->decrypt($encrypted), true);
        if (!is_array($decoded) || !is_array($decoded['dosyalar'] ?? null)) {
            throw new RuntimeException('Dosya yedeği çözüldü ama içeriği okunamadı.');
        }

        $sonuc = [];
        foreach ($decoded['dosyalar'] as $goreli => $base64) {
            if (!is_string($goreli) || !is_string($base64)) {
                continue;
            }
            $icerik = base64_decode($base64, true);
            if ($icerik !== false) {
                $sonuc[$goreli] = $icerik;
            }
        }

        return $sonuc;
    }

    /** SQL yedeğinin yanındaki dosya yedeğinin tam yolu (varsa). */
    public function filesPathFor(string $sqlName): ?string
    {
        if (preg_match(self::NAME_PATTERN, $sqlName) !== 1) {
            return null;
        }
        $path = $this->directory() . '/' . str_replace('.sql.enc', '.files.enc', $sqlName);

        return is_file($path) ? $path : null;
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
     * Yedek saklama (İE#11 EK-2 REV2): BACKUP_RETENTION_DAYS'ten eski dosyalar silinir;
     * her koşulda EN YENİ 5 dosya korunur; yalnız NAME_PATTERN eşleşenlere dokunulur
     * (yedek-*.sql.enc dışındaki hiçbir dosya silinemez — .htaccess dahil).
     *
     * @return list<string> silinen dosya adları
     */
    public function prune(int $retentionDays): array
    {
        $entries = $this->list(); // yeniden → eskiye sıralı, yalnız desen eşleşenler
        $keep = 5;
        $threshold = time() - max(1, $retentionDays) * 86400;

        $deleted = [];
        foreach (array_slice($entries, $keep) as $entry) {
            if ((int) strtotime($entry['created_at']) >= $threshold) {
                continue;
            }
            $path = $this->pathFor($entry['name']);
            if ($path !== null && @unlink($path)) {
                $deleted[] = $entry['name'];
                // G8: dosya yedeği SQL yedeğiyle birlikte yaşar, birlikte düşer.
                $dosyaYolu = $this->directory() . '/' . str_replace('.sql.enc', '.files.enc', $entry['name']);
                if (is_file($dosyaYolu)) {
                    @unlink($dosyaYolu);
                }
            }
        }

        return $deleted;
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
