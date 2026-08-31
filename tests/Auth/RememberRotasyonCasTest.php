<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\RememberTokenService;
use App\Core\Connection;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * SERTLEŞTİRME v1.2.1 BLOK D4 — "BENİ HATIRLA" ROTASYONU TEK KAZANANLI (TDR-022).
 *
 * KORUNAN FELAKET: rotasyon `WHERE id = ? AND user_id = ?` ile yazıyordu.
 * Çalınmış bir çerezle saldırgan ve gerçek kullanıcı AYNI ANDA istek atarsa
 * İKİSİ DE rotasyonu başarılı sayar ve İKİSİ DE geçerli yeni çerez alır —
 * çünkü koşul, ellerindeki token'ın HÂLÂ GEÇERLİ olduğunu denetlemiyordu,
 * yalnız satırın var olduğunu.
 *
 * Rotasyonun bütün amacı budur: çalınan bir token kullanıldığı anda ölmeli ve
 * yalnız BİR taraf devam edebilmeli. İkisi de devam ederse hırsızlık hiç fark
 * edilmez — asıl kullanıcı çıkış yapmaz, saldırgan da atılmaz.
 *
 * YENİ SÖZLEŞME: `WHERE id AND user_id AND selector AND token_hash` — yani
 * "elimdeki token hâlâ bu satırdaki token mı?". Kaybeden `null` alır ve
 * güvenli biçimde yeniden kimlik doğrulamaya düşer.
 */
final class RememberRotasyonCasTest extends TestCase
{
    private PDO $pdo;
    private RememberTokenService $servis;
    private DateTimeImmutable $simdi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Migration MySQL'e özgü DDL taşır; SQLite eşdeğeri AuthTestCase'teki
        // şemanın aynısıdır (kolonlar birebir).
        $this->pdo->exec(
            'CREATE TABLE remember_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                selector TEXT NOT NULL UNIQUE,
                token_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
        );

        $this->servis = new RememberTokenService(Connection::fromCallable(fn (): PDO => $this->pdo));
        $this->simdi = new DateTimeImmutable('2026-08-31 12:00:00');
    }

    /** @return array{id: int, cerez: string} */
    private function tokenAc(): array
    {
        $sonuc = $this->servis->issue(7, $this->simdi, 60 * 24);

        return ['id' => (int) $sonuc['id'], 'cerez' => (string) $sonuc['cookie']];
    }

    /**
     * Çerezdeki selector ve doğrulayıcı.
     *
     * @return array{0: string, 1: string}
     */
    private function parcala(string $cerez): array
    {
        return explode(':', $cerez, 2);
    }

    public function testGECERLITOKENROTASYONUGECER(): void
    {
        ['id' => $id, 'cerez' => $cerez] = $this->tokenAc();
        [$selector, $validator] = $this->parcala($cerez);

        $yeni = $this->servis->rotate($id, 7, $this->simdi, $selector, $validator);

        self::assertIsString($yeni);
        self::assertNotSame($cerez, $yeni, 'Rotasyon YENİ değer üretmeli.');
    }

    public function testAYNITOKENIKINCIKEZROTEEDILEMEZ(): void
    {
        // ASIL KORUMA: çalınmış çerezle ikinci rotasyon başarısız olmalı.
        // Eskiden ikisi de geçerli yeni çerez alıyordu ve hırsızlık hiç fark
        // edilmiyordu — asıl kullanıcı çıkış yapmıyor, saldırgan atılmıyordu.
        ['id' => $id, 'cerez' => $cerez] = $this->tokenAc();
        [$selector, $validator] = $this->parcala($cerez);

        $birinci = $this->servis->rotate($id, 7, $this->simdi, $selector, $validator);
        $ikinci = $this->servis->rotate($id, 7, $this->simdi, $selector, $validator);

        self::assertIsString($birinci, 'İlk kullanan kazanmalı.');
        self::assertNull($ikinci, 'Aynı token ikinci kez rote EDİLEMEMELİ.');
    }

    public function testYANLISDOGRULAYICIROTEEDEMEZ(): void
    {
        ['id' => $id, 'cerez' => $cerez] = $this->tokenAc();
        [$selector] = $this->parcala($cerez);

        self::assertNull($this->servis->rotate($id, 7, $this->simdi, $selector, bin2hex(random_bytes(32))));
    }

    public function testBASKAKULLANICIROTEEDEMEZ(): void
    {
        ['id' => $id, 'cerez' => $cerez] = $this->tokenAc();
        [$selector, $validator] = $this->parcala($cerez);

        self::assertNull($this->servis->rotate($id, 999, $this->simdi, $selector, $validator));
    }

    public function testKAZANANINYENITOKENIGECERLI(): void
    {
        // Kaybedenin reddedilmesi yetmez: kazananın yeni çerezi GERÇEKTEN
        // çalışmalı, yoksa rotasyon kullanıcıyı da atmış olur.
        ['id' => $id, 'cerez' => $cerez] = $this->tokenAc();
        [$selector, $validator] = $this->parcala($cerez);

        $yeni = (string) $this->servis->rotate($id, 7, $this->simdi, $selector, $validator);
        $eslesme = $this->servis->validate($yeni, $this->simdi);

        self::assertSame(
            \App\Auth\RememberTokenStatus::Valid,
            $eslesme->status,
            'Kazananın yeni çerezi geçerli olmalı.',
        );
    }

    public function testESKICEREZARTIKGECERSIZ(): void
    {
        ['id' => $id, 'cerez' => $cerez] = $this->tokenAc();
        [$selector, $validator] = $this->parcala($cerez);

        $this->servis->rotate($id, 7, $this->simdi, $selector, $validator);

        self::assertNotSame(
            \App\Auth\RememberTokenStatus::Valid,
            $this->servis->validate($cerez, $this->simdi)->status,
        );
    }
}
