<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Models\TranslationCacheRepository;
use App\Services\Translation\SozluksuzCeviriSayaci;
use Tests\Support\AuthTestCase;

/**
 * SERTLEŞTİRME v1.2.1 A6-EK — SİSTEM DURUMU KARTI VE "YENİDEN ÇEVİR" DÜĞMESİ.
 *
 * UÇTAN UCA: sayacın doğru sayması yetmez; sayının API'den GEÇMESİ ve düğmenin
 * ürünleri GERÇEKTEN kuyruğa alması gerekir. Aradaki bir kopukluk (rota yok,
 * kuyruk enjekte edilmemiş) birim testinde görünmezdi — kart sayı gösterir,
 * düğme hiçbir şey yapmazdı.
 */
final class SozluksuzCeviriKartiTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
    }

    private function sayac(): SozluksuzCeviriSayaci
    {
        return new SozluksuzCeviriSayaci($this->connection, $this->config(), dirname(__DIR__, 2));
    }

    private function bozukCevrilmisUrun(string $orijinal): int
    {
        $this->pdo->exec(
            "INSERT INTO lists (name, yuan_rate, usd_rate, created_at, updated_at)
             VALUES ('L', '7', '41', '2026-08-31', '2026-08-31')",
        );
        $listId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO products (list_id, name, name_original, created_at, updated_at)
             VALUES (:l, 'Ürün', :o, '2026-08-31', '2026-08-31')",
        )->execute(['l' => $listId, 'o' => $orijinal]);
        $urunId = (int) $this->pdo->lastInsertId();

        $bozuk = $this->sayac()->bozukAnahtar();
        $this->pdo->prepare(
            'INSERT INTO translation_cache
                (source_hash, source_lang, target_lang, source_text, suggested_text, provider, surum, created_at)
             VALUES (:h, "zh", "tr", :st, "çeviri", "llm", :v, "2026-08-31 12:00:00")',
        )->execute([
            'h' => TranslationCacheRepository::hash($orijinal, 'zh', 'tr', $bozuk),
            'st' => $orijinal,
            'v' => $bozuk,
        ]);

        return $urunId;
    }

    public function testSTATUSSAYIYIDONER(): void
    {
        // v1.2.1 F: `/api/system/status` artık SQLite altında da çalışıyor
        // (`VERSION()` süs bilgisi olarak ele alındı), bu yüzden sözleşme
        // KAYNAK TARAMASIYLA değil GERÇEK YANITLA sınanıyor.
        $yanit = $this->call('GET', '/api/system/status');
        $govde = $this->json($yanit)['data'] ?? null;

        self::assertIsArray($govde, (string) $yanit->getBody());
        self::assertArrayHasKey('sozluksuz_ceviri', $govde);
        self::assertSame(0, $govde['sozluksuz_ceviri'], 'Temiz kurulumda sayı 0 — panel kartı gizler.');
    }

    public function testSTATUSSAYIYIGUNCELLER(): void
    {
        $this->bozukCevrilmisUrun('不锈钢杯');

        $govde = $this->json($this->call('GET', '/api/system/status'))['data'];

        self::assertSame(1, $govde['sozluksuz_ceviri']);
    }

    public function testSTATUSSURUMOKUNAMAZSADUSMEZ(): void
    {
        // Tek bir süs alanı (DB sürümü) yüzünden bütün teşhis ekranını
        // düşürmek orantısızdı; SQLite'ta uç TAMAMEN erişilemezdi.
        $yanit = $this->call('GET', '/api/system/status');

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        self::assertNull($this->json($yanit)['data']['db_version'], 'Okunamayan sürüm null kalır, ekran çalışır.');
    }

    public function testDUGMEURUNLERIKUYRUGAALIR(): void
    {
        $urunId = $this->bozukCevrilmisUrun('折叠伞');

        $yanit = $this->call('POST', '/api/system/sozluksuz-ceviri-yenile', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        self::assertSame(1, $this->json($yanit)['data']['kuyruga_alinan']);

        $kuyruktaki = $this->pdo->query(
            "SELECT anahtar FROM jobs WHERE tur = 'ceviri'",
        )->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['urun:' . $urunId], $kuyruktaki, 'Ürün MEVCUT çeviri kuyruğuna alınmalı.');
    }

    public function testIKIKEZBASMAKIKIISACMAZ(): void
    {
        // İş anahtarı idempotenttir; kullanıcı düğmeye üst üste basabilir.
        $this->bozukCevrilmisUrun('保温杯');

        $this->call('POST', '/api/system/sozluksuz-ceviri-yenile', [], [Csrf::HEADER => $this->csrf]);
        $this->call('POST', '/api/system/sozluksuz-ceviri-yenile', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM jobs WHERE tur = 'ceviri'")->fetchColumn(),
        );
    }

    public function testUCOTURUMVECSRFISTER(): void
    {
        self::assertSame(
            403,
            $this->call('POST', '/api/system/sozluksuz-ceviri-yenile')->getStatusCode(),
            'CSRF şart — bu uç kuyruğa iş yazar.',
        );

        $this->call('POST', '/api/auth/logout', [], [Csrf::HEADER => $this->csrf]);
        self::assertSame(
            401,
            $this->call('POST', '/api/system/sozluksuz-ceviri-yenile', [], [Csrf::HEADER => $this->csrf])->getStatusCode(),
        );
    }

    public function testVERISILINMEZ(): void
    {
        // Eski (öksüz) önbellek satırları KALIR: silmek, sağlayıcı geri
        // alınırsa geri dönüşü imkânsız kılardı ve hiçbir şey kazandırmaz.
        $this->bozukCevrilmisUrun('雨伞');
        $once = (int) $this->pdo->query('SELECT COUNT(*) FROM translation_cache')->fetchColumn();

        $this->call('POST', '/api/system/sozluksuz-ceviri-yenile', [], [Csrf::HEADER => $this->csrf]);

        self::assertSame(
            $once,
            (int) $this->pdo->query('SELECT COUNT(*) FROM translation_cache')->fetchColumn(),
        );
    }
}
