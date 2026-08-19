<?php

declare(strict_types=1);

/**
 * GELİŞTİRME ARACI — yalnızca lokal test içindir.
 *
 * Üretimde ilk kullanıcı KURULUM SİHİRBAZI tarafından oluşturulur (İE#5, K16).
 * Bu betik sihirbazın yerine geçmez; sihirbaz devreye girdiğinde bu araç yalnızca
 * geliştirici makinesinde test kullanıcısı açmak için kullanılmaya devam eder.
 *
 * Kullanım:
 *   php bin/user-create.php --email=admin@example.com
 *   php bin/user-create.php --email=admin@example.com --password="en az 10 karakter"
 *   php bin/user-create.php --email=e2e@test --password=... --no-totp
 *
 * Şifre argümanla verilmezse ekrandan istenir (kabuk geçmişine düşmemesi için tercih edilir).
 *
 * `--no-totp` (İE#13 Blok E): 2FA'sız kullanıcı açar — K45 ile 2FA opsiyoneldir ve
 * E2E süiti girişi tek adımda sürer. ÜRETİMDE KULLANMAYIN: ikinci faktör güvenliğin
 * bir katmanıdır; bu bayrak test tohumlaması içindir.
 */

use App\Auth\PasswordHasher;
use App\Auth\RecoveryCodeService;
use App\Auth\TotpService;
use App\Auth\UserRepository;
use App\Core\AsciiQrCode;
use App\Core\Config;
use App\Core\Connection;
use App\Core\Database;
use App\Core\Encrypter;
use App\Core\SystemClock;
use App\Services\ActivityLog;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

/** @param list<string> $argv */
function readOption(array $argv, string $name): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $argument) {
        if (str_starts_with($argument, $prefix)) {
            return substr($argument, strlen($prefix));
        }
    }

    return null;
}

function prompt(string $question): string
{
    fwrite(STDOUT, $question);
    $line = fgets(STDIN);

    return $line === false ? '' : trim($line);
}

try {
    $config = Config::load($basePath);
    date_default_timezone_set($config->get('TZ', 'Europe/Istanbul'));

    /** @var list<string> $arguments */
    $arguments = $argv;

    $email = readOption($arguments, 'email') ?? prompt('E-posta: ');
    $email = trim($email);
    if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        fwrite(STDERR, "HATA: Geçerli bir e-posta adresi girin (en çok 190 karakter).\n");
        exit(1);
    }

    $password = readOption($arguments, 'password') ?? prompt('Şifre (en az ' . PasswordHasher::MIN_LENGTH . " karakter): ");
    if (strlen($password) < PasswordHasher::MIN_LENGTH) {
        fwrite(STDERR, sprintf("HATA: Şifre en az %d karakter olmalı (docs/04 §2d).\n", PasswordHasher::MIN_LENGTH));
        exit(1);
    }

    $connection = Connection::fromCallable(static fn (): PDO => Database::connect($config));
    $clock = SystemClock::fromConfig($config);
    $now = $clock->now();

    $users = new UserRepository($connection);
    if ($users->findByEmail($email) !== null) {
        fwrite(STDERR, "HATA: Bu e-posta ile bir kullanıcı zaten var.\n");
        exit(1);
    }

    $hasher = new PasswordHasher();
    $totp = new TotpService($config, new Encrypter($config), $clock);
    $recoveryCodes = new RecoveryCodeService($connection, $hasher);

    $totpsuz = in_array('--no-totp', $arguments, true);
    $secret = $totpsuz ? null : $totp->createSecret();
    $userId = $users->create(
        $email,
        $hasher->hash($password),
        $secret === null ? null : $totp->encryptSecret($secret),
        $now,
    );

    $codes = $recoveryCodes->generate();
    $recoveryCodes->replaceForUser($userId, $codes);

    (new ActivityLog($connection))->recordAuth(ActivityLog::USER_CREATED, $email, 'cli', $now, $userId);

    if ($totpsuz) {
        echo PHP_EOL;
        echo "Kullanıcı oluşturuldu (2FA KAPALI — yalnız test): {$email} (id: {$userId})" . PHP_EOL;
        echo PHP_EOL;
        echo 'Kurtarma kodları:' . PHP_EOL;
        foreach ($codes as $index => $code) {
            echo sprintf('   %2d) %s', $index + 1, $code) . PHP_EOL;
        }
        exit(0);
    }

    $uri = $totp->provisioningUri($email, $secret);

    echo PHP_EOL;
    echo "Kullanıcı oluşturuldu: {$email} (id: {$userId})" . PHP_EOL;
    echo PHP_EOL;
    echo '1) Authenticator uygulamanızla aşağıdaki QR kodunu okutun:' . PHP_EOL . PHP_EOL;
    echo AsciiQrCode::render($uri) . PHP_EOL . PHP_EOL;
    echo 'QR okutulamazsa kurulum bağlantısı:' . PHP_EOL;
    echo $uri . PHP_EOL;
    echo PHP_EOL;
    echo '2) Kurtarma kodları — BU EKRANDA BİR KEZ GÖSTERİLİR, güvenli bir yere kaydedin:' . PHP_EOL . PHP_EOL;
    foreach ($codes as $index => $code) {
        echo sprintf('   %2d) %s', $index + 1, $code) . PHP_EOL;
    }
    echo PHP_EOL;
    echo 'Telefonunuzu kaybederseniz giriş yapmanın TEK yolu bu kodlardır (e-posta kurtarma kapalı — K8).' . PHP_EOL;

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
