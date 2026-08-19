<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\AuthTestCase;

/**
 * İE#10.5 Blok 2 — bekleyen migration koruması (bugünkü canlı vakanın regresyonu:
 * 0018 bekleyenken panel "Undefined column" ile çöküyordu).
 *
 * Kurallar: bekleyen varken veri uçları NET 503 MIGRATION_PENDING döner (çökme yok);
 * /api/system/* ve /api/auth/* AÇIK kalır (migrate bu yolla koşulur); defter
 * okunamıyorsa (taze kurulum/test) koruma isteği GEÇİRİR.
 */
final class MigrationGuardTest extends AuthTestCase
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

    /** Defteri "gerçek migrations klasörünün GERİSİNDE" bırakır — 0018 vakasının eşdeğeri. */
    private function seedPartialLedger(): void
    {
        $this->pdo->exec(
            'CREATE TABLE migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                checksum TEXT NOT NULL,
                execution_ms INTEGER NOT NULL DEFAULT 0,
                applied_at TEXT NOT NULL
            )',
        );
        // Yalnız 0001 işli — 0002..0018 "bekleyen" görünür.
        $file = dirname(__DIR__, 2) . '/migrations/0001_create_users.php';
        $statement = $this->pdo->prepare("INSERT INTO migrations (name, checksum, applied_at) VALUES ('0001_create_users', :c, '2026-08-19')");
        $statement->execute(['c' => hash_file('sha256', $file)]);
    }

    public function testBekleyenVarkenVeriUcu503MigrationPendingDoner(): void
    {
        $this->seedPartialLedger();

        $response = $this->call('GET', '/api/lists');

        self::assertSame(503, $response->getStatusCode(), 'Çökme değil NET 503 dönmeli.');
        $payload = $this->json($response);
        self::assertSame('MIGRATION_PENDING', $payload['error']['code']);
    }

    public function testSistemVeAuthUclariAcikKalir(): void
    {
        $this->seedPartialLedger();

        // migrate bu yolla koşulacak: system + auth uçları BLOKLANMAZ.
        // (status test şemasında VERSION() yüzünden 500 verebilir — önemli olan 503 OLMAMASI.)
        self::assertSame(200, $this->call('GET', '/api/system/state-machine')->getStatusCode());
        self::assertNotSame(503, $this->call('GET', '/api/system/status')->getStatusCode());
        self::assertSame(200, $this->call('GET', '/api/auth/me')->getStatusCode());
    }

    public function testDefterYokkenKorumaGecirir(): void
    {
        // AuthTestCase şemasında migrations tablosu YOK (taze kurulum eşdeğeri) —
        // koruma kurulumu/testleri bloklamaz.
        self::assertSame(200, $this->call('GET', '/api/lists')->getStatusCode());
    }

    public function testDefterEsitleninceUclarAcilir(): void
    {
        $this->seedPartialLedger();
        self::assertSame(503, $this->call('GET', '/api/lists')->getStatusCode());

        // Baseline defteri gerçeğe eşitler (test şemasında var olan nesneler işlenir;
        // kalanları migrate koşar — burada tümünü elle işleyerek "güncellendi" durumu kurulur).
        foreach (glob(dirname(__DIR__, 2) . '/migrations/[0-9]*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $statement = $this->pdo->prepare('INSERT OR IGNORE INTO migrations (name, checksum, applied_at) VALUES (:n, :c, :t)');
            $statement->execute(['n' => $name, 'c' => hash_file('sha256', $file), 't' => '2026-08-19']);
        }

        self::assertSame(200, $this->call('GET', '/api/lists')->getStatusCode(), 'Defter eşitlenince uçlar açılmalı.');
    }
}
