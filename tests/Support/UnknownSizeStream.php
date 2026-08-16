<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Boyutu BİLİNMEYEN akış — gerçek sunucudaki `php://input` böyle davranır:
 * `getSize()` null döner.
 *
 * Slim'in test akışları boyutu bildiği için bu davranış birim testlerde hiç
 * görünmüyordu; JsonRequest'in gövdesiz POST'u 415'e düşürmesi ancak canlı
 * koşumda ortaya çıktı. Bu sahte akış o farkı teste taşır.
 */
final class UnknownSizeStream implements StreamInterface
{
    private int $position = 0;

    public function __construct(private readonly string $contents = '')
    {
    }

    public function getSize(): ?int
    {
        return null; // ← testin bütün amacı
    }

    public function __toString(): string
    {
        return $this->contents;
    }

    public function getContents(): string
    {
        $remaining = substr($this->contents, $this->position);
        $this->position = strlen($this->contents);

        return $remaining;
    }

    public function read(int $length): string
    {
        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->contents);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('Bu akış aranabilir değil.');
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('Bu akış yazılabilir değil.');
    }

    public function close(): void
    {
        $this->position = 0;
    }

    public function detach(): mixed
    {
        return null;
    }

    /** @return array<string, mixed>|mixed */
    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
