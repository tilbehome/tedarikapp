<?php

declare(strict_types=1);

namespace App\Setup;

use App\Auth\PasswordHasher;
use App\Auth\TotpService;
use App\Core\Connection;
use App\Core\Dates;
use App\Services\ActivityLog;
use DateTimeImmutable;
use DateTimeZone;
use SensitiveParameter;

/**
 * Kilit kaldırma kapısı (K46, İE#9.5) — K45'in "çıkmaz sokak olmasın" amacı KORUNUR,
 * işlem SAHİPLİK KANITI ister: config.php'deki APP_KEY'in tam değeri.
 *
 * K45'teki hâliyle `/api/setup/unlock` kimliksizdi — internetteki herkes kilidi silip
 * sihirbazı yeniden açabilirdi (K34/K37 ihlali). APP_KEY'i yalnız sunucu dosyalarını
 * okuyabilen sahibi bilir (File Manager) — WordPress'in "dosyaya erişimin varsa
 * sahipsin" modeliyle aynı varsayım.
 *
 * Kaba kuvvet: yanlış anahtar denemeleri IP bazlı ARTAN beklemeye tabidir
 * (LoginThrottle mantığı; sayaç activity_log üzerinde yaşar — denetim kaydı ile
 * kilit mantığı tek gerçek). Her deneme loglanır; APP_KEY asla loglanmaz.
 */
final class UnlockGate
{
    public const ACTION_SUCCESS = 'setup_unlock';
    public const ACTION_FAILED = 'setup_unlock_failed';

    /** C5: kimliksiz sahiplik sorgusu — tarama izi. */
    public const ACTION_OWNER_CHECK = 'owner_check';

    private const ENTITY = 'setup';
    private const MAX_ATTEMPTS = 3;
    private const BASE_LOCK_MINUTES = 1;
    private const MAX_LOCK_MINUTES = 60;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $basePath,
        private readonly DateTimeZone $timezone,
    ) {
    }

    /** Bu IP için kalan bekleme (saniye). 0 → deneme işlenebilir. */
    public function retryAfterSeconds(string $ip, DateTimeImmutable $now): int
    {
        $failures = $this->failuresSinceLastSuccess($ip);
        $count = count($failures);
        if ($count < self::MAX_ATTEMPTS) {
            return 0;
        }

        $minutes = self::BASE_LOCK_MINUTES;
        for ($i = self::MAX_ATTEMPTS; $i < $count && $minutes < self::MAX_LOCK_MINUTES; $i++) {
            $minutes *= 2;
        }
        $minutes = min($minutes, self::MAX_LOCK_MINUTES);

        $lastFailure = Dates::fromStorage($failures[$count - 1], $this->timezone);
        $lockUntil = $lastFailure->modify(sprintf('+%d minutes', $minutes));

        return max(0, $lockUntil->getTimestamp() - $now->getTimestamp());
    }

    /**
     * Sahiplik kanıtı: verilen değer config.php'deki (veya legacy .env'deki)
     * APP_KEY ile SABİT ZAMANLI karşılaştırmada eşleşiyor mu?
     */
    public function proofValid(#[SensitiveParameter] ?string $appKeyInput): bool
    {
        if (!is_string($appKeyInput)) {
            return false;
        }
        $appKeyInput = trim($appKeyInput);
        if (preg_match('/^[0-9a-f]{64}$/i', $appKeyInput) !== 1) {
            return false;
        }

        $expected = $this->configuredAppKey();

        return $expected !== null && hash_equals(strtolower($expected), strtolower($appKeyInput));
    }

    /** Sunucudaki gerçek APP_KEY: önce config.php, geriye dönük .env. */
    public function configuredAppKey(): ?string
    {
        $fromConfig = ConfigWriter::readAppKey($this->basePath . '/config.php');
        if ($fromConfig !== null) {
            return $fromConfig;
        }

        $envPath = $this->basePath . '/.env';
        if (!is_file($envPath)) {
            return null;
        }
        $content = @file_get_contents($envPath);
        if ($content === false) {
            return null;
        }
        if (preg_match('/^APP_KEY=("?)([0-9a-f]{64})\1\s*$/mi', $content, $match) === 1) {
            return $match[2];
        }

        return null;
    }

    /**
     * BİRİNCİL SAHİPLİK YOLU (İE#20 D2-REV): yönetici e-postası + şifresi.
     *
     * APP_KEY yolu doğruydu ama insanlık dışıydı: kullanıcıyı File Manager'a
     * gönderip 64 haneli bir diziyi kopyalatıyordu. Sistemi kuran kişi kendi
     * şifresini zaten biliyor; kanıt olarak ondan güçlüsü de yok. APP_KEY yolu
     * KALDIRILMADI — veritabanı okunabiliyor ama hesap erişilemiyor olabilir
     * (şifre unutuldu, 2FA cihazı kayıp); o zaman dosya sahipliği tek kanıttır.
     *
     * İE#21 B14 — 2FA ARTIK SORULUR (hesapta ETKİNSE). Önceki gerekçe ("bu bir
     * oturum açma değil") yarım doğruydu: işlem oturum üretmiyor ama YIKICI bir
     * yola (temiz kurulum) kapı açıyor. Panele girmek için iki faktör isteyip
     * veritabanını silmek için tek faktör istemek, korumayı en zayıf halkasından
     * delmek olurdu. 2FA tanımlı DEĞİLSE kod sorulmaz — olmayan bir faktörü
     * dayatmak kullanıcıyı kilitler.
     *
     * @param Connection|null $connection hedef veritabanı (config kayıpken sihirbazın
     *                                    yeni girilen bilgilerle açtığı bağlantı)
     */
    public function adminProofValid(
        ?string $email,
        #[SensitiveParameter] ?string $password,
        ?Connection $connection = null,
        ?string $totpKodu = null,
        ?TotpService $totp = null,
    ): bool {
        if (!is_string($email) || !is_string($password) || trim($email) === '' || $password === '') {
            return false;
        }

        $pdo = ($connection ?? $this->connection)->pdo();

        try {
            $statement = $pdo->prepare('SELECT password_hash, totp_secret FROM users WHERE email = :email');
            $statement->execute(['email' => trim(strtolower($email))]);
            $satir = $statement->fetch();
        } catch (\Throwable) {
            return false;
        }

        $hash = is_array($satir) && is_string($satir['password_hash'] ?? null) ? $satir['password_hash'] : '';

        if ($hash === '') {
            // Kullanıcı yoksa da HASH DOĞRULAMASI KOŞULUR: aksi hâlde yanıt süresi
            // "bu e-posta kayıtlı mı" sorusunu sızdırırdı (kullanıcı sayımı).
            password_verify($password, '$2y$12$' . str_repeat('x', 53));

            return false;
        }

        if (!(new PasswordHasher())->verify($password, $hash)) {
            return false;
        }

        // B14: hesapta 2FA tanımlıysa kod ZORUNLUDUR.
        $secret = is_array($satir) && is_string($satir['totp_secret'] ?? null) ? $satir['totp_secret'] : '';
        if ($secret === '') {
            return true;
        }
        if ($totp === null) {
            // Doğrulayıcı verilmemişse 2FA'lı hesap GEÇEMEZ (fail-closed): eksik
            // bağımlılık bir korumayı sessizce devre dışı bırakmamalı.
            return false;
        }

        return is_string($totpKodu) && $totpKodu !== '' && $totp->verify($secret, trim($totpKodu));
    }

    /** Bu e-postanın hesabında 2FA tanımlı mı? (arayüz kod alanını buna göre gösterir) */
    public function ikiAdimliGerekliMi(?string $email, ?Connection $connection = null): bool
    {
        if (!is_string($email) || trim($email) === '') {
            return false;
        }

        try {
            $statement = ($connection ?? $this->connection)->pdo()
                ->prepare('SELECT totp_secret FROM users WHERE email = :email');
            $statement->execute(['email' => trim(strtolower($email))]);
            $secret = $statement->fetchColumn();
        } catch (\Throwable) {
            return false;
        }

        return is_string($secret) && $secret !== '';
    }

    /**
     * SAHİPLİK SORGUSU İZİ (v1.2.1 C5) — kimliksiz uçtan gelen tarama görünsün.
     *
     * E-POSTA HAM YAZILMAZ: iz gerekli ama günlüğe adres dökmek, günlüğü
     * okuyabilen birine hazır bir hesap listesi verir (K51). Kısa özet, aynı
     * adresin tekrar tekrar sorulduğunu göstermeye yeter.
     */
    public function recordOwnerCheck(string $ip, ?string $email, DateTimeImmutable $now): void
    {
        $ozet = is_string($email) && trim($email) !== ''
            ? substr(hash('sha256', strtolower(trim($email))), 0, 12)
            : 'bos';

        $this->record(self::ACTION_OWNER_CHECK, 'Sahiplik sorgusu · eposta_ozeti:' . $ozet, $ip, $now);
    }

    public function recordFailure(string $ip, DateTimeImmutable $now): void
    {
        $this->record(self::ACTION_FAILED, 'Kilit kaldırma: sahiplik kanıtı GEÇERSİZ', $ip, $now);
    }

    public function recordSuccess(string $ip, DateTimeImmutable $now, string $method): void
    {
        $this->record(self::ACTION_SUCCESS, 'Kilit kaldırıldı (' . $method . ')', $ip, $now);
    }

    private function record(string $action, string $detail, string $ip, DateTimeImmutable $now): void
    {
        try {
            (new ActivityLog($this->connection))->record(
                self::ENTITY,
                null,
                $action,
                $detail,
                $ip,
                $now,
                ActivityLog::ACTOR_SYSTEM,
                null,
            );
        } catch (\Throwable) {
            // Denetim kaydı yazılamıyorsa (tablo yoksa) kapı yine de karar verebilir;
            // ama kilitli sistemde tablolar tamdır — pratikte buraya düşülmez.
        }
    }

    /** @return list<string> son başarılı kaldırmadan sonraki hatalı denemelerin zamanları */
    private function failuresSinceLastSuccess(string $ip): array
    {
        try {
            $pdo = $this->connection->pdo();

            $successStatement = $pdo->prepare(
                'SELECT MAX(id) AS last_success_id FROM activity_log
                 WHERE entity_type = :entity_type AND action = :action AND ip = :ip',
            );
            $successStatement->execute([
                'entity_type' => self::ENTITY,
                'action' => self::ACTION_SUCCESS,
                'ip' => $ip,
            ]);
            $row = $successStatement->fetch();
            $sinceId = is_array($row) && $row['last_success_id'] !== null ? (int) $row['last_success_id'] : 0;

            $failureStatement = $pdo->prepare(
                'SELECT created_at FROM activity_log
                 WHERE entity_type = :entity_type AND action = :action AND ip = :ip AND id > :since_id
                 ORDER BY id',
            );
            $failureStatement->execute([
                'entity_type' => self::ENTITY,
                'action' => self::ACTION_FAILED,
                'ip' => $ip,
                'since_id' => $sinceId,
            ]);

            /** @var list<array<string, mixed>> $rows */
            $rows = $failureStatement->fetchAll();

            return array_map(static fn (array $r): string => (string) $r['created_at'], $rows);
        } catch (\Throwable) {
            return []; // tablo yoksa (kurulum öncesi) kilit de yoktur; kapı proof'a düşer
        }
    }
}
