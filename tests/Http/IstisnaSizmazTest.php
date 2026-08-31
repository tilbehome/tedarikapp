<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\AuthTestCase;

/**
 * SERTLEŞTİRME v1.2.1 BLOK C3/C4 — TEKNİK İSTİSNA KULLANICIYA SIZMAZ (TDR-012, TDR-019).
 *
 * İKİ KUSUR, TEK KÖK:
 *
 * 1. `KesifController::index()` HER `Throwable`ı yakalayıp `200 OK` +
 *    `kurulu: false` döndürüyordu. Yani bozuk bir SQL, düşen bir bağlantı ya
 *    da bir program hatası, kullanıcıya "tablolar hazır değil" diye
 *    gösteriliyordu. Gerçek arıza, bekleyen bir migration gibi görünüyordu ve
 *    kimse doğru yere bakmıyordu.
 *
 * 2. Aynı yanıt `'hata' => $e->getMessage()` taşıyordu: ham SQLSTATE metni,
 *    tablo/kolon adları ve kimi sürücülerde dosya yolları DIŞARI ÇIKIYORDU.
 *    Bu, saldırgana şema haritası verir; üstelik 200 OK ile, yani hiçbir hata
 *    izleyicisi de uyarmaz.
 *
 * YENİ SÖZLEŞME:
 *   · YALNIZ doğrulanmış "tablo yok" (SQLSTATE 42S02) `kurulu: false` üretir,
 *   · başka her hata 500 + SABİT mesaj + HATA KİMLİĞİ,
 *   · ayrıntı yalnız sunucu günlüğüne (K51 disiplini).
 */
final class IstisnaSizmazTest extends AuthTestCase
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

    public function testTABLOYOKSAKURULUFALSE(): void
    {
        // Gerçek "tablolar hazır değil" hâli: keşif tablosu düşürülür.
        $this->pdo->exec('DROP TABLE IF EXISTS listings');

        $yanit = $this->call('GET', '/api/kesif');
        $govde = $this->json($yanit)['data'];

        self::assertSame(200, $yanit->getStatusCode());
        self::assertFalse($govde['kurulu'], 'Tablo gerçekten yoksa ekran çökmemeli.');
    }

    public function testHAMISTISNAMETNIYANITASIZMAZ(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS listings');

        $govde = (string) $this->call('GET', '/api/kesif')->getBody();

        self::assertArrayNotHasKey(
            'hata',
            $this->json($this->call('GET', '/api/kesif'))['data'],
            'Ham istisna metni yanıtta TAŞINMAMALI.',
        );
        foreach (['SQLSTATE', 'no such table', 'listings', 'C:\\', '/var/www'] as $sizinti) {
            self::assertStringNotContainsString(
                $sizinti,
                $govde,
                'Teknik ayrıntı (' . $sizinti . ') kullanıcıya sızıyor.',
            );
        }
    }

    public function testTABLOYOKDISINDAKIHATA500DONER(): void
    {
        // "Tablo yok" DIŞINDA bir arıza: kolon eksik (SQLSTATE 42S22 / HY000).
        // Eskiden bu da 200 + "kurulu:false" idi ve gerçek arıza görünmezdi.
        $this->pdo->exec('DROP TABLE IF EXISTS listings');
        $this->pdo->exec('CREATE TABLE listings (id INTEGER PRIMARY KEY)');

        $yanit = $this->call('GET', '/api/kesif');

        self::assertSame(500, $yanit->getStatusCode(), 'Gerçek arıza "tablolar hazır değil" diye gizlenmemeli.');
        self::assertStringNotContainsString('SQLSTATE', (string) $yanit->getBody());
    }

    public function testHATAKIMLIGIVERILIR(): void
    {
        // Kullanıcıya teknik metin verilmez ama DESTEK İÇİN bir tutamak gerekir:
        // günlükteki satırla eşleşen kısa bir kimlik. Kimlik olmadan
        // "bir şeyler ters gitti" mesajı hiçbir şeye yaramaz.
        $this->pdo->exec('DROP TABLE IF EXISTS listings');
        $this->pdo->exec('CREATE TABLE listings (id INTEGER PRIMARY KEY)');

        $govde = $this->json($this->call('GET', '/api/kesif'));

        self::assertArrayHasKey('hata_kimligi', $govde['error'] ?? [], json_encode($govde));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8,}$/', (string) $govde['error']['hata_kimligi']);
    }
}
