<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Terminal liste dokunulmazlığı (K37 §B4) — kural SUNUCUDA yaşar (K14 ilkesi).
 *
 * `completed` / `cancelled` liste DONMUŞ bir kayıttır: ürün ekleme/taşıma/silme,
 * durum ve alan düzenleme, yeniden sıralama — hepsi reddedilir (`LIST_IMMUTABLE`).
 * Yeniden açma ucu YOKTUR; devam etmenin yolu listeyi KOPYALAMAKTIR (kopya taslak
 * açılır ve güncel kuru alır — K4 ile de tutarlı).
 *
 * Bilinçli istisnalar (içerik değil yaşam döngüsü):
 *  • `visibility` (arşivleme/pasifleştirme) — tamamlanmış listeyi arşivlemek olağan
 *    düzendir, listenin verisine dokunmaz.
 *  • Soft delete (çöp kutusuna taşıma) — K15 kaza koruması aynen işler.
 *  • Kopyalama — kaynaktan yalnızca OKUR.
 */
final class ListMutationPolicy
{
    public const TERMINAL_STATUSES = [
        StateMachine::LIST_COMPLETED,
        StateMachine::LIST_CANCELLED,
    ];

    /** @param array<string, mixed> $list */
    public function isTerminal(array $list): bool
    {
        return in_array((string) ($list['status'] ?? ''), self::TERMINAL_STATUSES, true);
    }

    /**
     * @param array<string, mixed> $list
     *
     * @throws ListImmutableException
     */
    public function assertMutable(array $list): void
    {
        if ($this->isTerminal($list)) {
            throw new ListImmutableException((string) $list['status']);
        }
    }
}
