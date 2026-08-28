<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;

/**
 * settings tablosu — anahtar/değer ayarları (docs/04 §2).
 *
 * Kur burada tutulur; liste OLUŞTURULURKEN o anki değer listeye kopyalanır ve
 * `sent` durumunda kilitlenir (K4). Ayar değişmesi eski listelerin TL'sini etkilemez.
 */
final class SettingsRepository
{
    public const KEY_YUAN_RATE = 'yuan_tl';
    /** İE#11: eklenti token'ı — DB'de yalnız SHA-256 hash + tanıma için son 4 hane (K34). */
    public const KEY_EXTENSION_TOKEN_HASH = 'extension_token_hash';
    public const KEY_EXTENSION_TOKEN_PREVIEW = 'extension_token_preview';
    public const KEY_USD_RATE = 'usd_tl';

    /**
     * İE#13 F1 — Belge Antedi: çıktıların (Excel/PDF) ve paylaşım sayfasının üst
     * bandında görünen firma kimliği. BOŞ ALAN BASILMAZ; hiçbiri girilmemişse antet
     * yalnız liste bilgisini gösterir.
     */
    public const KEY_DOC_COMPANY = 'doc_company_name';
    public const KEY_DOC_WEB = 'doc_company_web';
    public const KEY_DOC_EMAIL = 'doc_company_email';
    public const KEY_DOC_PREPARED_BY = 'doc_prepared_by';

    /**
     * İE#21 EK-4 — PAYLAŞIM İLETİŞİM NUMARASI (B7).
     *
     * Kilit ekranındaki "yeni anahtar iste" düğmesi bu numaraya WhatsApp
     * köprüsü açar. BOŞ BIRAKILABİLİR: numara yoksa düğme basılmaz ve ekran
     * bilgi satırıyla yetinir (zarif bozulma). K44: `config.php`ye girmez,
     * ayar tablosunda yaşar.
     */
    public const KEY_SHARE_CONTACT_PHONE = 'share_contact_phone';

    /**
     * Ayar henüz girilmemişken kullanılan başlangıç değerleri.
     * Gerçek değerler `PUT /api/settings/rates` ile girilir (ayarlar iş emri);
     * o güne kadar liste oluşturulabilsin diye makul bir başlangıç verilir.
     */
    public const DEFAULT_YUAN_RATE = '7.0400';
    public const DEFAULT_USD_RATE = '41.5000';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Belge antedi alanları (İE#13 F1) — boşlar null döner, çıktıda basılmaz.
     *
     * @return array{company: string|null, web: string|null, email: string|null, prepared_by: string|null}
     */
    public function documentHeader(): array
    {
        $oku = function (string $key): ?string {
            $deger = $this->get($key);
            $deger = is_string($deger) ? trim($deger) : '';

            return $deger === '' ? null : $deger;
        };

        return [
            'company' => $oku(self::KEY_DOC_COMPANY),
            'web' => $oku(self::KEY_DOC_WEB),
            'email' => $oku(self::KEY_DOC_EMAIL),
            'prepared_by' => $oku(self::KEY_DOC_PREPARED_BY),
        ];
    }

    /**
     * Paylaşım iletişim numarası — YALNIZ RAKAM, boşsa null.
     *
     * wa.me yalnız ülke kodlu, işaretsiz rakam dizisi kabul eder ("905321234567").
     * Kullanıcı "+90 532 123 45 67" yazabilsin diye temizleme OKUMA anında da
     * yapılır: ayarı elle düzenleyen (ya da eski kayıt taşıyan) kurulumda düğme
     * bozuk bir bağlantı üretmemelidir.
     */
    public function shareContactPhone(): ?string
    {
        $ham = $this->get(self::KEY_SHARE_CONTACT_PHONE);
        $rakam = preg_replace('/\D+/', '', is_string($ham) ? $ham : '') ?? '';

        return $rakam === '' ? null : $rakam;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $statement = $this->connection->pdo()->prepare('SELECT value FROM settings WHERE `key` = :key');
        $statement->execute(['key' => $key]);
        $row = $statement->fetch();

        if (!is_array($row) || $row['value'] === null) {
            return $default;
        }

        return (string) $row['value'];
    }

    public function set(string $key, string $value): void
    {
        $pdo = $this->connection->pdo();

        // MySQL ve SQLite'ta ortak çalışan yol: önce güncelle, etkilenen satır yoksa ekle.
        $update = $pdo->prepare('UPDATE settings SET value = :value WHERE `key` = :key');
        $update->execute(['key' => $key, 'value' => $value]);

        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT 1 FROM settings WHERE `key` = :key');
            $exists->execute(['key' => $key]);
            if ($exists->fetch() === false) {
                $insert = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (:key, :value)');
                $insert->execute(['key' => $key, 'value' => $value]);
            }
        }
    }

    /**
     * K44 disksiz mod: Config'in DB katmanı — settings tablosundaki BÜYÜK_HARF anahtarlar
     * (APP_URL, LOG_DRIVER, TZ, MEDIA_* …) uygulama yapılandırması olarak okunur.
     * `media_mode` gibi küçük-harf iç ayarlar bilinçli olarak DIŞARIDA kalır.
     *
     * @return array<string, string>
     */
    public static function configOverrides(Connection $connection): array
    {
        $statement = $connection->pdo()->query('SELECT `key`, value FROM settings');
        if ($statement === false) {
            return [];
        }

        $overrides = [];
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $row) {
            $key = (string) $row['key'];
            if (preg_match('/^[A-Z][A-Z0-9_]+$/', $key) === 1 && is_string($row['value'])) {
                $overrides[$key] = $row['value'];
            }
        }

        return $overrides;
    }

    /**
     * GÜNCEL YUAN KURU — İE#22 A2: TEK OKUMA NOKTASI.
     *
     * Önce AKTİF SNAPSHOT (`rate_snapshots.superseded_at IS NULL`), yoksa
     * ayardaki kopya. Sıra bilinçlidir: snapshot tablosu kurun sürümlü
     * gerçeğidir; `settings.yuan_tl` artık ondan TÜRETİLMİŞ kopyadır ve
     * yalnız iki durumda okunur — migration henüz koşmadıysa ya da tablo
     * bir sebeple okunamıyorsa. Böylece yirmiden fazla çağrı yeri
     * DEĞİŞMEDEN yeni omurgaya bağlanır.
     */
    public function yuanRate(): string
    {
        return $this->snapshotDegeri('CNY')
            ?? $this->get(self::KEY_YUAN_RATE, self::DEFAULT_YUAN_RATE)
            ?? self::DEFAULT_YUAN_RATE;
    }

    public function usdRate(): string
    {
        return $this->snapshotDegeri('USD')
            ?? $this->get(self::KEY_USD_RATE, self::DEFAULT_USD_RATE)
            ?? self::DEFAULT_USD_RATE;
    }

    /** Aktif snapshot değeri; tablo yoksa/boşsa null (çağıran kopyaya düşer). */
    private function snapshotDegeri(string $currency): ?string
    {
        $deger = (new RateSnapshotRepository($this->connection))->aktifDeger($currency);

        return is_string($deger) && trim($deger) !== '' ? $deger : null;
    }
}
