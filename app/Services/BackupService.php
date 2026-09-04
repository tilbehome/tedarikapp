<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Ifsnop\Mysqldump\Mysqldump;
use RuntimeException;
use SensitiveParameter;
use Throwable;

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
    /**
     * Sunucu-üretimi yedek SETİ adı deseni — indirme/silme yalnız bu desenle
     * çalışır (path traversal kalkanı).
     */
    private const SET_PATTERN = '/^set-\d{8}-\d{6}(-[0-9a-f]{6})?$/';

    public function __construct(
        private readonly Config $config,
        private readonly string $basePath,
        /**
         * v1.2.2 B1 — DUMP ÜRETİCİSİ enjekte edilebilir.
         *
         * Set üretiminin kendisi (parçalar, manifest, atomik taşıma) gerçek bir
         * veritabanı olmadan sınanabilmeli: sınanan şey SQL'in içeriği değil,
         * PAKETİN bütünlüğü. Verilmezse gerçek `mysqldump` yolu kullanılır.
         *
         * @var (callable(): string)|null
         */
        private $dumpUretici = null,
        /**
         * Yedeğin alındığı andaki uygulanmış migration listesi. Manifest'e
         * girer ve geri yüklerken "bu yedek hangi şemaya ait?" sorusunu
         * yanıtlar. Verilmezse boş kalır (eski çağrılar kırılmaz).
         *
         * @var list<string>
         */
        private readonly array $migrationDefteri = [],
    ) {
    }

    /** v1.2.2 B1: set yazıcısı — atomik paketleme buradan geçer. */
    private function setYazici(): \App\Services\Yedek\YedekSetiYazici
    {
        return new \App\Services\Yedek\YedekSetiYazici(
            $this->directory(),
            \App\Core\AppVersion::VALUE,
            $this->migrationDefteri,
            self::CIPHER,
        );
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
     * Yedek SETİ üretir (v1.2.2 B1): dump + ayarlar + medya → tek paket, tek
     * manifest, atomik tamamlanma.
     *
     * @return array{set_id: string, set_dizini: string, created_at: string, parca_sayisi: int, toplam_bayt: int, files_included: list<string>, media_files: int, media_bytes: int, medya_atlandi: bool, durum: string, eksik: list<string>, sebep: string|null}
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

        $yazici = $this->setYazici();

        // Yarım kalmış önceki koşumları TEMİZLE: yeni set açmadan önce, çünkü
        // hazırlık dizinleri birikirse disk sessizce dolar.
        $yazici->yarimlariTemizle();

        $damga = date('Ymd-His');
        $set = $yazici->baslat($damga);

        // 1) VERİTABANI — zorunlu parça.
        $dump = ($this->dumpUretici ?? fn (): string => $this->dumpDatabase())();
        $yazici->parcaEkle($set, 'veritabani.sql.enc', 'sql', $this->encrypt($dump));
        unset($dump); // düz metin bellekte gereksiz durmasın

        // 2) AYARLAR + KULLANICI SÖZLÜKLERİ — H1: zorunlu DEĞİL. Okunamazsa
        //    set KISMİ olur, SQL yine yazılır; manifest eksiği ve sebebini
        //    taşır. Ayarlar yeniden girilebilir, veritabanı girilemez.
        $ayarlar = $this->ayarPaketi();
        if ($ayarlar !== null) {
            $yazici->parcaEkle($set, 'ayarlar.files.enc', 'config', $this->encrypt($ayarlar['govde']));
        } else {
            $yazici->eksikBildir($set, 'config', $this->ayarEksikSebebi());
        }

        // 3) MEDYA — isteğe bağlı; sınır aşılırsa set KISMİ olur, başarısız olmaz.
        $medya = $this->medyaParcalari($set, $yazici);

        $setDizini = $yazici->tamamla($set);

        return [
            'set_id' => $set['set_id'],
            'set_dizini' => $setDizini,
            'created_at' => date(DATE_ATOM),
            'parca_sayisi' => count($set['parcalar']),
            'toplam_bayt' => array_sum(array_map(
                static fn (array $p): int => (int) $p['boyut'],
                $set['parcalar'],
            )),
            'files_included' => $ayarlar['iceren'] ?? [],
            'media_files' => $medya['dosya_sayisi'],
            'media_bytes' => $medya['toplam_bayt'],
            'medya_atlandi' => $medya['atlandi'],
            'durum' => $set['eksik'] === [] ? \App\Services\Yedek\YedekManifesti::DURUM_TAM : \App\Services\Yedek\YedekManifesti::DURUM_KISMI,
            'eksik' => $set['eksik'],
            'sebep' => $set['sebep'],
        ];
    }

    /**
     * Ayar paketi alınamadığında manifeste yazılacak KISA sebep (H1).
     *
     * "Dosya yok" ile "dosya var ama okunamıyor" ayrı teşhislerdir ve
     * operatörün yapacağı iş farklıdır (ilkinde kurulum eksik, ikincisinde
     * izin bozuk). Sebep bunu söyler; bir yığın izi değil, tek satırdır.
     */
    private function ayarEksikSebebi(): string
    {
        foreach (['config.php', '.env'] as $ayar) {
            $yol = $this->basePath . '/' . $ayar;
            if (file_exists($yol)) {
                return is_readable($yol)
                    ? $ayar . ' okundu ama içerik alınamadı'
                    : $ayar . ' okunamadı: izin reddedildi';
            }
        }

        return 'config.php bulunamadı';
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
    /**
     * MEDYA PARÇALARI (v1.2.2 B1 — sınır aşımında BÖLÜNÜR, atlanmaz).
     *
     * ESKİ HÂL: `BACKUP_MEDIA_MAX_MB` aşılırsa arşiv TAMAMEN atlanıyordu.
     * Büyük medya klasörü olan bir kurulum görsellerini hiç yedekleyemiyordu
     * ve bunu yalnız gece koşusunun özet satırından öğrenebiliyordunuz.
     *
     * YENİ HÂL: dosyalar sınır kadar parçalara BÖLÜNÜR ve hepsi TEK manifest
     * altında toplanır. Bölünemeyecek kadar büyük TEK dosya varsa (tek başına
     * sınırı aşan bir görsel) o dosya atlanır ve durum raporlanır — sessizce
     * kaybolmaz.
     *
     * @param array{set_id: string, damga: string, hazirlik: string, parcalar: list<array{ad: string, tur: string, sira: int, boyut: int, sha256: string}>, eksik: list<string>, sebep: string|null} $set
     * @return array{dosya_sayisi: int, toplam_bayt: int, atlandi: bool}
     */
    private function medyaParcalari(array &$set, \App\Services\Yedek\YedekSetiYazici $yazici): array
    {
        $medyaDizini = $this->basePath . '/public/media';
        if (!is_dir($medyaDizini) || !class_exists(\ZipArchive::class)) {
            return ['dosya_sayisi' => 0, 'toplam_bayt' => 0, 'atlandi' => true];
        }

        $dosyalar = [];
        foreach (glob($medyaDizini . '/*') ?: [] as $yol) {
            if (is_file($yol)) {
                $dosyalar[] = $yol;
            }
        }
        if ($dosyalar === []) {
            return ['dosya_sayisi' => 0, 'toplam_bayt' => 0, 'atlandi' => false];
        }

        $sinirBayt = $this->config->getPositiveInt('BACKUP_MEDIA_MAX_MB', 200) * 1024 * 1024;
        $toplamBayt = 0;
        $atlandi = false;
        $parcaNo = 0;
        $grup = [];
        $grupBayt = 0;

        $grubuYaz = function (array $grup, int $no) use (&$set, $yazici): void {
            if ($grup === []) {
                return;
            }
            $gecici = $set['hazirlik'] . '/.medya-' . $no . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($gecici, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return;
            }
            foreach ($grup as $yol) {
                $zip->addFile($yol, 'media/' . basename($yol));
            }
            $zip->close();

            $icerik = (string) file_get_contents($gecici);
            @unlink($gecici);
            $yazici->parcaEkle($set, sprintf('medya-%03d.zip.enc', $no), 'medya', $this->encrypt($icerik));
        };

        foreach ($dosyalar as $yol) {
            $boyut = (int) filesize($yol);

            // TEK BAŞINA sınırı aşan dosya bölünemez: atlanır ve RAPORLANIR.
            if ($boyut > $sinirBayt) {
                $atlandi = true;

                continue;
            }

            if ($grupBayt + $boyut > $sinirBayt && $grup !== []) {
                $grubuYaz($grup, ++$parcaNo);
                $grup = [];
                $grupBayt = 0;
            }

            $grup[] = $yol;
            $grupBayt += $boyut;
            $toplamBayt += $boyut;
        }
        $grubuYaz($grup, ++$parcaNo);

        return [
            'dosya_sayisi' => count($dosyalar) - ($atlandi ? 1 : 0),
            'toplam_bayt' => $toplamBayt,
            'atlandi' => $atlandi,
        ];
    }

    /**
     * AYAR PAKETİ — `config.php` (yoksa legacy `.env`) + `storage/sozluk-*.php`.
     *
     * Veritabanı TEK BAŞINA yeterli değildir: dökümü geri yüklemek için DB
     * bilgileri gerekir ve APP_KEY olmadan dökümün kendisi de ÇÖZÜLEMEZ.
     * Kullanıcının aylarca biriktirdiği sözlük düzeltmeleri de buradadır.
     *
     * @return array{govde: string, iceren: list<string>}|null
     */
    private function ayarPaketi(): ?array
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
            return null;
        }

        return [
            'govde' => json_encode(
                ['surum' => 1, 'uretim' => date(DATE_ATOM), 'dosyalar' => $paket],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            'iceren' => $iceren,
        ];
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

    /**
     * Setteki bir parçanın tam yolu (v1.2.2 B1).
     *
     * Eskiden "SQL yedeğinin YANINDAKİ dosya yedeği" diye tarif ediliyordu ve
     * bağ yalnız ad benzerliğiydi. Artık parça, ait olduğu SETİN içinde durur:
     * hangi parçanın hangi yedeğe ait olduğu soru olmaktan çıkar.
     */
    public function parcaYolu(string $setAdi, string $parcaAdi): ?string
    {
        $dizin = $this->pathFor($setAdi);
        if ($dizin === null || preg_match('/^[a-z0-9._-]+$/i', $parcaAdi) !== 1) {
            return null;
        }
        $yol = $dizin . '/' . $parcaAdi;

        return is_file($yol) ? $yol : null;
    }

    /**
     * Var olan yedekler — yeniden eskiye.
     *
     * @return list<array{name: string, size: int, created_at: string}>
     */
    /**
     * Tamamlanmış yedek SETLERİ — yeniden eskiye (v1.2.2 B1).
     *
     * Yarım setler (`.hazirlik-*`) BURADA GÖRÜNMEZ: listede yer alan her satır,
     * kullanıcının "yedeğim var" diye güvendiği bir şeydir.
     *
     * @return list<array{name: string, set_id: string, size: int, created_at: string, tam: bool, durum: string, eksik: list<string>, sebep: string|null, medyasiz: bool, parca_sayisi: int, parcalar: list<array{ad: string, tur: string, sira?: int, boyut: int, sha256: string}>}>
     */
    public function list(): array
    {
        $entries = [];

        foreach ($this->setYazici()->setler() as $dizin) {
            $manifestYolu = $dizin . '/' . \App\Services\Yedek\YedekProvasi::MANIFEST_ADI;
            if (!is_file($manifestYolu)) {
                continue; // manifest yoksa set tamamlanmamıştır
            }

            try {
                $manifest = \App\Services\Yedek\YedekManifesti::jsondan(
                    (string) file_get_contents($manifestYolu),
                );
            } catch (Throwable) {
                continue; // okunamayan manifest = güvenilmez set
            }

            $ozet = $manifest->ozet();
            $entries[] = [
                'name' => basename($dizin),
                'set_id' => $manifest->setId(),
                'size' => $ozet['toplam_bayt'],
                'created_at' => date(DATE_ATOM, (int) filemtime($dizin)),
                'tam' => $ozet['tam'],
                // H1: durum + eksik + sebep — panel rozeti buradan beslenir ve
                // rozet KALICIDIR ("0'da gizle" kuralı sayaçlar içindir).
                'durum' => $ozet['durum'],
                'eksik' => $ozet['eksik'],
                'sebep' => $ozet['sebep'],
                'medyasiz' => $ozet['medyasiz'],
                'parca_sayisi' => $ozet['parca_sayisi'],
                // B4: parçalar SIRALI ve SHA'lariyla birlikte gider.
                // "Tümünü zip indir" düğmesi olmadığına göre, hangi dosyaları
                // indirmesi gerektiği ve indirdiğinin sağlam olup olmadığı artık
                // KULLANICININ sorusudur; panel bu soruyu yanıtlayabilmeli.
                'parcalar' => $manifest->siraliParcalar(),
            ];
        }

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

    /**
     * İndirme için doğrulanmış SET DİZİNİ — desen dışı ad reddedilir
     * (path traversal kalkanı).
     */
    public function pathFor(string $name): ?string
    {
        if (preg_match(self::SET_PATTERN, $name) !== 1) {
            return null;
        }
        $path = $this->directory() . '/' . $name;

        return is_dir($path) ? $path : null;
    }

    /**
     * Yedek saklama (İE#11 EK-2 REV2): BACKUP_RETENTION_DAYS'ten eski dosyalar silinir;
     * her koşulda EN YENİ 5 SET korunur; yalnız SET_PATTERN eşleşen dizinlere
     * dokunulur (`.htaccess` ve desen dışı hiçbir şey silinemez).
     *
     * @return list<string> silinen dosya adları
     */
    public function prune(int $retentionDays): array
    {
        $entries = $this->list(); // yeniden → eskiye, yalnız TAMAMLANMIŞ setler
        $keep = 5;
        $threshold = time() - max(1, $retentionDays) * 86400;

        $deleted = [];
        foreach (array_slice($entries, $keep) as $entry) {
            if ((int) strtotime($entry['created_at']) >= $threshold) {
                continue;
            }
            $dizin = $this->pathFor($entry['name']);
            if ($dizin === null) {
                continue;
            }

            // SET BÜTÜN OLARAK GİDER: parçalarından biri kalırsa, kalan dosya
            // manifestsiz bir yetim olur ve kimse ne olduğunu bilemez.
            foreach (glob($dizin . '/*') ?: [] as $parca) {
                @unlink($parca);
            }
            if (@rmdir($dizin)) {
                $deleted[] = $entry['name'];
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
