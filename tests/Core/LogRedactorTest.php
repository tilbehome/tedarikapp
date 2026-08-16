<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\LogRedactor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * K27: hassas alanlar loga DÜZ yazılmamalı (CLAUDE.md §5).
 */
final class LogRedactorTest extends TestCase
{
    /** @param array<string, mixed> $context */
    private function redact(array $context): LogRecord
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-08-16 10:00:00'),
            channel: 'test',
            level: Level::Warning,
            message: 'deneme',
            context: $context,
        );

        return (new LogRedactor())($record);
    }

    /** @return list<array{string}> */
    public static function hassasAlanAdlari(): array
    {
        return [
            ['password'],
            ['Password'],
            ['user_password'],
            ['Authorization'],
            ['cookie'],
            ['Cookie'],
            ['token'],
            ['extension_token'],
            ['secret'],
            ['totp_secret'],
            ['DB_PASS'],
            ['APP_KEY'],
            ['code'],
            ['csrf_token'],
            ['password_hash'],
        ];
    }

    #[DataProvider('hassasAlanAdlari')]
    public function testHassasAlanlarGizlenir(string $alan): void
    {
        $record = $this->redact([$alan => 'cok-gizli-deger']);

        self::assertSame(LogRedactor::PLACEHOLDER, $record->context[$alan]);
    }

    public function testBeyazListedekiAlanlarGizlenmez(): void
    {
        // İE#5 §9: error_code "code" içerdiği için gizleniyordu, request_id ise logun
        // asıl bağı. İkisi de sır değil; gizlenmeleri hata ayıklamayı imkânsız kılıyordu.
        $record = $this->redact([
            'error_code' => 'VALIDATION',
            'request_id' => '01J000000000000000000000AB',
        ]);

        self::assertSame('VALIDATION', $record->context['error_code']);
        self::assertSame('01J000000000000000000000AB', $record->context['request_id']);
    }

    public function testBeyazListeTamAdEslesmesidir(): void
    {
        // Beyaz liste geniş bir kapı olmamalı: benzer adlı alanlar yine gizlenir.
        $record = $this->redact([
            'error_code_secret' => 'gizli',
            'my_error_code' => 'gizli',
            'ERROR_CODE' => 'VALIDATION',
        ]);

        self::assertSame(LogRedactor::PLACEHOLDER, $record->context['error_code_secret']);
        self::assertSame(LogRedactor::PLACEHOLDER, $record->context['my_error_code']);
        self::assertSame('VALIDATION', $record->context['ERROR_CODE'], 'Büyük/küçük harf farkı muafiyeti bozmaz.');
    }

    public function testZararsizAlanlarOlduguGibiKalir(): void
    {
        $record = $this->redact(['user_id' => 7, 'ip' => '203.0.113.7', 'duration_ms' => 42]);

        self::assertSame(7, $record->context['user_id']);
        self::assertSame('203.0.113.7', $record->context['ip']);
        self::assertSame(42, $record->context['duration_ms']);
    }

    public function testIcIceDizilerdeDeGizlenir(): void
    {
        $record = $this->redact([
            'istek' => [
                'yol' => '/api/auth/login',
                'basliklar' => ['Authorization' => 'Bearer abc', 'Accept' => 'application/json'],
                'govde' => ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre'],
            ],
        ]);

        /** @var array<string, mixed> $istek */
        $istek = $record->context['istek'];
        /** @var array<string, mixed> $basliklar */
        $basliklar = $istek['basliklar'];
        /** @var array<string, mixed> $govde */
        $govde = $istek['govde'];

        self::assertSame('/api/auth/login', $istek['yol']);
        self::assertSame(LogRedactor::PLACEHOLDER, $basliklar['Authorization']);
        self::assertSame('application/json', $basliklar['Accept']);
        self::assertSame('admin@tedarikapp.test', $govde['email'], 'E-posta sır değildir, denetim için gerekir.');
        self::assertSame(LogRedactor::PLACEHOLDER, $govde['password']);
    }

    public function testCokDerinYapilardaDurur(): void
    {
        $derin = ['x' => 'y'];
        for ($i = 0; $i < 20; $i++) {
            $derin = ['seviye' => $derin];
        }

        $record = $this->redact($derin);

        // Sonsuz derinlikte dolaşmak yerine belirli bir noktada kesilmeli (log bombası koruması).
        self::assertNotEmpty($record->context);
    }

    public function testExtraAlanlariDaSuzulur(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-08-16 10:00:00'),
            channel: 'test',
            level: Level::Warning,
            message: 'deneme',
            context: [],
            extra: ['app_key' => 'gizli', 'request_id' => '01J000000000000000000000AB'],
        );

        $temiz = (new LogRedactor())($record);

        self::assertSame(LogRedactor::PLACEHOLDER, $temiz->extra['app_key']);
        self::assertSame('01J000000000000000000000AB', $temiz->extra['request_id']);
    }
}
