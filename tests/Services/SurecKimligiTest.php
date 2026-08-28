<?php

declare(strict_types=1);

namespace App\Services\Kuyruk;

/**
 * `disable_functions` BENZETİMİ (D7 saha bulgusu, 25 Ağu 2026).
 *
 * `JobRunner` içindeki `function_exists(...)` çağrıları NİTELİKSİZDİR; PHP önce
 * bu ad alanına bakar. Buraya konan sürüm, testin kapattığı adlar için `false`
 * döner — paylaşımlı hostingin `disable_functions` davranışı böyle, gerçek
 * sunucuya gitmeden, taklit edilebilir.
 *
 * Diğer bütün adlarda gerçek fonksiyona devredilir: benzetim yalnız sınanan
 * ismi etkiler, kuyruk kodunun geri kalanı normal çalışır.
 *
 * @param callable-string|string $ad
 */
function function_exists(string $ad): bool
{
    if (in_array($ad, \Tests\Services\SurecKimligiTest::$kapaliFonksiyonlar, true)) {
        return false;
    }

    return \function_exists($ad);
}

namespace Tests\Services;

use App\Services\Kuyruk\JobRunner;
use PHPUnit\Framework\TestCase;

/**
 * D7 — KUYRUK İŞLEYİCİ KİMLİĞİ SÜREÇ FONKSİYONLARINA GÜVENMEZ.
 *
 * SAHA BULGUSU (MegaTR, ea-php83 CLI): `bin/kuyruk.php` her turda
 * "Call to undefined function App\Services\Kuyruk\getmypid()" ile ölüyordu;
 * paylaşımlı hosting `disable_functions` ile `getmypid`'i kapatmış. Sonuç:
 * kuyruk cron'dan HİÇ işlemedi — yani "kuyruk var" demek "kuyruk çalışıyor"
 * demek değilmiş.
 *
 * Kimlikten beklenen tek şey kira sahipliğinde BENZERSİZLİKTİR; gerçek PID şart
 * değildir. Bu süit üç şeyi kilitler: fonksiyon varken PID kullanılır, yokken
 * hata YERİNE rastgele kimlik üretilir, üretilen kimlikler çakışmaz.
 */
final class SurecKimligiTest extends TestCase
{
    /** @var list<string> Benzetimde "kapalı" sayılan fonksiyon adları. */
    public static array $kapaliFonksiyonlar = [];

    protected function tearDown(): void
    {
        self::$kapaliFonksiyonlar = [];
    }

    public function testFONKSIYONVARSAPIDKULLANILIR(): void
    {
        $kimlik = JobRunner::surecKimligi();

        // Gerçek PID okunabiliyorsa kimlik onu taşır: aynı sürecin iki turu aynı
        // kimliği kullanır, log okunur kalır.
        self::assertMatchesRegularExpression('/^.+:\d+$/', $kimlik);
        self::assertStringEndsWith(':' . getmypid(), $kimlik);
    }

    public function testGETMYPIDKAPALIYSAHATAYERINEKIMLIKURETILIR(): void
    {
        self::$kapaliFonksiyonlar = ['getmypid', 'posix_getpid'];

        // Asıl kabul: ÖLÜMCÜL HATA YOK. Canlıda kuyruğu durduran şey buydu.
        $kimlik = JobRunner::surecKimligi();

        self::assertNotSame('', $kimlik);
        // Kimlik "PID yok" olduğunu SÖYLER — log okuyan kişi olmayan bir PID aramasın.
        self::assertMatchesRegularExpression('/^.+:x[0-9a-f]{16}$/', $kimlik);
    }

    public function testPOSIXVARSAGETMYPIDYOKKENONAKULLANILIR(): void
    {
        if (!\function_exists('posix_getpid')) {
            self::markTestSkipped('posix eklentisi bu ortamda yok — yedek yol sınanamaz.');
        }

        self::$kapaliFonksiyonlar = ['getmypid'];

        self::assertStringEndsWith(':' . posix_getpid(), JobRunner::surecKimligi());
    }

    public function testKAPALIORTAMDAKIMLIKLERCAKISMAZ(): void
    {
        self::$kapaliFonksiyonlar = ['getmypid', 'posix_getpid'];

        $kimlikler = [];
        for ($i = 0; $i < 50; $i++) {
            $kimlikler[] = JobRunner::surecKimligi();
        }

        // Benzersizlik kiranın TEK gereğidir: iki işleyici aynı kimliği
        // kullanırsa biri diğerinin işini "kendi işi" sanır.
        self::assertCount(50, array_unique($kimlikler));
    }

    public function testGETHOSTNAMEKAPALIYSAKIMLIKYINEURETILIR(): void
    {
        self::$kapaliFonksiyonlar = ['gethostname', 'getmypid', 'posix_getpid'];

        self::assertStringStartsWith('cron:x', JobRunner::surecKimligi());
    }
}
