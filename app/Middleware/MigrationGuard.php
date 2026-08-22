<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Connection;
use App\Core\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bekleyen migration koruması (İE#10.5 Blok 2 · İE#19 G7 ile sertleştirildi).
 *
 * Canlı ders: 0018 bekleyenken panel "Undefined column" çöküşleri yaşadı. Şema
 * bağımlı uçlar, defterde BEKLEYEN migration varken çalıştırılmaz: net 503
 * MIGRATION_PENDING döner; panel bunu yakalayıp "Güncelleme tamamlanmalı" ekranı +
 * migrate düğmesi gösterir. `/api/system/*` (migrate!), `/api/auth/*` (migrate için
 * giriş şart) ve kurulum uçları DIŞARIDADIR.
 *
 * G7 — ÜÇ DURUM, FAIL-CLOSED:
 *  • Bekleyen var          → 503 MIGRATION_PENDING
 *  • Defter okunabiliyor   → geç
 *  • Defter OKUNAMIYOR     → ayrım yapılır:
 *      – veritabanı yanıt veriyor ama `migrations` tablosu yok  → TAZE KURULUM;
 *        istek GEÇER (K45 ruhu: kurulum bloklanmaz, test şeması da bu yoldadır),
 *      – veritabanının kendisi yanıt vermiyor                    → 503 (fail-closed).
 *
 * Eski davranışta her iki hâl de "geç" idi: veritabanı düşmüşken panel, şema
 * denetimi yapılmadan veri uçlarına giriyor ve anlaşılmaz 500'ler üretiyordu.
 */
final class MigrationGuard implements MiddlewareInterface
{
    private const DURUM_TEMIZ = 'temiz';
    private const DURUM_BEKLEYEN = 'bekleyen';
    private const DURUM_BILINMIYOR = 'bilinmiyor';

    /** İstek başına tek denetim; aynı istek zincirinde tekrar sorgu atılmaz. */
    private ?string $durum = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationsDir,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $durum = $this->durum();

        if ($durum === self::DURUM_BEKLEYEN) {
            return Response::error(
                $this->responseFactory->createResponse(),
                'MIGRATION_PENDING',
                'Veritabanı güncellemesi tamamlanmalı: bekleyen migration var. '
                . 'Ayarlar > Sistem durumu ekranından "migrate" çalıştırın.',
                503,
            );
        }

        if ($durum === self::DURUM_BILINMIYOR) {
            return Response::error(
                $this->responseFactory->createResponse(),
                'SCHEMA_STATE_UNKNOWN',
                'Veritabanı durumu doğrulanamadı (bağlantı yanıt vermiyor). '
                . 'Güvenlik gereği veri uçları bu durumda çalıştırılmaz.',
                503,
            );
        }

        return $handler->handle($request);
    }

    private function durum(): string
    {
        if ($this->durum !== null) {
            return $this->durum;
        }

        // Migrator::pending() KULLANILAMAZ: defter tablosunu yoksa YARATIR (yan etki) —
        // taze kurulumda "18 bekleyen" görünür ve koruma kurulumu bloklardı.
        try {
            $statement = $this->connection->pdo()->query('SELECT name FROM migrations');
            if ($statement === false) {
                return $this->durum = self::DURUM_BILINMIYOR;
            }
            /** @var list<string> $applied */
            $applied = $statement->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            // Tablo mu yok, veritabanı mı yok? Ayrımı basit bir yoklama verir.
            return $this->durum = $this->veritabaniYanitVeriyor()
                ? self::DURUM_TEMIZ        // taze kurulum / test şeması → bloklama yok
                : self::DURUM_BILINMIYOR;  // bağlantı yok → fail-closed
        }

        $known = array_fill_keys($applied, true);
        foreach (glob($this->migrationsDir . '/[0-9][0-9][0-9][0-9]_*.php') ?: [] as $file) {
            if (!isset($known[basename($file, '.php')])) {
                return $this->durum = self::DURUM_BEKLEYEN;
            }
        }

        return $this->durum = self::DURUM_TEMIZ;
    }

    /** "Tablo yok" ile "veritabanı yok" ayrımı — tek ucuz yoklama. */
    private function veritabaniYanitVeriyor(): bool
    {
        try {
            return $this->connection->pdo()->query('SELECT 1') !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
