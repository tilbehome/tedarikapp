<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Oturum deposu soyutlaması.
 *
 * Üretimde PHP native session (NativeSession) kullanılır; sözleşme testleri
 * HTTP sunucusu ve çerez olmadan koştuğu için dizi tabanlı bir uygulama enjekte eder.
 */
interface SessionInterface
{
    public function start(): void;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** Oturum sabitleme (session fixation) saldırısına karşı kimliği tazeler. */
    public function regenerate(): void;

    public function destroy(): void;
}
