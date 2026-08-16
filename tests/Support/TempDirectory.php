<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Test başına izole geçici klasör.
 *
 * Kurulum sihirbazı dosya sistemine yazar (.env, setup.lock); testler gerçek repoyu
 * kirletmemeli. Klasör test bitiminde tamamen silinir.
 */
trait TempDirectory
{
    private ?string $temporaryRoot = null;

    protected function tempRoot(): string
    {
        if ($this->temporaryRoot === null) {
            $path = sys_get_temp_dir() . '/tedarikapp-test-' . bin2hex(random_bytes(6));
            mkdir($path, 0775, true);
            $this->temporaryRoot = $path;
        }

        return $this->temporaryRoot;
    }

    protected function tempPath(string $relative): string
    {
        return $this->tempRoot() . '/' . ltrim($relative, '/');
    }

    /** @after */
    protected function removeTemporaryRoot(): void
    {
        if ($this->temporaryRoot === null) {
            return;
        }
        $this->deleteRecursively($this->temporaryRoot);
        $this->temporaryRoot = null;
    }

    private function deleteRecursively(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->deleteRecursively($path . '/' . $entry);
        }
        @rmdir($path);
    }
}
