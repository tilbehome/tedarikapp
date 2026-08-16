<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Services\TrashPolicy;
use Tests\Support\AuthTestCase;

/**
 * K15 — silme kaza korumasıdır: kayıt 30 gün geri alınabilir kalır.
 * Arayüzdeki "kalan gün" ile purge betiğinin eşiği AYNI hesaptan gelmeli;
 * aksi hâlde kullanıcıya "3 gün kaldı" derken betik onu silebilir.
 */
final class TrashPolicyTest extends AuthTestCase
{
    private function policy(int $days = 30): TrashPolicy
    {
        return new TrashPolicy($days);
    }

    public function testKalanGunHesabi(): void
    {
        $policy = $this->policy();
        $deletedAt = '2026-08-16 10:00:00';

        // Silindiği gün: 30 gün kaldı.
        self::assertSame(30, $policy->daysLeft($deletedAt, $this->clock->now(), $this->timezone()));

        $this->clock->advance('+29 days');
        self::assertSame(1, $policy->daysLeft($deletedAt, $this->clock->now(), $this->timezone()));

        $this->clock->advance('+1 day');
        self::assertSame(0, $policy->daysLeft($deletedAt, $this->clock->now(), $this->timezone()));
    }

    public function testSuresiDolanKayitSifirGunGosterir(): void
    {
        $this->clock->advance('+100 days');

        self::assertSame(0, $this->policy()->daysLeft('2026-08-16 10:00:00', $this->clock->now(), $this->timezone()));
    }

    public function testPurgeEsigiSaklamaSuresiKadarGeridedir(): void
    {
        $threshold = $this->policy()->purgeThreshold($this->clock->now());

        self::assertSame('2026-07-17 10:00:00', $threshold->format('Y-m-d H:i:s'));
    }

    public function testSuresiDolmayanKayitPurgeListesineGirmez(): void
    {
        $lists = new ListRepository($this->connection);
        $listId = $lists->create([
            'name' => 'Silinecek liste',
            'yuan_rate' => '7.0400',
            'usd_rate' => '41.5000',
        ], $this->clock->now());
        $lists->softDelete($listId, $this->clock->now());

        // 29 gün sonra: henüz süresi dolmadı.
        $this->clock->advance('+29 days');
        $threshold = $this->policy()->purgeThreshold($this->clock->now());
        self::assertSame([], $lists->expiredTrashIds($threshold));

        // 31. günde eşiği geçer.
        $this->clock->advance('+2 days');
        $threshold = $this->policy()->purgeThreshold($this->clock->now());
        self::assertSame([$listId], $lists->expiredTrashIds($threshold));
    }

    public function testSuresiDolanUrunPurgeListesineGirer(): void
    {
        $lists = new ListRepository($this->connection);
        $products = new ProductRepository($this->connection);

        $listId = $lists->create([
            'name' => 'Liste',
            'yuan_rate' => '7.0400',
            'usd_rate' => '41.5000',
        ], $this->clock->now());
        $productId = $products->create($listId, ['name' => 'Ürün', 'qty' => 1], $this->clock->now());
        $products->softDelete($productId, $this->clock->now());

        $this->clock->advance('+31 days');
        $threshold = $this->policy()->purgeThreshold($this->clock->now());

        self::assertSame([$productId], $products->expiredTrashIds($threshold));
        self::assertSame([], $lists->expiredTrashIds($threshold), 'Listenin kendisi silinmemişti.');
    }

    public function testSaklamaSuresiYapilandirilabilir(): void
    {
        $policy = $this->policy(7);

        self::assertSame(7, $policy->retentionDays());
        self::assertSame(7, $policy->daysLeft('2026-08-16 10:00:00', $this->clock->now(), $this->timezone()));
    }

    private function timezone(): \DateTimeZone
    {
        return new \DateTimeZone('Europe/Istanbul');
    }
}
