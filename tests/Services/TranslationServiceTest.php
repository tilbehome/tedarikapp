<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\TranslationCacheRepository;
use App\Services\Translation\TranslationClient;
use App\Services\Translation\TranslationService;
use Psr\Log\NullLogger;
use Tests\Support\AuthTestCase;

/**
 * Çeviri önerisi servisi (İE#13 Blok C — K54).
 *
 * KRİTİK kurallar: öneri hiçbir alana yazılmaz (servis yalnız metin döndürür);
 * aynı metin ikinci kez sağlayıcıya SORULMAZ; her hata yolu null döner ve akış
 * bloklanmaz; sağlayıcının kota uyarısı çeviri sanılmaz.
 */
final class TranslationServiceTest extends AuthTestCase
{
    private function service(TranslationClient $client, bool $enabled = true): TranslationService
    {
        return new TranslationService(
            new TranslationCacheRepository($this->connection),
            $client,
            $this->clock,
            new NullLogger(),
            $enabled,
        );
    }

    /** @param string|null $result sağlayıcının döndüreceği metin */
    private function client(?string $result, ?\Throwable $throw = null): TranslationClient
    {
        return new class ($result, $throw) implements TranslationClient {
            public int $cagriSayisi = 0;

            public function __construct(private readonly ?string $result, private readonly ?\Throwable $throw)
            {
            }

            public function translate(string $text, string $sourceLang, string $targetLang): ?string
            {
                $this->cagriSayisi++;
                if ($this->throw !== null) {
                    throw $this->throw;
                }

                return $this->result;
            }

            public function name(): string
            {
                return 'sahte';
            }
        };
    }

    public function testOneriUretilirVeOnbellegeYazilir(): void
    {
        $client = $this->client('Taşınabilir meyve sıkacağı');
        $result = $this->service($client)->suggest('便携式榨汁机');

        self::assertSame('Taşınabilir meyve sıkacağı', $result['suggestion']);
        self::assertFalse($result['cached']);
        self::assertSame('sahte', $result['provider']);
        self::assertSame(
            1,
            (int) $this->pdo->query('SELECT COUNT(*) FROM translation_cache')->fetchColumn(),
        );
    }

    public function testAyniMetinIkinciKezSaglayiciyaSORULMAZ(): void
    {
        $client = $this->client('Taşınabilir meyve sıkacağı');
        $service = $this->service($client);

        $service->suggest('便携式榨汁机');
        $ikinci = $service->suggest('  便携式榨汁机  '); // boşluk farkı önbelleği ıskalamaz

        self::assertTrue($ikinci['cached']);
        self::assertSame('Taşınabilir meyve sıkacağı', $ikinci['suggestion']);
        self::assertSame(1, $client->cagriSayisi);
    }

    public function testSaglayiciYanitVermezseONERI_YOK_amaAkisBloklanmaz(): void
    {
        $sonuc = $this->service($this->client(null))->suggest('便携式榨汁机');

        self::assertNull($sonuc['suggestion']);
        self::assertSame(
            0,
            (int) $this->pdo->query('SELECT COUNT(*) FROM translation_cache')->fetchColumn(),
            'Başarısızlık önbelleğe yazılmamalı: geçici kesinti kalıcı "öneri yok"a dönmemeli.',
        );
    }

    public function testSaglayiciPATLARSA_istisna_disari_sizmaz(): void
    {
        $sonuc = $this->service($this->client(null, new \RuntimeException('ağ yok')))->suggest('便携式榨汁机');

        self::assertNull($sonuc['suggestion']);
    }

    public function testKapaliyken_agaCIKILMAZ(): void
    {
        $client = $this->client('X');
        $sonuc = $this->service($client, false)->suggest('便携式榨汁机');

        self::assertNull($sonuc['suggestion']);
        self::assertSame(0, $client->cagriSayisi);
    }

    public function testCokUzunMetinOnerilmez(): void
    {
        $client = $this->client('X');
        $sonuc = $this->service($client)->suggest(str_repeat('字', TranslationService::MAX_LENGTH + 1));

        self::assertNull($sonuc['suggestion']);
        self::assertSame(0, $client->cagriSayisi, 'Kota koruması: sınırı aşan metin sağlayıcıya gitmez.');
    }
}
