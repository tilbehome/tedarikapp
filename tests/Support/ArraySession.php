<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Auth\SessionInterface;

/**
 * Bellek içi oturum — sözleşme testleri HTTP sunucusu ve çerez olmadan koşar,
 * PHP native session CLI'da başlık gönderemez.
 *
 * `regenerate()` gerçek uygulamada olduğu gibi VERİYİ KORUR, yalnızca kimliği tazeler;
 * `destroy()` her şeyi siler. Testler bu davranışa güvenir.
 */
final class ArraySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    private string $id = 'ilk-oturum';

    private int $regenerationCount = 0;

    public function start(): void
    {
        // Bellek içi oturumun açılacak bir şeyi yok.
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerationCount++;
        $this->id = 'oturum-' . $this->regenerationCount;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->id = 'yok';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function regenerationCount(): int
    {
        return $this->regenerationCount;
    }
}
