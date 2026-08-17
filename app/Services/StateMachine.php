<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Durum makinesi (docs/04 §2b) — kural yalnızca arayüzde değil SUNUCUDA yaşar (K14).
 *
 * Ürün : to_order → ordered → in_transit → received
 *        her durumdan → cancelled (received HARİÇ)
 *        düzeltme: yalnızca BİR adım geri; durum atlama YASAK
 * Liste: draft → sent → ordered → completed (+cancelled)
 *        completed ⇐ ancak tüm ürünler received veya cancelled ise
 *
 * `cancelled` ve `completed`'dan çıkış YOKTUR (K37 §B4): iptal edilen bir kaydın
 * hangi duruma döneceği belirsizdir; tamamlanan liste ise donmuş bir kayıttır ve
 * yeniden açma ucu bulunmaz — yanlış kapatılan listenin çözümü KOPYALAMAKTIR
 * (kopya taslak açılır, güncel kuru alır). Ürün tarafındaki `received → in_transit`
 * tek-adım-geri düzeltmesi aynen korunur (docs/04 §2b).
 */
final class StateMachine
{
    // ─────────────── Ürün ───────────────

    public const string PRODUCT_TO_ORDER = 'to_order';
    public const string PRODUCT_ORDERED = 'ordered';
    public const string PRODUCT_IN_TRANSIT = 'in_transit';
    public const string PRODUCT_RECEIVED = 'received';
    public const string PRODUCT_CANCELLED = 'cancelled';

    /** @var array<string, list<string>> */
    public const array PRODUCT_TRANSITIONS = [
        self::PRODUCT_TO_ORDER => [self::PRODUCT_ORDERED, self::PRODUCT_CANCELLED],
        self::PRODUCT_ORDERED => [self::PRODUCT_IN_TRANSIT, self::PRODUCT_TO_ORDER, self::PRODUCT_CANCELLED],
        self::PRODUCT_IN_TRANSIT => [self::PRODUCT_RECEIVED, self::PRODUCT_ORDERED, self::PRODUCT_CANCELLED],
        self::PRODUCT_RECEIVED => [self::PRODUCT_IN_TRANSIT],
        self::PRODUCT_CANCELLED => [],
    ];

    // ─────────────── Liste ───────────────

    public const string LIST_DRAFT = 'draft';
    public const string LIST_SENT = 'sent';
    public const string LIST_ORDERED = 'ordered';
    public const string LIST_COMPLETED = 'completed';
    public const string LIST_CANCELLED = 'cancelled';

    /** @var array<string, list<string>> */
    public const array LIST_TRANSITIONS = [
        self::LIST_DRAFT => [self::LIST_SENT, self::LIST_CANCELLED],
        self::LIST_SENT => [self::LIST_ORDERED, self::LIST_DRAFT, self::LIST_CANCELLED],
        self::LIST_ORDERED => [self::LIST_COMPLETED, self::LIST_SENT, self::LIST_CANCELLED],
        // K37 §B4: completed TERMİNALDİR — reopen yok, çözüm kopyalama.
        self::LIST_COMPLETED => [],
        self::LIST_CANCELLED => [],
    ];

    /** Ürünün "kapandığı" durumlar — liste ancak hepsi buradaysa completed olabilir. */
    public const array PRODUCT_CLOSED_STATES = [self::PRODUCT_RECEIVED, self::PRODUCT_CANCELLED];

    // ─────────────── Ürün API'si ───────────────

    /** @return list<string> */
    public function allowedProductTransitions(string $current): array
    {
        return self::PRODUCT_TRANSITIONS[$current] ?? [];
    }

    public function isValidProductStatus(string $status): bool
    {
        return array_key_exists($status, self::PRODUCT_TRANSITIONS);
    }

    public function canTransitionProduct(string $from, string $to): bool
    {
        return in_array($to, $this->allowedProductTransitions($from), true);
    }

    /** @throws StateTransitionException */
    public function assertProductTransition(string $from, string $to): void
    {
        if (!$this->isValidProductStatus($to)) {
            throw new StateTransitionException($from, $to, $this->allowedProductTransitions($from), sprintf(
                'Bilinmeyen ürün durumu: "%s".',
                $to,
            ));
        }
        if ($this->canTransitionProduct($from, $to)) {
            return;
        }

        throw new StateTransitionException($from, $to, $this->allowedProductTransitions($from), sprintf(
            'Ürün "%s" durumundan "%s" durumuna geçemez.',
            $from,
            $to,
        ));
    }

    // ─────────────── Liste API'si ───────────────

    /** @return list<string> */
    public function allowedListTransitions(string $current): array
    {
        return self::LIST_TRANSITIONS[$current] ?? [];
    }

    public function isValidListStatus(string $status): bool
    {
        return array_key_exists($status, self::LIST_TRANSITIONS);
    }

    public function canTransitionList(string $from, string $to): bool
    {
        return in_array($to, $this->allowedListTransitions($from), true);
    }

    /**
     * @param list<string> $productStatuses Listedeki (silinmemiş) ürünlerin durumları
     *
     * @throws StateTransitionException
     */
    public function assertListTransition(string $from, string $to, array $productStatuses): void
    {
        if (!$this->isValidListStatus($to)) {
            throw new StateTransitionException($from, $to, $this->allowedListTransitions($from), sprintf(
                'Bilinmeyen liste durumu: "%s".',
                $to,
            ));
        }
        if (!$this->canTransitionList($from, $to)) {
            throw new StateTransitionException($from, $to, $this->allowedListTransitions($from), sprintf(
                'Liste "%s" durumundan "%s" durumuna geçemez.',
                $from,
                $to,
            ));
        }

        if ($to === self::LIST_COMPLETED && !$this->allProductsClosed($productStatuses)) {
            throw new StateTransitionException($from, $to, $this->allowedListTransitions($from), sprintf(
                'Liste tamamlanamaz: %d ürün henüz "Geldi" veya "İptal" değil.',
                $this->openProductCount($productStatuses),
            ));
        }
    }

    /** @param list<string> $productStatuses */
    public function allProductsClosed(array $productStatuses): bool
    {
        return $this->openProductCount($productStatuses) === 0;
    }

    /** @param list<string> $productStatuses */
    private function openProductCount(array $productStatuses): int
    {
        $open = 0;
        foreach ($productStatuses as $status) {
            if (!in_array($status, self::PRODUCT_CLOSED_STATES, true)) {
                $open++;
            }
        }

        return $open;
    }
}
