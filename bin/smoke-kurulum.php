<?php

declare(strict_types=1);

/**
 * DİSKSİZ KURULUM SMOKE'U (K44, İE#9.4 Görev 4) — yalnızca CLI.
 *
 * Sihirbazı GERÇEK HTTP üzerinden uçtan uca sürer (requirements → database →
 * config.php → migrate → admin+TOTP → finish) ve ardından panel girişinin
 * OTURUMUNUN KORUNDUĞUNU kanıtlar (login → totp → me → me).
 *
 * Sunucu `-d session.save_path=/yok-boyle-dizin` ile başlatıldığında bile TAM
 * akmalıdır: sihirbaz state'i şifreli çerezde, panel oturumu DB'dedir (sessions
 * tablosu). CI `uretim-profili` job'ı bu senaryo yeşil olmadan release vermez.
 *
 * Kullanım:
 *   php bin/smoke-kurulum.php <base-url> <db-host> <db-port> <db-name> <db-user> <db-pass>
 */

use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$base = $argv[1] ?? 'http://127.0.0.1:8080';
$dbHost = $argv[2] ?? '127.0.0.1';
$dbPort = $argv[3] ?? '3306';
$dbName = $argv[4] ?? 'tedarikapp_smoke';
$dbUser = $argv[5] ?? 'root';
$dbPass = $argv[6] ?? '';

$cookieJar = tempnam(sys_get_temp_dir(), 'tedarikapp-smoke');
$csrf = '';

/**
 * @param array<string, mixed>|null $payload
 * @param array<string, string> $headers
 *
 * @return array{status: int, body: array<string, mixed>}
 */
function istek(string $method, string $path, ?array $payload = null, array $headers = []): array
{
    global $base, $cookieJar;
    $ch = curl_init($base . $path);
    $headerLines = ['Accept: application/json'];
    if ($payload !== null) {
        $headerLines[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;

    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : ['raw' => substr((string) $raw, 0, 300)]];
}

/**
 * @param array{status: int, body: array<string, mixed>} $sonuc
 *
 * @return array<string, mixed>
 */
function adim(string $etiket, array $sonuc, int $beklenen = 200): array
{
    $tamam = $sonuc['status'] === $beklenen;
    printf("[%s] %-32s HTTP %d\n", $tamam ? 'OK' : 'HATA', $etiket, $sonuc['status']);
    if (!$tamam) {
        fwrite(STDERR, json_encode($sonuc['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }

    return $sonuc['body'];
}

// ── Sihirbaz: uçtan uca (state şifreli ÇEREZDE — save_path'e dokunulmaz) ──
$state = adim('setup/state', istek('GET', '/api/setup/state'));
$csrf = (string) $state['data']['csrf_token'];
$h = ['X-Setup-Token' => $csrf];

adim('setup/requirements', istek('GET', '/api/setup/requirements'));
adim('setup/database', istek('POST', '/api/setup/database', [
    'host' => $dbHost, 'port' => $dbPort, 'name' => $dbName, 'user' => $dbUser, 'pass' => $dbPass,
], $h));
$env = adim('setup/env (config.php)', istek('POST', '/api/setup/env', ['app_url' => $base], $h));
if (($env['data']['manual'] ?? false) === true) {
    // Yazılamaz kök: içerik ekrandan alınır — smoke ortamında dosyayı biz kaydederiz.
    file_put_contents(dirname(__DIR__) . '/config.php', $env['data']['content']);
    adim('setup/env/verify', istek('POST', '/api/setup/env/verify', [], $h));
}
$migrate = adim('setup/migrate', istek('POST', '/api/setup/migrate', [], $h));
printf("     migration: %d uygulandı\n", count($migrate['data']['applied']));

$adminEmail = 'smoke@tedarikapp.test';
$adminPass = 'smoke-kurulum-sifresi-1';
$admin = adim('setup/admin', istek('POST', '/api/setup/admin', ['email' => $adminEmail, 'password' => $adminPass], $h));
$secret = (string) $admin['data']['manual_key'];
$totp = new TwoFactorAuth(new BaconQrCodeProvider(format: 'svg'), 'tedarikapp');
adim('setup/admin/verify', istek('POST', '/api/setup/admin/verify', ['code' => $totp->getCode($secret)], $h));
adim('setup/finish', istek('POST', '/api/setup/finish', ['codes_saved' => true], $h));
adim('kilit: /api/setup/state 403', istek('GET', '/api/setup/state'), 403);

// ── Panel: login sonrası OTURUM KORUNUYOR mu? (DB session — sessions tablosu) ──
adim('auth/login', istek('POST', '/api/auth/login', ['email' => $adminEmail, 'password' => $adminPass]));
adim('auth/totp', istek('POST', '/api/auth/totp', ['code' => $totp->getCode($secret)]));
$me1 = adim('auth/me (1. istek)', istek('GET', '/api/auth/me'));
$me2 = adim('auth/me (2. istek — oturum kalıcı)', istek('GET', '/api/auth/me'));
if (($me2['data']['user']['email'] ?? '') !== $adminEmail) {
    fwrite(STDERR, "HATA: oturum ikinci istekte korunmadı (DB session çalışmıyor).\n");
    exit(1);
}

// Kanıt: oturum GERÇEKTEN veritabanında mı?
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$count = (int) $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
printf("[%s] %-32s %d kayıt\n", $count > 0 ? 'OK' : 'HATA', 'sessions tablosu (DB oturumu)', $count);
if ($count < 1) {
    exit(1);
}

$integrity = adim('system/integrity', istek('GET', '/api/system/integrity'));
printf("     integrity: ok=%s\n", var_export($integrity['data']['ok'], true));

echo "\nDİSKSİZ SMOKE TAMAM: sihirbaz uçtan uca + login oturumu DB'de korunuyor.\n";
exit(0);
