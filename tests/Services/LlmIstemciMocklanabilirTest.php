<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Core\Encrypter;
use App\Models\SettingsRepository;
use App\Models\TranslationCacheRepository;
use App\Services\Translation\CeviriAyarlari;
use App\Services\Translation\Glossary;
use App\Services\Translation\LayeredTranslator;
use App\Services\Translation\LlmIstemciInterface;
use App\Services\Translation\LlmTranslator;
use App\Services\Translation\TranslationService;
use App\Services\Translation\TranslatorInterface;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\Support\FrozenClock;

/**
 * İE#22 E2 — LLM HATTI ARTIK GERÇEK MOCK'LA SINANIYOR.
 *
 * D12'nin kanıt turunda şu engelle karşılaşıldı: `LlmIstemci` `final` olduğu
 * için sağlayıcı yanıtı taklit edilemiyordu; akışı sınamanın tek yolu ya
 * üretim koduna geçici yama atmak ya da çevirmenin TAMAMINI taklit etmekti.
 * İkincisi sınanan kodu atlar — `LlmTranslator`ın yanıt çözme, sözlükle
 * harmanlama ve önbelleğe yazma davranışı hiç çalışmaz.
 *
 * Arayüz sayesinde artık YALNIZ EN DIŞTAKİ AĞ ÇAĞRISI taklit ediliyor;
 * gerisi gerçek kod. Sınanan üç şey:
 *   1. LLM yanıtı önbelleğe `llm:*` sağlayıcısıyla yazılır (kalıcı satır, K91),
 *   2. sağlayıcı patlarsa yedek katmana düşülür ve çeviri KAYBOLMAZ,
 *   3. istenen diller isteğe doğru taşınır.
 */
final class LlmIstemciMocklanabilirTest extends TestCase
{
    private PDO $pdo;
    private Connection $connection;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NULL)');
        $this->pdo->exec(
            'CREATE TABLE translation_cache (
                source_hash TEXT PRIMARY KEY, source_text TEXT NOT NULL, suggested_text TEXT NOT NULL,
                provider TEXT NOT NULL, source_lang TEXT NOT NULL, target_lang TEXT NOT NULL,
                surum TEXT NULL, guven TEXT NULL, created_at TEXT NOT NULL
            )',
        );
        $this->connection = Connection::fromCallable(fn (): PDO => $this->pdo);
        $this->clock = new FrozenClock();
    }

    private function ayarlar(): CeviriAyarlari
    {
        $depo = new SettingsRepository($this->connection);
        $ayarlar = new CeviriAyarlari($depo, new Encrypter($this->sahteConfig()));
        $ayarlar->saglayiciKaydet('deepseek');
        $ayarlar->anahtariKaydet('sk-test-anahtar');

        return $ayarlar;
    }

    private function sahteConfig(): \App\Core\Config
    {
        // Encrypter yalnız APP_KEY okur; kurulum dosyası gerekmez.
        return new \App\Core\Config([
            'APP_ENV' => 'local',
            'APP_URL' => 'https://tedarikapp.test',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test',
            'DB_USER' => 'root',
            'TZ' => 'Europe/Istanbul',
            'APP_KEY' => str_repeat('a', 64),
        ]);
    }

    private function cevirmen(LlmIstemciInterface $istemci): TranslatorInterface
    {
        $sozluk = new Glossary(dirname(__DIR__, 2) . '/config');
        $onbellek = new TranslationCacheRepository($this->connection);
        $makine = new TranslationService(
            $onbellek,
            // Makine katmani bu suitte KAPALI (enabled: false); istemci
            // yalniz tip uyumu icin verilir, aga cikmaz.
            new \App\Services\Translation\MyMemoryTranslator(new \App\Services\UrlGuard(['api.mymemory.translated.net'])),
            $this->clock,
            new NullLogger(),
            false,
            'zh',
            'tr',
            $sozluk,
        );

        return new LlmTranslator(
            $sozluk,
            $this->ayarlar(),
            $istemci,
            $onbellek,
            $this->clock,
            new NullLogger(),
            new LayeredTranslator($sozluk, $makine),
        );
    }

    /** @return list<array{provider: string, target_lang: string, suggested_text: string}> */
    private function onbellekSatirlari(): array
    {
        $satirlar = $this->pdo->query(
            'SELECT provider, target_lang, suggested_text FROM translation_cache ORDER BY target_lang',
        )->fetchAll();

        return array_map(static fn (array $s): array => [
            'provider' => (string) $s['provider'],
            'target_lang' => (string) $s['target_lang'],
            'suggested_text' => (string) $s['suggested_text'],
        ], $satirlar ?: []);
    }

    public function testLLMYANITIONBELLEGEKALICIYAZILIR(): void
    {
        $istemci = new SahteLlmIstemci(json_encode([
            'ceviriler' => [
                'tr' => ['name' => 'Pedalsız Denge Bisikleti'],
                'en' => ['name' => 'Pedal-free Balance Bike'],
            ],
        ], JSON_UNESCAPED_UNICODE) ?: '{}');

        $this->cevirmen($istemci)->translateProduct([
            'name' => '无脚踏平衡车',
            'category' => null,
            'source_lang' => 'zh',
            'target_langs' => ['tr', 'en'],
            'attributes' => [],
        ]);

        $satirlar = $this->onbellekSatirlari();
        self::assertNotEmpty($satirlar, 'LLM yanıtı önbelleğe yazılmalı.');
        foreach ($satirlar as $satir) {
            self::assertStringStartsWith(
                'llm:',
                $satir['provider'],
                'Sağlayıcı `llm:*` olmalı — K91 kalıcılık ölçütü buna bakıyor.',
            );
        }
    }

    public function testISTENENDILLERISTEGETASINIR(): void
    {
        $istemci = new SahteLlmIstemci('{"ceviriler":{"en":{"name":"Balance Bike"}}}');

        $this->cevirmen($istemci)->translateProduct([
            'name' => '无脚踏平衡车',
            'category' => null,
            'source_lang' => 'zh',
            'target_langs' => ['en'],
            'attributes' => [],
        ]);

        self::assertStringContainsString('EN', $istemci->sonSistemIstemi, 'Hedef dil isteme yazılmalı.');
        self::assertStringContainsString('无脚踏平衡车', $istemci->sonKullaniciIstemi, 'Çevrilecek metin isteğe girmeli.');
    }

    public function testSAGLAYICIPATLARSAYEDEKKATMANADUSULUR(): void
    {
        // Ağ hatası çeviriyi TAMAMEN kaybettirmemeli: sözlük+makine yedeği
        // devreye girer ve akış sürer (K56).
        $sonuc = $this->cevirmen(new PatlayanLlmIstemci())->translateProduct([
            'name' => '无脚踏平衡车',
            'category' => null,
            'source_lang' => 'zh',
            'target_langs' => ['tr'],
            'attributes' => [],
        ]);

        self::assertIsArray($sonuc, 'Yedek katman bir sonuç döndürmeli — istisna sızmamalı.');
    }
}

/** Ağ çağrısının YERİNE geçer; ne sorulduğunu kaydeder. */
final class SahteLlmIstemci implements LlmIstemciInterface
{
    public string $sonSistemIstemi = '';
    public string $sonKullaniciIstemi = '';

    public function __construct(private readonly string $yanit)
    {
    }

    public function sor(
        string $saglayici,
        #[\SensitiveParameter] string $apiAnahtari,
        string $model,
        string $sistemIstemi,
        string $kullaniciIstemi,
    ): string {
        $this->sonSistemIstemi = $sistemIstemi;
        $this->sonKullaniciIstemi = $kullaniciIstemi;

        return $this->yanit;
    }
}

/** Sağlayıcı arızası. */
final class PatlayanLlmIstemci implements LlmIstemciInterface
{
    public function sor(
        string $saglayici,
        #[\SensitiveParameter] string $apiAnahtari,
        string $model,
        string $sistemIstemi,
        string $kullaniciIstemi,
    ): string {
        throw new RuntimeException('sağlayıcı 503');
    }
}
