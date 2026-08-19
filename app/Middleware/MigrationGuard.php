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
 * Bekleyen migration koruması (İE#10.5 Blok 2 — canlı ders: 0018 bekleyenken panel
 * "Undefined column" çöküşleri yaşadı).
 *
 * Veri uçları, defterde BEKLEYEN migration varken çalıştırılmaz: net 503
 * MIGRATION_PENDING döner; panel bunu yakalayıp "Güncelleme tamamlanmalı" ekranı +
 * migrate düğmesi gösterir. Kapsam BİLEREK dar: yalnız bu middleware'in eklendiği
 * gruplar (liste/ürün/ayar verisi). /api/system/* (migrate!), /api/auth/* (migrate
 * için giriş şart) ve kurulum uçları DIŞARIDADIR.
 *
 * Denetim hatası (migrations tablosu yok/okunamıyor — taze kurulum, test şeması)
 * isteği GEÇİRİR: bu koruma çalışan sistemi yarım güncellemeden korur, kurulumu
 * bloklamaz (K45 ruhu).
 */
final class MigrationGuard implements MiddlewareInterface
{
    /** İstek başına tek denetim; aynı istek zincirinde tekrar sorgu atılmaz. */
    private ?bool $pending = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationsDir,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->hasPending()) {
            return Response::error(
                $this->responseFactory->createResponse(),
                'MIGRATION_PENDING',
                'Veritabanı güncellemesi tamamlanmalı: bekleyen migration var. '
                . 'Ayarlar > Sistem durumu ekranından "migrate" çalıştırın.',
                503,
            );
        }

        return $handler->handle($request);
    }

    private function hasPending(): bool
    {
        if ($this->pending !== null) {
            return $this->pending;
        }

        // Migrator::pending() KULLANILAMAZ: defter tablosunu yoksa YARATIR (yan etki) —
        // taze kurulumda "18 bekleyen" görünür ve koruma kurulumu bloklardı. Burada defter
        // yan etkisiz okunur; tablo yoksa (taze kurulum/test şeması) istek GEÇER.
        try {
            $statement = $this->connection->pdo()->query('SELECT name FROM migrations');
            if ($statement === false) {
                return $this->pending = false;
            }
            /** @var list<string> $applied */
            $applied = $statement->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            return $this->pending = false;
        }

        $known = array_fill_keys($applied, true);
        foreach (glob($this->migrationsDir . '/[0-9][0-9][0-9][0-9]_*.php') ?: [] as $file) {
            if (!isset($known[basename($file, '.php')])) {
                return $this->pending = true;
            }
        }

        return $this->pending = false;
    }
}
