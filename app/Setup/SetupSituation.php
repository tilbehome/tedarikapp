<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\AppVersion;
use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Migrator;
use App\Services\IntegrityChecker;
use PDO;
use Throwable;

/**
 * TEŞHİS MOTORU (İE#20 D2-REV) — "/setup açıldığında ne buldun?"
 *
 * ESKİ DAVRANIŞ VE NEDEN YETMİYORDU: sihirbaz tek bir soru soruyordu — "kilit var
 * mı?". İki cevabı vardı: yoksa baştan kur, varsa "zaten kurulmuş" de. Aradaki
 * BÜTÜN hâller (config kayıp, migration yarım, dosya eksik, DB düşmüş, dosyalar
 * yeni şema eski) kullanıcıyı sihirbazın DIŞINA — File Manager'a, phpMyAdmin'e,
 * runbook'un "ters giderse" satırlarına — gönderiyordu.
 *
 * Bu sınıf tek bir iş yapar: sistemi BAŞTAN SONA okur ve SEKİZ durumdan hangisinde
 * olduğunu söyler. Karar vermez, hiçbir şey yazmaz, hiçbir şeyi onarmaz — teşhis ile
 * tedaviyi ayırmak bilinçlidir: teşhis her koşulda (kilitliyken, DB düşmüşken)
 * çalışabilmelidir, tedavi ise sahiplik kanıtı ister.
 *
 * SIR KURALI: çıktısında DB şifresi, APP_KEY, token YOKTUR. Yalnız "var/yok" ve
 * sınıflandırılmış hata kodları döner.
 */
final class SetupSituation
{
    /** 1) Hiç kurulum yok — normal akış. */
    public const KURULUM_YOK = 'KURULUM_YOK';
    /** 2) Sağlıklı kurulum var. */
    public const SAGLIKLI = 'SAGLIKLI';
    /** 3) Kurulum yarım kalmış (config var, kilit yok). */
    public const YARIM = 'YARIM';
    /** 4) config.php yok/bozuk ama veritabanında kurulum izi var. */
    public const CONFIG_KAYIP = 'CONFIG_KAYIP';
    /** 5) Paket dosyaları eksik/bozuk (MANIFEST'e göre). */
    public const DOSYA_EKSIK = 'DOSYA_EKSIK';
    /** 6) Tablolar kısmen var / migration yarıda kalmış. */
    public const MIGRATION_YARIM = 'MIGRATION_YARIM';
    /** 7) Dosyalar yeni, veritabanı şeması eski — güncelleme gerekiyor. */
    public const SURUM_UYUSMAZLIGI = 'SURUM_UYUSMAZLIGI';
    /** 8) Veritabanına erişilemiyor. */
    public const DB_ERISILEMIYOR = 'DB_ERISILEMIYOR';

    /** Kurulu sürümün yazıldığı ayar anahtarı — sürüm uyuşmazlığı bununla ölçülür. */
    public const SETTING_VERSION = 'system.app_version';

    /**
     * @param (\Closure(): Connection)|null $baglantiCozucu Testlerin gerçek bir
     *        veritabanı (SQLite) verebilmesi için; üretimde null gelir ve bağlantı
     *        config.php üzerinden kurulur. Teşhis motorunun bağlantıyı KENDİSİ
     *        kurması şart: "config'e bakarak bağlanabiliyor muyuz" sorusunun cevabı
     *        durumun ta kendisidir, dışarıdan verilemez.
     */
    public function __construct(
        private readonly string $basePath,
        private readonly SetupLock $lock,
        private readonly ConfigWriter $configWriter,
        private readonly ?\Closure $baglantiCozucu = null,
    ) {
    }

    /**
     * Tam teşhis.
     *
     * @return array<string, mixed>
     */
    public function analyze(): array
    {
        $dosyalar = $this->dosyaDurumu();
        $config = $this->configDurumu();
        $veritabani = $this->veritabaniDurumu($config);
        $sema = $this->semaDurumu($veritabani['baglanti'] ?? null);
        $kilit = $this->lock->status();
        $surum = $this->surumDurumu($veritabani['baglanti'] ?? null, $sema);

        unset($veritabani['baglanti']); // PDO nesnesi dışarı çıkmaz

        $durum = $this->kararVer($dosyalar, $config, $veritabani, $sema, $kilit, $surum);

        return [
            'durum' => $durum,
            'rozet' => self::ROZET[$durum],
            'baslik' => self::BASLIK[$durum],
            'aciklama' => $this->aciklama($durum, $veritabani, $sema, $surum),
            'secenekler' => $this->secenekler($durum, $surum),
            'dosyalar' => $dosyalar,
            'config' => $config,
            'veritabani' => $veritabani,
            'sema' => $sema,
            'kilit' => $kilit,
            'surum' => $surum,
            'zaman' => date(DATE_ATOM),
        ];
    }

    // ─────────────────────────── durum rozetleri ───────────────────────────

    /** @var array<string, string> */
    private const ROZET = [
        self::KURULUM_YOK => 'nötr',
        self::SAGLIKLI => 'iyi',
        self::YARIM => 'uyarı',
        self::CONFIG_KAYIP => 'uyarı',
        self::DOSYA_EKSIK => 'kötü',
        self::MIGRATION_YARIM => 'uyarı',
        self::SURUM_UYUSMAZLIGI => 'uyarı',
        self::DB_ERISILEMIYOR => 'kötü',
    ];

    /** @var array<string, string> */
    private const BASLIK = [
        self::KURULUM_YOK => 'Kurulum bulunamadı — sıfırdan kurulacak',
        self::SAGLIKLI => 'Sağlıklı bir kurulum bulundu',
        self::YARIM => 'Yarım kalmış bir kurulum bulundu',
        self::CONFIG_KAYIP => 'Ayar dosyası (config.php) kayıp veya bozuk',
        self::DOSYA_EKSIK => 'Paket dosyaları eksik veya bozuk',
        self::MIGRATION_YARIM => 'Veritabanı tabloları eksik',
        self::SURUM_UYUSMAZLIGI => 'Yeni sürüm yüklenmiş — veritabanı güncellenmeli',
        self::DB_ERISILEMIYOR => 'Veritabanına erişilemiyor',
    ];

    // ─────────────────────────── ölçümler ───────────────────────────

    /** @return array<string, mixed> */
    private function dosyaDurumu(): array
    {
        $rapor = (new IntegrityChecker($this->basePath))->check();

        return [
            'manifest_var' => $rapor['manifest_exists'],
            'tamam' => $rapor['ok'],
            'toplam' => $rapor['total'],
            'eksik_sayisi' => $rapor['missing_count'],
            'bozuk_sayisi' => $rapor['modified_count'],
            'eksik' => $rapor['missing'],
            'bozuk' => $rapor['modified'],
        ];
    }

    /**
     * config.php'nin üç hâli: yok · var ama bozuk (alan eksik) · sağlam.
     *
     * @return array<string, mixed>
     */
    private function configDurumu(): array
    {
        $yol = $this->configWriter->path();
        $legacy = $this->configWriter->legacyEnvExists();
        $varMi = $this->configWriter->exists() || $legacy;

        if (!$varMi) {
            return [
                'var' => false,
                'legacy_env' => false,
                'saglam' => false,
                'eksik_alanlar' => ['DB_NAME', 'DB_USER', 'APP_KEY'],
                'app_key_var' => false,
                'yol' => basename($yol),
            ];
        }

        $eksik = [];
        $appKeyVar = ConfigWriter::readAppKey($yol) !== null;

        try {
            $config = Config::load($this->basePath);
            foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $anahtar) {
                if ((string) $config->get($anahtar, '') === '') {
                    $eksik[] = $anahtar;
                }
            }
        } catch (Throwable) {
            // Dosya var ama PHP olarak yüklenemiyor / dizi döndürmüyor: bozuktur.
            $eksik = ['DB_HOST', 'DB_NAME', 'DB_USER'];
        }

        if (!$appKeyVar) {
            $eksik[] = 'APP_KEY';
        }

        return [
            'var' => true,
            'legacy_env' => $legacy && !$this->configWriter->exists(),
            'saglam' => $eksik === [],
            'eksik_alanlar' => $eksik,
            'app_key_var' => $appKeyVar,
            'yol' => basename($yol),
        ];
    }

    /**
     * Veritabanı erişimi — hata İNSAN DİLİNDE sınıflandırılır (emir §8).
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed> `baglanti` anahtarı çağıran için PDO taşır, dışarı verilmez
     */
    private function veritabaniDurumu(array $config): array
    {
        if (($config['var'] ?? false) !== true || ($config['saglam'] ?? false) !== true) {
            return [
                'denendi' => false,
                'erisim' => false,
                'hata_kodu' => null,
                'hata' => null,
                'surum' => null,
                'baglanti' => null,
            ];
        }

        try {
            if ($this->baglantiCozucu !== null) {
                $connection = ($this->baglantiCozucu)();
                $pdo = $connection->pdo();
            } else {
                $pdo = Database::connect(Config::load($this->basePath));
            }
            $pdo->query('SELECT 1');
            $surum = null;
            try {
                $statement = $pdo->query('SELECT VERSION() AS v');
                $row = $statement === false ? false : $statement->fetch();
                if (is_array($row)) {
                    $surum = (string) $row['v'];
                }
            } catch (Throwable) {
                $surum = null; // SQLite'ta VERSION() yoktur; teşhis bunun için durmaz
            }

            return [
                'denendi' => true,
                'erisim' => true,
                'hata_kodu' => null,
                'hata' => null,
                'surum' => $surum,
                'baglanti' => Connection::fromCallable(static fn (): PDO => $pdo),
            ];
        } catch (Throwable $e) {
            $siniflandirma = DatabaseProbe::classify($e->getMessage());

            return [
                'denendi' => true,
                'erisim' => false,
                'hata_kodu' => $siniflandirma['kod'],
                'hata' => $siniflandirma['mesaj'],
                'odak_alan' => $siniflandirma['alan'],
                'surum' => null,
                'baglanti' => null,
            ];
        }
    }

    /**
     * Tablolar ve migration defteri.
     *
     * @return array<string, mixed>
     */
    private function semaDurumu(?Connection $connection): array
    {
        if ($connection === null) {
            return [
                'okundu' => false,
                'tablo_sayisi' => 0,
                'defter_var' => false,
                'uygulanan' => [],
                'bekleyen' => [],
                'dosya_sayisi' => count(glob($this->basePath . '/migrations/[0-9]*.php') ?: []),
            ];
        }

        $tabloSayisi = 0;
        try {
            // SQLite (testler) ile MySQL/MariaDB (üretim) tablo listesini farklı
            // sorar; teşhis ikisinde de AYNI cevabı vermeli.
            $sqlite = $connection->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $statement = $connection->pdo()->query(
                $sqlite ? "SELECT name FROM sqlite_master WHERE type = 'table'" : 'SHOW TABLES',
            );
            $tabloSayisi = $statement === false ? 0 : count($statement->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) {
            $tabloSayisi = 0;
        }

        $uygulanan = [];
        $defterVar = false;
        try {
            $statement = $connection->pdo()->query('SELECT name FROM migrations ORDER BY id');
            if ($statement !== false) {
                $defterVar = true;
                /** @var list<string> $uygulanan */
                $uygulanan = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
            }
        } catch (Throwable) {
            $defterVar = false;
        }

        $bekleyen = [];
        try {
            // Migrator::pending() defteri KENDİSİ kurar; teşhis hiçbir şey yazmamalı,
            // bu yüzden defter yoksa dosya listesinin tamamı "bekliyor" sayılır.
            if ($defterVar) {
                $migrator = new Migrator($connection->pdo(), $this->basePath . '/migrations');
                $bekleyen = $migrator->pending();
            } else {
                foreach (glob($this->basePath . '/migrations/[0-9]*.php') ?: [] as $dosya) {
                    $bekleyen[] = basename($dosya, '.php');
                }
            }
        } catch (Throwable) {
            $bekleyen = [];
        }

        return [
            'okundu' => true,
            'tablo_sayisi' => $tabloSayisi,
            'defter_var' => $defterVar,
            'uygulanan' => $uygulanan,
            'uygulanan_sayisi' => count($uygulanan),
            'bekleyen' => $bekleyen,
            'bekleyen_sayisi' => count($bekleyen),
            'dosya_sayisi' => count(glob($this->basePath . '/migrations/[0-9]*.php') ?: []),
        ];
    }

    /**
     * Dosyadaki sürüm ile veritabanına yazılmış sürüm.
     *
     * @param array<string, mixed> $sema
     *
     * @return array<string, mixed>
     */
    private function surumDurumu(?Connection $connection, array $sema): array
    {
        // Tablo SAYISINA bakılmaz: sayım sürücüye göre başarısız olabilir ve o zaman
        // "sürüm kaydı yok" denip bir GÜNCELLEME yarım kurulum sanılırdı. Doğrudan
        // okumayı deneriz; tablo yoksa istisna zaten yakalanır.
        $kurulu = null;
        if ($connection !== null) {
            try {
                $statement = $connection->pdo()->prepare('SELECT value FROM settings WHERE `key` = :key');
                $statement->execute(['key' => self::SETTING_VERSION]);
                $deger = $statement->fetchColumn();
                $kurulu = is_string($deger) && $deger !== '' ? $deger : null;
            } catch (Throwable) {
                $kurulu = null;
            }
        }

        return [
            'dosya' => AppVersion::VALUE,
            'kurulu' => $kurulu,
            'ayni' => $kurulu !== null && $kurulu === AppVersion::VALUE,
        ];
    }

    // ─────────────────────────── karar ───────────────────────────

    /**
     * Sekiz durumdan hangisi? SIRA ÖNEMLİDİR — en temel arıza önce gelir.
     *
     * @param array<string, mixed> $dosyalar
     * @param array<string, mixed> $config
     * @param array<string, mixed> $veritabani
     * @param array<string, mixed> $sema
     * @param array<string, mixed> $surum
     */
    private function kararVer(
        array $dosyalar,
        array $config,
        array $veritabani,
        array $sema,
        string $kilit,
        array $surum,
    ): string {
        // 5) Dosya bütünlüğü EN BAŞTA: eksik dosyayla yapılan her teşhis şüphelidir.
        // (MANIFEST yoksa — geliştirme kurulumu — bu denetim atlanır.)
        if ($dosyalar['manifest_var'] === true && $dosyalar['tamam'] !== true) {
            return self::DOSYA_EKSIK;
        }

        // 1/4) config yok: gerçekten ilk kurulum olabilir de, config kaybolmuş da
        // olabilir. AYRIM DB BİLGİSİ İSTER, o da elimizde yok — bu yüzden burada
        // "kurulum yok" denir; DB adımında tablolar görülünce akış CONFIG_KAYIP'a
        // döner (SetupController::database bunu bildirir).
        if (($config['var'] ?? false) !== true) {
            return self::KURULUM_YOK;
        }

        // 4) config VAR ama bozuk — alan eksik ya da dosya yüklenemiyor.
        if (($config['saglam'] ?? false) !== true) {
            return self::CONFIG_KAYIP;
        }

        // 8) config sağlam ama bağlanamıyoruz.
        if (($veritabani['erisim'] ?? false) !== true) {
            return self::DB_ERISILEMIYOR;
        }

        $bekleyen = (int) ($sema['bekleyen_sayisi'] ?? 0);
        $tablo = (int) ($sema['tablo_sayisi'] ?? 0);

        // 3) Kilit yok → kurulum bitmemiş. Tablo hiç yoksa da, kısmen varsa da
        // "yarım"dır; kullanıcıya kaldığı yerden devam / baştan başla sunulur.
        if ($kilit !== SetupLock::STATE_LOCKED) {
            return self::YARIM;
        }

        // Buradan aşağısı: KİLİTLİ, yani tamamlanmış bir kurulum.
        if ($bekleyen > 0) {
            // 7) Dosyalar yeni sürüm, DB'deki kayıt eski → bu bir GÜNCELLEMEdir.
            if (($surum['kurulu'] ?? null) !== null && ($surum['ayni'] ?? false) !== true) {
                return self::SURUM_UYUSMAZLIGI;
            }

            // 6) Sürüm kaydı yok ya da aynı ama migration bekliyor → yarım tur.
            return self::MIGRATION_YARIM;
        }

        // Kilitli, bekleyen yok ama tablo da yok: tutarsız — migration yarım sayılır.
        if ($tablo === 0) {
            return self::MIGRATION_YARIM;
        }

        return self::SAGLIKLI;
    }

    /**
     * @param array<string, mixed> $veritabani
     * @param array<string, mixed> $sema
     * @param array<string, mixed> $surum
     */
    private function aciklama(string $durum, array $veritabani, array $sema, array $surum): string
    {
        return match ($durum) {
            self::KURULUM_YOK => 'Ayar dosyası yok ve veritabanı bilgisi elimizde değil. '
                . 'Kuruluma baştan başlanacak; veritabanı adımında mevcut bir kurulum görülürse '
                . 'sihirbaz kendiliğinden "ayar dosyası kayıp" akışına geçer.',
            // İE#22 E1: fark VARSA İKİ DEĞER BİRDEN basılır. Tek sürüm
            // yazmak, "dosya X ama kurulu Y" durumunu görünmez kılıyordu —
            // kullanıcı damganın geride kaldığını hiçbir yerde göremiyordu.
            self::SAGLIKLI => ($surum['ayni'] ?? true)
                ? sprintf(
                    'Kurulum tamam: %d tablo, %d migration uygulanmış, bekleyen yok. Sürüm %s.',
                    (int) ($sema['tablo_sayisi'] ?? 0),
                    (int) ($sema['uygulanan_sayisi'] ?? 0),
                    (string) ($surum['kurulu'] ?? $surum['dosya']),
                )
                : sprintf(
                    'Kurulum tamam: %d tablo, %d migration uygulanmış, bekleyen yok. '
                    . 'Dosya sürümü %s · kurulu sürüm %s — şema güncel, yalnız damga geride.',
                    (int) ($sema['tablo_sayisi'] ?? 0),
                    (int) ($sema['uygulanan_sayisi'] ?? 0),
                    (string) $surum['dosya'],
                    (string) ($surum['kurulu'] ?? 'kayıtsız'),
                ),
            self::YARIM => sprintf(
                'Ayar dosyası yazılmış ama kurulum tamamlanmamış (kurulum kilidi yok). '
                . 'Veritabanında %d tablo var, %d migration bekliyor.',
                (int) ($sema['tablo_sayisi'] ?? 0),
                (int) ($sema['bekleyen_sayisi'] ?? 0),
            ),
            self::CONFIG_KAYIP => 'Ayar dosyası okunamıyor ya da içindeki alanlar eksik. '
                . 'Veritabanı bilgilerini yeniden girmeniz gerekiyor.',
            self::DOSYA_EKSIK => 'Paketin bazı dosyaları sunucuda yok ya da değişmiş. '
                . 'Sihirbaz dosya indirmez; eksikleri aşağıda listeler, siz paketi yeniden yükleyip '
                . 'bu sayfaya dönersiniz.',
            self::MIGRATION_YARIM => sprintf(
                '%d migration uygulanmış, %d tanesi bekliyor. Tablo yapısı eksik olduğu için panel '
                . 'düzgün çalışmaz.',
                (int) ($sema['uygulanan_sayisi'] ?? 0),
                (int) ($sema['bekleyen_sayisi'] ?? 0),
            ),
            self::SURUM_UYUSMAZLIGI => sprintf(
                'Dosyalar %s sürümünde, veritabanı %s sürümünde. %d migration koşulacak.',
                (string) $surum['dosya'],
                (string) ($surum['kurulu'] ?? 'bilinmiyor'),
                (int) ($sema['bekleyen_sayisi'] ?? 0),
            ),
            self::DB_ERISILEMIYOR => (string) ($veritabani['hata'] ?? 'Veritabanına bağlanılamadı.'),
            default => '',
        };
    }

    /**
     * Bu durumda sihirbazın sunacağı yollar. Her biri sihirbaz İÇİNDE çözülür.
     *
     * @return list<array{kod: string, etiket: string, yikici: bool, aciklama: string}>
     */
    /**
     * @param array{kurulu: string|null, dosya: string, ayni: bool}|null $surum
     *
     * @return list<array{kod: string, etiket: string, yikici: bool, aciklama: string}>
     */
    private function secenekler(string $durum, ?array $surum = null): array
    {
        $temizKurulum = [
            'kod' => 'temiz_kurulum',
            'etiket' => 'Temiz kurulum — her şeyi sil, sıfırdan kur',
            'yikici' => true,
            'aciklama' => 'Veritabanındaki TÜM tablolar silinir. Kutuya birebir SIFIRLA yazmanız '
                . 've sahiplik kanıtı vermeniz gerekir.',
        ];

        return match ($durum) {
            self::KURULUM_YOK => [[
                'kod' => 'normal_kurulum',
                'etiket' => 'Kuruluma başla',
                'yikici' => false,
                'aciklama' => 'Yedi adımlık normal kurulum akışı.',
            ]],
            self::SAGLIKLI => array_values(array_filter([
                [
                    'kod' => 'panele_git',
                    'etiket' => 'Panele git',
                    'yikici' => false,
                    'aciklama' => 'Sisteme dokunulmaz.',
                ],
                // İE#22 E1 (Blok H · PM onaylı seçenek B): SAĞLIKLI durumda
                // "dosya sürümü ≠ DB damgası" farkı görmezden geliniyordu.
                // Sekiz durumlu teşhis sözleşmesi (D2-REV) BOZULMAZ: yeni bir
                // DURUM eklenmez, yalnız fark VARKEN görünen bir EYLEM eklenir.
                // Uç zaten mevcut (`POST /api/setup/update` → `surumKaydet()`).
                $surum !== null && $surum['ayni'] === false ? [
                    'kod' => 'damgayi_esitle',
                    'etiket' => 'Sürüm damgasını eşitle',
                    'yikici' => false,
                    'aciklama' => sprintf(
                        'Dosya sürümü %s, veritabanında kayıtlı sürüm %s. Şema güncel; '
                        . 'yalnız kurulu sürüm kaydı geride kalmış. Bu işlem tabloya dokunmaz, '
                        . 'yalnız damgayı günceller.',
                        $surum['dosya'],
                        $surum['kurulu'] ?? 'kayıtsız',
                    ),
                ] : null,
                $temizKurulum,
            ])),
            self::YARIM => [
                [
                    'kod' => 'devam_et',
                    'etiket' => 'Kaldığım adımdan devam et',
                    'yikici' => false,
                    'aciklama' => 'Mevcut ayar dosyası ve tablolar korunur.',
                ],
                $temizKurulum,
            ],
            self::CONFIG_KAYIP => [[
                'kod' => 'config_onar',
                'etiket' => 'Ayar dosyasını yeniden oluştur',
                'yikici' => false,
                'aciklama' => 'Veritabanı bilgileri yeniden sorulur. Dosyada APP_KEY kalmadıysa '
                    . 'YENİ anahtar üretilir — eski anahtarla şifrelenmiş veriler (2FA gizli anahtarı, '
                    . 'API anahtarları) çözülemez ve yeniden girilmeleri gerekir.',
            ]],
            self::DOSYA_EKSIK => [[
                'kod' => 'yeniden_tara',
                'etiket' => 'Paketi yükledim — yeniden tara',
                'yikici' => false,
                'aciklama' => 'Dosyaları yeniden yükledikten sonra bütünlük taraması tekrarlanır.',
            ]],
            self::MIGRATION_YARIM => [
                [
                    'kod' => 'bekleyenleri_tamamla',
                    'etiket' => 'Bekleyen migration\'ları tamamla',
                    'yikici' => false,
                    'aciklama' => 'Yalnız eksik tablolar kurulur; mevcut veriye dokunulmaz.',
                ],
                $temizKurulum,
            ],
            self::SURUM_UYUSMAZLIGI => [
                [
                    'kod' => 'guncelle',
                    'etiket' => 'Güncellemeyi çalıştır',
                    'yikici' => false,
                    'aciklama' => 'Bekleyen migration\'lar koşar, sürüm kaydı tazelenir. Veri korunur.',
                ],
                $temizKurulum,
            ],
            self::DB_ERISILEMIYOR => [[
                'kod' => 'db_bilgilerini_duzelt',
                'etiket' => 'Veritabanı bilgilerini düzelt',
                'yikici' => false,
                'aciklama' => 'Yeni bilgiler test edilir ve ayar dosyası APP_KEY KORUNARAK yeniden yazılır.',
            ]],
            default => [],
        };
    }
}
