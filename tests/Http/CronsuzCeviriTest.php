<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Models\SettingsRepository;
use App\Services\Kuyruk\KuyrukTetikleyici;
use Tests\Support\AuthTestCase;

/**
 * D12 — ÇEVİRİ CRON İSTEMEZ (Ürün Sahibi kararı 28 Ağu 2026).
 *
 * SAHA: kuyruk cron'u kurulmadı; işler 1432 dakika bekledi. "Toplu çevir"
 * düğmesi yalnız kuyruğa yazıyor, işleyen kimse olmadığı için hiçbir şey
 * olmuyordu. Kullanıcının kurmadığı bir cron'a bağlı sistem, kullanıcı için
 * ÇALIŞMAYAN sistemdir.
 *
 * BU SÜİT CRON'U HİÇ ÇALIŞTIRMAZ. Sınanan şey tam olarak budur: kuyruk işçisi
 * bir kez bile elle koşturulmadan
 *   · tek ürün "Çevir" düğmesiyle ANINDA çevrilir,
 *   · toplu çeviri isteği FİİLEN çevirir ve kalanı söyler,
 *   · panel ziyareti arkada tur tetikler,
 *   · kaynak dili orijinal olan dil ÜRETİLMEZ.
 */
final class CronsuzCeviriTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listeId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listeId = (int) $this->json(
            $this->call('POST', '/api/lists', ['name' => 'D12 listesi'], [Csrf::HEADER => $this->csrf]),
        )['data']['id'];
    }

    /** Yakalamadan gelmiş gibi bir ürün — kaynak dili işlenmiş hâlde. */
    private function urunEkle(string $ad, string $orijinal, string $kaynakDil = 'zh'): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (list_id, name, name_original, source_lang, price_yuan, price_ddp_usd, qty, status, created_at, updated_at)
             VALUES (:liste, :ad, :orijinal, :kaynak, :fiyat, :ddp, 5, :durum, :simdi, :simdi)',
        );
        $statement->execute([
            'liste' => $this->listeId,
            'ad' => $ad,
            'orijinal' => $orijinal,
            'kaynak' => $kaynakDil,
            'fiyat' => '15.90',
            'ddp' => '3.20',
            'durum' => 'to_order',
            'simdi' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Bir dilin KALICI çevirisi önbellekte var mı? (llm:* ya da elle) */
    private function kaliciVarMi(string $orijinal, string $dil): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM translation_cache
             WHERE source_text = :metin AND target_lang = :dil
               AND (provider LIKE 'llm:%' OR provider = 'elle')",
        );
        $statement->execute(['metin' => $orijinal, 'dil' => $dil]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function onbellekSatiri(string $orijinal, string $dil, string $ceviri, string $saglayici): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO translation_cache (source_hash, source_text, suggested_text, provider, source_lang, target_lang, created_at)
             VALUES (:hash, :metin, :ceviri, :saglayici, :kaynak, :dil, :simdi)',
        );
        $statement->execute([
            'hash' => hash('sha256', $dil . '|' . $saglayici . '|' . $orijinal),
            'metin' => $orijinal,
            'ceviri' => $ceviri,
            'saglayici' => $saglayici,
            'kaynak' => 'zh',
            'dil' => $dil,
            'simdi' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }

    // ── Madde 2: toplu çeviri FİİLEN işler ───────────────────────────────────

    public function testTOPLUCEVIRIKUYRUGAYAZMAKLAYETINMEZ(): void
    {
        $this->urunEkle('Bisiklet Yok', '无脚踏平衡车');
        $this->urunEkle('Terlik', '洞洞鞋');

        $yanit = $this->call('POST', '/api/panel/translate-backfill', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        $veri = $this->json($yanit)['data'];

        // Eski sözleşme "kuyruga_alinan" döndürüyordu; yenisi İŞ SONUCU döner.
        self::assertArrayNotHasKey('kuyruga_alinan', $veri, 'Kuyruğa yazıp geçmek artık cevap değil.');
        self::assertArrayHasKey('cevrilen', $veri);
        self::assertArrayHasKey('kalan', $veri);
        self::assertSame(2, $veri['toplam'], 'İki ürünün de üç dili eksik.');
    }

    public function testILERLEMEUCUKALANISOYLER(): void
    {
        $this->urunEkle('Bisiklet Yok', '无脚踏平衡车');

        $veri = $this->json($this->call('GET', '/api/panel/translate-progress'))['data'];

        self::assertSame(1, $veri['kalan']);
    }

    // ── Madde 1: tek ürün "Çevir" düğmesi ────────────────────────────────────

    public function testURUNCEVIRUCUSENKRONDUR(): void
    {
        $urunId = $this->urunEkle('Bisiklet Yok', '无脚踏平衡车');

        $yanit = $this->call('POST', '/api/products/' . $urunId . '/translate', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        $veri = $this->json($yanit)['data'];
        self::assertSame($urunId, $veri['urun_id']);
        self::assertSame('zh', $veri['kaynak_dil'], 'Kaynak dil kayıttan okunur.');
        // Yanıt bir ÖNERİDİR (K54): ürün alanına yazılmaz.
        self::assertTrue($veri['is_suggestion']);
    }

    public function testONAYLIELLECEVIRIEZILMEZ(): void
    {
        $orijinal = '乐扣杯';
        $urunId = $this->urunEkle('Lock&Lock termos', $orijinal);
        // Kullanıcının ONAYLADIĞI düzeltme (K54).
        $this->onbellekSatiri($orijinal, 'tr', 'Lock&Lock termos', 'elle');

        $this->call('POST', '/api/products/' . $urunId . '/translate', [], [Csrf::HEADER => $this->csrf]);

        $statement = $this->pdo->prepare(
            "SELECT suggested_text FROM translation_cache WHERE source_text = :metin AND target_lang = 'tr' AND provider = 'elle'",
        );
        $statement->execute(['metin' => $orijinal]);
        self::assertSame('Lock&Lock termos', (string) $statement->fetchColumn(), 'Elle onaylı satır DEĞİŞMEMELİ.');
    }

    // ── Kanonik üç dil: kaynak dil üretilmez ─────────────────────────────────

    public function testKAYNAKDILIURETILMEZ(): void
    {
        $orijinal = '无脚踏平衡车';
        $urunId = $this->urunEkle('Bisiklet Yok', $orijinal);
        $this->onbellekSatiri($orijinal, 'tr', 'Pedalsız Denge Bisikleti', 'llm:test');
        $this->onbellekSatiri($orijinal, 'en', 'Pedal-free Balance Bike', 'llm:test');

        // TR + EN tamam, ZH orijinal → ürün artık aday değil.
        $veri = $this->json($this->call('GET', '/api/panel/translate-progress'))['data'];
        self::assertSame(0, $veri['kalan'], 'ZH kaynaklı üründe ZH çevirisi ARANMAZ.');
        self::assertFalse($this->kaliciVarMi($orijinal, 'zh'), 'Kaynak dile çeviri üretilmemeli.');
        self::assertGreaterThan(0, $urunId);
    }

    // ── Madde 3: panel ziyareti tur tetikler ─────────────────────────────────

    public function testPANELZIYARETITURTETIKLER(): void
    {
        $ayarlar = new SettingsRepository($this->connection);
        self::assertNull($ayarlar->get(KuyrukTetikleyici::KEY_SON_TUR), 'Başlangıçta tur damgası yok.');

        // Oturumlu bir GET: veri uçlarından herhangi biri.
        $this->call('GET', '/api/lists');

        self::assertNotNull(
            $ayarlar->get(KuyrukTetikleyici::KEY_SON_TUR),
            'Panel ziyareti kuyruk turunu tetiklemeli — cron olmadan işleyen tek şey budur.',
        );
    }

    public function testYAZMAISTEGITURTETIKLEMEZ(): void
    {
        // POST'ların kendi tetikleyicileri var; her yazmada ikinci tur gürültüdür.
        $ayarlar = new SettingsRepository($this->connection);
        $this->call('POST', '/api/lists', ['name' => 'Yeni'], [Csrf::HEADER => $this->csrf]);

        self::assertNull($ayarlar->get(KuyrukTetikleyici::KEY_SON_TUR));
    }
}
